<?php

declare(strict_types=1);

namespace EzPhp\Validation;

use Closure;

/**
 * Class Rule
 *
 * Static factory for building conditional validation rules.
 *
 * Usage:
 *   $v = Validator::make($data, [
 *       'discount' => ['required', Rule::when($isPremium, 'integer|max:50')],
 *       'phone'    => [Rule::when(fn() => $needsPhone, ['required', 'string'])],
 *   ]);
 *
 * @package EzPhp\Validation
 */
final class Rule
{
    /**
     * Apply the given rules only when the condition is true.
     *
     * @param bool|Closure(): bool                  $condition
     * @param string|list<string|RuleInterface>     $rules     Pipe-separated string or array of rule strings / objects.
     *
     * @return ConditionalRule
     */
    public static function when(bool|Closure $condition, string|array $rules): ConditionalRule
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        return new ConditionalRule($condition, $rules);
    }
}
