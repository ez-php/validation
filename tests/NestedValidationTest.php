<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Validation\ValidationException;
use EzPhp\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Validator::class)]
#[UsesClass(ValidationException::class)]
final class NestedValidationTest extends TestCase
{
    // =========================================================================
    // Dot-notation (no wildcard)
    // =========================================================================

    public function test_dot_notation_resolves_nested_value(): void
    {
        $v = Validator::make(
            ['address' => ['city' => 'Vienna']],
            ['address.city' => ['required', 'string']],
        );

        self::assertTrue($v->passes());
    }

    public function test_dot_notation_fails_when_nested_value_is_missing(): void
    {
        $v = Validator::make(
            ['address' => []],
            ['address.city' => ['required']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('address.city', $v->errors());
    }

    public function test_dot_notation_fails_type_rule_on_wrong_type(): void
    {
        $v = Validator::make(
            ['address' => ['city' => 42]],
            ['address.city' => ['string']],
        );

        self::assertTrue($v->fails());
    }

    public function test_deeply_nested_dot_notation(): void
    {
        $v = Validator::make(
            ['a' => ['b' => ['c' => 'hello']]],
            ['a.b.c' => ['required', 'string']],
        );

        self::assertTrue($v->passes());
    }

    public function test_dot_notation_returns_null_when_parent_is_not_array(): void
    {
        $v = Validator::make(
            ['address' => 'flat string'],
            ['address.city' => ['required']],
        );

        // 'address' is not an array, so 'address.city' resolves to null → required fails
        self::assertTrue($v->fails());
    }

    // =========================================================================
    // Wildcard expansion
    // =========================================================================

    public function test_wildcard_expands_to_all_items(): void
    {
        $v = Validator::make(
            ['items' => [['name' => 'foo'], ['name' => 'bar']]],
            ['items.*.name' => ['required', 'string']],
        );

        self::assertTrue($v->passes());
    }

    public function test_wildcard_fails_for_invalid_item(): void
    {
        $v = Validator::make(
            ['items' => [['name' => 'ok'], ['name' => '']]],
            ['items.*.name' => ['required']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('items.1.name', $v->errors());
    }

    public function test_wildcard_collects_errors_for_all_failing_items(): void
    {
        $v = Validator::make(
            ['items' => [['name' => ''], ['name' => '']]],
            ['items.*.name' => ['required']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('items.0.name', $v->errors());
        self::assertArrayHasKey('items.1.name', $v->errors());
    }

    public function test_wildcard_passes_with_empty_array(): void
    {
        // No items → no paths to validate → no errors
        $v = Validator::make(
            ['items' => []],
            ['items.*.name' => ['required']],
        );

        self::assertTrue($v->passes());
    }

    public function test_wildcard_skips_when_parent_is_not_array(): void
    {
        $v = Validator::make(
            ['items' => 'not-an-array'],
            ['items.*.name' => ['required']],
        );

        // resolveFieldPaths returns [] → nothing validated → passes
        self::assertTrue($v->passes());
    }

    public function test_wildcard_for_flat_list_values(): void
    {
        $v = Validator::make(
            ['tags' => ['php', 'symfony', '']],
            ['tags.*' => ['required', 'string']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('tags.2', $v->errors());
    }

    // =========================================================================
    // sometimes + dot notation
    // =========================================================================

    public function test_sometimes_with_dot_notation_skips_absent_path(): void
    {
        $v = Validator::make(
            ['address' => []],
            ['address.city' => ['sometimes', 'required', 'string']],
        );

        self::assertTrue($v->passes());
    }

    public function test_sometimes_with_dot_notation_validates_when_present(): void
    {
        $v = Validator::make(
            ['address' => ['city' => '']],
            ['address.city' => ['sometimes', 'required']],
        );

        self::assertTrue($v->fails());
    }

    // =========================================================================
    // Multi-level wildcard (matrix.*.*)
    // =========================================================================

    public function test_multi_level_wildcard_passes_when_all_valid(): void
    {
        $v = Validator::make(
            ['matrix' => [[1, 2], [3, 4]]],
            ['matrix.*.*' => ['required', 'integer']],
        );

        self::assertTrue($v->passes());
    }

    public function test_multi_level_wildcard_fails_for_invalid_cell(): void
    {
        $v = Validator::make(
            ['matrix' => [[1, 'bad'], [3, 4]]],
            ['matrix.*.*' => ['integer']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('matrix.0.1', $v->errors());
    }

    public function test_multi_level_wildcard_collects_all_failing_cells(): void
    {
        $v = Validator::make(
            ['matrix' => [['', ''], [3, '']]],
            ['matrix.*.*' => ['required']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('matrix.0.0', $v->errors());
        self::assertArrayHasKey('matrix.0.1', $v->errors());
        self::assertArrayHasKey('matrix.1.1', $v->errors());
    }

    public function test_multi_level_wildcard_passes_with_empty_inner_arrays(): void
    {
        $v = Validator::make(
            ['matrix' => [[], []]],
            ['matrix.*.*' => ['required']],
        );

        // No items in inner arrays → no paths → passes
        self::assertTrue($v->passes());
    }

    public function test_triple_level_wildcard(): void
    {
        $v = Validator::make(
            ['a' => [[['x', 'y'], ['z']]]],
            ['a.*.*.*' => ['required', 'string']],
        );

        self::assertTrue($v->passes());
    }

    // =========================================================================
    // Simple fields are unchanged
    // =========================================================================

    public function test_simple_keys_still_work_without_dots(): void
    {
        $v = Validator::make(
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'required|string', 'age' => 'required|integer'],
        );

        self::assertTrue($v->passes());
    }
}
