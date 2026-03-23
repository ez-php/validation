<?php

declare(strict_types=1);

namespace Tests\Validation;

use EzPhp\I18n\Translator;
use EzPhp\Validation\ValidationException;
use EzPhp\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Class ValidatorTest
 *
 * @package Tests\Validation
 */
#[CoversClass(Validator::class)]
#[UsesClass(ValidationException::class)]
final class ValidatorTest extends TestCase
{
    // --- required ---

    /**
     * @return void
     */
    public function testRequiredPassesWhenPresent(): void
    {
        $v = Validator::make(['name' => 'John'], ['name' => 'required']);
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testRequiredFailsWhenMissing(): void
    {
        $v = Validator::make([], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    /**
     * @return void
     */
    public function testRequiredFailsOnEmptyString(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());
    }

    // --- string ---

    /**
     * @return void
     */
    public function testStringPassesForStringValue(): void
    {
        $v = Validator::make(['name' => 'hello'], ['name' => 'string']);
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testStringFailsForNonString(): void
    {
        $v = Validator::make(['name' => 42], ['name' => 'string']);
        $this->assertTrue($v->fails());
    }

    /**
     * @return void
     */
    public function testStringPassesWhenNull(): void
    {
        // null means absent — string check skips absent fields
        $v = Validator::make([], ['name' => 'string']);
        $this->assertTrue($v->passes());
    }

    // --- integer ---

    /**
     * @return void
     */
    public function testIntegerPassesForIntValue(): void
    {
        $v = Validator::make(['age' => 25], ['age' => 'integer']);
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testIntegerPassesForNumericString(): void
    {
        $v = Validator::make(['age' => '25'], ['age' => 'integer']);
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testIntegerFailsForFloat(): void
    {
        $v = Validator::make(['age' => '3.14'], ['age' => 'integer']);
        $this->assertTrue($v->fails());
    }

    /**
     * @return void
     */
    public function testIntegerFailsForAlpha(): void
    {
        $v = Validator::make(['age' => 'abc'], ['age' => 'integer']);
        $this->assertTrue($v->fails());
    }

    /**
     * @return void
     */
    public function testIntAliasWorks(): void
    {
        $v = Validator::make(['age' => 'abc'], ['age' => 'int']);
        $this->assertTrue($v->fails());
    }

    /**
     * '0' is a valid integer string: FILTER_VALIDATE_INT returns 0, which !== false.
     *
     * @return void
     */
    public function testIntegerPassesForStringZero(): void
    {
        $v = Validator::make(['count' => '0'], ['count' => 'integer']);
        $this->assertTrue($v->passes());
    }

    /**
     * Negative integer strings are accepted.
     *
     * @return void
     */
    public function testIntegerPassesForNegativeString(): void
    {
        $v = Validator::make(['offset' => '-5'], ['offset' => 'integer']);
        $this->assertTrue($v->passes());
    }

    /**
     * Whitespace-only strings fail FILTER_VALIDATE_INT and are treated as invalid integers.
     *
     * @return void
     */
    public function testIntegerFailsForWhitespaceOnlyString(): void
    {
        $v = Validator::make(['age' => '   '], ['age' => 'integer']);
        $this->assertTrue($v->fails());
    }

    // --- email ---

    /**
     * @return void
     */
    public function testEmailPasses(): void
    {
        $v = Validator::make(['email' => 'user@example.com'], ['email' => 'email']);
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testEmailFails(): void
    {
        $v = Validator::make(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertTrue($v->fails());
    }

    /**
     * @return void
     */
    public function testEmailSkipsWhenNull(): void
    {
        $v = Validator::make([], ['email' => 'email']);
        $this->assertTrue($v->passes());
    }

    // --- min ---

    /**
     * @return void
     */
    public function testMinPassesForLongEnoughString(): void
    {
        $v = Validator::make(['pass' => 'secret'], ['pass' => 'min:4']);
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testMinFailsForShortString(): void
    {
        $v = Validator::make(['pass' => 'abc'], ['pass' => 'min:4']);
        $this->assertTrue($v->fails());
    }

    /**
     * @return void
     */
    public function testMinPassesForHighEnoughNumber(): void
    {
        $v = Validator::make(['age' => 18], ['age' => 'min:18']);
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testMinFailsForLowNumber(): void
    {
        $v = Validator::make(['age' => 17], ['age' => 'min:18']);
        $this->assertTrue($v->fails());
    }

    // --- max ---

    /**
     * @return void
     */
    public function testMaxPassesForShortEnoughString(): void
    {
        $v = Validator::make(['name' => 'John'], ['name' => 'max:10']);
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testMaxFailsForLongString(): void
    {
        $v = Validator::make(['name' => 'VeryLongNameIndeed'], ['name' => 'max:5']);
        $this->assertTrue($v->fails());
    }

    /**
     * @return void
     */
    public function testMaxPassesForLowEnoughNumber(): void
    {
        $v = Validator::make(['score' => 99], ['score' => 'max:100']);
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testMaxFailsForHighNumber(): void
    {
        $v = Validator::make(['score' => 101], ['score' => 'max:100']);
        $this->assertTrue($v->fails());
    }

    // --- regex ---

    /**
     * @return void
     */
    public function testRegexPasses(): void
    {
        $v = Validator::make(['code' => 'ABC123'], ['code' => 'regex:/^[A-Z0-9]+$/']);
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testRegexFails(): void
    {
        $v = Validator::make(['code' => 'abc!'], ['code' => 'regex:/^[A-Z0-9]+$/']);
        $this->assertTrue($v->fails());
    }

    /**
     * @return void
     */
    public function testRegexSkipsNonStrings(): void
    {
        $v = Validator::make(['code' => 123], ['code' => 'regex:/^[A-Z]+$/']);
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testInvalidRegexPatternThrowsRuntimeException(): void
    {
        // An invalid regex is a programming error — throws instead of adding a validation error
        $this->expectException(RuntimeException::class);
        $v = Validator::make(['code' => 'abc'], ['code' => 'regex:not_a_valid_regex']);
        $v->fails();
    }

    // --- pipe-separated rules ---

    /**
     * @return void
     */
    public function testMultipleRulesViaPipe(): void
    {
        $v = Validator::make(['email' => ''], ['email' => 'required|email']);
        $this->assertTrue($v->fails());
        $this->assertCount(1, $v->errors()['email']); // required fires, email check skips null/empty
    }

    /**
     * @return void
     */
    public function testMultipleRulesAsArray(): void
    {
        $v = Validator::make(['age' => 'abc'], ['age' => ['required', 'integer']]);
        $this->assertTrue($v->fails());
    }

    // --- validate() throws ValidationException ---

    /**
     * @return void
     */
    public function testValidateThrowsOnFailure(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['name' => ''], ['name' => 'required'])->validate();
    }

    /**
     * @return void
     */
    public function testValidateDoesNotThrowOnSuccess(): void
    {
        $this->expectNotToPerformAssertions();
        Validator::make(['name' => 'John'], ['name' => 'required'])->validate();
    }

    // --- ValidationException ---

    /**
     * @return void
     */
    public function testValidationExceptionCarriesErrors(): void
    {
        try {
            Validator::make(['name' => ''], ['name' => 'required'])->validate();
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
            $this->assertStringContainsString('required', $e->errors()['name'][0]);
            $this->assertStringContainsString('Validation failed', $e->getMessage());
        }
    }

    // --- DB rules throw without Database ---

    /**
     * @return void
     */
    public function testUniqueRuleThrowsWithoutDatabase(): void
    {
        $this->expectException(RuntimeException::class);
        Validator::make(['email' => 'test@example.com'], ['email' => 'unique:users'])->fails();
    }

    /**
     * @return void
     */
    public function testExistsRuleThrowsWithoutDatabase(): void
    {
        $this->expectException(RuntimeException::class);
        Validator::make(['user_id' => '5'], ['user_id' => 'exists:users,id'])->fails();
    }

    // --- SQL injection protection: invalid table/column names ---

    /**
     * @return void
     */
    public function testUniqueRuleThrowsOnInvalidTableName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid table name');
        Validator::make(['email' => 'x@x.com'], ['email' => 'unique:users; DROP TABLE users--'])->fails();
    }

    /**
     * @return void
     */
    public function testUniqueRuleThrowsOnInvalidColumnName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid column name');
        Validator::make(['email' => 'x@x.com'], ['email' => 'unique:users,email`=`email'])->fails();
    }

    /**
     * @return void
     */
    public function testExistsRuleThrowsOnInvalidTableName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid table name');
        Validator::make(['id' => '1'], ['id' => 'exists:users OR 1=1--'])->fails();
    }

    /**
     * @return void
     */
    public function testExistsRuleThrowsOnInvalidColumnName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid column name');
        Validator::make(['id' => '1'], ['id' => 'exists:users,id OR 1=1'])->fails();
    }

    // --- skipping rules for null/absent values ---

    /**
     * @return void
     */
    public function testRulesSkipAbsentFieldsExceptRequired(): void
    {
        $v = Validator::make([], ['age' => 'integer|min:0|max:150']);
        $this->assertTrue($v->passes());
    }

    // --- confirmed ---

    /**
     * @return void
     */
    public function testConfirmedPassesWhenConfirmationMatches(): void
    {
        $v = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'confirmed'],
        );
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testConfirmedFailsWhenConfirmationMissing(): void
    {
        $v = Validator::make(['password' => 'secret'], ['password' => 'confirmed']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('password', $v->errors());
    }

    /**
     * @return void
     */
    public function testConfirmedFailsWhenConfirmationDiffers(): void
    {
        $v = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'other'],
            ['password' => 'confirmed'],
        );
        $this->assertTrue($v->fails());
    }

    // --- same ---

    /**
     * @return void
     */
    public function testSamePassesWhenFieldsMatch(): void
    {
        $v = Validator::make(
            ['email' => 'a@b.com', 'email_repeat' => 'a@b.com'],
            ['email_repeat' => 'same:email'],
        );
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testSameFailsWhenFieldsDiffer(): void
    {
        $v = Validator::make(
            ['email' => 'a@b.com', 'email_repeat' => 'x@y.com'],
            ['email_repeat' => 'same:email'],
        );
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('email_repeat', $v->errors());
    }

    /**
     * @return void
     */
    public function testSameFailsWhenOtherFieldMissing(): void
    {
        $v = Validator::make(['email_repeat' => 'a@b.com'], ['email_repeat' => 'same:email']);
        $this->assertTrue($v->fails());
    }

    // --- different ---

    /**
     * @return void
     */
    public function testDifferentPassesWhenFieldsDiffer(): void
    {
        $v = Validator::make(
            ['old_password' => 'old', 'new_password' => 'new'],
            ['new_password' => 'different:old_password'],
        );
        $this->assertTrue($v->passes());
    }

    /**
     * @return void
     */
    public function testDifferentFailsWhenFieldsMatch(): void
    {
        $v = Validator::make(
            ['old_password' => 'same', 'new_password' => 'same'],
            ['new_password' => 'different:old_password'],
        );
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('new_password', $v->errors());
    }

    /**
     * @return void
     */
    public function testDifferentPassesWhenOtherFieldMissing(): void
    {
        // other field missing → null, value is non-null → they differ
        $v = Validator::make(['new_password' => 'secret'], ['new_password' => 'different:old_password']);
        $this->assertTrue($v->passes());
    }

    // --- unknown rule throws RuntimeException ---

    /**
     * @return void
     */
    public function testUnknownRuleThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/unknown_rule/');
        Validator::make(['x' => 'hello'], ['x' => 'unknown_rule'])->fails();
    }

    // --- result is cached (run() idempotent) ---

    /**
     * @return void
     */
    public function testResultIsCached(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertTrue($v->fails()); // second call returns same result
        $this->assertCount(1, $v->errors()['name']);
    }

    // --- with Translator ---

    public function testTranslatorIsUsedForMessages(): void
    {
        $langPath = sys_get_temp_dir() . '/ez-php-i18n-' . uniqid();
        mkdir($langPath . '/de', 0o755, true);
        file_put_contents(
            $langPath . '/de/validation.php',
            "<?php\nreturn ['required' => 'Das Feld :field ist erforderlich.'];\n",
        );

        $translator = new Translator('de', 'en', $langPath);
        $v = Validator::make([], ['name' => 'required'], null, $translator);

        $this->assertTrue($v->fails());
        $this->assertStringContainsString('Das Feld name ist erforderlich.', $v->errors()['name'][0]);

        unlink($langPath . '/de/validation.php');
        rmdir($langPath . '/de');
        rmdir($langPath);
    }
}
