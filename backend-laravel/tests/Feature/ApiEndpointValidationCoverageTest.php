<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiEndpointValidationCoverageTest extends TestCase
{
    public function test_auth_login_requires_username_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'only-username',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Dati non validi',
            ])
            ->assertJsonStructure([
                'errors' => ['password'],
            ]);
    }

    public function test_auth_refresh_requires_refresh_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', []);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Dati non validi',
            ])
            ->assertJsonStructure([
                'errors' => ['refreshToken'],
            ]);
    }

    public function test_users_change_password_validates_min_length(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->putJson('/api/users/1/password', [
                'password' => 'short',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Dati non validi',
            ])
            ->assertJsonStructure([
                'errors' => ['password'],
            ]);
    }

    public function test_clients_add_referents_requires_user_ids_or_user_id(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->postJson('/api/clients/1/referents', []);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Dati non validi',
            ])
            ->assertJsonStructure([
                'errors' => ['user_ids'],
            ]);
    }

    public function test_projects_resolve_tariff_requires_project_id(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->getJson('/api/projects/tariff/resolve');

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Dati non validi',
            ])
            ->assertJsonStructure([
                'errors' => ['project_id'],
            ]);
    }
}
