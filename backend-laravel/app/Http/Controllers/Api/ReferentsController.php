<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $id = DB::table('referents')->insertGetId([
            'first_name'  => $request->input('first_name'),
            'last_name'   => $request->input('last_name'),
            'email'       => $request->input('email'),
            'phone'       => $request->input('phone'),
            'fiscal_code' => $request->input('fiscal_code'),
            'notes'       => $request->input('notes'),
        ]);

        Log::info('Referents: creato', ['id' => $id]);
        return response()->json(['id' => $id], 201);
    }

    public function update(Request $request, int $id)
    {
        DB::table('referents')->where('id', $id)->update([
            'first_name'  => $request->input('first_name'),
            'last_name'   => $request->input('last_name'),
            'email'       => $request->input('email'),
            'phone'       => $request->input('phone'),
            'fiscal_code' => $request->input('fiscal_code'),
            'notes'       => $request->input('notes'),
            'is_active'   => $request->input('is_active', 1),
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
