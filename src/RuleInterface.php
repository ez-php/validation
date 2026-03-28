<?php

declare(strict_types=1);

namespace EzPhp\Validation;

/**
 * Interface RuleInterface
 *
 * Implement this interface to define a custom validation rule.
 *
 * Usage:
 *
 *   class Uppercase implements RuleInterface
 *   {
 *       public function passes(string $field, mixed $value): bool
 *       {
 *           return is_string($value) && strtoupper($value) === $value;
 *       }
 *
 *       public function message(): string
 *       {
 *           return 'The :field must be uppercase.';
 *       }
 *   }
 *
 *   $v = Validator::make($data, ['code' => ['required', new Uppercase()]]);
 *
 * The `:field` placeholder in the message string is replaced with the field name.
 *
 * @package EzPhp\Validation
 */
interface RuleInterface
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $field The field name being validated.
     * @param mixed  $value The field value being validated.
     *
     * @return bool
     */
    public function passes(string $field, mixed $value): bool;

    /**
     * Get the validation error message.
     *
     * May contain the `:field` placeholder, which is replaced with the field name.
     *
     * @return string
     */
    public function message(): string;
}
