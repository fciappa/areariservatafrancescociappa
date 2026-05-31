<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiRequestValidator;
use App\Support\ApiValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferentsController extends Controller
{
    public function index()
    {
        $rows = DB::select('SELECT * FROM referents ORDER BY last_name, first_name');
        return response()->json($rows);
    }

    public function show(int $id)
    {
        $rows = DB::select('SELECT * FROM referents WHERE id = ?', [$id]);
        if (empty($rows)) {
            return response()->json(['message' => 'Referente non trovato'], 404);
        }
        return response()->json($rows[0]);
    }

    public function store(Request $request)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::referentStore());

        $id = DB::table('referents')->insertGetId([
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'email'       => $data['email'],
            'phone'       => $data['phone'] ?? null,
            'fiscal_code' => $data['fiscal_code'] ?? null,
            'notes'       => $data['notes'] ?? null,
        ]);

        Log::info('Referents: creato', ['id' => $id]);
        return response()->json(['id' => $id], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::referentUpdate($id));

        DB::table('referents')->where('id', $id)->update([
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'email'       => $data['email'],
            'phone'       => $data['phone'] ?? null,
            'fiscal_code' => $data['fiscal_code'] ?? null,
            'notes'       => $data['notes'] ?? null,
            'is_active'   => $data['is_active'] ?? 1,
        ]);

        Log::info('Referents: aggiornato', ['id' => $id]);
        return response()->json(['message' => 'Aggiornato']);
    }

    public function destroy(int $id)
    {
        DB::table('referents')->where('id', $id)->update(['is_active' => 0]);
        Log::info('Referents: disattivato', ['id' => $id]);
        return response()->json(['message' => 'Disattivato']);
    }
}
