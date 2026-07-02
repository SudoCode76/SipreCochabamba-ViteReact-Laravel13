<?php

use App\Http\Middleware\EnsureAuthenticatedUserIsActive;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserIsAdministrator;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : route('login'));

        $middleware->alias([
            'admin' => EnsureUserIsAdministrator::class,
            'active_user' => EnsureAuthenticatedUserIsActive::class,
            'db_permission' => EnsureUserHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->expectsJson() === false && $request->is('api/*') === false) {
                return null;
            }

            return ApiResponse::error('Error de validacion.', $exception->errors(), $exception->status);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson() === false && $request->is('api/*') === false) {
                return null;
            }

            return ApiResponse::error('No autenticado.', null, 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->expectsJson() === false && $request->is('api/*') === false) {
                return null;
            }

            return ApiResponse::error('No tiene permisos para acceder a este recurso.', [
                'authorization' => [$exception->getMessage() !== '' ? $exception->getMessage() : 'Acceso denegado.'],
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if ($request->expectsJson() === false && $request->is('api/*') === false) {
                return null;
            }

            return ApiResponse::error('Recurso no encontrado.', null, 404);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->expectsJson() === false && $request->is('api/*') === false) {
                return null;
            }

            if ($exception instanceof ValidationException
                || $exception instanceof AuthenticationException
                || $exception instanceof AuthorizationException
                || $exception instanceof ModelNotFoundException) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $message = match ($status) {
                    401 => 'No autenticado.',
                    403 => 'No tiene permisos para acceder a este recurso.',
                    404 => 'Recurso no encontrado.',
                    default => $exception->getMessage() !== '' ? $exception->getMessage() : 'Error en la solicitud.',
                };

                return ApiResponse::error($message, null, $status);
            }

            report($exception);

            if ($exception instanceof QueryException) {
                $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

                if (in_array($sqlState, ['42P01', '42703'], true)) {
                    return ApiResponse::error('La base de datos no está actualizada. Ejecute las migraciones pendientes.', [
                        'code' => 'DB_SCHEMA_MISMATCH',
                        'action' => 'run_migrations',
                    ], 500);
                }

                return ApiResponse::error('No se pudo consultar la base de datos. Revise los logs del servidor.', [
                    'code' => 'DB_QUERY_ERROR',
                ], 500);
            }

            return ApiResponse::error('Ocurrio un error inesperado.', null, 500);
        });
    })->create();
