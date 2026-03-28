<?php

declare(strict_types=1);

/**
 * Performance benchmark for EzPhp\Validation\Validator.
 *
 * Measures the overhead of running a realistic set of validation rules
 * against a data payload — no database or translator involved.
 *
 * Exits with code 1 if the per-validation time exceeds the defined threshold,
 * allowing CI to detect performance regressions automatically.
 *
 * Usage:
 *   php benchmarks/validate.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EzPhp\Validation\Validator;

const ITERATIONS = 2000;
const THRESHOLD_MS = 5.0; // per-validation upper bound in milliseconds

// ── Benchmark data and rules ──────────────────────────────────────────────────

$data = [
    'name' => 'Alice Wonderland',
    'email' => 'alice@example.com',
    'age' => 28,
    'website' => 'https://example.com',
    'password' => 'Secret123!',
    'password_confirmation' => 'Secret123!',
    'bio' => str_repeat('Lorem ipsum dolor sit amet. ', 5),
    'items' => [
        ['name' => 'Widget A', 'qty' => 2],
        ['name' => 'Widget B', 'qty' => 5],
        ['name' => 'Widget C', 'qty' => 1],
    ],
];

$rules = [
    'name' => 'required|string|min:2|max:100',
    'email' => 'required|email',
    'age' => 'required|integer|min:18|max:120',
    'website' => 'string',
    'password' => 'required|string|min:8|confirmed',
    'bio' => 'string|max:500',
    'items.*.name' => 'required|string',
    'items.*.qty' => 'required|integer|min:1',
];

// Warm-up
Validator::make($data, $rules)->validate();

// ── Benchmark ─────────────────────────────────────────────────────────────────

$start = hrtime(true);

for ($i = 0; $i < ITERATIONS; $i++) {
    Validator::make($data, $rules)->validate();
}

$end = hrtime(true);

$totalMs = ($end - $start) / 1_000_000;
$perValidation = $totalMs / ITERATIONS;

echo sprintf(
    "Validator Benchmark\n" .
    "  Rules per run        : %d field rules + 3 wildcard items\n" .
    "  Iterations           : %d\n" .
    "  Total time           : %.2f ms\n" .
    "  Per validation       : %.3f ms\n" .
    "  Threshold            : %.1f ms\n",
    count($rules),
    ITERATIONS,
    $totalMs,
    $perValidation,
    THRESHOLD_MS,
);

if ($perValidation > THRESHOLD_MS) {
    echo sprintf(
        "FAIL: %.3f ms exceeds threshold of %.1f ms\n",
        $perValidation,
        THRESHOLD_MS,
    );
    exit(1);
}

echo "PASS\n";
exit(0);
