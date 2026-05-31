<?php

namespace Tests\Feature;

use App\Support\ApiRequestValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiValidationErrorFormatTest extends TestCase
{
    public function test_api_validation_error_has_standard_422_shape(): void
    {
        $uri = '/api/__test/validation-' . uniqid();

        Route::post($uri, function (Request $request) {
            ApiRequestValidator::validate($request, [
                'name' => ['required', 'string'],
            ]);

            return response()->json(['ok' => true]);
        });

        $response = $this->postJson($uri, []);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Dati non validi',
            ])
            ->assertJsonStructure([
                'errors' => ['name'],
            ]);
    }

    public function test_api_validation_passes_with_valid_payload(): void
    {
        $uri = '/api/__test/validation-ok-' . uniqid();

        Route::post($uri, function (Request $request) {
            $data = ApiRequestValidator::validate($request, [
                'name' => ['required', 'string'],
            ]);

            return response()->json([
                'ok' => true,
                'name' => $data['name'],
            ]);
        });

        $response = $this->postJson($uri, [
            'name' => 'Mario',
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'name' => 'Mario',
            ]);
    }
}
