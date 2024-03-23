<?php

namespace Glutio\DomainSSO;

use Flarum\Extend;
use Illuminate\Events\Dispatcher;
use Flarum\Http\Middleware\AuthenticateWithSession;

return [
  (new Extend\Middleware('forum'))->replace(AuthenticateWithSession::class, Middleware\DomainSSOAuthMiddleware::class),
  (new Extend\Middleware('admin'))->replace(AuthenticateWithSession::class, Middleware\DomainSSOAuthMiddleware::class),
  (new Extend\Middleware('api'))->add(Middleware\DomainSSOAuthMiddleware::class)
];
