<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Validation\ConditionalRule;
use EzPhp\Validation\Rule;
use EzPhp\Validation\RuleInterface;
use EzPhp\Validation\ValidationException;
use EzPhp\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Validator::class)]
#[CoversClass(Rule::class)]
#[CoversClass(ConditionalRule::class)]
#[UsesClass(ValidationException::class)]
final class ConditionalRuleTest extends TestCase
{
    // =========================================================================
    // sometimes
    // =========================================================================

    public function test_sometimes_skips_all_rules_when_key_is_absent(): void
    {
        $v = Validator::make([], ['name' => ['sometimes', 'required', 'string']]);

        self::assertTrue($v->passes());
    }

    public function test_sometimes_skips_required_when_key_is_absent(): void
    {
        $v = Validator::make([], ['email' => ['sometimes', 'required', 'email']]);

        self::assertSame([], $v->errors());
    }

    public function test_sometimes_validates_normally_when_key_is_present(): void
    {
        $v = Validator::make(['name' => ''], ['name' => ['sometimes', 'required', 'string']]);

        self::assertTrue($v->fails());
        self::assertArrayHasKey('name', $v->errors());
    }

    public function test_sometimes_passes_when_key_is_present_and_valid(): void
    {
        $v = Validator::make(['name' => 'Alice'], ['name' => ['sometimes', 'required', 'string']]);

        self::assertTrue($v->passes());
    }

    public function test_sometimes_in_pipe_string_form(): void
    {
        $v = Validator::make([], ['name' => 'sometimes|required|string']);

        self::assertTrue($v->passes());
    }

    public function test_sometimes_pipe_string_validates_when_present(): void
    {
        $v = Validator::make(['name' => 42], ['name' => 'sometimes|required|string']);

        self::assertTrue($v->fails());
    }

    public function test_without_sometimes_absent_field_still_fails_required(): void
    {
        $v = Validator::make([], ['name' => ['required', 'string']]);

        self::assertTrue($v->fails());
        self::assertArrayHasKey('name', $v->errors());
    }

    public function test_sometimes_only_skips_absent_fields_not_null_values(): void
    {
        // Key IS present in data, value is null — required should still fail
        $v = Validator::make(['name' => null], ['name' => ['sometimes', 'required']]);

        self::assertTrue($v->fails());
    }

    // =========================================================================
    // Rule::when — bool condition
    // =========================================================================

    public function test_rule_when_true_applies_rules(): void
    {
        $v = Validator::make(
            ['age' => 'abc'],
            ['age' => [Rule::when(true, ['required', 'integer'])]],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('age', $v->errors());
    }

    public function test_rule_when_false_skips_rules(): void
    {
        $v = Validator::make(
            ['age' => 'not-an-integer'],
            ['age' => [Rule::when(false, ['integer'])]],
        );

        self::assertTrue($v->passes());
    }

    public function test_rule_when_true_with_pipe_string_rules(): void
    {
        $v = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => [Rule::when(true, 'required|email')]],
        );

        self::assertTrue($v->fails());
    }

    public function test_rule_when_false_with_pipe_string_rules(): void
    {
        $v = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => [Rule::when(false, 'required|email')]],
        );

        self::assertTrue($v->passes());
    }

    // =========================================================================
    // Rule::when — closure condition
    // =========================================================================

    public function test_rule_when_closure_returning_true_applies_rules(): void
    {
        $v = Validator::make(
            ['score' => 'x'],
            ['score' => [Rule::when(fn () => true, ['integer'])]],
        );

        self::assertTrue($v->fails());
    }

    public function test_rule_when_closure_returning_false_skips_rules(): void
    {
        $v = Validator::make(
            ['score' => 'x'],
            ['score' => [Rule::when(fn () => false, ['integer'])]],
        );

        self::assertTrue($v->passes());
    }

    public function test_closure_can_capture_external_state(): void
    {
        $isPremium = true;

        $v = Validator::make(
            ['discount' => 999],
            ['discount' => ['required', Rule::when(fn () => $isPremium, ['integer', 'max:100'])]],
        );

        self::assertTrue($v->fails());
    }

    // =========================================================================
    // Rule::when — mixed with built-in and custom rules
    // =========================================================================

    public function test_rule_when_mixed_with_builtin_rules(): void
    {
        $v = Validator::make(
            ['code' => ''],
            ['code' => ['required', Rule::when(true, ['string', 'min:3'])]],
        );

        // 'required' fails because code is empty
        self::assertTrue($v->fails());
    }

    public function test_rule_when_with_custom_rule_object(): void
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
            ['code' => [Rule::when(true, [$uppercase])]],
        );

        self::assertTrue($v->fails());
        self::assertSame('The code must be uppercase.', $v->errors()['code'][0]);
    }

    public function test_multiple_rule_when_on_same_field(): void
    {
        $v = Validator::make(
            ['score' => 'x'],
            ['score' => [
                Rule::when(true, ['required']),
                Rule::when(true, ['integer']),
            ]],
        );

        // 'required' passes (value is 'x', not empty), 'integer' fails
        self::assertTrue($v->fails());
        $errors = $v->errors()['score'];
        self::assertCount(1, $errors);
    }

    // =========================================================================
    // Rule::when — validate() integration
    // =========================================================================

    public function test_rule_when_true_causes_validate_to_throw(): void
    {
        $v = Validator::make(
            ['age' => 'not-int'],
            ['age' => [Rule::when(true, ['integer'])]],
        );

        $this->expectException(ValidationException::class);
        $v->validate();
    }

    public function test_rule_when_false_does_not_cause_validate_to_throw(): void
    {
        $v = Validator::make(
            ['age' => 'not-int'],
            ['age' => [Rule::when(false, ['integer'])]],
        );

        $v->validate(); // must not throw
        self::assertTrue($v->passes());
    }
}
