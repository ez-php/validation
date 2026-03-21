<?php

declare(strict_types=1);

namespace EzPhp\Validation;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\TranslatorInterface;
use RuntimeException;

/**
 * Class Validator
 *
 * Validates an array of data against a set of rules.
 *
 * Usage:
 *   $v = Validator::make($data, ['email' => 'required|email', 'age' => 'integer|min:18']);
 *   if ($v->fails()) { ... $v->errors() ... }
 *   // or:
 *   $v->validate(); // throws ValidationException on failure
 *
 * Supported rules:
 *   required, string, integer, email, min:n, max:n, regex:/pattern/,
 *   unique:table,column, exists:table,column
 *
 * Custom rules can be passed as RuleInterface instances in the rules array:
 *   $v = Validator::make($data, ['field' => ['required', new MyCustomRule()]]);
 *
 * Pass a Translator instance to receive localised error messages.
 * Without a Translator, English messages are used as fallback.
 *
 * @package EzPhp\Validation
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    private bool $ran = false;

    /**
     * @param array<string, mixed>                               $data
     * @param array<string, string|list<string|RuleInterface>>   $rules
     */
    private function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly ?DatabaseInterface $db,
        private readonly ?TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed>                               $data
     * @param array<string, string|list<string|RuleInterface>>   $rules
     */
    public static function make(
        array $data,
        array $rules,
        ?DatabaseInterface $db = null,
        ?TranslatorInterface $translator = null,
    ): self {
        return new self($data, $rules, $db, $translator);
    }

    /**
     * Run validation and throw ValidationException if it fails.
     *
     * @throws ValidationException
     */
    public function validate(): void
    {
        $this->run();

        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }
    }

    /**
     * @return bool
     */
    public function fails(): bool
    {
        $this->run();

        return $this->errors !== [];
    }

    /**
     * @return bool
     */
    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        $this->run();

        return $this->errors;
    }

    /**
     * @return void
     */
    private function run(): void
    {
        if ($this->ran) {
            return;
        }

        $this->ran = true;

        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                if ($rule instanceof RuleInterface) {
                    $this->applyCustomRule($field, $value, $rule);
                } else {
                    $this->applyRule($field, $value, $rule);
                }
            }
        }
    }

    /**
     * @param string $field
     * @param mixed  $value
     * @param string $rule
     *
     * @return void
     */
    private function applyRule(string $field, mixed $value, string $rule): void
    {
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        $param = $parts[1] ?? null;

        match ($name) {
            'required' => $this->checkRequired($field, $value),
            'string' => $this->checkString($field, $value),
            'integer', 'int' => $this->checkInteger($field, $value),
            'email' => $this->checkEmail($field, $value),
            'min' => $this->checkMin($field, $value, (int) ($param ?? 0)),
            'max' => $this->checkMax($field, $value, (int) ($param ?? PHP_INT_MAX)),
            'regex' => $this->checkRegex($field, $value, $param ?? ''),
            'unique' => $this->checkUnique($field, $value, $param ?? ''),
            'exists' => $this->checkExists($field, $value, $param ?? ''),
            default => throw new RuntimeException("Unknown validation rule '$name' on field '$field'."),
        };
    }

    /**
     * Apply a custom rule object. Calls RuleInterface::passes() and records the error on failure.
     *
     * @param string        $field
     * @param mixed         $value
     * @param RuleInterface $rule
     *
     * @return void
     */
    private function applyCustomRule(string $field, mixed $value, RuleInterface $rule): void
    {
        if (!$rule->passes($field, $value)) {
            $message = str_replace(':field', $field, $rule->message());
            $this->addError($field, $message);
        }
    }

    /**
     * @param string $field
     * @param string $message
     *
     * @return void
     */
    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    /**
     * @param array<string, string|int|float> $replacements
     */
    private function translate(string $key, array $replacements): string
    {
        if ($this->translator !== null) {
            return $this->translator->get("validation.$key", $replacements);
        }

        return $this->fallbackMessage($key, $replacements);
    }

    /**
     * English fallback messages used when no Translator is provided.
     *
     * @param array<string, string|int|float> $replacements
     */
    private function fallbackMessage(string $key, array $replacements): string
    {
        $templates = [
            'required' => 'The :field field is required.',
            'string' => 'The :field field must be a string.',
            'integer' => 'The :field field must be an integer.',
            'email' => 'The :field field must be a valid email address.',
            'regex' => 'The :field field format is invalid.',
            'unique' => 'The :field has already been taken.',
            'exists' => 'The selected :field is invalid.',
            'min.string' => 'The :field field must be at least :min characters.',
            'min.numeric' => 'The :field field must be at least :min.',
            'max.string' => 'The :field field must not exceed :max characters.',
            'max.numeric' => 'The :field field must not exceed :max.',
        ];

        $template = $templates[$key] ?? $key;

        foreach ($replacements as $placeholder => $value) {
            $template = str_replace(":$placeholder", (string) $value, $template);
        }

        return $template;
    }

    /**
     * @param string $field
     * @param mixed  $value
     *
     * @return void
     */
    private function checkRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->addError($field, $this->translate('required', ['field' => $field]));
        }
    }

    /**
     * @param string $field
     * @param mixed  $value
     *
     * @return void
     */
    private function checkString(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !is_string($value)) {
            $this->addError($field, $this->translate('string', ['field' => $field]));
        }
    }

    /**
     * @param string $field
     * @param mixed  $value
     *
     * @return void
     */
    private function checkInteger(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, $this->translate('integer', ['field' => $field]));
        }
    }

    /**
     * @param string $field
     * @param mixed  $value
     *
     * @return void
     */
    private function checkEmail(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, $this->translate('email', ['field' => $field]));
        }
    }

    /**
     * @param string $field
     * @param mixed  $value
     * @param int    $min
     *
     * @return void
     */
    private function checkMin(string $field, mixed $value, int $min): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_string($value) && mb_strlen($value) < $min) {
            $this->addError($field, $this->translate('min.string', ['field' => $field, 'min' => $min]));
        } elseif (is_numeric($value) && (float) $value < (float) $min) {
            $this->addError($field, $this->translate('min.numeric', ['field' => $field, 'min' => $min]));
        }
    }

    /**
     * @param string $field
     * @param mixed  $value
     * @param int    $max
     *
     * @return void
     */
    private function checkMax(string $field, mixed $value, int $max): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (is_string($value) && mb_strlen($value) > $max) {
            $this->addError($field, $this->translate('max.string', ['field' => $field, 'max' => $max]));
        } elseif (is_numeric($value) && (float) $value > (float) $max) {
            $this->addError($field, $this->translate('max.numeric', ['field' => $field, 'max' => $max]));
        }
    }

    /**
     * @param string $field
     * @param mixed  $value
     * @param string $pattern
     *
     * @return void
     */
    private function checkRegex(string $field, mixed $value, string $pattern): void
    {
        if ($value === null || $value === '' || !is_string($value)) {
            return;
        }

        if (@preg_match($pattern, $value) !== 1) {
            $this->addError($field, $this->translate('regex', ['field' => $field]));
        }
    }

    /**
     * Rule format: unique:table  or  unique:table,column
     * If column is omitted, the field name is used as the column.
     */
    private function checkUnique(string $field, mixed $value, string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->db === null) {
            throw new RuntimeException("A database instance is required for the 'unique' rule on field '$field'.");
        }

        [$table, $column] = $this->parseTableColumn($param, $field);

        $rows = $this->db->query("SELECT COUNT(*) AS cnt FROM `$table` WHERE `$column` = ?", [$value]);

        $cnt = $rows[0]['cnt'] ?? 0;
        if (is_numeric($cnt) && (int) $cnt > 0) {
            $this->addError($field, $this->translate('unique', ['field' => $field]));
        }
    }

    /**
     * Rule format: exists:table  or  exists:table,column
     * If column is omitted, the field name is used as the column.
     */
    private function checkExists(string $field, mixed $value, string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($this->db === null) {
            throw new RuntimeException("A database instance is required for the 'exists' rule on field '$field'.");
        }

        [$table, $column] = $this->parseTableColumn($param, $field);

        $rows = $this->db->query("SELECT COUNT(*) AS cnt FROM `$table` WHERE `$column` = ?", [$value]);

        $cnt = $rows[0]['cnt'] ?? 0;
        if (!is_numeric($cnt) || (int) $cnt === 0) {
            $this->addError($field, $this->translate('exists', ['field' => $field]));
        }
    }

    /**
     * @return array{string, string}
     */
    private function parseTableColumn(string $param, string $fallbackColumn): array
    {
        $parts = explode(',', $param, 2);
        $table = $parts[0];
        $column = $parts[1] ?? $fallbackColumn;

        return [$table, $column];
    }
}
