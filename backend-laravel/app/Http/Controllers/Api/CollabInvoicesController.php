<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollabInvoicesController extends Controller
{
    public function index(Request $request)
    {
        $sql    = '
            SELECT ci.*, c.first_name, c.last_name,
                   CONCAT(c.last_name, \' \', c.first_name) AS collaborator_name
            FROM collab_invoices ci
            JOIN collaborators c ON c.id = ci.collaborator_id
        ';
        $params = [];
        $where  = [];

        if ($request->filled('year') && $request->filled('month')) {
            $where[]  = 'YEAR(ci.invoice_date) = ? AND MONTH(ci.invoice_date) = ?';
            $params[] = $request->query('year');
            $params[] = $request->query('month');
        } elseif ($request->filled('year')) {
            $where[]  = 'YEAR(ci.invoice_date) = ?';
            $params[] = $request->query('year');
        }

        if ($request->filled('collaborator_id')) {
            $where[]  = 'ci.collaborator_id = ?';
            $params[] = $request->query('collaborator_id');
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY ci.invoice_date DESC';
        return response()->json(DB::select($sql, $params));
    }

    public function show(int $id)
    {
        $rows = DB::select('
            SELECT ci.*, c.first_name, c.last_name,
                   CONCAT(c.last_name, \' \', c.first_name) AS collaborator_name
            FROM collab_invoices ci
            JOIN collaborators c ON c.id = ci.collaborator_id
            WHERE ci.id = ?
        ', [$id]);

        if (empty($rows)) {
            return response()->json(['message' => 'Fattura non trovata'], 404);
        }

        $items = DB::select('SELECT * FROM collab_invoice_items WHERE collab_invoice_id = ?', [$id]);
        return response()->json(array_merge((array) $rows[0], ['items' => $items]));
    }

    public function store(Request $request)
    {
        $items     = $request->input('items', []);
        $invoiceId = null;

        DB::transaction(function () use ($request, $items, &$invoiceId) {
            $invoiceId = DB::table('collab_invoices')->insertGetId([
                'collaborator_id' => $request->input('collaborator_id'),
                'invoice_number'  => $request->input('invoice_number'),
                'invoice_date'    => $request->input('invoice_date'),
                'subtotal'        => $request->input('subtotal'),
                'tax_amount'      => $request->input('tax_amount'),
                'total'           => $request->input('total'),
                'notes'           => $request->input('notes'),
            ]);

            foreach ($items as $item) {
                DB::table('collab_invoice_items')->insert([
                    'collab_invoice_id' => $invoiceId,
                    'collab_hour_id'    => $item['collab_hour_id'] ?? null,
                    'description'       => $item['description'] ?? '',
                    'tariff_id'         => $item['tariff_id'],
                    'hours'             => $item['hours'],
                    'hourly_rate'       => $item['hourly_rate'],
                    'tax_inclusive'     => $item['tax_inclusive'] ?? false,
                    'line_total'        => $item['line_total'],
                ]);
            }
        });

        Log::info('CollabInvoices: fattura proforma creata', [
            'id'              => $invoiceId,
            'invoice_number'  => $request->input('invoice_number'),
            'collaborator_id' => $request->input('collaborator_id'),
            'total'           => $request->input('total'),
        ]);
        return response()->json(['id' => $invoiceId], 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        DB::table('collab_invoices')->where('id', $id)->update(['status' => $request->input('status')]);
        Log::info('CollabInvoices: stato aggiornato', ['id' => $id, 'status' => $request->input('status')]);
        return response()->json(['message' => 'Stato aggiornato']);
    }

    public function destroy(int $id)
    {
        DB::table('collab_invoices')->where('id', $id)->delete();
        Log::info('CollabInvoices: fattura eliminata', ['id' => $id]);
        return response()->json(['message' => 'Eliminata']);
    }
}
