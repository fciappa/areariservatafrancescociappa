<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiEndpointErrorCoverageTest extends TestCase
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
        DB::statement('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, name TEXT, is_active INTEGER DEFAULT 1, created_at TEXT)');
        DB::statement('CREATE TABLE client_deadlines (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, due_date TEXT)');
        DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT, email TEXT, role TEXT, is_active INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE project_referents (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, user_id INTEGER, UNIQUE(project_id, user_id))');
    }

    private function seedBaseData(): void
    {
        DB::table('clients')->insert([
            'id' => 1,
            'company_name' => 'Client Test',
            'vat_number' => 'IT00000000001',
        ]);

        DB::table('projects')->insert([
            'id' => 1,
            'client_id' => 1,
            'name' => 'Project Test',
            'is_active' => 1,
            'created_at' => now()->toDateTimeString(),
        ]);

        DB::table('users')->insert([
            'id' => 1,
            'username' => 'referent_test',
            'email' => 'referent@test.local',
            'role' => 'referent',
            'is_active' => 1,
        ]);
    }

    public function test_projects_show_returns_404_for_missing_project(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->getJson('/api/projects/999999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Progetto non trovato',
            ]);
    }

    public function test_deadlines_renew_returns_404_for_missing_deadline(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->putJson('/api/deadlines/999999/renew');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Scadenza non trovata',
            ]);
    }

    public function test_projects_add_referent_returns_409_on_duplicate_assignment(): void
    {
        DB::table('project_referents')->insert([
            'project_id' => 1,
            'user_id' => 1,
        ]);

        $response = $this
            ->withoutMiddleware()
            ->postJson('/api/projects/1/referents', [
                'user_id' => 1,
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Referente già assegnato a questo progetto',
            ]);
    }
}
