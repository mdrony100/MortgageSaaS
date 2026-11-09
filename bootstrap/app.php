<?php

use App\Bank\ProcessingException;
use App\Http\Middleware\BlockInactiveOrganization;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\ImpersonationGuard;
use App\Http\Middleware\OwnerConsole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->appendToGroup('web', [EnsureAccountActive::class, ImpersonationGuard::class]);
        // Run right after the session starts, before route-model binding, so blocked support-session
        // actions and disabled accounts are rejected before any resource lookup.
        $middleware->appendToPriorityList(StartSession::class, ImpersonationGuard::class);
        $middleware->appendToPriorityList(StartSession::class, EnsureAccountActive::class);
        $middleware->alias(['owner.console' => OwnerConsole::class, 'org.active' => BlockInactiveOrganization::class]);
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $financial = fn (Request $r) => $r->is('api/files/*', 'api/document-versions/*', 'api/documents/*', 'api/organizations/*/billing', 'api/billing/*');
        $exceptions->report(function (Throwable $e) use ($financial) {
            if ($e instanceof QueryException || (! app()->runningInConsole() && $financial(request()))) {
                Log::error('Financial workflow exception; source details deliberately omitted', ['exception_type' => get_class($e)]);

                return false;
            }
        });
        $exceptions->render(function (Throwable $e, Request $r) use ($financial) {
            if (($financial($r) || ($e instanceof QueryException && $r->is('api/*'))) && ! ($e instanceof HttpExceptionInterface) && ! ($e instanceof ValidationException) && ! ($e instanceof AuthenticationException) && ! ($e instanceof AuthorizationException)) {
                return response()->json(['message' => $e instanceof ProcessingException ? $e->getMessage() : 'Financial workflow could not complete; sensitive diagnostics are redacted.'], 500);
            }
        });
        $exceptions->dontFlash(['password', 'password_confirmation', 'token', 'text', 'notes', 'policy', 'correction', 'statement_correction', 'input', 'value', 'address_evidence', 'context', 'classifications']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
