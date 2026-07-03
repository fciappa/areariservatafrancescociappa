<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiRequestValidator;
use App\Support\ApiValidationRules;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeadlinesController extends Controller
{
    private function hasDeadlineConflict(int $clientId, string $dueDate, string $itemType, string $description, ?int $ignoreId = null): bool
    {
        $query = DB::table('client_deadlines')
            ->where('client_id', $clientId)
            ->where('due_date', $dueDate)
            ->where('item_type', trim($itemType))
            ->where('description', trim($description));

        if ($ignoreId !== null) {
            $query->where('id', '<>', $ignoreId);
        }

        return $query->exists();
    }

    private function deadlineConflictResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => 'Esiste gia una scadenza con stesso cliente, data, tipo e descrizione.',
            'error' => 'deadline_conflict',
        ], 409);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        if (in_array($sqlState, ['23000', '23505', '19'], true)) {
            return true;
        }

        return str_contains(strtolower($exception->getMessage()), 'unique');
    }

    public function index(Request $request)
    {
        $sortMap = [
            'due_date'      => 'd.due_date',
            'company_name'  => 'c.company_name',
            'project_name'  => 'p.name',
            'item_type'     => 'd.item_type',
            'description'   => 'd.description',
            'linked_to'     => 'd.linked_to',
            'avada_version' => 'd.avada_version',
            'php_version'   => 'd.php_version',
            'mysql_version' => 'd.mysql_version',
            'wp_version'    => 'd.wp_version',
            'test_email'    => 'd.test_email',
            'notes'         => 'd.notes',
            'amount'        => 'd.amount',
        ];

        $sortBy = (string) $request->query('sort_by', 'due_date');
        $sortDir = strtolower((string) $request->query('sort_dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $sortColumn = $sortMap[$sortBy] ?? 'd.due_date';

        $sql = '
            SELECT d.*, c.company_name, p.name AS project_name
            FROM client_deadlines d
            JOIN clients c ON c.id = d.client_id
            LEFT JOIN projects p ON p.id = d.project_id
        ';

        $bindings = [];
        $conditions = [];

        if ($request->filled('client_id')) {
            $conditions[] = 'd.client_id = ?';
            $bindings[] = (int) $request->query('client_id');
        }

        if ($request->filled('project_id')) {
            $conditions[] = 'd.project_id = ?';
            $bindings[] = (int) $request->query('project_id');
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions) . ' ';
        }

        $sql .= " ORDER BY {$sortColumn} {$sortDir}, d.description ASC, d.id ASC ";

        $rows = DB::select($sql, $bindings);
        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::deadlineStore());
        $clientId = (int) $data['client_id'];
        $itemType = trim((string) $data['item_type']);
        $description = trim((string) $data['description']);

        $projectId = $data['project_id'] ?? null;
        if ($projectId) {
            $projectRows = DB::select('SELECT id FROM projects WHERE id = ? AND client_id = ? LIMIT 1', [$projectId, $clientId]);
            if (empty($projectRows)) {
                return response()->json(['message' => 'Progetto non valido per il cliente selezionato'], 422);
            }
        }

        if ($this->hasDeadlineConflict($clientId, $data['due_date'], $itemType, $description)) {
            return $this->deadlineConflictResponse();
        }

        try {
            $id = DB::table('client_deadlines')->insertGetId([
                'client_id'      => $clientId,
                'project_id'     => $projectId ?: null,
                'due_date'       => $data['due_date'],
                'item_type'      => $itemType,
                'description'    => $description,
                'linked_to'      => $data['linked_to'] ?? null,
                'avada_version'  => $data['avada_version'] ?? null,
                'php_version'    => $data['php_version'] ?? null,
                'mysql_version'  => $data['mysql_version'] ?? null,
                'wp_version'     => $data['wp_version'] ?? null,
                'test_email'     => $data['test_email'] ?? null,
                'line_ref'       => $data['line_ref'] ?? null,
                'notes'          => $data['notes'] ?? null,
                'amount'         => $data['amount'] ?? null,
                'is_active'      => $data['is_active'] ?? 1,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return $this->deadlineConflictResponse();
            }

            throw $exception;
        }

        Log::info('Deadlines: creata', ['id' => $id, 'client_id' => $clientId]);

        $rows = DB::select('
            SELECT d.*, c.company_name, p.name AS project_name
            FROM client_deadlines d
            JOIN clients c ON c.id = d.client_id
            LEFT JOIN projects p ON p.id = d.project_id
            WHERE d.id = ?
            LIMIT 1
        ', [$id]);

        return response()->json($rows[0], 201);
    }

    public function update(Request $request, int $id)
    {
        $rows = DB::select('SELECT id FROM client_deadlines WHERE id = ? LIMIT 1', [$id]);
        if (empty($rows)) {
            return response()->json(['message' => 'Scadenza non trovata'], 404);
        }
        $data = ApiRequestValidator::validate($request, ApiValidationRules::deadlineUpdate());
        $clientId = (int) $data['client_id'];
        $itemType = trim((string) $data['item_type']);
        $description = trim((string) $data['description']);

        $projectId = $data['project_id'] ?? null;
        if ($projectId) {
            $projectRows = DB::select('SELECT id FROM projects WHERE id = ? AND client_id = ? LIMIT 1', [$projectId, $clientId]);
            if (empty($projectRows)) {
                return response()->json(['message' => 'Progetto non valido per il cliente selezionato'], 422);
            }
        }

        if ($this->hasDeadlineConflict($clientId, $data['due_date'], $itemType, $description, $id)) {
            return $this->deadlineConflictResponse();
        }

        try {
            DB::table('client_deadlines')->where('id', $id)->update([
                'client_id'      => $clientId,
                'project_id'     => $projectId ?: null,
                'due_date'       => $data['due_date'],
                'item_type'      => $itemType,
                'description'    => $description,
                'linked_to'      => $data['linked_to'] ?? null,
                'avada_version'  => $data['avada_version'] ?? null,
                'php_version'    => $data['php_version'] ?? null,
                'mysql_version'  => $data['mysql_version'] ?? null,
                'wp_version'     => $data['wp_version'] ?? null,
                'test_email'     => $data['test_email'] ?? null,
                'line_ref'       => $data['line_ref'] ?? null,
                'notes'          => $data['notes'] ?? null,
                'amount'         => $data['amount'] ?? null,
                'is_active'      => $data['is_active'] ?? 1,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return $this->deadlineConflictResponse();
            }

            throw $exception;
        }

        Log::info('Deadlines: aggiornata', ['id' => $id, 'client_id' => $clientId]);

        $updated = DB::select('
            SELECT d.*, c.company_name, p.name AS project_name
            FROM client_deadlines d
            JOIN clients c ON c.id = d.client_id
            LEFT JOIN projects p ON p.id = d.project_id
            WHERE d.id = ?
            LIMIT 1
        ', [$id]);

        return response()->json($updated[0]);
    }

    public function renew(int $id)
    {
        $rows = DB::select('SELECT id, client_id, due_date, item_type, description FROM client_deadlines WHERE id = ? LIMIT 1', [$id]);
        if (empty($rows)) {
            return response()->json(['message' => 'Scadenza non trovata'], 404);
        }

        if (empty($rows[0]->due_date)) {
            return response()->json(['message' => 'Data scadenza non valida o mancante'], 422);
        }

        $currentDueDate = Carbon::parse((string) $rows[0]->due_date);
        $newDueDate = $currentDueDate->copy()->addYearNoOverflow()->format('Y-m-d');

        if ($this->hasDeadlineConflict(
            (int) $rows[0]->client_id,
            $newDueDate,
            (string) $rows[0]->item_type,
            (string) $rows[0]->description,
            $id
        )) {
            return $this->deadlineConflictResponse();
        }

        try {
            DB::table('client_deadlines')->where('id', $id)->update([
                'due_date' => $newDueDate,
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return $this->deadlineConflictResponse();
            }

            throw $exception;
        }

        Log::info('Deadlines: rinnovata di un anno', [
            'id' => $id,
            'old_due_date' => $rows[0]->due_date,
            'new_due_date' => $newDueDate,
        ]);

        $updated = DB::select('
            SELECT d.*, c.company_name, p.name AS project_name
            FROM client_deadlines d
            JOIN clients c ON c.id = d.client_id
            LEFT JOIN projects p ON p.id = d.project_id
            WHERE d.id = ?
            LIMIT 1
        ', [$id]);

        return response()->json($updated[0]);
    }

    public function destroy(int $id)
    {
        $rows = DB::select('SELECT id, description, due_date FROM client_deadlines WHERE id = ? LIMIT 1', [$id]);
        if (empty($rows)) {
            return response()->json(['message' => 'Scadenza non trovata'], 404);
        }

        DB::table('client_deadlines')->where('id', $id)->delete();

        Log::info('Deadlines: eliminata', [
            'id' => $id,
            'description' => $rows[0]->description,
            'due_date' => $rows[0]->due_date,
        ]);

        return response()->json([
            'message' => 'Scadenza eliminata correttamente.',
        ]);
    }
}
