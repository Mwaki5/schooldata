<?php

use App\Http\Middleware\EnsureAccessToken;
use App\Http\Middleware\EnsurePermission;
use App\Services\Finance\LedgerException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'access.type' => EnsureAccessToken::class,
            'permission' => EnsurePermission::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A finance ledger rule refused the write — already reversed,
        // a reversal would push net payments negative, no active
        // session. The message says which rule and the status is 409:
        // the request was well-formed, the ledger's state said no.
        $exceptions->render(function (LedgerException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        });
    })->create();
