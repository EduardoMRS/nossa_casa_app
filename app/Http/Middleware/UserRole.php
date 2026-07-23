<?php

namespace App\Http\Middleware;

use App\Enums\UserRole as UserRoleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserRole
{
    public function handle(Request $request, Closure $next, string $roles, ?string $redirectTo = null): Response
    {
        $user = $request->user();

        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated.'], 401)
                : redirect()->route('login');
        }

        $allowedRoles = array_map(UserRoleEnum::from(...), explode('|', $roles));
        $userRole = $user->role instanceof UserRoleEnum
            ? $user->role
            : UserRoleEnum::from($user->role);

        if (! $this->hasRole($userRole, $allowedRoles)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            if ($redirectTo) {
                return redirect()->route($redirectTo);
            }

            abort(403);
        }

        return $next($request);
    }

    /**
     * @param  array<int, UserRoleEnum>  $allowedRoles
     */
    private function hasRole(UserRoleEnum $userRole, array $allowedRoles): bool
    {
        if (in_array($userRole, [UserRoleEnum::SYSTEM, UserRoleEnum::SUPERADMIN], true)) {
            return true;
        }

        if ($userRole === UserRoleEnum::ADMIN) {
            return in_array(UserRoleEnum::ADMIN, $allowedRoles, true)
                || in_array(UserRoleEnum::LEADER, $allowedRoles, true)
                || in_array(UserRoleEnum::MEDIA, $allowedRoles, true);
        }

        return in_array($userRole, $allowedRoles, true);
    }
}
