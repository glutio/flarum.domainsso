<?php

namespace Glutio\DomainSSO\Middleware;

use Flarum\User\Guest;
use Flarum\User\User;
use Flarum\User\UserRepository;
use Flarum\Http\AccessToken;
use Flarum\Http\Middleware\AuthenticateWithSession;
use Flarum\Http\RequestUtil;
use Flarum\Http\SessionAccessToken;
use Flarum\Http\SessionAuthenticator;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Event\LoggedOut;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\RedirectResponse;
use GuzzleHttp\Client;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DomainSSOAuthLogout implements RequestHandlerInterface
{
    private $settings;
    private $generator;
    private $auth;
    private $events;
    public function __construct(SettingsRepositoryInterface $settings, UrlGenerator $generator, SessionAuthenticator $auth, Dispatcher $events)
    {
        $this->settings = $settings;
        $this->generator = $generator;
        $this->auth = $auth;
        $this->events = $events;
    }
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $prefix = DomainSSOAuthMiddleware::$prefix;
        $url = $this->settings->get($prefix . ".url");
        $logout = $this->settings->get($prefix . ".logout");
        $logoutUrl = $url . $logout;
        $redirect = $this->settings->get($prefix . ".redirect");
        $baseUrl = $this->generator->to('forum')->base();
        if ($redirect) {
            $redirect = "?" . $redirect . "=" . urlencode($baseUrl);
            $logoutUrl .= $redirect;
        }

        $this->logOut($request);
        if (empty($logoutUrl)) {
            $logout = $baseUrl;
        }

        return new RedirectResponse($logoutUrl);
    }

    public function logOut(ServerRequestInterface $request)
    {
        $session = $request->getAttribute('session');
        $actor = RequestUtil::getActor($request);
        $this->auth->logOut($session);    
        if ($actor) {
            $this->events->dispatch(new LoggedOut($actor, false));
        }
    }
}

final class DomainSSOAuthMiddleware extends AuthenticateWithSession
{
    private $users;
    private $auth;
    private $logger;
    private $settings;
    static public $prefix = "glutio-domainsso";

    public function __construct(UserRepository $users, SessionAuthenticator $auth, SettingsRepositoryInterface $settings, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->users = $users;
        $this->auth = $auth;
        $this->settings = $settings;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // get the user info if logged in
        $externalSession = $this->getExternalSession($request);

        // is $session ever null?
        $session = $request->getAttribute('session');
        $actor = $this->getActor($session, $request);
        if ($externalSession) {
            // find existing or create new user in Flarum
            $user = $this->getUser($externalSession);

            // get user associated with Flarum token or create new token
            if ($actor && $actor->email != $user->email) {
                $actor = null;
            }

            if (!$actor) {
                $token = SessionAccessToken::generate($user->id);
                $this->auth->logIn($session, $token);
                $actor = $user;
            }

            $request = RequestUtil::withActor($request, $actor);
        } else {
            if ($actor && !$actor->isAdmin() && !$actor->isGuest()) {
                $this->auth->logOut($session);
                $actor = new Guest();
                $request = RequestUtil::withActor($request, $actor);
            } else {
                return parent::process($request, $handler);
            }
        }

        return $handler->handle($request);
    }

    // based on from AuthenticateWithSession.php
    public function getActor(Session $session, ServerRequestInterface $request)
    {
        if ($session->has('access_token')) {
            $token = AccessToken::findValid($session->get('access_token'));
            if ($token) {
                $actor = $token->user;
                $actor->updateLastSeen()->save();
                $token->touch($request);
                return $actor;
            }
        }

        return null;
    }

    private function getUser($externalSession)
    {
        // extract user info from the external session json
        $userEmail = isset($externalSession->user->email) ? $externalSession->user->email : null;
        $userName = isset($externalSession->user->name) ? $externalSession->user->name : null;
        $userAvatar = isset($externalSession->user->image) ? $externalSession->user->image : null;
        
        // find the existing or create new Flarum user
        $user = $this->users->findByEmail($userEmail);
        if (is_null($user)) {
            $randomString = Str::random(32);
            $user = User::register($userName, $userEmail, $randomString);
            $user->changeAvatarPath($userAvatar);
            $user->activate();
            $user->save();
        } else if ($user->avatar_url != $userAvatar) {
            $user->changeAvatarPath($userAvatar);
            $user->save();
        }
        return $user;
    }

    private function getExternalSession(ServerRequestInterface $request)
    {
        $client = new Client();
        try {
            // rebuild cookie header from request cookies
            $cookies = $request->getCookieParams();
            $cookieHeader = '';
            foreach ($cookies as $name => $value) {
                $cookieHeader .= $name . '=' . $value . '; ';
            }

            // forward cookies to SSO
            $prefix = DomainSSOAuthMiddleware::$prefix;
            $url = $this->settings->get($prefix . ".url");
            $sessionUrl = $this->settings->get($prefix . ".session");
            $externalServiceUrl = $url . $sessionUrl;
            $response = $client->request('GET', $externalServiceUrl, [
                'headers' => [
                    'Cookie' => $cookieHeader
                ]
            ]);

            // decode json response
            $body = $response->getBody();
            $externalSession = json_decode($body);
            return isset($externalSession->user->email) ? $externalSession : null;
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            $this->logger->error('Error calling external service: ' . $e->getMessage());
        }

        return null;
    }
}
