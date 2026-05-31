<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Carica .env.development o .env.production in base a APP_ENV (da OS/server)
$appEnv = getenv('APP_ENV') ?: 'development';
$envFile = '.env.' . $appEnv;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.jwt' => \App\Http\Middleware\JwtAuthenticate::class,
            'admin'    => \App\Http\Middleware\AdminOnly::class,
            'referent' => \App\Http\Middleware\ReferentOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*')) {
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'message' => 'Dati non validi',
                        'errors'  => $e->errors(),
                    ], 422);
                }

                if ($e instanceof \Illuminate\Database\QueryException && (($e->errorInfo[1] ?? null) === 1062)) {
                    return response()->json([
                        'message' => 'Conflitto dati',
                        'error'   => class_basename($e),
                    ], 409);
                }

                $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                if ($status < 400 || $status > 599) {
                    $status = 500;
                }

                $message = match ($status) {
                    404 => 'Risorsa non trovata',
                    409 => 'Conflitto dati',
                    500 => 'Errore interno del server',
                    default => $e->getMessage() ?: 'Errore API',
                };

                return response()->json([
                    'message' => $message,
                    'error'   => class_basename($e),
                ], $status);
            }
        });
    })
    ->create();

$app->loadEnvironmentFrom($envFile);

return $app;
