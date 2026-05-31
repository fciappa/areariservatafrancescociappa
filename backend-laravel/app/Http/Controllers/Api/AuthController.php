<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiRequestValidator;
use App\Support\ApiValidationRules;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            'referent_id'     => $user->referent_id,
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
        $data = ApiRequestValidator::validate($request, ApiValidationRules::authLogin());
        $username = $data['username'];
        $password = $data['password'];

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

        $firstName = null;
        $lastName  = null;
        if ($user->collaborator_id) {
            $collab = DB::select('SELECT first_name, last_name FROM collaborators WHERE id = ? LIMIT 1', [$user->collaborator_id]);
            if (!empty($collab)) {
                $firstName = $collab[0]->first_name;
                $lastName  = $collab[0]->last_name;
            }
        } elseif ($user->referent_id) {
            $referent = DB::select('SELECT first_name, last_name FROM referents WHERE id = ? LIMIT 1', [$user->referent_id]);
            if (!empty($referent)) {
                $firstName = $referent[0]->first_name;
                $lastName  = $referent[0]->last_name;
            }
        }

        $accessToken  = $this->signAccess($user);
        $refreshToken = $this->signRefresh($user->id);

        $decoded = JWT::decode($refreshToken, new Key(env('JWT_REFRESH_SECRET'), 'HS256'));
        DB::table('refresh_tokens')->insert([
            'user_id' => $user->id,
            'token' => $refreshToken,
            'expires_at' => Carbon::createFromTimestamp((int) $decoded->exp)->toDateTimeString(),
        ]);

        Log::info('Login: successo', ['user_id' => $user->id, 'username' => $user->username, 'role' => $user->role]);

        return response()->json([
            'accessToken'  => $accessToken,
            'refreshToken' => $refreshToken,
            'user'         => [
                'id'              => $user->id,
                'username'        => $user->username,
                'role'            => $user->role,
                'collaborator_id' => $user->collaborator_id,
                'referent_id'     => $user->referent_id,
                'first_name'      => $firstName,
                'last_name'       => $lastName,
            ],
        ]);
    }

    public function refresh(Request $request)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::authRefresh());
        $refreshToken = $data['refreshToken'];

        try {
            $payload = JWT::decode($refreshToken, new Key(env('JWT_REFRESH_SECRET'), 'HS256'));
        } catch (\Exception $e) {
            Log::warning('Refresh: token non valido o scaduto', ['ip' => $request->ip(), 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Refresh token scaduto o non valido'], 401);
        }

        $tokenExists = DB::table('refresh_tokens')
            ->where('token', $refreshToken)
            ->where('expires_at', '>', now())
            ->exists();
        if (!$tokenExists) {
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
            DB::table('refresh_tokens')->where('token', $refreshToken)->delete();
        }
        Log::info('Logout effettuato', ['ip' => $request->ip()]);
        return response()->json(['message' => 'Logout effettuato']);
    }
}
