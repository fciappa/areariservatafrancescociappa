<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiRequestValidator;
use App\Support\ApiValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollaboratorsController extends Controller
{
    public function index()
    {
        $rows = DB::select('SELECT * FROM collaborators ORDER BY last_name, first_name');
        return response()->json($rows);
    }

    public function show(int $id)
    {
        $rows = DB::select('SELECT * FROM collaborators WHERE id = ?', [$id]);
        if (empty($rows)) {
            return response()->json(['message' => 'Collaboratore non trovato'], 404);
        }
        return response()->json($rows[0]);
    }

    public function store(Request $request)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::collaboratorStore());

        $id = null;
        DB::transaction(function () use ($data, &$id) {
            if (!empty($data['is_me'])) {
                DB::table('collaborators')->update(['is_me' => 0]);
            }

            $id = DB::table('collaborators')->insertGetId([
                'first_name'  => $data['first_name'],
                'last_name'   => $data['last_name'],
                'email'       => $data['email'],
                'phone'       => $data['phone'] ?? null,
                'fiscal_code' => $data['fiscal_code'] ?? null,
                'notes'       => $data['notes'] ?? null,
                'is_active'   => array_key_exists('is_active', $data) ? (int) ((bool) $data['is_active']) : 1,
                'is_me'       => !empty($data['is_me']) ? 1 : 0,
            ]);
        });

        Log::info('Collaborators: creato', ['id' => $id, 'name' => $request->input('last_name').' '.$request->input('first_name')]);
        return response()->json(['id' => $id], 201);
    }

    public function update(Request $request, int $id)
    {
        if (!DB::table('collaborators')->where('id', $id)->exists()) {
            return response()->json(['message' => 'Collaboratore non trovato'], 404);
        }

        $data = ApiRequestValidator::validate($request, ApiValidationRules::collaboratorUpdate($id));

        DB::transaction(function () use ($data, $id) {
            if (!empty($data['is_me'])) {
                DB::table('collaborators')->where('id', '!=', $id)->update(['is_me' => 0]);
            }

            DB::table('collaborators')->where('id', $id)->update([
                'first_name'  => $data['first_name'],
                'last_name'   => $data['last_name'],
                'email'       => $data['email'],
                'phone'       => $data['phone'] ?? null,
                'fiscal_code' => $data['fiscal_code'] ?? null,
                'notes'       => $data['notes'] ?? null,
                'is_active'   => array_key_exists('is_active', $data) ? (int) ((bool) $data['is_active']) : 1,
                'is_me'       => !empty($data['is_me']) ? 1 : 0,
            ]);
        });

        Log::info('Collaborators: aggiornato', ['id' => $id]);
        return response()->json(['message' => 'Aggiornato']);
    }

    public function destroy(int $id)
    {
        DB::table('collaborators')->where('id', $id)->update(['is_active' => 0]);
        Log::info('Collaborators: disattivato', ['id' => $id]);
        return response()->json(['message' => 'Disattivato']);
    }
}
