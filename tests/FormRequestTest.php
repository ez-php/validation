<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Validation\AuthorizationException;
use EzPhp\Validation\ConditionalRule;
use EzPhp\Validation\FormRequest;
use EzPhp\Validation\RuleInterface;
use EzPhp\Validation\ValidationException;
use EzPhp\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(FormRequest::class)]
#[CoversClass(AuthorizationException::class)]
#[UsesClass(Validator::class)]
#[UsesClass(ValidationException::class)]
#[UsesClass(ConditionalRule::class)]
final class FormRequestTest extends TestCase
{
    // =========================================================================
    // Helpers — concrete subclasses for testing
    // =========================================================================

    /**
     * @param array<string, mixed>                                          $data
     * @param array<string, string|list<string|RuleInterface|ConditionalRule>> $rules
     */
    private function makeRequest(array $data, array $rules, bool $authorized = true): FormRequest
    {
        return new class ($data, $rules, $authorized) extends FormRequest {
            /** @param array<string, string|list<string|RuleInterface|ConditionalRule>> $ruleMap */
            public function __construct(
                array $data,
                private readonly array $ruleMap,
                private readonly bool $authorized,
            ) {
                parent::__construct($data);
            }

            public function rules(): array
            {
                return $this->ruleMap;
            }

            public function authorize(): bool
            {
                return $this->authorized;
            }
        };
    }

    // =========================================================================
    // Construction — validation on init
    // =========================================================================

    public function test_construction_succeeds_when_data_is_valid(): void
    {
        $req = $this->makeRequest(
            ['name' => 'Alice', 'email' => 'alice@example.com'],
            ['name' => 'required|string', 'email' => 'required|email'],
        );

        self::assertInstanceOf(FormRequest::class, $req);
    }

    public function test_construction_throws_validation_exception_on_failure(): void
    {
        $this->expectException(ValidationException::class);

        $this->makeRequest(
            ['name' => ''],
            ['name' => 'required'],
        );
    }

    public function test_construction_throws_authorization_exception_when_not_authorized(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->makeRequest(
            ['name' => 'Alice'],
            ['name' => 'required'],
            authorized: false,
        );
    }

    public function test_authorization_is_checked_before_validation(): void
    {
        // Both authorization fail and validation fail — AuthorizationException wins
        $this->expectException(AuthorizationException::class);

        $this->makeRequest(
            ['name' => ''],
            ['name' => 'required'],
            authorized: false,
        );
    }

    // =========================================================================
    // validated()
    // =========================================================================

    public function test_validated_returns_only_rule_fields(): void
    {
        $req = $this->makeRequest(
            ['name' => 'Alice', 'extra' => 'ignored', 'other' => 'also-ignored'],
            ['name' => 'required|string'],
        );

        $validated = $req->validated();

        self::assertSame(['name' => 'Alice'], $validated);
    }

    public function test_validated_excludes_extra_fields(): void
    {
        $req = $this->makeRequest(
            ['name' => 'Alice', 'extra' => 'evil-injection'],
            ['name' => 'required'],
        );

        self::assertArrayNotHasKey('extra', $req->validated());
    }

    public function test_validated_includes_multiple_validated_fields(): void
    {
        $req = $this->makeRequest(
            ['name' => 'Alice', 'age' => 30, 'extra' => 'x'],
            ['name' => 'required', 'age' => 'required|integer'],
        );

        $validated = $req->validated();

        self::assertArrayHasKey('name', $validated);
        self::assertArrayHasKey('age', $validated);
        self::assertArrayNotHasKey('extra', $validated);
    }

    // =========================================================================
    // all() and input()
    // =========================================================================

    public function test_all_returns_complete_data(): void
    {
        $req = $this->makeRequest(
            ['name' => 'Alice', 'extra' => 'yes'],
            ['name' => 'required'],
        );

        self::assertSame(['name' => 'Alice', 'extra' => 'yes'], $req->all());
    }

    public function test_input_returns_specific_field(): void
    {
        $req = $this->makeRequest(
            ['name' => 'Alice'],
            ['name' => 'required'],
        );

        self::assertSame('Alice', $req->input('name'));
    }

    public function test_input_returns_default_for_absent_key(): void
    {
        $req = $this->makeRequest(
            ['name' => 'Alice'],
            ['name' => 'required'],
        );

        self::assertSame('default', $req->input('missing', 'default'));
    }

    // =========================================================================
    // Default authorize() = true
    // =========================================================================

    public function test_default_authorize_is_true(): void
    {
        $req = new class (['name' => 'Alice']) extends FormRequest {
            public function rules(): array
            {
                return ['name' => 'required'];
            }
        };

        self::assertInstanceOf(FormRequest::class, $req);
    }

    // =========================================================================
    // validated() with nested rule keys
    // =========================================================================

    public function test_validated_includes_top_level_key_for_nested_rules(): void
    {
        $req = $this->makeRequest(
            ['address' => ['city' => 'Vienna'], 'extra' => 'ignored'],
            ['address.city' => 'required|string'],
        );

        $validated = $req->validated();

        self::assertArrayHasKey('address', $validated);
        self::assertArrayNotHasKey('extra', $validated);
    }
}
