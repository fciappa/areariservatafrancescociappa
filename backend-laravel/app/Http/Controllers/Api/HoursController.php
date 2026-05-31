<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiRequestValidator;
use App\Support\ApiValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HoursController extends Controller
{
    public function indexCollaborators(Request $request)
    {
        $user = $request->attributes->get('jwt_user');

        if ($user->role === 'admin') {
            $rows = DB::select('
                SELECT ch.*, c.first_name, c.last_name,
                       p.name AS project_name,
                       t.name AS tariff_name, t.hourly_rate, t.rate_type, t.tax_inclusive
                FROM collaborator_hours ch
                LEFT JOIN collaborators c ON c.id = ch.collaborator_id
                LEFT JOIN projects p ON p.id = ch.project_id
                LEFT JOIN tariffs t ON t.id = ch.tariff_id
                ORDER BY ch.work_date DESC
            ');
        } else {
            $rows = DB::select('
                SELECT ch.*, p.name AS project_name,
                       t.name AS tariff_name, t.hourly_rate, t.rate_type, t.tax_inclusive
                FROM collaborator_hours ch
                LEFT JOIN projects p ON p.id = ch.project_id
                JOIN tariffs t ON t.id = ch.tariff_id
                WHERE ch.collaborator_id = ?
                ORDER BY ch.work_date DESC
            ', [$user->collaborator_id]);
        }

        return response()->json($rows);
    }

    public function storeCollaborator(Request $request)
    {
        $user    = $request->attributes->get('jwt_user');
        $isAdmin = $user->role === 'admin';

        $validated = ApiRequestValidator::validate($request, ApiValidationRules::hoursCollaboratorStore($isAdmin));

        $id = DB::table('collaborator_hours')->insertGetId([
            'collaborator_id' => $isAdmin ? $validated['collaborator_id'] : $user->collaborator_id,
            'project_id'      => $validated['project_id'],
            'tariff_id'       => $validated['tariff_id'],
            'work_date'       => $validated['work_date'],
            'hours'           => $validated['hours'],
            'description'     => $validated['description'] ?? '',
            'status'          => $isAdmin ? 'approved' : 'pending',
        ]);
        Log::info('Hours: ore collaboratore registrate', [
            'id'             => $id,
            'collaborator_id' => $isAdmin ? $validated['collaborator_id'] : $user->collaborator_id,
            'work_date'      => $validated['work_date'],
            'hours'          => $validated['hours'],
            'status'         => $isAdmin ? 'approved' : 'pending',
        ]);
        return response()->json(['id' => $id], 201);
    }

    public function bulkStoreCollaborator(Request $request)
    {
        $user    = $request->attributes->get('jwt_user');
        $isAdmin = $user->role === 'admin';
        $payload = ApiRequestValidator::validate($request, ApiValidationRules::hoursCollaboratorBulk($isAdmin));
        $rows    = $payload['rows'];
        $count   = 0;

        foreach ($rows as $validRow) {
            DB::table('collaborator_hours')->insert([
                'collaborator_id' => $isAdmin ? $validRow['collaborator_id'] : $user->collaborator_id,
                'project_id'      => $validRow['project_id'],
                'tariff_id'       => $validRow['tariff_id'],
                'work_date'       => $validRow['work_date'],
                'hours'           => $validRow['hours'],
                'description'     => $validRow['description'] ?? '',
                'status'          => $isAdmin ? 'approved' : 'pending',
            ]);
            $count++;
        }

        Log::info('Hours: bulk insert ore collaboratore', [
            'count'         => $count,
            'collaborator_id' => $isAdmin ? null : $user->collaborator_id,
            'status'        => $isAdmin ? 'approved' : 'pending',
        ]);

        return response()->json(['count' => $count], 201);
    }

    public function updateCollaborator(Request $request, int $id)
    {
        $user    = $request->attributes->get('jwt_user');
        $isAdmin = $user->role === 'admin';

        $validated = ApiRequestValidator::validate($request, ApiValidationRules::hoursCollaboratorStore($isAdmin));

        if (!$isAdmin) {
            $exists = DB::table('collaborator_hours')
                ->where('id', $id)
                ->where('collaborator_id', $user->collaborator_id)
                ->where('status', 'pending')
                ->whereNull('invoiced_at')
                ->exists();
            if (!$exists) {
                return response()->json(['message' => 'Non modificabile'], 403);
            }
        }

        $data = [
            'project_id'  => $validated['project_id'],
            'tariff_id'   => $validated['tariff_id'],
            'work_date'   => $validated['work_date'],
            'hours'       => $validated['hours'],
            'description' => $validated['description'] ?? '',
        ];
        if ($isAdmin) {
            $data['collaborator_id'] = $validated['collaborator_id'];
        }

        DB::table('collaborator_hours')->where('id', $id)->update($data);
        Log::info('Hours: ore collaboratore aggiornate', ['id' => $id]);
        return response()->json(['message' => 'Aggiornato']);
    }

    public function destroyCollaborator(Request $request, int $id)
    {
        $user    = $request->attributes->get('jwt_user');
        $isAdmin = $user->role === 'admin';

        $query = DB::table('collaborator_hours')->where('id', $id);
        if (!$isAdmin) {
            $query->where('collaborator_id', $user->collaborator_id)
                  ->where('status', 'pending')
                  ->whereNull('invoiced_at');
        }
        $deleted = $query->delete();
        if (!$deleted) {
            return response()->json(['message' => 'Non eliminabile'], 403);
        }
        Log::info('Hours: ore collaboratore eliminate', ['id' => $id]);
        return response()->json(['message' => 'Eliminato']);
    }

    public function approveCollaborator(int $id)
    {
        DB::table('collaborator_hours')->where('id', $id)->update(['status' => 'approved']);
        Log::info('Hours: ora collaboratore approvata', ['id' => $id]);
        return response()->json(['message' => 'Approvata']);
    }

    public function rejectCollaborator(int $id)
    {
        DB::table('collaborator_hours')->where('id', $id)->update(['status' => 'rejected']);
        Log::info('Hours: ora collaboratore rifiutata', ['id' => $id]);
        return response()->json(['message' => 'Rifiutata']);
    }

    public function groupedMy(Request $request)
    {
        $sql    = '
            SELECT
                mwh.project_id,
                p.name          AS project_name,
                cl.id           AS client_id,
                cl.company_name,
                mwh.tariff_id,
                t.name          AS tariff_name,
                t.hourly_rate,
                t.rate_type,
                t.tax_inclusive,
                DATE_FORMAT(mwh.work_date, \'%Y-%m\') AS month,
                SUM(mwh.hours) AS total_hours,
                GROUP_CONCAT(mwh.id) AS work_hour_ids,
                SUM(CASE WHEN mwh.invoiced_at IS NOT NULL THEN 1 ELSE 0 END) AS invoiced_count
            FROM my_work_hours mwh
            JOIN clients cl  ON cl.id  = mwh.client_id
            JOIN tariffs t   ON t.id   = mwh.tariff_id
            LEFT JOIN projects p ON p.id = mwh.project_id
        ';
        $params = [];
        $where  = [];

        if ($request->filled('project_id')) {
            $where[]  = 'mwh.project_id = ?';
            $params[] = $request->query('project_id');
        }
        if ($request->filled('client_id')) {
            $where[]  = 'mwh.client_id = ?';
            $params[] = $request->query('client_id');
        }
        if ($request->filled('month')) {
            $where[]  = 'DATE_FORMAT(mwh.work_date, \'%Y-%m\') = ?';
            $params[] = $request->query('month');
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY mwh.client_id, mwh.project_id, mwh.tariff_id, DATE_FORMAT(mwh.work_date, \'%Y-%m\')
                  ORDER BY month DESC, project_name, tariff_name';

        $rows = DB::select($sql, $params);

        // Convert work_hour_ids string to array
        foreach ($rows as $row) {
            $row->work_hour_ids = $row->work_hour_ids
                ? array_map('intval', explode(',', $row->work_hour_ids))
                : [];
        }

        return response()->json($rows);
    }

    public function groupedCollaborators(Request $request)
    {
        $sql    = '
            SELECT
                ch.collaborator_id,
                c.first_name,
                c.last_name,
                ch.project_id,
                p.name          AS project_name,
                ch.tariff_id,
                t.name          AS tariff_name,
                t.hourly_rate,
                t.rate_type,
                t.tax_inclusive,
                DATE_FORMAT(ch.work_date, \'%Y-%m\') AS month,
                SUM(ch.hours) AS total_hours,
                GROUP_CONCAT(ch.id) AS collab_hour_ids,
                SUM(CASE WHEN ch.invoiced_at IS NOT NULL THEN 1 ELSE 0 END) AS invoiced_count
            FROM collaborator_hours ch
            JOIN collaborators c ON c.id = ch.collaborator_id
            JOIN tariffs t        ON t.id = ch.tariff_id
            LEFT JOIN projects p  ON p.id = ch.project_id
        ';
        $params = [];
        $where  = ["ch.status = 'approved'"];

        if ($request->filled('collaborator_id')) {
            $where[]  = 'ch.collaborator_id = ?';
            $params[] = $request->query('collaborator_id');
        }
        if ($request->filled('project_id')) {
            $where[]  = 'ch.project_id = ?';
            $params[] = $request->query('project_id');
        }
        if ($request->filled('month')) {
            $where[]  = 'DATE_FORMAT(ch.work_date, \'%Y-%m\') = ?';
            $params[] = $request->query('month');
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY ch.collaborator_id, ch.project_id, ch.tariff_id, DATE_FORMAT(ch.work_date, \'%Y-%m\')
                  ORDER BY month DESC, last_name, first_name, project_name';

        $rows = DB::select($sql, $params);

        foreach ($rows as $row) {
            $row->collab_hour_ids = $row->collab_hour_ids
                ? array_map('intval', explode(',', $row->collab_hour_ids))
                : [];
        }

        return response()->json($rows);
    }

    public function indexMy()
    {
        $rows = DB::select('
            SELECT mwh.*, cl.company_name,
                   t.name AS tariff_name, t.hourly_rate, t.rate_type, t.tax_inclusive
            FROM my_work_hours mwh
            JOIN clients cl ON cl.id = mwh.client_id
            JOIN tariffs t  ON t.id  = mwh.tariff_id
            ORDER BY mwh.work_date DESC
        ');
        return response()->json($rows);
    }

    public function storeMy(Request $request)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::hoursMyStore());

        $id = DB::table('my_work_hours')->insertGetId([
            'client_id'   => $data['client_id'],
            'project_id'  => $data['project_id'] ?? null,
            'tariff_id'   => $data['tariff_id'],
            'work_date'   => $data['work_date'],
            'hours'       => $data['hours'],
            'description' => $data['description'] ?? '',
        ]);
        Log::info('Hours: ore personali registrate', ['id' => $id, 'work_date' => $data['work_date'], 'hours' => $data['hours']]);
        return response()->json(['id' => $id], 201);
    }

    public function updateMy(Request $request, int $id)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::hoursMyStore());

        DB::table('my_work_hours')->where('id', $id)->update([
            'client_id'   => $data['client_id'],
            'project_id'  => $data['project_id'] ?? null,
            'tariff_id'   => $data['tariff_id'],
            'work_date'   => $data['work_date'],
            'hours'       => $data['hours'],
            'description' => $data['description'] ?? '',
        ]);
        Log::info('Hours: ore personali aggiornate', ['id' => $id]);
        return response()->json(['message' => 'Aggiornato']);
    }

    public function destroyMy(int $id)
    {
        DB::table('my_work_hours')->where('id', $id)->delete();
        Log::info('Hours: ore personali eliminate', ['id' => $id]);
        return response()->json(['message' => 'Eliminato']);
    }

    public function bulkStoreMy(Request $request)
    {
        $data = ApiRequestValidator::validate($request, ApiValidationRules::hoursMyBulk());
        $rows = $data['rows'];
        $count = 0;
        foreach ($rows as $row) {
            DB::table('my_work_hours')->insert([
                'client_id'   => $row['client_id'],
                'project_id'  => $row['project_id'] ?? null,
                'tariff_id'   => $row['tariff_id'],
                'work_date'   => $row['work_date'],
                'hours'       => $row['hours'],
                'description' => $row['description'] ?? '',
            ]);
            $count++;
        }
        Log::info('Hours: bulk insert ore personali', ['count' => $count]);
        return response()->json(['count' => $count], 201);
    }
}
