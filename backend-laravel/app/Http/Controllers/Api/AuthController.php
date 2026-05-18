<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    private function signAccess(object $user): string
    {
        $payload = [
            'id'              => $user->id,
            'role'            => $user->role,
            'collaborator_id' => $user->collaborator_id,
            'exp'             => time() + (int) env('JWT_EXPIRES_SECONDS', 900),
        ];
        return JWT::encode($payload, env('JWT_SECRET'), 'HS256');
    }

    private function signRefresh(int $userId): string
    {
        $payload = [
            'id'  => $userId,
            'exp' => time() + (int) env('JWT_REFRESH_EXPIRES_SECONDS', 604800),
        ];
        return JWT::encode($payload, env('JWT_REFRESH_SECRET'), 'HS256');
    }

    public function login(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');

        if (!$username || !$password) {
            Log::warning('Login: credenziali mancanti', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Username e password obbligatori'], 400);
        }

        Log::debug('Login: tentativo', ['username' => $username, 'ip' => $request->ip()]);

        $users = DB::select(
            'SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1',
            [$username]
        );
        $user = $users[0] ?? null;

        // password_verify() accetta sia $2b$ (Node.js) che $2y$ (PHP)
        if (!$user || !password_verify($password, $user->password_hash)) {
            Log::warning('Login: fallito', ['username' => $username, 'ip' => $request->ip()]);
            return response()->json(['message' => 'Credenziali non valide'], 401);
        }

        $accessToken  = $this->signAccess($user);
        $refreshToken = $this->signRefresh($user->id);

        $decoded = JWT::decode($refreshToken, new Key(env('JWT_REFRESH_SECRET'), 'HS256'));
        DB::insert(
            'INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))',
            [$user->id, $refreshToken, $decoded->exp]
        );

        Log::info('Login: successo', ['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role]);

        return response()->json([
            'accessToken'  => $accessToken,
            'refreshToken' => $refreshToken,
            'user'         => [
                'id'             => $user->id,
                'username'       => $user->username,
                'role'           => $user->role,
                'collaborator_id' => $user->collaborator_id,
            ],
        ]);
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->input('refreshToken');
        if (!$refreshToken) {
            Log::warning('Refresh: token mancante', ['ip' => $request->ip()]);
            return response()->json(['message' => 'Refresh token mancante'], 400);
        }

        try {
            $payload = JWT::decode($refreshToken, new Key(env('JWT_REFRESH_SECRET'), 'HS256'));
        } catch (\Exception $e) {
            Log::warning('Refresh: token non valido o scaduto', ['ip' => $request->ip(), 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Refresh token scaduto o non valido'], 401);
        }

        $tokens = DB::select(
            'SELECT * FROM refresh_tokens WHERE token = ? AND expires_at > NOW()',
            [$refreshToken]
        );
        if (empty($tokens)) {
            Log::warning('Refresh: token non trovato in DB', ['user_id' => $payload->id]);
            return response()->json(['message' => 'Refresh token non valido'], 401);
        }

        $users = DB::select('SELECT * FROM users WHERE id = ?', [$payload->id]);
        $user  = $users[0] ?? null;
        if (!$user || !$user->is_active) {
            Log::warning('Refresh: utente non attivo', ['user_id' => $payload->id]);
            return response()->json(['message' => 'Utente non attivo'], 401);
        }

        Log::debug('Refresh: nuovo access token emesso', ['user_id' => $user->id]);
        return response()->json(['accessToken' => $this->signAccess($user)]);
    }

    public function logout(Request $request)
    {
        $refreshToken = $request->input('refreshToken');
        if ($refreshToken) {
            DB::delete('DELETE FROM refresh_tokens WHERE token = ?', [$refreshToken]);
        }
        Log::info('Logout effettuato', ['ip' => $request->ip()]);
        return response()->json(['message' => 'Logout effettuato']);
    }
}
