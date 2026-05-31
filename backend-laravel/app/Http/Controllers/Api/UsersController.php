<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiRequestValidator;
use App\Support\ApiValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UsersController extends Controller
{
    public function index()
    {
        $rows = DB::select(
            'SELECT u.id, u.username, u.email, u.role, u.collaborator_id, u.referent_id, u.is_active, u.created_at,
                    r.first_name AS referent_first_name, r.last_name AS referent_last_name
             FROM users u
             LEFT JOIN referents r ON r.id = u.referent_id
             ORDER BY u.username'
        );
        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::userStore());

        if ($data['role'] !== 'collaborator' && !empty($data['collaborator_id'])) {
            return response()->json(['message' => 'collaborator_id ammesso solo per ruolo collaborator'], 422);
        }

        if ($data['role'] !== 'referent' && !empty($data['referent_id'])) {
            return response()->json(['message' => 'referent_id ammesso solo per ruolo referent'], 422);
        }

        $id = DB::table('users')->insertGetId([
            'username'        => $data['username'],
            'email'           => $data['email'],
            'password_hash'   => Hash::make($data['password']),
            'role'            => $data['role'],
            'collaborator_id' => $data['role'] === 'collaborator' ? ($data['collaborator_id'] ?? null) : null,
            'referent_id'     => $data['role'] === 'referent' ? ($data['referent_id'] ?? null) : null,
        ]);
        Log::info('Users: creato', ['user_id' => $id, 'username' => $data['username'], 'role' => $data['role']]);
        return response()->json(['id' => $id], 201);
    }

    public function changePassword(Request $request, int $id)
    {
        DB::table('users')->where('id', $id)->update([
            'password_hash' => Hash::make($request->input('password')),
        ]);
        Log::info('Users: password cambiata', ['target_user_id' => $id]);
        return response()->json(['message' => 'Password aggiornata']);
    }

    public function toggle(int $id)
    {
        DB::statement('UPDATE users SET is_active = NOT is_active WHERE id = ?', [$id]);
        Log::info('Users: toggle attivo', ['target_user_id' => $id]);
        return response()->json(['message' => 'Stato aggiornato']);
    }
}
