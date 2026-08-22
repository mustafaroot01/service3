<?php

use App\Exceptions\OtpException;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                // A service fault carries no field error — the app must show it
                // as an outage, not redden the code box — while a customer fault
                // keeps errors.otp so the OTP field can flag it.
                $e instanceof OtpException => $e->isServiceFault()
                    ? ApiResponse::error($e->getMessage(), [], $e->status())
                    : ApiResponse::error($e->getMessage(), ['otp' => [$e->reason]], $e->status()),
                $e instanceof ValidationException => ApiResponse::validationError($e->errors()),
                // The unique index is the real gatekeeper; the pre-checks in the
                // services are only there for a nicer message. When two requests
                // race past those checks — a double-tapped signup, two admins
                // accepting one application — the second trips the index. That is
                // a conflict the caller can act on, not a server fault to log.
                $e instanceof UniqueConstraintViolationException => ApiResponse::error(
                    'هذه العملية نُفّذت مسبقاً، لا داعي لتكرارها',
                    [],
                    409
                ),
                // Raised before any controller runs when the whole request exceeds
                // PHP's post_max_size — a four-image gallery can. Name the cap so
                // the admin knows what to shrink instead of reading a raw English
                // framework message.
                $e instanceof PostTooLargeException => ApiResponse::error(
                    'حجم الملفات المرفوعة كبير جداً — الحد الأقصى للطلب الواحد '.ini_get('post_max_size').'. قلّل حجم الصور أو ارفعها على دفعات.',
                    [],
                    413
                ),
                $e instanceof AuthenticationException => ApiResponse::unauthorized(),
                $e instanceof AuthorizationException => ApiResponse::forbidden(),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::notFound(),
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getMessage() ?: 'Request failed',
                    [],
                    $e->getStatusCode()
                ),
                default => config('app.debug')
                    ? null
                    : ApiResponse::error('Server error', [], 500),
            };
        });
    })->create();
