<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardModuleAccess
{
    public function handle(Request $request, Closure $next, string $module, string $ability = 'read'): Response
    {
        if ($module === 'path') {
            $module = explode('/', (string) $request->route('path'))[0] ?: 'overview';
            if (! in_array($module, User::DASHBOARD_MODULES, true)) {
                return $next($request);
            }
        }

        abort_unless(
            in_array($ability, ['read', 'write'], true)
            && $request->user()->canAccessDashboardModule($module, $ability),
            403
        );

        return $next($request);
    }
}
