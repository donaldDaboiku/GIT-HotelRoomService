<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role?->value;
        if ($role === null || ! in_array($role, $roles, true)) {
            abort(403, 'You do not have permission for this action.');
        }

        return $next($request);
    }
}
