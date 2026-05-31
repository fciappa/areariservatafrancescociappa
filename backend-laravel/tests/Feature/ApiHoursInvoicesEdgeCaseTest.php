<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiHoursInvoicesEdgeCaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->seedBaseData();
    }

    private function createSchema(): void
    {
        DB::statement('CREATE TABLE clients (id INTEGER PRIMARY KEY AUTOINCREMENT, company_name TEXT, vat_number TEXT)');
        DB::statement('CREATE TABLE collaborators (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT, last_name TEXT)');
        DB::statement('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, name TEXT, is_active INTEGER DEFAULT 1, created_at TEXT)');
        DB::statement('CREATE TABLE tariffs (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, hourly_rate NUMERIC, rate_type TEXT, tax_inclusive INTEGER DEFAULT 0, is_default INTEGER DEFAULT 0)');

        DB::statement('CREATE TABLE my_work_hours (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, project_id INTEGER NULL, tariff_id INTEGER, work_date TEXT, hours NUMERIC, description TEXT, invoiced_at TEXT NULL)');

        DB::statement('CREATE TABLE invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_number TEXT UNIQUE,
            client_id INTEGER,
            invoice_date TEXT,
            stamp_duty NUMERIC,
            subtotal NUMERIC,
            tax_amount NUMERIC,
            total NUMERIC,
            notes TEXT NULL,
            status TEXT DEFAULT "draft"
        )');
        DB::statement('CREATE TABLE invoice_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_id INTEGER,
            work_hour_id INTEGER NULL,
            description TEXT,
            tariff_id INTEGER,
            hours NUMERIC,
            hourly_rate NUMERIC,
            tax_inclusive INTEGER DEFAULT 0,
            line_total NUMERIC
        )');

        DB::statement('CREATE TABLE collab_invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            collaborator_id INTEGER,
            invoice_number TEXT,
            invoice_date TEXT,
            subtotal NUMERIC,
            tax_amount NUMERIC,
            total NUMERIC,
            notes TEXT NULL,
            status TEXT DEFAULT "draft",
            paid_at TEXT NULL
        )');
        DB::statement('CREATE TABLE collab_invoice_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            collab_invoice_id INTEGER,
            collab_hour_id INTEGER NULL,
            description TEXT,
            tariff_id INTEGER,
            hours NUMERIC,
            hourly_rate NUMERIC,
            tax_inclusive INTEGER DEFAULT 0,
            line_total NUMERIC
        )');

        DB::statement('CREATE TABLE collaborator_hours (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            collaborator_id INTEGER,
            project_id INTEGER,
            tariff_id INTEGER,
            work_date TEXT,
            hours NUMERIC,
            description TEXT,
            status TEXT DEFAULT "pending",
            invoiced_at TEXT NULL
        )');
    }

    private function seedBaseData(): void
    {
        DB::table('clients')->insert([
            'id' => 1,
            'company_name' => 'Client Test',
            'vat_number' => 'IT00000000001',
        ]);

        DB::table('collaborators')->insert([
            'id' => 1,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ]);

        DB::table('projects')->insert([
            'id' => 1,
            'client_id' => 1,
            'name' => 'Project Test',
            'is_active' => 1,
            'created_at' => now()->toDateTimeString(),
        ]);

        DB::table('tariffs')->insert([
            'id' => 1,
            'name' => 'Standard',
            'hourly_rate' => 100,
            'rate_type' => 'hourly',
            'tax_inclusive' => 0,
            'is_default' => 1,
        ]);
    }

    public function test_invoices_show_returns_404_for_missing_invoice(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->getJson('/api/invoices/999999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Fattura non trovata',
            ]);
    }

    public function test_hours_bulk_store_my_requires_non_empty_rows(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->postJson('/api/hours/my/bulk', [
                'rows' => [],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Dati non validi',
            ])
            ->assertJsonStructure([
                'errors' => ['rows'],
            ]);
    }

    public function test_hours_store_my_rejects_hours_above_limit(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->postJson('/api/hours/my', [
                'client_id' => 1,
                'project_id' => 1,
                'tariff_id' => 1,
                'work_date' => '2026-06-01',
                'hours' => 25,
                'description' => 'edge case',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Dati non validi',
            ])
            ->assertJsonStructure([
                'errors' => ['hours'],
            ]);
    }

    public function test_invoices_index_rejects_month_without_year(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->getJson('/api/invoices?month=6');

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Dati non validi',
            ])
            ->assertJsonStructure([
                'errors' => ['year'],
            ]);
    }

    public function test_invoices_store_returns_409_for_duplicate_invoice_number(): void
    {
        DB::table('invoices')->insert([
            'invoice_number' => 'INV-2026-0001',
            'client_id' => 1,
            'invoice_date' => '2026-06-01',
            'stamp_duty' => 2,
            'subtotal' => 100,
            'tax_amount' => 4,
            'total' => 106,
            'notes' => null,
            'status' => 'draft',
        ]);

        $response = $this
            ->withoutMiddleware()
            ->postJson('/api/invoices', [
                'invoice_number' => 'INV-2026-0001',
                'client_id' => 1,
                'invoice_date' => '2026-06-02',
                'stamp_duty' => 2,
                'subtotal' => 100,
                'tax_amount' => 4,
                'total' => 106,
                'items' => [
                    [
                        'description' => 'Line test',
                        'tariff_id' => 1,
                        'hours' => 1,
                        'hourly_rate' => 100,
                        'tax_inclusive' => false,
                        'line_total' => 100,
                    ],
                ],
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Conflitto dati',
            ])
            ->assertJsonStructure([
                'error',
            ]);
    }
}
