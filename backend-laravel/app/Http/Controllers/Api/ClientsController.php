<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientsController extends Controller
{
    public function index()
    {
        $rows = DB::select('SELECT * FROM clients ORDER BY company_name');
        return response()->json($rows);
    }

    public function show(int $id)
    {
        $rows = DB::select('SELECT * FROM clients WHERE id = ?', [$id]);
        if (empty($rows)) {
            return response()->json(['message' => 'Cliente non trovato'], 404);
        }
        return response()->json($rows[0]);
    }

    public function store(Request $request)
    {
        $id = DB::table('clients')->insertGetId([
            'company_name' => $request->input('company_name'),
            'vat_number'   => $request->input('vat_number'),
            'email'        => $request->input('email'),
            'phone'        => $request->input('phone'),
            'address'      => $request->input('address'),
            'city'         => $request->input('city'),
            'postal_code'  => $request->input('postal_code'),
            'country'      => $request->input('country', 'Italia'),
            'notes'        => $request->input('notes'),
        ]);
        return response()->json(['id' => $id], 201);
    }

    public function update(Request $request, int $id)
    {
        DB::table('clients')->where('id', $id)->update([
            'company_name' => $request->input('company_name'),
            'vat_number'   => $request->input('vat_number'),
            'email'        => $request->input('email'),
            'phone'        => $request->input('phone'),
            'address'      => $request->input('address'),
            'city'         => $request->input('city'),
            'postal_code'  => $request->input('postal_code'),
            'country'      => $request->input('country', 'Italia'),
            'notes'        => $request->input('notes'),
            'is_active'    => $request->input('is_active', 1),
        ]);
        return response()->json(['message' => 'Aggiornato']);
    }

    public function destroy(int $id)
    {
        DB::table('clients')->where('id', $id)->update(['is_active' => 0]);
        return response()->json(['message' => 'Disattivato']);
    }
}
