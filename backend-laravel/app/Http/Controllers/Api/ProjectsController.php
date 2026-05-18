<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectsController extends Controller
{
    public function index()
    {
        $rows = DB::select('
            SELECT p.*, c.company_name, c.vat_number
            FROM projects p
            JOIN clients c ON c.id = p.client_id
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

        return response()->json(array_merge((array) $projects[0], ['assignments' => $assignments]));
    }

    public function store(Request $request)
    {
        if (!$request->filled('client_id') || !$request->filled('name') || !$request->filled('start_date')) {
            Log::warning('Projects: store - dati obbligatori mancanti', ['input' => $request->only('client_id', 'name', 'start_date')]);
            return response()->json(['message' => 'client_id, name e start_date sono obbligatori'], 400);
        }

        $id = DB::table('projects')->insertGetId([
            'client_id'   => $request->input('client_id'),
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
            'status'      => $request->input('status', 'active'),
            'start_date'  => $request->input('start_date'),
            'end_date'    => $request->input('end_date'),
            'notes'       => $request->input('notes'),
        ]);
        Log::info('Projects: creato', ['id' => $id, 'name' => $request->input('name'), 'client_id' => $request->input('client_id')]);

        $rows = DB::select(
            'SELECT p.*, c.company_name FROM projects p JOIN clients c ON c.id = p.client_id WHERE p.id = ?',
            [$id]
        );
        return response()->json($rows[0], 201);
    }

    public function update(Request $request, int $id)
    {
        DB::table('projects')->where('id', $id)->update([
            'client_id'   => $request->input('client_id'),
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
            'status'      => $request->input('status', 'active'),
            'start_date'  => $request->input('start_date'),
            'end_date'    => $request->input('end_date'),
            'notes'       => $request->input('notes'),
            'is_active'   => $request->input('is_active', true) !== false ? 1 : 0,
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
        if (!$request->filled('tariff_id')) {
            Log::warning('Projects: addAssignment - tariff_id mancante', ['project_id' => $id]);
            return response()->json(['message' => 'tariff_id obbligatorio'], 400);
        }

        try {
            $assignId = DB::table('tariff_assignments')->insertGetId([
                'tariff_id'       => $request->input('tariff_id'),
                'project_id'      => $id,
                'collaborator_id' => $request->input('collaborator_id'),
            ]);
            Log::info('Projects: assegnazione aggiunta', ['project_id' => $id, 'assign_id' => $assignId, 'tariff_id' => $request->input('tariff_id')]);

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

    public function resolveTargetTariff(Request $request)
    {
        $projectId      = $request->query('project_id');
        $collaboratorId = $request->query('collaborator_id');

        if (!$projectId) {
            return response()->json(['message' => 'project_id obbligatorio'], 400);
        }

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
        $rows = DB::select('
            SELECT p.id, p.name, p.is_active,
                   t.id AS tariff_id, t.name AS tariff_name,
                   t.hourly_rate, t.rate_type, t.tax_inclusive
            FROM projects p
            JOIN tariff_assignments ta ON ta.project_id = p.id
                AND ta.collaborator_id = ?
            LEFT JOIN tariffs t ON t.id = ta.tariff_id
            WHERE p.is_active = 1
            ORDER BY p.name
        ', [$user->collaborator_id]);
        return response()->json($rows);
    }
}
