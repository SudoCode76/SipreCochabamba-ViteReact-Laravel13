<?php

use App\Http\Middleware\EnsureAuthenticatedUserIsActive;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserIsAdministrator;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'admin' => EnsureUserIsAdministrator::class,
            'active_user' => EnsureAuthenticatedUserIsActive::class,
            'db_permission' => EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->expectsJson() === false) {
                return null;
            }

            return ApiResponse::error('Error de validacion.', $exception->errors(), $exception->status);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson() === false) {
                return null;
            }

            return ApiResponse::error('No autenticado.', null, 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->expectsJson() === false) {
                return null;
            }

            return ApiResponse::error('No tiene permisos para acceder a este recurso.', [
                'authorization' => [$exception->getMessage() !== '' ? $exception->getMessage() : 'Acceso denegado.'],
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if ($request->expectsJson() === false) {
                return null;
            }

            return ApiResponse::error('Recurso no encontrado.', null, 404);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->expectsJson() === false) {
                return null;
            }

            if ($exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException
                || $exception instanceof ModelNotFoundException) {
                return null;
            }

            report($exception);

            return ApiResponse::error('Ocurrio un error inesperado.', null, 500);
        });
    })->create();
