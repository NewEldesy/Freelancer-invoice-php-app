<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RequestValidator;
use PHPUnit\Framework\TestCase;

final class RequestValidatorTest extends TestCase
{
    private RequestValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RequestValidator();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'number'       => '20261101-1',
            'issuer_name'  => 'ISSU DEV',
            'issuer_email' => 'contact@issudev.com',
            'lines'        => [
                ['description' => 'Développement', 'quantity' => 1, 'unit_price' => 500_000],
            ],
        ], $overrides);
    }

    public function testValidDataPasses(): void
    {
        $this->assertTrue($this->validator->validate($this->validData()));
        $this->assertEmpty($this->validator->errors());
    }

    public function testMissingNumberFails(): void
    {
        $data = $this->validData(['number' => '']);
        $this->assertFalse($this->validator->validate($data));
        $this->assertArrayHasKey('number', $this->validator->errors());
    }

    public function testMissingIssuerNameFails(): void
    {
        $data = $this->validData(['issuer_name' => '']);
        $this->assertFalse($this->validator->validate($data));
        $this->assertArrayHasKey('issuer_name', $this->validator->errors());
    }

    public function testMissingEmailFails(): void
    {
        $data = $this->validData(['issuer_email' => '']);
        $this->assertFalse($this->validator->validate($data));
        $this->assertArrayHasKey('issuer_email', $this->validator->errors());
    }

    public function testInvalidEmailFails(): void
    {
        $data = $this->validData(['issuer_email' => 'not-an-email']);
        $this->assertFalse($this->validator->validate($data));
        $this->assertArrayHasKey('issuer_email', $this->validator->errors());
    }

    public function testEmptyLinesFail(): void
    {
        $data = $this->validData(['lines' => []]);
        $this->assertFalse($this->validator->validate($data));
        $this->assertArrayHasKey('lines', $this->validator->errors());
    }

    public function testLinesWithBlankDescriptionsOnly(): void
    {
        $data = $this->validData(['lines' => [
            ['description' => '  ', 'quantity' => 1, 'unit_price' => 0],
        ]]);
        $this->assertFalse($this->validator->validate($data));
        $this->assertArrayHasKey('lines', $this->validator->errors());
    }

    public function testMultipleErrorsReportedTogether(): void
    {
        $data = $this->validData(['number' => '', 'issuer_name' => '', 'lines' => []]);
        $this->assertFalse($this->validator->validate($data));
        $errors = $this->validator->errors();
        $this->assertArrayHasKey('number', $errors);
        $this->assertArrayHasKey('issuer_name', $errors);
        $this->assertArrayHasKey('lines', $errors);
    }

    public function testValidatorIsReusable(): void
    {
        $this->validator->validate($this->validData(['number' => '']));
        $this->assertNotEmpty($this->validator->errors());

        // second call must reset errors
        $this->validator->validate($this->validData());
        $this->assertEmpty($this->validator->errors());
    }
}
