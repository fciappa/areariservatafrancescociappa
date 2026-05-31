<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiRequestValidator;
use App\Support\ApiValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollabInvoicesController extends Controller
{
    public function index(Request $request)
    {
        $filters = ApiRequestValidator::validate($request, ApiValidationRules::collabInvoicesIndexFilters());

        $sql    = '
            SELECT ci.*, c.first_name, c.last_name,
                   CONCAT(c.last_name, \' \', c.first_name) AS collaborator_name
            FROM collab_invoices ci
            JOIN collaborators c ON c.id = ci.collaborator_id
        ';
        $params = [];
        $where  = [];

        if (!empty($filters['year']) && !empty($filters['month'])) {
            $where[]  = 'YEAR(ci.invoice_date) = ? AND MONTH(ci.invoice_date) = ?';
            $params[] = $filters['year'];
            $params[] = $filters['month'];
        } elseif (!empty($filters['year'])) {
            $where[]  = 'YEAR(ci.invoice_date) = ?';
            $params[] = $filters['year'];
        }

        if (!empty($filters['collaborator_id'])) {
            $where[]  = 'ci.collaborator_id = ?';
            $params[] = $filters['collaborator_id'];
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
        $data      = ApiRequestValidator::validate($request, ApiValidationRules::collabInvoicesStore());
        $items     = $data['items'];
        $invoiceId = null;

        DB::transaction(function () use ($data, $items, &$invoiceId) {
            $invoiceId = DB::table('collab_invoices')->insertGetId([
                'collaborator_id' => $data['collaborator_id'],
                'invoice_number'  => $data['invoice_number'],
                'invoice_date'    => $data['invoice_date'],
                'subtotal'        => $data['subtotal'],
                'tax_amount'      => $data['tax_amount'],
                'total'           => $data['total'],
                'notes'           => $data['notes'] ?? null,
            ]);

            $allCollabHourIds = [];
            foreach ($items as $item) {
                DB::table('collab_invoice_items')->insert([
                    'collab_invoice_id' => $invoiceId,
                    'collab_hour_id'    => null,
                    'description'       => $item['description'] ?? '',
                    'tariff_id'         => $item['tariff_id'],
                    'hours'             => $item['hours'],
                    'hourly_rate'       => $item['hourly_rate'],
                    'tax_inclusive'     => $item['tax_inclusive'] ?? false,
                    'line_total'        => $item['line_total'],
                ]);
                if (!empty($item['collab_hour_ids']) && \is_array($item['collab_hour_ids'])) {
                    $allCollabHourIds = array_merge($allCollabHourIds, $item['collab_hour_ids']);
                }
            }

            if ($allCollabHourIds) {
                DB::table('collaborator_hours')
                    ->whereIn('id', $allCollabHourIds)
                    ->update(['invoiced_at' => now()]);
            }
        });

        Log::info('CollabInvoices: fattura proforma creata', [
            'id'              => $invoiceId,
            'invoice_number'  => $data['invoice_number'],
            'collaborator_id' => $data['collaborator_id'],
            'total'           => $data['total'],
        ]);
        return response()->json(['id' => $invoiceId], 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::collabInvoicesUpdateStatus());
        $status = $data['status'];
        $update = ['status' => $status];

        if ($status === 'paid') {
            $inv = DB::table('collab_invoices')->where('id', $id)->first();
            if ($inv && !$inv->paid_at) {
                $update['paid_at'] = now()->toDateString();
            }
        }

        DB::table('collab_invoices')->where('id', $id)->update($update);
        Log::info('CollabInvoices: stato aggiornato', ['id' => $id, 'status' => $status]);
        return response()->json(['message' => 'Stato aggiornato']);
    }

    public function destroy(int $id)
    {
        DB::table('collab_invoices')->where('id', $id)->delete();
        Log::info('CollabInvoices: fattura eliminata', ['id' => $id]);
        return response()->json(['message' => 'Eliminata']);
    }

    public function myInvoices(Request $request)
    {
        $user = $request->attributes->get('jwt_user');
        $rows = DB::select('
            SELECT *
            FROM collab_invoices
            WHERE collaborator_id = ?
              AND status IN ("sent", "paid")
            ORDER BY invoice_date DESC
        ', [$user->collaborator_id]);
        return response()->json($rows);
    }

    public function myInvoiceDetail(Request $request, int $id)
    {
        $user = $request->attributes->get('jwt_user');
        $rows = DB::select(
            'SELECT * FROM collab_invoices WHERE id = ? AND collaborator_id = ?',
            [$id, $user->collaborator_id]
        );
        if (empty($rows)) {
            return response()->json(['message' => 'Non trovata'], 404);
        }
        $items = DB::select('SELECT * FROM collab_invoice_items WHERE collab_invoice_id = ?', [$id]);
        return response()->json(array_merge((array) $rows[0], ['items' => $items]));
    }

    public function markPaid(Request $request, int $id)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::collabInvoicesMarkPaid());

        $user = $request->attributes->get('jwt_user');
        $inv  = DB::table('collab_invoices')
            ->where('id', $id)
            ->where('collaborator_id', $user->collaborator_id)
            ->first();
        if (!$inv) {
            return response()->json(['message' => 'Non trovata'], 404);
        }
        $paidAt = $data['paid_at'] ?? now()->toDateString();
        DB::table('collab_invoices')->where('id', $id)->update([
            'status'  => 'paid',
            'paid_at' => $paidAt,
        ]);
        Log::info('CollabInvoices: collaboratore ha segnato come pagata', ['id' => $id, 'paid_at' => $paidAt]);
        return response()->json(['message' => 'Segnata come pagata', 'paid_at' => $paidAt]);
    }
}
