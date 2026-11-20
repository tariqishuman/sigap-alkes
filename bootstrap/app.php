<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : route('filament.admin.auth.login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Return HL7 FHIR R4 OperationOutcome JSON for all errors under /api/fhir/*
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/fhir/*')) {
                return null;
            }

            [$status, $code, $diagnostics] = match (true) {
                $e instanceof AuthenticationException => [401, 'login', 'Autentikasi diperlukan. Sertakan Bearer token yang valid.'],
                $e instanceof AuthorizationException => [403, 'forbidden', 'Anda tidak memiliki izin untuk mengakses resource ini.'],
                $e instanceof ModelNotFoundException => [404, 'not-found', 'Resource FHIR tidak ditemukan.'],
                $e instanceof ValidationException => [422, 'invalid', $e->getMessage()],
                $e instanceof ThrottleRequestsException => [429, 'throttled', 'Batas permintaan (60 request/menit) terlampaui.'],
                $e instanceof HttpExceptionInterface => [
                    $e->getStatusCode(),
                    match ($e->getStatusCode()) {
                        401 => 'login',
                        403 => 'forbidden',
                        404 => 'not-found',
                        422 => 'invalid',
                        429 => 'throttled',
                        default => 'exception',
                    },
                    $e->getMessage() ?: 'Terjadi kesalahan pada permintaan FHIR.',
                ],
                default => [500, 'exception', 'Terjadi kesalahan internal pada server FHIR.'],
            };

            return response()->json([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code' => $code,
                        'diagnostics' => $diagnostics,
                    ],
                ],
            ], $status, ['Content-Type' => 'application/fhir+json']);
        });
    })->create();
