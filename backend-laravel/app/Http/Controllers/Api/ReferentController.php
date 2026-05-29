<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferentController extends Controller
{
    private function resolveMonth(Request $request): string
    {
        $month = $request->query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            abort(response()->json(['message' => 'Parametro month non valido (usa YYYY-MM)'], 422));
        }
        return $month;
    }

    public function projectSummary(Request $request)
    {
        $user  = $request->attributes->get('jwt_user');
        $month = $this->resolveMonth($request);

        $rows = DB::select(
            'SELECT
                p.id,
                p.name,
                p.status,
                c.company_name,
                SUM(CASE WHEN ch.id IS NOT NULL AND ch.status <> "rejected" THEN ch.hours ELSE 0 END) AS total_hours,
                SUM(CASE WHEN ch.id IS NOT NULL AND ch.status <> "rejected" AND ch.invoiced_at IS NOT NULL THEN ch.hours ELSE 0 END) AS invoiced_hours,
                SUM(CASE WHEN ch.id IS NOT NULL AND ch.status <> "rejected" AND ch.invoiced_at IS NULL THEN ch.hours ELSE 0 END) AS to_invoice_hours,
                SUM(CASE
                    WHEN ch.id IS NOT NULL AND ch.status <> "rejected" AND ch.invoiced_at IS NOT NULL THEN
                        CASE WHEN t.rate_type = "daily" THEN (t.hourly_rate / 8) * ch.hours ELSE t.hourly_rate * ch.hours END
                    ELSE 0 END
                ) AS invoiced_gross,
                SUM(CASE
                    WHEN ch.id IS NOT NULL AND ch.status <> "rejected" AND ch.invoiced_at IS NULL THEN
                        CASE WHEN t.rate_type = "daily" THEN (t.hourly_rate / 8) * ch.hours ELSE t.hourly_rate * ch.hours END
                    ELSE 0 END
                ) AS to_invoice_gross
            FROM project_referents pr
            JOIN projects p ON p.id = pr.project_id
            JOIN clients c ON c.id = p.client_id
            LEFT JOIN collaborator_hours ch
                ON ch.project_id = p.id
               AND DATE_FORMAT(ch.work_date, "%Y-%m") = ?
            LEFT JOIN tariffs t ON t.id = ch.tariff_id
            WHERE pr.user_id = ?
            GROUP BY p.id, p.name, p.status, c.company_name
            ORDER BY p.name',
            [$month, $user->id]
        );

        return response()->json($rows);
    }

    public function projectHours(Request $request)
    {
        $user      = $request->attributes->get('jwt_user');
        $month     = $this->resolveMonth($request);
        $projectId = $request->query('project_id');

        $params = [$month, $user->id];
        $whereProject = '';
        if ($projectId) {
            $whereProject = ' AND p.id = ? ';
            $params[] = $projectId;
        }

        $rows = DB::select(
            'SELECT
                ch.id,
                ch.project_id,
                p.name AS project_name,
                ch.work_date,
                ch.hours,
                ch.status,
                ch.invoiced_at,
                ch.description,
                co.first_name,
                co.last_name,
                t.name AS tariff_name,
                t.hourly_rate,
                t.rate_type,
                t.tax_inclusive
            FROM collaborator_hours ch
            JOIN projects p ON p.id = ch.project_id
            JOIN project_referents pr ON pr.project_id = p.id
            LEFT JOIN collaborators co ON co.id = ch.collaborator_id
            LEFT JOIN tariffs t ON t.id = ch.tariff_id
            WHERE DATE_FORMAT(ch.work_date, "%Y-%m") = ?
              AND pr.user_id = ?
              AND ch.status <> "rejected" '
              . $whereProject .
            ' ORDER BY p.name, ch.work_date DESC, co.last_name, co.first_name',
            $params
        );

        return response()->json($rows);
    }
}
