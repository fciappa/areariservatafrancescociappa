<?php

namespace Tests\Unit;

use App\Support\ApiRequestValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ApiRequestValidator::class)]
class ApiRequestValidatorTest extends TestCase
{
    public function test_validate_returns_validated_payload(): void
    {
        $request = Request::create('/api/test', 'POST', [
            'name' => 'Luigi',
            'extra' => 'drop-me',
        ]);

        $validated = ApiRequestValidator::validate($request, [
            'name' => ['required', 'string'],
        ]);

        $this->assertSame(['name' => 'Luigi'], $validated);
    }

    public function test_validate_throws_validation_exception_on_invalid_payload(): void
    {
        $this->expectException(ValidationException::class);

        $request = Request::create('/api/test', 'POST', []);

        ApiRequestValidator::validate($request, [
            'name' => ['required', 'string'],
        ]);
    }
}
