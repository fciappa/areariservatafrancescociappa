<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiRequestValidator;
use App\Support\ApiValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectsController extends Controller
{
    public function index()
    {
        $rows = DB::select('
            SELECT p.*, c.company_name, c.vat_number,
                   COALESCE(r.referents_count, 0) AS referents_count,
                   r.referents_list
            FROM projects p
            JOIN clients c ON c.id = p.client_id
            LEFT JOIN (
                SELECT t.project_id,
                       COUNT(*) AS referents_count,
                       GROUP_CONCAT(u.username ORDER BY u.username SEPARATOR ", ") AS referents_list
                FROM (
                    SELECT pr.project_id, pr.user_id
                    FROM project_referents pr
                    UNION
                    SELECT p2.id AS project_id, cr.user_id
                    FROM client_referents cr
                    JOIN projects p2 ON p2.client_id = cr.client_id
                ) t
                JOIN users u ON u.id = t.user_id
                WHERE u.role = "referent"
                GROUP BY t.project_id
            ) r ON r.project_id = p.id
            ORDER BY p.created_at DESC
        ');
        return response()->json($rows);
    }

    public function show(int $id)
    {
        $projects = DB::select('
            SELECT p.*, c.company_name, c.vat_number
            FROM projects p
            JOIN clients c ON c.id = p.client_id
            WHERE p.id = ?
        ', [$id]);

        if (empty($projects)) {
            return response()->json(['message' => 'Progetto non trovato'], 404);
        }

        $assignments = DB::select('
            SELECT ta.id, ta.tariff_id, ta.collaborator_id,
                   t.name AS tariff_name, t.hourly_rate, t.tax_inclusive,
                   co.first_name, co.last_name
            FROM tariff_assignments ta
            JOIN tariffs t ON t.id = ta.tariff_id
            LEFT JOIN collaborators co ON co.id = ta.collaborator_id
            WHERE ta.project_id = ?
            ORDER BY (ta.collaborator_id IS NULL) DESC, co.last_name
        ', [$id]);

        $referents = DB::select('
            SELECT t.user_id, u.username, u.email,
                   MIN(t.source) AS source
            FROM (
                SELECT pr.user_id, "project" AS source
                FROM project_referents pr
                WHERE pr.project_id = ?

                UNION ALL

                SELECT cr.user_id, "client" AS source
                FROM client_referents cr
                JOIN projects p2 ON p2.client_id = cr.client_id
                WHERE p2.id = ?
            ) t
            JOIN users u ON u.id = t.user_id
            GROUP BY t.user_id, u.username, u.email
            ORDER BY u.username
        ', [$id, $id]);

        return response()->json(array_merge((array) $projects[0], [
            'assignments' => $assignments,
            'referents'   => $referents,
        ]));
    }

    public function store(Request $request)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::projectStore());

        $id = DB::table('projects')->insertGetId([
            'client_id'   => $data['client_id'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'status'      => $data['status'] ?? 'active',
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'] ?? null,
            'notes'       => $data['notes'] ?? null,
        ]);
        Log::info('Projects: creato', ['id' => $id, 'name' => $data['name'], 'client_id' => $data['client_id']]);

        $rows = DB::select(
            'SELECT p.*, c.company_name FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ?',
            [$id]
        );
        return response()->json($rows[0], 201);
    }

    public function update(Request $request, int $id)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::projectUpdate());

        DB::table('projects')->where('id', $id)->update([
            'client_id'   => $data['client_id'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'status'      => $data['status'] ?? 'active',
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'] ?? null,
            'notes'       => $data['notes'] ?? null,
            'is_active'   => ($data['is_active'] ?? true) !== false ? 1 : 0,
        ]);
        Log::info('Projects: aggiornato', ['id' => $id]);

        $rows = DB::select(
            'SELECT p.*, c.company_name FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ?',
            [$id]
        );
        return response()->json($rows[0]);
    }

    public function addAssignment(Request $request, int $id)
    {
        $data = ApiRequestValidator::validate($request, [
            'tariff_id'       => ['required', 'integer', 'exists:tariffs,id'],
            'collaborator_id' => ['nullable', 'integer', 'exists:collaborators,id'],
        ]);

        try {
            $assignId = DB::table('tariff_assignments')->insertGetId([
                'tariff_id'       => $data['tariff_id'],
                'project_id'      => $id,
                'collaborator_id' => $data['collaborator_id'] ?? null,
            ]);
            Log::info('Projects: assegnazione aggiunta', ['project_id' => $id, 'assign_id' => $assignId, 'tariff_id' => $data['tariff_id']]);

            $rows = DB::select('
                SELECT ta.*, t.name AS tariff_name, t.hourly_rate, t.tax_inclusive,
                       co.first_name, co.last_name
                FROM tariff_assignments ta
                JOIN tariffs t ON t.id = ta.tariff_id
                LEFT JOIN collaborators co ON co.id = ta.collaborator_id
                WHERE ta.id = ?
            ', [$assignId]);

            return response()->json($rows[0], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                Log::warning('Projects: assegnazione duplicata', ['project_id' => $id, 'tariff_id' => $request->input('tariff_id'), 'collaborator_id' => $request->input('collaborator_id')]);
                return response()->json([
                    'message' => 'Assegnazione già esistente per questo progetto e collaboratore'
                ], 409);
            }
            throw $e;
        }
    }

    public function removeAssignment(int $assignId)
    {
        DB::table('tariff_assignments')->where('id', $assignId)->delete();
        Log::info('Projects: assegnazione rimossa', ['assign_id' => $assignId]);
        return response()->json(['success' => true]);
    }

    public function addReferent(Request $request, int $id)
    {
        $data = ApiRequestValidator::validate($request, [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $userRows = DB::select('SELECT id, role, is_active FROM users WHERE id = ? LIMIT 1', [$data['user_id']]);
        $target = $userRows[0] ?? null;
        if (!$target || $target->role !== 'referent' || !$target->is_active) {
            return response()->json(['message' => 'Utente referente non valido'], 422);
        }

        try {
            $refId = DB::table('project_referents')->insertGetId([
                'project_id' => $id,
                'user_id'    => $data['user_id'],
            ]);
            Log::info('Projects: referente assegnato', ['project_id' => $id, 'project_referent_id' => $refId, 'user_id' => $data['user_id']]);

            $rows = DB::select('
                SELECT pr.id, pr.user_id, u.username, u.email
                FROM project_referents pr
                JOIN users u ON u.id = pr.user_id
                WHERE pr.id = ?
            ', [$refId]);

            return response()->json($rows[0], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                return response()->json(['message' => 'Referente già assegnato a questo progetto'], 409);
            }
            throw $e;
        }
    }

    public function removeReferent(int $assignId)
    {
        DB::table('project_referents')->where('id', $assignId)->delete();
        Log::info('Projects: referente rimosso', ['project_referent_id' => $assignId]);
        return response()->json(['success' => true]);
    }

    public function resolveTargetTariff(Request $request)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::projectResolveTargetTariff());
        $projectId      = $data['project_id'];
        $collaboratorId = $data['collaborator_id'] ?? null;

        // 1. Tariffa specifica per il collaboratore nel progetto
        if ($collaboratorId) {
            $rows = DB::select('
                SELECT t.* FROM tariffs t
                JOIN tariff_assignments ta ON ta.tariff_id = t.id
                WHERE ta.project_id = ? AND ta.collaborator_id = ?
                LIMIT 1
            ', [$projectId, $collaboratorId]);
            if (!empty($rows)) return response()->json($rows[0]);
        }

        // 2. Tariffa generica del progetto (collaborator_id IS NULL)
        $rows = DB::select('
            SELECT t.* FROM tariffs t
            JOIN tariff_assignments ta ON ta.tariff_id = t.id
            WHERE ta.project_id = ? AND ta.collaborator_id IS NULL
            LIMIT 1
        ', [$projectId]);
        if (!empty($rows)) return response()->json($rows[0]);

        // 3. Tariffa di default globale
        $rows = DB::select('SELECT * FROM tariffs WHERE is_default = TRUE LIMIT 1');
        if (!empty($rows)) return response()->json($rows[0]);

        return response()->json(['message' => 'Nessuna tariffa trovata'], 404);
    }

    public function assignedProjects(Request $request)
    {
        $user = $request->attributes->get('jwt_user');
        $cid  = $user->collaborator_id;

        // Mirror resolveTargetTariff priority: collaborator-specific > generic project > default
        $rows = DB::select('
            SELECT p.id, p.name, p.is_active,
                   COALESCE(ts.id,       tg.id,       td.id)         AS tariff_id,
                   COALESCE(ts.name,     tg.name,     td.name)       AS tariff_name,
                   COALESCE(ts.hourly_rate, tg.hourly_rate, td.hourly_rate) AS hourly_rate,
                   COALESCE(ts.rate_type,   tg.rate_type,   td.rate_type)   AS rate_type,
                   COALESCE(ts.tax_inclusive, tg.tax_inclusive, td.tax_inclusive) AS tax_inclusive
            FROM projects p
            LEFT JOIN tariff_assignments ta_s ON ta_s.project_id = p.id AND ta_s.collaborator_id = ?
            LEFT JOIN tariffs ts ON ts.id = ta_s.tariff_id
            LEFT JOIN tariff_assignments ta_g ON ta_g.project_id = p.id AND ta_g.collaborator_id IS NULL
            LEFT JOIN tariffs tg ON tg.id = ta_g.tariff_id
            LEFT JOIN tariffs td ON td.is_default = TRUE
            WHERE p.is_active = 1
              AND (ta_s.id IS NOT NULL OR ta_g.id IS NOT NULL)
            ORDER BY p.name
        ', [$cid]);
        return response()->json($rows);
    }
}
