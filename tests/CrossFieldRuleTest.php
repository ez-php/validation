<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Validation\ValidationException;
use EzPhp\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Validator::class)]
#[UsesClass(ValidationException::class)]
final class CrossFieldRuleTest extends TestCase
{
    // =========================================================================
    // confirmed
    // =========================================================================

    public function test_confirmed_passes_when_field_matches_confirmation(): void
    {
        $v = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => ['confirmed']],
        );

        self::assertTrue($v->passes());
    }

    public function test_confirmed_fails_when_confirmation_differs(): void
    {
        $v = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'wrong'],
            ['password' => ['confirmed']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('password', $v->errors());
    }

    public function test_confirmed_fails_when_confirmation_is_absent(): void
    {
        $v = Validator::make(
            ['password' => 'secret'],
            ['password' => ['confirmed']],
        );

        self::assertTrue($v->fails());
    }

    public function test_confirmed_fails_when_value_is_null_and_confirmation_is_not(): void
    {
        $v = Validator::make(
            ['password' => null, 'password_confirmation' => 'secret'],
            ['password' => ['confirmed']],
        );

        self::assertTrue($v->fails());
    }

    public function test_confirmed_passes_when_both_are_null(): void
    {
        $v = Validator::make(
            ['password' => null, 'password_confirmation' => null],
            ['password' => ['confirmed']],
        );

        self::assertTrue($v->passes());
    }

    // =========================================================================
    // same
    // =========================================================================

    public function test_same_passes_when_fields_are_equal(): void
    {
        $v = Validator::make(
            ['email' => 'a@b.com', 'email_verify' => 'a@b.com'],
            ['email' => ['same:email_verify']],
        );

        self::assertTrue($v->passes());
    }

    public function test_same_fails_when_fields_differ(): void
    {
        $v = Validator::make(
            ['email' => 'a@b.com', 'email_verify' => 'b@b.com'],
            ['email' => ['same:email_verify']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('email', $v->errors());
    }

    public function test_same_fails_when_other_field_is_absent(): void
    {
        $v = Validator::make(
            ['email' => 'a@b.com'],
            ['email' => ['same:email_verify']],
        );

        self::assertTrue($v->fails());
    }

    public function test_same_passes_when_both_fields_are_null(): void
    {
        $v = Validator::make(
            ['a' => null, 'b' => null],
            ['a' => ['same:b']],
        );

        self::assertTrue($v->passes());
    }

    public function test_same_error_message_contains_field_names(): void
    {
        $v = Validator::make(
            ['a' => 'x', 'b' => 'y'],
            ['a' => ['same:b']],
        );

        $v->fails();
        $msg = $v->errors()['a'][0];

        self::assertStringContainsString('a', $msg);
        self::assertStringContainsString('b', $msg);
    }

    // =========================================================================
    // different
    // =========================================================================

    public function test_different_passes_when_fields_differ(): void
    {
        $v = Validator::make(
            ['new_password' => 'newpass', 'old_password' => 'oldpass'],
            ['new_password' => ['different:old_password']],
        );

        self::assertTrue($v->passes());
    }

    public function test_different_fails_when_fields_are_equal(): void
    {
        $v = Validator::make(
            ['new_password' => 'same', 'old_password' => 'same'],
            ['new_password' => ['different:old_password']],
        );

        self::assertTrue($v->fails());
        self::assertArrayHasKey('new_password', $v->errors());
    }

    public function test_different_passes_when_other_field_is_absent(): void
    {
        // absent field resolves to null; 'value' !== null → passes
        $v = Validator::make(
            ['field' => 'value'],
            ['field' => ['different:other']],
        );

        self::assertTrue($v->passes());
    }

    public function test_different_fails_when_both_are_null(): void
    {
        $v = Validator::make(
            ['a' => null, 'b' => null],
            ['a' => ['different:b']],
        );

        self::assertTrue($v->fails());
    }

    public function test_different_pipe_string_form(): void
    {
        $v = Validator::make(
            ['new' => 'abc', 'old' => 'abc'],
            ['new' => 'required|different:old'],
        );

        self::assertTrue($v->fails());
    }
}
