<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Validation\Validator;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Column resolution of the database-backed `unique` / `exists` rules.
 *
 * Regression: for wildcard fields (`items.*.email`) the fallback column was the
 * expanded field name (`items.0.email`), which failed the identifier check and
 * threw instead of validating.
 *
 * @package Tests
 */
#[CoversClass(Validator::class)]
final class DatabaseRuleColumnTest extends TestCase
{
    public function testPlainFieldUsesTheFieldNameAsColumn(): void
    {
        $db = $this->recordingDatabase(0);

        $this->assertFalse(Validator::make(['email' => 'a@example.com'], ['email' => 'unique:users'], $db)->fails());
        $this->assertSame(['SELECT COUNT(*) AS cnt FROM `users` WHERE `email` = ?'], $db->queries);
    }

    public function testWildcardFieldFallsBackToTheLastPathSegment(): void
    {
        $db = $this->recordingDatabase(0);

        $data = ['items' => [['email' => 'a@example.com'], ['email' => 'b@example.com']]];
        $validator = Validator::make($data, ['items.*.email' => 'unique:users'], $db);

        $this->assertFalse($validator->fails());
        $this->assertSame(array_fill(0, 2, 'SELECT COUNT(*) AS cnt FROM `users` WHERE `email` = ?'), $db->queries);
    }

    public function testWildcardFieldWithExistsFailsWhenRowIsMissing(): void
    {
        $db = $this->recordingDatabase(0);

        $validator = Validator::make(['ids' => [['user_id' => 7]]], ['ids.*.user_id' => 'exists:users,id'], $db);

        $this->assertTrue($validator->fails());
        $this->assertSame(['SELECT COUNT(*) AS cnt FROM `users` WHERE `id` = ?'], $db->queries);
    }

    /**
     * @return DatabaseInterface&object{queries: list<string>}
     */
    private function recordingDatabase(int $count): DatabaseInterface
    {
        return new class ($count) implements DatabaseInterface {
            /** @var list<string> */
            public array $queries = [];

            public function __construct(private readonly int $count)
            {
            }

            public function query(string $sql, array $bindings = []): array
            {
                $this->queries[] = $sql;

                return [['cnt' => $this->count]];
            }

            public function execute(string $sql, array $bindings = []): int
            {
                return 0;
            }

            public function transaction(callable $fn): mixed
            {
                return $fn();
            }

            public function getPdo(): PDO
            {
                return new PDO('sqlite::memory:');
            }
        };
    }
}
