<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemAdminModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        [$module, $ability] = $this->moduleAccess($request);

        if ($module === null || ! $request->user()->canAccessSystemAdminModule($module, $ability)) {
            if ($request->route()?->getName() === 'super-admin.spa' && $request->route('path') === null) {
                $firstAllowedModule = collect(User::SYSTEM_ADMIN_MODULES)
                    ->first(fn (string $candidate): bool => $request->user()->canAccessSystemAdminModule($candidate));
                if ($firstAllowedModule !== null) {
                    return redirect('/super-admin/'.$firstAllowedModule);
                }
            }

            abort(403);
        }

        return $next($request);
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function moduleAccess(Request $request): array
    {
        $routeName = (string) $request->route()?->getName();
        $routeSegment = explode('.', $routeName)[1] ?? '';

        if ($routeName === 'super-admin.workspace') {
            $routeSegment = (string) $request->route('section');
        } elseif ($routeName === 'super-admin.exports') {
            $routeSegment = match ($request->route('type')) {
                'businesses' => 'businesses',
                'members' => 'accounts',
                'memberships' => 'memberships',
                'registrants' => 'events',
                'marketing' => 'marketing',
                'orders' => 'orders',
                'payments' => 'billing',
                default => '',
            };
        } elseif ($routeName === 'super-admin.spa') {
            $routeSegment = explode('/', trim((string) $request->route('path'), '/'))[0] ?? 'overview';
            $routeSegment = $routeSegment === '' ? 'overview' : $routeSegment;
        }

        $module = match ($routeSegment) {
            'resources' => 'content',
            'services' => 'orders',
            'announcements' => 'communications',
            'event-registrations' => 'events',
            default => $routeSegment,
        };

        if (! in_array($module, User::SYSTEM_ADMIN_MODULES, true)) {
            return [null, 'read'];
        }

        $ability = $request->isMethod('get') || $routeName === 'super-admin.exports'
            || $routeName === 'super-admin.announcements.preview'
            ? 'read'
            : 'write';

        return [$module, $ability];
    }
}
