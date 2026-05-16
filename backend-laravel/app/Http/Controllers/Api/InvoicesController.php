<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoicesController extends Controller
{
    public function index(Request $request)
    {
        $sql    = 'SELECT i.*, c.company_name FROM invoices i JOIN clients c ON c.id = i.client_id';
        $params = [];

        if ($request->filled('year') && $request->filled('month')) {
            $sql .= ' WHERE YEAR(i.invoice_date) = ? AND MONTH(i.invoice_date) = ?';
            $params = [$request->query('year'), $request->query('month')];
        } elseif ($request->filled('year')) {
            $sql .= ' WHERE YEAR(i.invoice_date) = ?';
            $params = [$request->query('year')];
        }

        $sql .= ' ORDER BY i.invoice_date DESC';
        return response()->json(DB::select($sql, $params));
    }

    public function monthlySummary()
    {
        $rows = DB::select('
            SELECT
                YEAR(invoice_date)  AS year,
                MONTH(invoice_date) AS month,
                COUNT(*)            AS count,
                SUM(total)          AS total_invoiced
            FROM invoices
            WHERE status != "draft"
            GROUP BY YEAR(invoice_date), MONTH(invoice_date)
            ORDER BY year DESC, month DESC
        ');
        return response()->json($rows);
    }

    public function show(int $id)
    {
        $invoices = DB::select(
            'SELECT i.*, c.company_name FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ?',
            [$id]
        );
        if (empty($invoices)) {
            return response()->json(['message' => 'Fattura non trovata'], 404);
        }

        $items = DB::select('SELECT * FROM invoice_items WHERE invoice_id = ?', [$id]);
        return response()->json(array_merge((array) $invoices[0], ['items' => $items]));
    }

    public function simulate(Request $request)
    {
        $items     = $request->input('items', []);
        $stampDuty = (float) $request->input('stamp_duty', 2);

        $subtotal  = 0.0;
        $taxAmount = 0.0;

        $computed = array_map(function ($item) use (&$subtotal, &$taxAmount) {
            $gross = (float) $item['hourly_rate'] * (float) $item['hours'];

            if (!empty($item['tax_inclusive'])) {
                $imponibile = $gross / 1.04;
                $tax        = $gross - $imponibile;
            } else {
                $imponibile = $gross;
                $tax        = $gross * 0.04;
            }

            $subtotal  += $imponibile;
            $taxAmount += $tax;

            return array_merge($item, [
                'gross'      => round($gross, 2),
                'imponibile' => round($imponibile, 2),
                'tax'        => round($tax, 2),
            ]);
        }, $items);

        $total = $subtotal + $taxAmount + $stampDuty;

        return response()->json([
            'items'      => $computed,
            'subtotal'   => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'stamp_duty' => round($stampDuty, 2),
            'total'      => round($total, 2),
        ]);
    }

    public function store(Request $request)
    {
        $items     = $request->input('items', []);
        $invoiceId = null;

        DB::transaction(function () use ($request, $items, &$invoiceId) {
            $invoiceId = DB::table('invoices')->insertGetId([
                'invoice_number' => $request->input('invoice_number'),
                'client_id'      => $request->input('client_id'),
                'invoice_date'   => $request->input('invoice_date'),
                'stamp_duty'     => $request->input('stamp_duty', 2),
                'subtotal'       => $request->input('subtotal'),
                'tax_amount'     => $request->input('tax_amount'),
                'total'          => $request->input('total'),
                'notes'          => $request->input('notes'),
            ]);

            foreach ($items as $item) {
                DB::table('invoice_items')->insert([
                    'invoice_id'    => $invoiceId,
                    'work_hour_id'  => $item['work_hour_id'] ?? null,
                    'description'   => $item['description'] ?? '',
                    'tariff_id'     => $item['tariff_id'],
                    'hours'         => $item['hours'],
                    'hourly_rate'   => $item['hourly_rate'],
                    'tax_inclusive' => $item['tax_inclusive'] ?? false,
                    'line_total'    => $item['line_total'],
                ]);
            }
        });

        Log::info('Invoices: fattura creata', ['id' => $invoiceId, 'invoice_number' => $request->input('invoice_number'), 'client_id' => $request->input('client_id'), 'total' => $request->input('total')]);
        return response()->json(['id' => $invoiceId], 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        DB::table('invoices')->where('id', $id)->update(['status' => $request->input('status')]);
        Log::info('Invoices: stato aggiornato', ['id' => $id, 'status' => $request->input('status')]);
        return response()->json(['message' => 'Stato aggiornato']);
    }
}
