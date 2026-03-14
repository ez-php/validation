<?php

declare(strict_types=1);

namespace EzPhp\Validation;

use EzPhp\ServiceProvider\ServiceProvider;

/**
 * Class ValidationServiceProvider
 *
 * @package EzPhp\Validation
 */
final class ValidationServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Validator::class, fn () => Validator::make([], []));
    }
}
