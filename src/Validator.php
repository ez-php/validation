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
 *   unique:table,column, exists:table,column,
 *   confirmed, same:field, different:field,
 *   date, date_format:Y-m-d, before:date, after:date,
 *   file, image, mimes:ext1,ext2, max_size:kb, dimensions:min_width=N,...
 *
 * Conditional modifiers:
 *   sometimes — skip all rules for a field if it is absent from the data array
 *   Rule::when($condition, $rules) — apply nested rules only when condition is true
 *
 * Nested / wildcard field paths:
 *   'address.city' => ['required']      — dot-notation access into nested arrays
 *   'items.*.name' => ['required']      — wildcard expands to all indices
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
     * @param array<string, mixed>                                          $data
     * @param array<string, string|list<string|RuleInterface|ConditionalRule>> $rules
     */
    private function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly ?DatabaseInterface $db,
        private readonly ?TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed>                                          $data
     * @param array<string, string|list<string|RuleInterface|ConditionalRule>> $rules
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

        foreach ($this->rules as $fieldPattern => $ruleSet) {
            $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;

            foreach ($this->resolveFieldPaths($fieldPattern) as $field) {
                // 'sometimes': skip this field if its path is absent from data
                if (in_array('sometimes', $rules, true) && !$this->fieldExists($field)) {
                    continue;
                }

                $value = $this->getNestedValue($field);

                foreach ($rules as $rule) {
                    if ($rule instanceof ConditionalRule) {
                        $this->applyConditionalRule($field, $value, $rule);
                    } elseif ($rule instanceof RuleInterface) {
                        $this->applyCustomRule($field, $value, $rule);
                    } elseif ($rule !== 'sometimes') {
                        $this->applyRule($field, $value, $rule);
                    }
                }
            }
        }
    }

    /**
     * Resolve a field pattern to concrete field paths.
     * 'name'          → ['name']
     * 'address.city'  → ['address.city']
     * 'items.*.name'  → ['items.0.name', 'items.1.name', ...]
     *
     * @return list<string>
     */
    private function resolveFieldPaths(string $pattern): array
    {
        if (!str_contains($pattern, '*')) {
            return [$pattern];
        }

        $parts = explode('.*', $pattern, 2);
        $prefix = $parts[0];
        $suffix = $parts[1] ?? '';

        $array = $this->getNestedValue($prefix);

        if (!is_array($array)) {
            return [];
        }

        $paths = [];

        foreach (array_keys($array) as $key) {
            $expanded = $prefix . '.' . $key . $suffix;

            // Recursively expand if the suffix still contains wildcards.
            foreach ($this->resolveFieldPaths($expanded) as $resolved) {
                $paths[] = $resolved;
            }
        }

        return $paths;
    }

    /**
     * Resolve a dot-notation path to its value in $this->data.
     * 'address.city' → $this->data['address']['city']
     */
    private function getNestedValue(string $path): mixed
    {
        if (!str_contains($path, '.')) {
            return $this->data[$path] ?? null;
        }

        $current = $this->data;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Check whether a dot-notation path exists in $this->data.
     */
    private function fieldExists(string $path): bool
    {
        if (!str_contains($path, '.')) {
            return array_key_exists($path, $this->data);
        }

        $current = $this->data;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
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
            'confirmed' => $this->checkConfirmed($field, $value),
            'same' => $this->checkSame($field, $value, $param ?? ''),
            'different' => $this->checkDifferent($field, $value, $param ?? ''),
            'date' => $this->checkDate($field, $value),
            'date_format' => $this->checkDateFormat($field, $value, $param ?? ''),
            'before' => $this->checkBefore($field, $value, $param ?? ''),
            'after' => $this->checkAfter($field, $value, $param ?? ''),
            'before_or_equal' => $this->checkBeforeOrEqual($field, $value, $param ?? ''),
            'after_or_equal' => $this->checkAfterOrEqual($field, $value, $param ?? ''),
            'file' => $this->checkFile($field, $value),
            'image' => $this->checkImage($field, $value),
            'mimes' => $this->checkMimes($field, $value, $param ?? ''),
            'max_size' => $this->checkMaxSize($field, $value, (int) ($param ?? 0)),
            'dimensions' => $this->checkDimensions($field, $value, $param ?? ''),
            'in' => $this->checkIn($field, $value, $param ?? ''),
            'array' => $this->checkArray($field, $value),
            'between' => $this->checkBetween($field, $value, $param ?? ''),
            'nullable' => $this->checkNullable(),
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
     * Apply a conditional rule. Evaluates the condition and, if active, dispatches nested rules.
     *
     * @param string          $field
     * @param mixed           $value
     * @param ConditionalRule $rule
     *
     * @return void
     */
    private function applyConditionalRule(string $field, mixed $value, ConditionalRule $rule): void
    {
        if (!$rule->isActive()) {
            return;
        }

        foreach ($rule->getRules() as $nested) {
            if ($nested instanceof RuleInterface) {
                $this->applyCustomRule($field, $value, $nested);
            } else {
                $this->applyRule($field, $value, $nested);
            }
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
            'confirmed' => 'The :field confirmation does not match.',
            'same' => 'The :field and :other must match.',
            'different' => 'The :field and :other must be different.',
            'file' => 'The :field must be a valid uploaded file.',
            'image' => 'The :field must be an image.',
            'date' => 'The :field is not a valid date.',
            'date_format' => 'The :field does not match the format :format.',
            'before' => 'The :field must be a date before :date.',
            'after' => 'The :field must be a date after :date.',
            'before_or_equal' => 'The :field must be a date before or equal to :date.',
            'after_or_equal' => 'The :field must be a date after or equal to :date.',
            'mimes' => 'The :field must be a file of type: :values.',
            'max_size' => 'The :field must not exceed :max kilobytes.',
            'dimensions' => 'The :field has invalid image dimensions.',
            'min.string' => 'The :field field must be at least :min characters.',
            'min.numeric' => 'The :field field must be at least :min.',
            'max.string' => 'The :field field must not exceed :max characters.',
            'max.numeric' => 'The :field field must not exceed :max.',
            'in' => 'The :field must be one of: :values.',
            'array' => 'The :field must be an array.',
            'between.string' => 'The :field must be between :min and :max characters.',
            'between.numeric' => 'The :field must be between :min and :max.',
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
     * Uses FILTER_VALIDATE_INT for validation. Accepted: PHP int, and string representations
     * of integers (e.g. '0', '-5', '42'). Rejected: floats, alpha strings, whitespace-only strings.
     *
     * Note: string '0' passes (FILTER_VALIDATE_INT returns 0, which !== false).
     * Null and empty string are skipped — combine with 'required' to enforce presence.
     *
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
     * @param string $pattern A valid PCRE pattern including delimiters, e.g. '/^[A-Z]+$/i'.
     *
     * @throws RuntimeException When the pattern is not a valid PCRE expression.
     *
     * @return void
     */
    private function checkRegex(string $field, mixed $value, string $pattern): void
    {
        if ($value === null || $value === '' || !is_string($value)) {
            return;
        }

        $result = @preg_match($pattern, $value);

        if ($result === false) {
            throw new RuntimeException(
                "Invalid regex pattern '$pattern' for the 'regex' rule on field '$field'. "
                . 'Pattern must be a valid PCRE expression including delimiters, e.g. /^[A-Z]+$/.',
            );
        }

        if ($result === 0) {
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

        [$table, $column] = $this->parseTableColumn($param, $field);

        if ($this->db === null) {
            throw new RuntimeException("A database instance is required for the 'unique' rule on field '$field'.");
        }

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

        [$table, $column] = $this->parseTableColumn($param, $field);

        if ($this->db === null) {
            throw new RuntimeException("A database instance is required for the 'exists' rule on field '$field'.");
        }

        $rows = $this->db->query("SELECT COUNT(*) AS cnt FROM `$table` WHERE `$column` = ?", [$value]);

        $cnt = $rows[0]['cnt'] ?? 0;
        if (!is_numeric($cnt) || (int) $cnt === 0) {
            $this->addError($field, $this->translate('exists', ['field' => $field]));
        }
    }

    /**
     * confirmed: value must equal {field}_confirmation in $this->data.
     *
     * @param string $field
     * @param mixed  $value
     *
     * @return void
     */
    private function checkConfirmed(string $field, mixed $value): void
    {
        $confirmation = $this->data[$field . '_confirmation'] ?? null;

        if ($value !== $confirmation) {
            $this->addError($field, $this->translate('confirmed', ['field' => $field]));
        }
    }

    /**
     * same:other — value must equal the value of the other field.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $other Field name to compare against.
     *
     * @return void
     */
    private function checkSame(string $field, mixed $value, string $other): void
    {
        $otherValue = $this->data[$other] ?? null;

        if ($value !== $otherValue) {
            $this->addError($field, $this->translate('same', ['field' => $field, 'other' => $other]));
        }
    }

    /**
     * different:other — value must differ from the value of the other field.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $other Field name to compare against.
     *
     * @return void
     */
    private function checkDifferent(string $field, mixed $value, string $other): void
    {
        $otherValue = $this->data[$other] ?? null;

        if ($value === $otherValue) {
            $this->addError($field, $this->translate('different', ['field' => $field, 'other' => $other]));
        }
    }

    /**
     * date — value must be a valid date parseable by strtotime().
     * Skipped when value is null or empty string.
     *
     * @param string $field
     * @param mixed  $value
     *
     * @return void
     */
    private function checkDate(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value) || strtotime($value) === false) {
            $this->addError($field, $this->translate('date', ['field' => $field]));
        }
    }

    /**
     * date_format:format — value must match the given PHP date format exactly.
     * Skipped when value is null or empty string.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $format PHP date format string, e.g. 'Y-m-d', 'd/m/Y H:i'.
     *
     * @return void
     */
    private function checkDateFormat(string $field, mixed $value, string $format): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            $this->addError($field, $this->translate('date_format', ['field' => $field, 'format' => $format]));

            return;
        }

        $d = \DateTime::createFromFormat($format, $value);

        if ($d === false || $d->format($format) !== $value) {
            $this->addError($field, $this->translate('date_format', ['field' => $field, 'format' => $format]));
        }
    }

    /**
     * Resolve a date reference for before/after comparisons.
     *
     * When $ref matches a field name in the current data array the field's
     * string value is returned. Otherwise $ref is returned as-is so that
     * literal date strings (e.g. 'tomorrow', '2026-01-01') continue to work.
     *
     * @param string $ref Field name or date string.
     *
     * @return string Resolved date string.
     */
    private function resolveDate(string $ref): string
    {
        if (array_key_exists($ref, $this->data)) {
            $fieldValue = $this->data[$ref];
            return is_string($fieldValue) ? $fieldValue : $ref;
        }

        return $ref;
    }

    /**
     * before:date — value must be a date strictly before the given reference date.
     * The reference may be a field name in the current data or a date string.
     * Skipped when value is null or empty string.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $date  Field name or reference date string parseable by strtotime().
     *
     * @return void
     */
    private function checkBefore(string $field, mixed $value, string $date): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            $this->addError($field, $this->translate('before', ['field' => $field, 'date' => $date]));

            return;
        }

        $resolved = $this->resolveDate($date);
        $valueTs = strtotime($value);
        $compareTs = strtotime($resolved);

        if ($valueTs === false || $compareTs === false || $valueTs >= $compareTs) {
            $this->addError($field, $this->translate('before', ['field' => $field, 'date' => $date]));
        }
    }

    /**
     * after:date — value must be a date strictly after the given reference date.
     * The reference may be a field name in the current data or a date string.
     * Skipped when value is null or empty string.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $date  Field name or reference date string parseable by strtotime().
     *
     * @return void
     */
    private function checkAfter(string $field, mixed $value, string $date): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            $this->addError($field, $this->translate('after', ['field' => $field, 'date' => $date]));

            return;
        }

        $resolved = $this->resolveDate($date);
        $valueTs = strtotime($value);
        $compareTs = strtotime($resolved);

        if ($valueTs === false || $compareTs === false || $valueTs <= $compareTs) {
            $this->addError($field, $this->translate('after', ['field' => $field, 'date' => $date]));
        }
    }

    /**
     * before_or_equal:date — value must be a date before or equal to the reference date.
     * The reference may be a field name in the current data or a date string.
     * Skipped when value is null or empty string.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $date  Field name or reference date string parseable by strtotime().
     *
     * @return void
     */
    private function checkBeforeOrEqual(string $field, mixed $value, string $date): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            $this->addError($field, $this->translate('before_or_equal', ['field' => $field, 'date' => $date]));

            return;
        }

        $resolved = $this->resolveDate($date);
        $valueTs = strtotime($value);
        $compareTs = strtotime($resolved);

        if ($valueTs === false || $compareTs === false || $valueTs > $compareTs) {
            $this->addError($field, $this->translate('before_or_equal', ['field' => $field, 'date' => $date]));
        }
    }

    /**
     * after_or_equal:date — value must be a date after or equal to the reference date.
     * The reference may be a field name in the current data or a date string.
     * Skipped when value is null or empty string.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $date  Field name or reference date string parseable by strtotime().
     *
     * @return void
     */
    private function checkAfterOrEqual(string $field, mixed $value, string $date): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            $this->addError($field, $this->translate('after_or_equal', ['field' => $field, 'date' => $date]));

            return;
        }

        $resolved = $this->resolveDate($date);
        $valueTs = strtotime($value);
        $compareTs = strtotime($resolved);

        if ($valueTs === false || $compareTs === false || $valueTs < $compareTs) {
            $this->addError($field, $this->translate('after_or_equal', ['field' => $field, 'date' => $date]));
        }
    }

    /**
     * file — value must be a valid $_FILES-style upload (error = UPLOAD_ERR_OK).
     * Skipped when value is null or empty string (combine with 'required' to enforce presence).
     *
     * @param string $field
     * @param mixed  $value
     *
     * @return void
     */
    private function checkFile(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!$this->isValidUpload($value)) {
            $this->addError($field, $this->translate('file', ['field' => $field]));
        }
    }

    /**
     * image — value must be a valid upload with an image/* MIME type.
     *
     * @param string $field
     * @param mixed  $value
     *
     * @return void
     */
    private function checkImage(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_array($value) || !$this->isValidUpload($value)) {
            $this->addError($field, $this->translate('image', ['field' => $field]));

            return;
        }

        $type = is_string($value['type'] ?? null) ? (string) $value['type'] : '';

        if (!str_starts_with($type, 'image/')) {
            $this->addError($field, $this->translate('image', ['field' => $field]));
        }
    }

    /**
     * mimes:ext1,ext2 — upload file extension must be in the allowed list.
     * Extension is taken from the original filename in the upload array.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $param Comma-separated list of allowed extensions (e.g. 'jpg,png,pdf').
     *
     * @return void
     */
    private function checkMimes(string $field, mixed $value, string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_array($value) || !$this->isValidUpload($value)) {
            $this->addError($field, $this->translate('mimes', ['field' => $field, 'values' => $param]));

            return;
        }

        $allowed = array_map('trim', explode(',', $param));
        $name = is_string($value['name'] ?? null) ? (string) $value['name'] : '';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            $this->addError($field, $this->translate('mimes', ['field' => $field, 'values' => $param]));
        }
    }

    /**
     * max_size:n — upload file size must not exceed n kilobytes.
     * Skipped when value is null/empty or not a valid upload (use 'file' for upload validation).
     *
     * @param string $field
     * @param mixed  $value
     * @param int    $maxKb Maximum allowed size in kilobytes.
     *
     * @return void
     */
    private function checkMaxSize(string $field, mixed $value, int $maxKb): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_array($value) || !$this->isValidUpload($value)) {
            return;
        }

        $size = is_int($value['size'] ?? null) ? (int) $value['size'] : 0;

        if ($size > $maxKb * 1024) {
            $this->addError($field, $this->translate('max_size', ['field' => $field, 'max' => $maxKb]));
        }
    }

    /**
     * dimensions:key=value,... — uploaded image must satisfy all given dimension constraints.
     * Supported keys: width, height, min_width, max_width, min_height, max_height.
     * Skipped when value is null/empty or not a valid upload.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $param Comma-separated constraints, e.g. 'min_width=100,max_height=200'.
     *
     * @return void
     */
    private function checkDimensions(string $field, mixed $value, string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_array($value) || !$this->isValidUpload($value)) {
            return;
        }

        $tmpName = is_string($value['tmp_name'] ?? null) ? (string) $value['tmp_name'] : '';
        $size = @getimagesize($tmpName);

        if ($size === false) {
            $this->addError($field, $this->translate('dimensions', ['field' => $field]));

            return;
        }

        [$imgWidth, $imgHeight] = $size;

        $constraints = [];
        foreach (explode(',', $param) as $pair) {
            $kv = explode('=', $pair, 2);
            if (count($kv) === 2) {
                $constraints[trim($kv[0])] = (int) trim($kv[1]);
            }
        }

        $valid = true;

        if (isset($constraints['width']) && $imgWidth !== $constraints['width']) {
            $valid = false;
        }

        if (isset($constraints['height']) && $imgHeight !== $constraints['height']) {
            $valid = false;
        }

        if (isset($constraints['min_width']) && $imgWidth < $constraints['min_width']) {
            $valid = false;
        }

        if (isset($constraints['max_width']) && $imgWidth > $constraints['max_width']) {
            $valid = false;
        }

        if (isset($constraints['min_height']) && $imgHeight < $constraints['min_height']) {
            $valid = false;
        }

        if (isset($constraints['max_height']) && $imgHeight > $constraints['max_height']) {
            $valid = false;
        }

        if (!$valid) {
            $this->addError($field, $this->translate('dimensions', ['field' => $field]));
        }
    }

    /**
     * in:value1,value2,... — value must be one of the comma-separated allowed values.
     * Skipped when value is null or empty string.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $param Comma-separated list of allowed values (e.g. 'foo,bar,baz').
     *
     * @return void
     */
    private function checkIn(string $field, mixed $value, string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $allowed = array_map('trim', explode(',', $param));
        $stringValue = is_scalar($value) ? (string) $value : '';

        if (!in_array($stringValue, $allowed, true)) {
            $this->addError($field, $this->translate('in', ['field' => $field, 'values' => $param]));
        }
    }

    /**
     * array — value must be a PHP array.
     * Skipped when value is null or empty string.
     *
     * @param string $field
     * @param mixed  $value
     *
     * @return void
     */
    private function checkArray(string $field, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_array($value)) {
            $this->addError($field, $this->translate('array', ['field' => $field]));
        }
    }

    /**
     * between:min,max — value must be within the given range.
     * String values: mb_strlen must be between min and max (inclusive).
     * Numeric values: numeric comparison between min and max (inclusive).
     * Skipped when value is null or empty string.
     *
     * @param string $field
     * @param mixed  $value
     * @param string $param Comma-separated min and max (e.g. '3,10').
     *
     * @return void
     */
    private function checkBetween(string $field, mixed $value, string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $parts = explode(',', $param, 2);
        $min = (int) ($parts[0] ?? 0);
        $max = (int) ($parts[1] ?? PHP_INT_MAX);

        if (is_string($value) && !is_numeric($value)) {
            $len = mb_strlen($value);

            if ($len < $min || $len > $max) {
                $this->addError($field, $this->translate('between.string', ['field' => $field, 'min' => $min, 'max' => $max]));
            }
        } elseif (is_numeric($value)) {
            $floatVal = (float) $value;

            if ($floatVal < (float) $min || $floatVal > (float) $max) {
                $this->addError($field, $this->translate('between.numeric', ['field' => $field, 'min' => $min, 'max' => $max]));
            }
        }
    }

    /**
     * nullable — declares that null is an acceptable value for this field.
     * This is a no-op: all type rules already skip silently when the value is null or ''.
     * The rule exists so that rule sets can explicitly document optionality and avoid
     * a RuntimeException for the 'nullable' rule name.
     *
     * @return void
     */
    private function checkNullable(): void
    {
        // No-op: type rules already skip on null/empty string.
    }

    /**
     * Check whether a value looks like a valid $_FILES entry with no upload error.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function isValidUpload(mixed $value): bool
    {
        return is_array($value)
            && isset($value['error'], $value['tmp_name'])
            && $value['error'] === UPLOAD_ERR_OK
            && is_string($value['tmp_name'])
            && $value['tmp_name'] !== '';
    }

    /**
     * @return array{string, string}
     *
     * @throws \RuntimeException if table or column name contains characters outside [a-zA-Z0-9_]
     */
    private function parseTableColumn(string $param, string $fallbackColumn): array
    {
        $parts = explode(',', $param, 2);
        $table = $parts[0];
        $column = $parts[1] ?? $fallbackColumn;

        if (preg_match('/[^a-zA-Z0-9_]/', $table) === 1) {
            throw new \RuntimeException("Invalid table name '$table': only [a-zA-Z0-9_] are allowed.");
        }

        if (preg_match('/[^a-zA-Z0-9_]/', $column) === 1) {
            throw new \RuntimeException("Invalid column name '$column': only [a-zA-Z0-9_] are allowed.");
        }

        return [$table, $column];
    }
}
