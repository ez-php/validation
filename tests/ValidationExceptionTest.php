<?php

declare(strict_types=1);

namespace Tests\Validation;

use EzPhp\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class ValidationExceptionTest
 *
 * @package Tests\Validation
 */
#[CoversClass(ValidationException::class)]
final class ValidationExceptionTest extends TestCase
{
    /**
     * @return void
     */
    public function testErrorsAreAccessible(): void
    {
        $errors = ['email' => ['The email field is required.']];
        $e = new ValidationException($errors);

        $this->assertSame($errors, $e->errors());
        $this->assertSame('Validation failed.', $e->getMessage());
    }
}
