<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Validation\RuleInterface;
use EzPhp\Validation\ValidationException;
use EzPhp\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Validator::class)]
#[UsesClass(ValidationException::class)]
final class CustomRuleTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /** @return RuleInterface */
    private function alwaysPasses(): RuleInterface
    {
        return new class () implements RuleInterface {
            public function passes(string $field, mixed $value): bool
            {
                return true;
            }

            public function message(): string
            {
                return 'The :field is invalid.';
            }
        };
    }

    /** @return RuleInterface */
    private function alwaysFails(): RuleInterface
    {
        return new class () implements RuleInterface {
            public function passes(string $field, mixed $value): bool
            {
                return false;
            }

            public function message(): string
            {
                return 'The :field failed custom validation.';
            }
        };
    }

    // ---------------------------------------------------------------------------
    // Passing rules
    // ---------------------------------------------------------------------------

    public function test_custom_rule_that_passes_produces_no_error(): void
    {
        $v = Validator::make(['name' => 'hello'], ['name' => [$this->alwaysPasses()]]);

        self::assertTrue($v->passes());
        self::assertSame([], $v->errors());
    }

    // ---------------------------------------------------------------------------
    // Failing rules
    // ---------------------------------------------------------------------------

    public function test_custom_rule_that_fails_adds_error(): void
    {
        $v = Validator::make(['name' => 'hello'], ['name' => [$this->alwaysFails()]]);

        self::assertTrue($v->fails());
        self::assertArrayHasKey('name', $v->errors());
    }

    public function test_field_placeholder_is_replaced_in_message(): void
    {
        $v = Validator::make(['email' => 'x'], ['email' => [$this->alwaysFails()]]);

        $errors = $v->errors();

        self::assertSame('The email failed custom validation.', $errors['email'][0]);
    }

    public function test_custom_rule_validate_throws_on_failure(): void
    {
        $v = Validator::make(['name' => 'x'], ['name' => [$this->alwaysFails()]]);

        $this->expectException(ValidationException::class);
        $v->validate();
    }

    // ---------------------------------------------------------------------------
    // Mixed with built-in rules
    // ---------------------------------------------------------------------------

    public function test_custom_rule_combined_with_builtin_rules_both_can_fail(): void
    {
        $uppercase = new class () implements RuleInterface {
            public function passes(string $field, mixed $value): bool
            {
                return is_string($value) && strtoupper($value) === $value;
            }

            public function message(): string
            {
                return 'The :field must be uppercase.';
            }
        };

        $v = Validator::make(
            ['code' => 'abc'],
            ['code' => ['required', 'string', $uppercase]],
        );

        self::assertTrue($v->fails());
        self::assertSame('The code must be uppercase.', $v->errors()['code'][0]);
    }

    public function test_custom_rule_combined_with_builtin_rules_all_pass(): void
    {
        $uppercase = new class () implements RuleInterface {
            public function passes(string $field, mixed $value): bool
            {
                return is_string($value) && strtoupper($value) === $value;
            }

            public function message(): string
            {
                return 'The :field must be uppercase.';
            }
        };

        $v = Validator::make(
            ['code' => 'ABC'],
            ['code' => ['required', 'string', $uppercase]],
        );

        self::assertTrue($v->passes());
    }

    // ---------------------------------------------------------------------------
    // Multiple custom rules on the same field
    // ---------------------------------------------------------------------------

    public function test_multiple_custom_rules_all_failures_collected(): void
    {
        $v = Validator::make(
            ['field' => 'value'],
            ['field' => [$this->alwaysFails(), $this->alwaysFails()]],
        );

        self::assertCount(2, $v->errors()['field']);
    }

    // ---------------------------------------------------------------------------
    // Absent / null values
    // ---------------------------------------------------------------------------

    public function test_custom_rule_receives_null_for_absent_field(): void
    {
        $spy = new class () implements RuleInterface {
            public mixed $capturedValue = 'not-set';

            public function passes(string $field, mixed $value): bool
            {
                $this->capturedValue = $value;

                return true;
            }

            public function message(): string
            {
                return '';
            }
        };

        $v = Validator::make([], ['name' => [$spy]]);
        $v->passes();

        self::assertNull($spy->capturedValue);
    }
}
