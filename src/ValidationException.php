<?php

declare(strict_types=1);

namespace EzPhp\Validation;

use EzPhp\Contracts\EzPhpException;

/**
 * Class ValidationException
 *
 * @package EzPhp\Validation
 */
final class ValidationException extends EzPhpException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validation failed.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
