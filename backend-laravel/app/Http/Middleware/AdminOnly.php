<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->attributes->get('jwt_user');

        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Accesso riservato agli amministratori'], 403);
        }

        return $next($request);
    }
}
