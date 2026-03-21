<?php

declare(strict_types=1);

namespace EzPhp\Validation;

use EzPhp\Contracts\EzPhpException;

/**
 * Class AuthorizationException
 *
 * Thrown by FormRequest when authorize() returns false.
 * Indicates the current user is not permitted to make this request.
 *
 * @package EzPhp\Validation
 */
final class AuthorizationException extends EzPhpException
{
    /**
     * @param string $message
     */
    public function __construct(string $message = 'This action is not authorized.')
    {
        parent::__construct($message);
    }
}
