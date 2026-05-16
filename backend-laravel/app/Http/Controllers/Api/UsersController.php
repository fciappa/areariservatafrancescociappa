<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    public function index()
    {
        $rows = DB::select(
            'SELECT id, username, email, role, collaborator_id, is_active, created_at FROM users ORDER BY username'
        );
        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $id = DB::table('users')->insertGetId([
            'username'        => $request->input('username'),
            'email'           => $request->input('email'),
            'password_hash'   => Hash::make($request->input('password')),
            'role'            => $request->input('role', 'collaborator'),
            'collaborator_id' => $request->input('collaborator_id'),
        ]);
        return response()->json(['id' => $id], 201);
    }

    public function changePassword(Request $request, int $id)
    {
        DB::table('users')->where('id', $id)->update([
            'password_hash' => Hash::make($request->input('password')),
        ]);
        return response()->json(['message' => 'Password aggiornata']);
    }

    public function toggle(int $id)
    {
        DB::statement('UPDATE users SET is_active = NOT is_active WHERE id = ?', [$id]);
        return response()->json(['message' => 'Stato aggiornato']);
    }
}
