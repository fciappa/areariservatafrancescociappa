<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TariffsController extends Controller
{
    public function index()
    {
        $rows = DB::select('SELECT * FROM tariffs ORDER BY is_default DESC, name');
        return response()->json($rows);
    }

    public function show(int $id)
    {
        $rows = DB::select('SELECT * FROM tariffs WHERE id = ?', [$id]);
        if (empty($rows)) {
            return response()->json(['message' => 'Tariffa non trovata'], 404);
        }
        return response()->json($rows[0]);
    }

    public function store(Request $request)
    {
        $isDefault = (bool) $request->input('is_default', false);
        $insertId  = null;

        DB::transaction(function () use ($request, $isDefault, &$insertId) {
            if ($isDefault) {
                DB::update('UPDATE tariffs SET is_default = 0');
            }
            $insertId = DB::table('tariffs')->insertGetId([
                'name'          => $request->input('name'),
                'rate_type'     => $request->input('rate_type', 'hourly'),
                'hourly_rate'   => $request->input('hourly_rate'),
                'valid_from'    => $request->input('valid_from'),
                'valid_to'      => $request->input('valid_to'),
                'is_default'    => $isDefault,
                'tax_inclusive' => (bool) $request->input('tax_inclusive', false),
                'notes'         => $request->input('notes'),
            ]);
        });

        Log::info('Tariffs: creata', ['id' => $insertId, 'name' => $request->input('name'), 'is_default' => $isDefault]);
        return response()->json(['id' => $insertId], 201);
    }

    public function update(Request $request, int $id)
    {
        $isDefault = (bool) $request->input('is_default', false);

        DB::transaction(function () use ($request, $id, $isDefault) {
            if ($isDefault) {
                DB::update('UPDATE tariffs SET is_default = 0 WHERE id != ?', [$id]);
            }
            DB::table('tariffs')->where('id', $id)->update([
                'name'          => $request->input('name'),
                'rate_type'     => $request->input('rate_type', 'hourly'),
                'hourly_rate'   => $request->input('hourly_rate'),
                'valid_from'    => $request->input('valid_from'),
                'valid_to'      => $request->input('valid_to'),
                'is_default'    => $isDefault,
                'tax_inclusive' => (bool) $request->input('tax_inclusive', false),
                'notes'         => $request->input('notes'),
            ]);
        });

        Log::info('Tariffs: aggiornata', ['id' => $id]);
        return response()->json(['message' => 'Aggiornato']);
    }

    public function destroy(int $id)
    {
        DB::table('tariffs')->where('id', $id)->delete();
        Log::info('Tariffs: eliminata', ['id' => $id]);
        return response()->json(['message' => 'Eliminato']);
    }
}
