<?php

namespace Glutio\DomainSSO;

use Exception;
use Flarum\Extend;
use Flarum\Foundation\Config;
use Flarum\Frontend\Document;
use Flarum\Http\Middleware\AuthenticateWithSession;
use Flarum\Settings\SettingsRepositoryInterface;
use Glutio\DomainSSO\Middleware\DomainSSOAuthLogin;
use Glutio\DomainSSO\Middleware\DomainSSOAuthLogout;
use Glutio\DomainSSO\Middleware\DomainSSOConfig;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Flarum\Foundation\Application;

final class DomainSSOFrontendSettings
{
  private $app;
  public function __construct(Application $app)
  {
    $this->app = $app;
  }

  public function __invoke($retrieved)
  {
    $config = $this->app->config("glutio-domainsso");
    return json_encode($config);
  }
}

return [
  (new Extend\Middleware('forum'))->replace(AuthenticateWithSession::class, Middleware\DomainSSOAuthMiddleware::class),
  (new Extend\Middleware('admin'))->replace(AuthenticateWithSession::class, Middleware\DomainSSOAuthMiddleware::class),
  (new Extend\Middleware('api'))->add(Middleware\DomainSSOAuthMiddleware::class),
  (new Extend\Routes('forum'))->remove('login'),
  (new Extend\Routes('forum'))->remove('register'),
  (new Extend\Routes('forum'))->remove('confirmEmail'),
  (new Extend\Routes('forum'))->remove('confirmEmail.submit'),
  (new Extend\Routes('forum'))->remove('resetPassword'),
  (new Extend\Routes('forum'))->remove('savePassword'),
  (new Extend\Routes('forum'))->remove('logout'),
  (new Extend\Routes('forum'))->remove('globalLogout'),
  (new Extend\Routes('forum'))->post('/login', 'login', DomainSSOAuthLogin::class),
  (new Extend\Routes('forum'))->get('/logout', 'logout', DomainSSOAuthLogout::class),
  (new Extend\Routes('forum'))->post('/global-logout', 'globalLogout', DomainSSOAuthLogout::class),
  (new Extend\Frontend('forum'))->js(__DIR__ . '/js/dist/forum.js'),
  (new Extend\Settings)->serializeToForum("glutio-domainsso", "glutio-domainsso", DomainSSOFrontendSettings::class)
];
