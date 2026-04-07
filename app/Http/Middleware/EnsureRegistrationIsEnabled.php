<?php

namespace App\Http\Middleware;

use App\Settings\AuthSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class EnsureRegistrationIsEnabled
{
    public function __construct(
        private AuthSettings $settings
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (
            ! ($this->settings->allowRegistration ?? true)
            && $request->routeIs('register', 'register.store')
        ) {
            return redirect()
                ->route('login')
                ->with('status', __('Registration is currently closed.'));
        }

        return $next($request);
    }
}
