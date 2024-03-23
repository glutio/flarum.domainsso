<?php

namespace Glutio\DomainSSO\Middleware;

use Flarum\Forum\Controller\LogOutController;
use Flarum\Foundation\Application;
use Flarum\Foundation\Config;
use Flarum\User\Guest;
use Flarum\User\User;
use Flarum\User\UserRepository;
use Flarum\Http\AccessToken;
use Flarum\Http\Middleware\AuthenticateWithSession;
use Flarum\Http\RequestUtil;
use Flarum\Http\SessionAccessToken;
use Flarum\Http\SessionAuthenticator;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\RedirectResponse;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DomainSSOAuthLogin extends LogOutController
{
    private $settings;
    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $prefix = DomainSSOAuthMiddleware::$prefix;
        $url = $this->settings->get($prefix . ".url");
        $login = $this->settings->get($prefix . ".login");
        $redirect = $this->settings->get($prefix . ".redirect");
        if (!empty($redirect)) {
            $redirect = "?" . $redirect . "=" . $request->getHeaderLine('Referer');
        }
        $redirect = "?a=b";
        $loginUrl = $url . $login . $redirect;
        if (empty($loginUrl)) {
            return parent::handle($request);
        }
        return new RedirectResponse($loginUrl);
    }
};

final class DomainSSOAuthLogout implements RequestHandlerInterface
{
    private $settings;
    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $prefix = DomainSSOAuthMiddleware::$prefix;
        $url = $this->settings->get($prefix . ".url");
        $logout = $this->settings->get($prefix . ".logout");
        $logoutUrl = $url . $logout;    
        return new RedirectResponse($logoutUrl);
    }
};

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
        $actor = self::getActor($session, $request);
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
            if ($actor && !$actor->isAdmin()) {
                // always logout 
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
    public static function getActor(Session $session, ServerRequestInterface $request)
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
        $user = $this->users->findByIdentification(['username' => $userName, 'email' => $userEmail]);
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
