<?php

namespace Glutio\DomainSSO;

use Flarum\Extend;
use Flarum\Http\Middleware\AuthenticateWithHeader;
use Flarum\Http\Middleware\AuthenticateWithSession;
use Glutio\DomainSSO\Middleware\DomainSSOAuthLogout;

return [
  (new Extend\Middleware('forum'))->replace(AuthenticateWithSession::class, Middleware\DomainSSOAuthMiddleware::class),
  (new Extend\Middleware('admin'))->replace(AuthenticateWithSession::class, Middleware\DomainSSOAuthMiddleware::class),
  (new Extend\Middleware('api'))->remove(AuthenticateWithHeader::class),
  (new Extend\Middleware('api'))->add(Middleware\DomainSSOAuthMiddleware::class),
  (new Extend\Routes('forum'))->remove('register'),
  (new Extend\Routes('forum'))->remove('confirmEmail'),
  (new Extend\Routes('forum'))->remove('confirmEmail.submit'),
  (new Extend\Routes('forum'))->remove('resetPassword'),
  (new Extend\Routes('forum'))->remove('savePassword'),
  (new Extend\Routes('forum'))->remove('logout'),
  (new Extend\Routes('forum'))->remove('globalLogout'),
  (new Extend\Routes('forum'))->get('/logout', 'logout', DomainSSOAuthLogout::class),
  (new Extend\Routes('forum'))->post('/global-logout', 'globalLogout', DomainSSOAuthLogout::class),
  (new Extend\Settings)->serializeToForum('glutio-domainsso.url', 'glutio-domainsso.url'),
  (new Extend\Settings)->serializeToForum('glutio-domainsso.login', 'glutio-domainsso.login'),
  (new Extend\Settings)->serializeToForum('glutio-domainsso.redirect', 'glutio-domainsso.redirect'),
  (new Extend\Frontend('forum'))->js(__DIR__ . '/js/dist/forum.js'),
  (new Extend\Frontend('admin'))->js(__DIR__ . '/js/dist/admin.js'),
  new Extend\Locales(__DIR__ . '/locale')
];
