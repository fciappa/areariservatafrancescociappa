<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiEndToEndAuthHoursInvoicesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('JWT_SECRET=test-jwt-secret-ar115');
        putenv('JWT_REFRESH_SECRET=test-jwt-refresh-secret-ar115');
        putenv('JWT_EXPIRES_SECONDS=3600');
        putenv('JWT_REFRESH_EXPIRES_SECONDS=7200');

        $_ENV['JWT_SECRET'] = 'test-jwt-secret-ar115';
        $_ENV['JWT_REFRESH_SECRET'] = 'test-jwt-refresh-secret-ar115';
        $_ENV['JWT_EXPIRES_SECONDS'] = '3600';
        $_ENV['JWT_REFRESH_EXPIRES_SECONDS'] = '7200';

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->registerSqliteFunctions();
        $this->createSchema();
        $this->seedEndToEndDataset();
    }

    private function registerSqliteFunctions(): void
    {
        $pdo = DB::connection()->getPdo();

        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('FROM_UNIXTIME', function ($timestamp) {
                return date('Y-m-d H:i:s', (int) $timestamp);
            }, 1);

            $pdo->sqliteCreateFunction('NOW', function () {
                return date('Y-m-d H:i:s');
            }, 0);
        }
    }

    private function createSchema(): void
    {
        DB::statement('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            email TEXT,
            password_hash TEXT,
            role TEXT,
            collaborator_id INTEGER NULL,
            referent_id INTEGER NULL,
            is_active INTEGER DEFAULT 1
        )');

        DB::statement('CREATE TABLE refresh_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            token TEXT,
            expires_at TEXT
        )');

        DB::statement('CREATE TABLE clients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_name TEXT,
            vat_number TEXT
        )');

        DB::statement('CREATE TABLE projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_id INTEGER,
            name TEXT,
            is_active INTEGER DEFAULT 1,
            created_at TEXT
        )');

        DB::statement('CREATE TABLE tariffs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            hourly_rate NUMERIC,
            rate_type TEXT,
            tax_inclusive INTEGER DEFAULT 0,
            is_default INTEGER DEFAULT 0
        )');

        DB::statement('CREATE TABLE my_work_hours (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_id INTEGER,
            project_id INTEGER NULL,
            tariff_id INTEGER,
            work_date TEXT,
            hours NUMERIC,
            description TEXT,
            invoiced_at TEXT NULL
        )');

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
    }

    private function seedEndToEndDataset(): void
    {
        DB::table('users')->insert([
            'id' => 1,
            'username' => 'admin_e2e',
            'email' => 'admin-e2e@test.local',
            'password_hash' => password_hash('adminpass123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'collaborator_id' => null,
            'referent_id' => null,
            'is_active' => 1,
        ]);

        DB::table('clients')->insert([
            'id' => 1,
            'company_name' => 'E2E Client',
            'vat_number' => 'IT99999999999',
        ]);

        DB::table('projects')->insert([
            'id' => 1,
            'client_id' => 1,
            'name' => 'E2E Project',
            'is_active' => 1,
            'created_at' => now()->toDateTimeString(),
        ]);

        DB::table('tariffs')->insert([
            'id' => 1,
            'name' => 'E2E Tariff',
            'hourly_rate' => 100,
            'rate_type' => 'hourly',
            'tax_inclusive' => 0,
            'is_default' => 1,
        ]);
    }

    public function test_e2e_auth_hours_and_invoices_flow(): void
    {
        $unauthorized = $this->getJson('/api/hours/my');
        $unauthorized->assertStatus(401)
            ->assertJson([
                'message' => 'Token mancante',
            ]);

        $login = $this->postJson('/api/auth/login', [
            'username' => 'admin_e2e',
            'password' => 'adminpass123',
        ]);

        $login->assertStatus(200)
            ->assertJsonStructure([
                'accessToken',
                'refreshToken',
                'user' => ['id', 'username', 'role'],
            ]);

        $accessToken = (string) $login->json('accessToken');
        $refreshToken = (string) $login->json('refreshToken');

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        $storeHour = $this->withHeaders($headers)->postJson('/api/hours/my', [
            'client_id' => 1,
            'project_id' => 1,
            'tariff_id' => 1,
            'work_date' => '2026-06-01',
            'hours' => 2,
            'description' => 'E2E tracked hours',
        ]);

        $storeHour->assertStatus(201)
            ->assertJsonStructure(['id']);

        $hourId = (int) $storeHour->json('id');

        $simulate = $this->withHeaders($headers)->postJson('/api/invoices/simulate', [
            'items' => [
                [
                    'description' => 'E2E line',
                    'hourly_rate' => 100,
                    'hours' => 2,
                    'tax_inclusive' => false,
                ],
            ],
            'stamp_duty' => 2,
        ]);

        $simulate->assertStatus(200)
            ->assertJson([
                'subtotal' => 200,
                'tax_amount' => 8,
                'stamp_duty' => 2,
                'total' => 210,
            ]);

        $storeInvoice = $this->withHeaders($headers)->postJson('/api/invoices', [
            'invoice_number' => 'E2E-INV-0001',
            'client_id' => 1,
            'invoice_date' => '2026-06-02',
            'stamp_duty' => 2,
            'subtotal' => 200,
            'tax_amount' => 8,
            'total' => 210,
            'notes' => 'E2E invoice',
            'items' => [
                [
                    'description' => 'E2E line',
                    'tariff_id' => 1,
                    'hours' => 2,
                    'hourly_rate' => 100,
                    'tax_inclusive' => false,
                    'line_total' => 200,
                    'work_hour_ids' => [$hourId],
                ],
            ],
        ]);

        $storeInvoice->assertStatus(201)
            ->assertJsonStructure(['id']);

        $invoiceId = (int) $storeInvoice->json('id');

        $showInvoice = $this->withHeaders($headers)->getJson('/api/invoices/' . $invoiceId);
        $showInvoice->assertStatus(200)
            ->assertJsonPath('id', $invoiceId)
            ->assertJsonPath('company_name', 'E2E Client')
            ->assertJsonCount(1, 'items');

        $invoicedAt = DB::table('my_work_hours')->where('id', $hourId)->value('invoiced_at');
        $this->assertNotNull($invoicedAt);

        $refresh = $this->postJson('/api/auth/refresh', [
            'refreshToken' => $refreshToken,
        ]);

        $refresh->assertStatus(200)
            ->assertJsonStructure(['accessToken']);

        $logout = $this->postJson('/api/auth/logout', [
            'refreshToken' => $refreshToken,
        ]);

        $logout->assertStatus(200)
            ->assertJson([
                'message' => 'Logout effettuato',
            ]);

        $refreshAfterLogout = $this->postJson('/api/auth/refresh', [
            'refreshToken' => $refreshToken,
        ]);

        $refreshAfterLogout->assertStatus(401)
            ->assertJson([
                'message' => 'Refresh token non valido',
            ]);
    }
}
