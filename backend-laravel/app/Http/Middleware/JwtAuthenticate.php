<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization', '');

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            Log::warning('JWT: token mancante nell\'header', ['ip' => $request->ip(), 'path' => $request->path()]);
            return response()->json(['message' => 'Token mancante'], 401);
        }

        $token = substr($header, 7);

        try {
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
            $request->attributes->set('jwt_user', $decoded);
        } catch (\Exception $e) {
            Log::warning('JWT: token non valido o scaduto', ['ip' => $request->ip(), 'path' => $request->path(), 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Token non valido o scaduto'], 401);
        }

        return $next($request);
    }
}
