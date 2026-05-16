<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HoursController extends Controller
{
    public function indexCollaborators(Request $request)
    {
        $user = $request->attributes->get('jwt_user');

        if ($user->role === 'admin') {
            $rows = DB::select('
                SELECT ch.*, c.first_name, c.last_name,
                       t.name AS tariff_name, t.hourly_rate, t.rate_type, t.tax_inclusive
                FROM collaborator_hours ch
                LEFT JOIN collaborators c ON c.id = ch.collaborator_id
                LEFT JOIN tariffs t ON t.id = ch.tariff_id
                ORDER BY ch.work_date DESC
            ');
        } else {
            $rows = DB::select('
                SELECT ch.*, t.name AS tariff_name, t.hourly_rate, t.rate_type, t.tax_inclusive
                FROM collaborator_hours ch
                JOIN tariffs t ON t.id = ch.tariff_id
                WHERE ch.collaborator_id = ?
                ORDER BY ch.work_date DESC
            ', [$user->collaborator_id]);
        }

        return response()->json($rows);
    }

    public function storeCollaborator(Request $request)
    {
        $id = DB::table('collaborator_hours')->insertGetId([
            'collaborator_id' => $request->input('collaborator_id'),
            'project_id'      => $request->input('project_id'),
            'tariff_id'       => $request->input('tariff_id'),
            'work_date'       => $request->input('work_date'),
            'hours'           => $request->input('hours'),
            'description'     => $request->input('description', ''),
        ]);
        return response()->json(['id' => $id], 201);
    }

    public function updateCollaborator(Request $request, int $id)
    {
        DB::table('collaborator_hours')->where('id', $id)->update([
            'collaborator_id' => $request->input('collaborator_id'),
            'project_id'      => $request->input('project_id'),
            'tariff_id'       => $request->input('tariff_id'),
            'work_date'       => $request->input('work_date'),
            'hours'           => $request->input('hours'),
            'description'     => $request->input('description', ''),
        ]);
        return response()->json(['message' => 'Aggiornato']);
    }

    public function destroyCollaborator(int $id)
    {
        DB::table('collaborator_hours')->where('id', $id)->delete();
        return response()->json(['message' => 'Eliminato']);
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
        $id = DB::table('my_work_hours')->insertGetId([
            'client_id'   => $request->input('client_id'),
            'project_id'  => $request->input('project_id'),
            'tariff_id'   => $request->input('tariff_id'),
            'work_date'   => $request->input('work_date'),
            'hours'       => $request->input('hours'),
            'description' => $request->input('description', ''),
        ]);
        return response()->json(['id' => $id], 201);
    }

    public function updateMy(Request $request, int $id)
    {
        DB::table('my_work_hours')->where('id', $id)->update([
            'client_id'   => $request->input('client_id'),
            'project_id'  => $request->input('project_id'),
            'tariff_id'   => $request->input('tariff_id'),
            'work_date'   => $request->input('work_date'),
            'hours'       => $request->input('hours'),
            'description' => $request->input('description', ''),
        ]);
        return response()->json(['message' => 'Aggiornato']);
    }

    public function destroyMy(int $id)
    {
        DB::table('my_work_hours')->where('id', $id)->delete();
        return response()->json(['message' => 'Eliminato']);
    }

    public function bulkStoreMy(Request $request)
    {
        $rows = $request->input('rows', []);
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
        return response()->json(['count' => $count], 201);
    }
}
