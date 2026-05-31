<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        Log::info('Clients: creato', ['id' => $id, 'company' => $request->input('company_name')]);
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
        Log::info('Clients: aggiornato', ['id' => $id]);
        return response()->json(['message' => 'Aggiornato']);
    }

    public function destroy(int $id)
    {
        DB::table('clients')->where('id', $id)->update(['is_active' => 0]);
        Log::info('Clients: disattivato', ['id' => $id]);
        return response()->json(['message' => 'Disattivato']);
    }

    public function referents(int $id)
    {
        $rows = DB::select(
            'SELECT cr.id, cr.user_id, u.username, u.email, u.referent_id,
                    r.first_name, r.last_name
             FROM client_referents cr
             JOIN users u ON u.id = cr.user_id
             LEFT JOIN referents r ON r.id = u.referent_id
             WHERE cr.client_id = ?
             ORDER BY u.username',
            [$id]
        );

        return response()->json($rows);
    }

    public function addReferents(Request $request, int $id)
    {
        $userIds = $request->input('user_ids');
        if (!$userIds) {
            $single = $request->input('user_id');
            $userIds = $single ? [$single] : [];
        }

        if (!is_array($userIds) || empty($userIds)) {
            return response()->json(['message' => 'user_ids obbligatorio'], 400);
        }

        $clientRows = DB::select('SELECT id FROM clients WHERE id = ? LIMIT 1', [$id]);
        if (empty($clientRows)) {
            return response()->json(['message' => 'Cliente non trovato'], 404);
        }

        $inserted = 0;
        foreach ($userIds as $userId) {
            $userRows = DB::select('SELECT id, role, is_active FROM users WHERE id = ? LIMIT 1', [$userId]);
            $target = $userRows[0] ?? null;
            if (!$target || $target->role !== 'referent' || !$target->is_active) {
                continue;
            }

            try {
                DB::table('client_referents')->insert([
                    'client_id' => $id,
                    'user_id'   => $target->id,
                ]);
                $inserted++;
            } catch (\Illuminate\Database\QueryException $e) {
                if (($e->errorInfo[1] ?? null) !== 1062) {
                    throw $e;
                }
            }
        }

        Log::info('Clients: referenti assegnati', ['client_id' => $id, 'count' => $inserted]);
        return response()->json(['inserted' => $inserted]);
    }

    public function removeReferent(int $id, int $userId)
    {
        DB::table('client_referents')
            ->where('client_id', $id)
            ->where('user_id', $userId)
            ->delete();

        Log::info('Clients: referente rimosso', ['client_id' => $id, 'user_id' => $userId]);
        return response()->json(['success' => true]);
    }
}
