<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReferentOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->attributes->get('jwt_user');

        if (!$user || $user->role !== 'referent') {
            Log::warning('ReferentOnly: accesso negato', [
                'ip'      => $request->ip(),
                'path'    => $request->path(),
                'user_id' => $user->id ?? null,
                'role'    => $user->role ?? null,
            ]);
            return response()->json(['message' => 'Accesso riservato ai referenti'], 403);
        }

        return $next($request);
    }
}
