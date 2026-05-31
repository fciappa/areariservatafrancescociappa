<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeadlinesController extends Controller
{
    public function index(Request $request)
    {
        $sql = '
            SELECT d.*, c.company_name
            FROM client_deadlines d
            JOIN clients c ON c.id = d.client_id
        ';

        $bindings = [];

        if ($request->filled('client_id')) {
            $sql .= ' WHERE d.client_id = ? ';
            $bindings[] = (int) $request->query('client_id');
        }

        $sql .= ' ORDER BY d.due_date ASC, d.id ASC ';

        $rows = DB::select($sql, $bindings);
        return response()->json($rows);
    }

    public function store(Request $request)
    {
        if (!$request->filled('client_id') || !$request->filled('due_date') || !$request->filled('item_type') || !$request->filled('description')) {
            return response()->json([
                'message' => 'client_id, due_date, item_type e description sono obbligatori',
            ], 400);
        }

        $clientId = (int) $request->input('client_id');
        $exists = DB::select('SELECT id FROM clients WHERE id = ? LIMIT 1', [$clientId]);

        if (empty($exists)) {
            return response()->json(['message' => 'Cliente non trovato'], 404);
        }

        $id = DB::table('client_deadlines')->insertGetId([
            'client_id'      => $clientId,
            'due_date'       => $request->input('due_date'),
            'item_type'      => $request->input('item_type'),
            'description'    => $request->input('description'),
            'linked_to'      => $request->input('linked_to'),
            'avada_version'  => $request->input('avada_version'),
            'php_version'    => $request->input('php_version'),
            'mysql_version'  => $request->input('mysql_version'),
            'wp_version'     => $request->input('wp_version'),
            'test_email'     => $request->input('test_email'),
            'line_ref'       => $request->input('line_ref'),
            'notes'          => $request->input('notes'),
            'amount'         => $request->filled('amount') ? $request->input('amount') : null,
            'is_active'      => $request->input('is_active', 1),
        ]);

        Log::info('Deadlines: creata', ['id' => $id, 'client_id' => $clientId]);

        $rows = DB::select('
            SELECT d.*, c.company_name
            FROM client_deadlines d
            JOIN clients c ON c.id = d.client_id
            WHERE d.id = ?
            LIMIT 1
        ', [$id]);

        return response()->json($rows[0], 201);
    }
}
