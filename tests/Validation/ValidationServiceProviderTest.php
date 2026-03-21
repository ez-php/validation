<?php

declare(strict_types=1);

namespace Tests\Validation;

use EzPhp\Application\Application;
use EzPhp\Validation\ValidationServiceProvider;
use EzPhp\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\ApplicationTestCase;

/**
 * Class ValidationServiceProviderTest
 *
 * @package Tests\Validation
 */
#[CoversClass(ValidationServiceProvider::class)]
#[UsesClass(Validator::class)]
final class ValidationServiceProviderTest extends ApplicationTestCase
{
    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(ValidationServiceProvider::class);
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function test_validator_is_bound_in_container(): void
    {
        $this->assertInstanceOf(Validator::class, $this->app()->make(Validator::class));
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function test_validator_resolves_as_singleton(): void
    {
        $v1 = $this->app()->make(Validator::class);
        $v2 = $this->app()->make(Validator::class);

        $this->assertSame($v1, $v2);
    }
}
