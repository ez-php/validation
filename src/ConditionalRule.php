<?php

declare(strict_types=1);

namespace EzPhp\Validation;

use Closure;

/**
 * Class ConditionalRule
 *
 * Wraps a set of rules that are only applied when a condition evaluates to true.
 * Create instances via the Rule::when() factory — do not instantiate directly.
 *
 * @package EzPhp\Validation
 */
final class ConditionalRule
{
    /** @var list<string|RuleInterface> */
    private readonly array $rules;

    /**
     * @param bool|Closure(): bool       $condition
     * @param list<string|RuleInterface> $rules
     */
    public function __construct(
        private readonly bool|Closure $condition,
        array $rules,
    ) {
        $this->rules = $rules;
    }

    /**
     * Evaluate the condition. Calls the closure if needed.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        if (is_bool($this->condition)) {
            return $this->condition;
        }

        return ($this->condition)();
    }

    /**
     * @return list<string|RuleInterface>
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
