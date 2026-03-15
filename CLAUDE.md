# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

When creating a new module or `CLAUDE.md` anywhere in this repository:

**CLAUDE.md structure:**
- Start with the full content of `CODING_GUIDELINES.md`, verbatim
- Then add `---` followed by `# Package: ez-php/<name>` (or `# Directory: <name>`)
- Module-specific section must cover:
  - Source structure (file tree with one-line descriptions per file)
  - Key classes and their responsibilities
  - Design decisions and constraints
  - Testing approach and any infrastructure requirements (e.g. needs MySQL, Redis)
  - What does **not** belong in this module

**Each module needs its own:**
`composer.json` · `phpstan.neon` · `phpunit.xml` · `.php-cs-fixer.php` · `.gitignore` · `.github/workflows/ci.yml` · `README.md` · `tests/TestCase.php`

**Docker setup:** copy `docker-compose.yml`, `docker/`, `.env.example` and `start.sh` from the repository root and adapt them for the module (service names, ports, required services). Use a unique `DB_PORT` in `.env.example` that is not used by any other package — increment by one per package starting with `3306` (root).
---

# Package: ez-php/validation

Rule-based input validation with optional database checks and i18n error messages.

---

## Source Structure

```
src/
├── Validator.php                — Rule-based validator; lazy execution; optional DB and Translator
├── ValidationException.php     — Thrown by validate(); carries field → messages error map
└── ValidationServiceProvider.php — Binds a no-op Validator placeholder to the container

tests/
├── TestCase.php                        — Base PHPUnit test case
├── ValidatorTest.php                   — Covers all rules, fails/passes/errors/validate, DB rules, translator
└── ValidationExceptionTest.php         — Covers exception construction and errors() accessor
```

---

## Key Classes and Responsibilities

### Validator (`src/Validator.php`)

Created via the static named constructor. Private `__construct` prevents direct instantiation.

```php
$v = Validator::make($data, $rules);
$v = Validator::make($data, $rules, db: $db);
$v = Validator::make($data, $rules, db: $db, translator: $translator);
```

**Rules** are passed as `array<string, string|list<string>>`. The pipe-separated string form is equivalent to an array:

```php
// equivalent:
['email' => 'required|email', 'age' => 'integer|min:18']
['email' => ['required', 'email'], 'age' => ['integer', 'min:18']]
```

**Supported rules:**

| Rule | Format | Behaviour |
|---|---|---|
| `required` | `required` | Fails if value is `null` or `''` |
| `string` | `string` | Fails if value is present and not a string |
| `integer` / `int` | `integer` | Fails if value is present and not a valid integer (uses `FILTER_VALIDATE_INT`) |
| `email` | `email` | Fails if value is present and not a valid email (uses `FILTER_VALIDATE_EMAIL`) |
| `min` | `min:n` | String: fails if `mb_strlen < n`; numeric: fails if value `< n`; skipped if absent |
| `max` | `max:n` | String: fails if `mb_strlen > n`; numeric: fails if value `> n`; skipped if absent |
| `regex` | `regex:/pattern/` | Fails if string doesn't match pattern; skipped if absent or non-string |
| `unique` | `unique:table` or `unique:table,column` | Fails if value already exists in DB; requires `Database` instance |
| `exists` | `exists:table` or `exists:table,column` | Fails if value does not exist in DB; requires `Database` instance |

**Rule parameter parsing:** `rule:param` splits on the first `:` only, so `regex:/foo:bar/` works correctly.

**Absent/empty values and type rules** — `string`, `integer`, `email`, `min`, `max`, `regex` all skip silently if the value is `null` or `''`. Only `required` fails on absence. This allows optional fields with type constraints.

**Lazy execution** — validation runs on the first call to `fails()`, `passes()`, `errors()`, or `validate()`, and is idempotent thereafter (guarded by `$ran`).

**Result methods:**

| Method | Returns | Notes |
|---|---|---|
| `fails()` | `bool` | `true` if any rule failed |
| `passes()` | `bool` | `!fails()` |
| `errors()` | `array<string, list<string>>` | Field → list of error messages |
| `validate()` | `void` | Throws `ValidationException` on failure |

**Error messages** — resolved via `Translator::get("validation.$key", $replacements)` if a `Translator` is provided. Without one, English fallback templates are used (defined inline in `fallbackMessage()`).

**DB rules (`unique`, `exists`)** — throw `RuntimeException` if called without a `Database` instance. The error is a programming mistake, not a validation failure.

---

### ValidationException (`src/ValidationException.php`)

Extends `EzPhpException`. Carries the full error map.

```php
$e->errors(); // array<string, list<string>>
$e->getMessage(); // always "Validation failed."
```

Catch this in controllers or a global exception handler to return a 422 response with the error details.

---

### ValidationServiceProvider (`src/ValidationServiceProvider.php`)

Binds `Validator::class` to a no-op placeholder (`Validator::make([], [])`). This satisfies the container if something resolves `Validator` by type. In practice, controllers create validators directly via `Validator::make()` with the real data — the container binding is rarely used.

---

## Design Decisions and Constraints

- **Private constructor, static `make()`** — A validator is meaningless without data and rules. The named constructor makes instantiation intent explicit and prevents partially constructed objects.
- **Lazy execution, idempotent run** — Rules are applied once on first result access. Calling `fails()` then `errors()` does not run rules twice. This is important because some rules (DB queries) have side effects.
- **Optional `Database` and `Translator`** — Both are `null` by default. The validator is fully functional for simple rules without either. DB rules throw `RuntimeException` (programmer error) rather than silently skipping, so misconfiguration is caught immediately.
- **`unique`/`exists` use raw SQL with backtick-quoted identifiers** — Table and column names come from the application's rule definitions, not from user input. If user-controlled values were ever used as table/column names, this would be a SQL injection risk. They must always be hardcoded in application code.
- **`min`/`max` are type-aware** — String values use `mb_strlen` (multibyte safe); numeric values compare as `float`. A value that is both a string and numeric (e.g. `"42"`) will be treated as numeric by `is_numeric()`.
- **Unknown rules are silently ignored** — The `match` in `applyRule()` has a `default => null` branch. This prevents exceptions on typos in rule names but also means misspelled rules produce no errors and no warnings. Be precise when writing rules.
- **Error messages use `:placeholder` syntax** — Consistent with the `ez-php/i18n` `Translator`. When adding new rules, define both a `validation.<key>` translation key and a fallback template in `fallbackMessage()`.
- **`ValidationException` extends `EzPhpException`** — This ties the package to `ez-php/framework`. If standalone use is needed in the future, this dependency should be reconsidered.

---

## Testing Approach

- **No external infrastructure for most rules** — `required`, `string`, `integer`, `email`, `min`, `max`, `regex` are fully testable in-process with no DB or translator.
- **DB rules require a live database** — `unique` and `exists` tests must use a real `Database` instance (via `DatabaseTestCase` from `ez-php/framework` tests, or a test-specific SQLite database). Do not mock the database for these rules.
- **Translator tests** — Pass an inline anonymous-class `Translator` or a real `Translator` pointing at a `sys_get_temp_dir()` lang directory. Assert that error messages reflect the translated strings.
- **Test absent-value skip behaviour** — Confirm that type rules (`string`, `email`, etc.) produce no errors when the field is absent or empty, and that `required` does.
- **Test `validate()` throws** — Assert `ValidationException` is thrown, and that `$e->errors()` contains the expected field → messages structure.
- **`#[UsesClass]` required** — PHPUnit is configured with `beStrictAboutCoverageMetadata=true`. Declare indirectly used classes with `#[UsesClass]`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| HTTP 422 response rendering | Application exception handler or base controller |
| Form request objects (auto-validation on inject) | Application layer |
| Sanitisation / data transformation | Application layer (validate first, then transform) |
| Custom rule objects / interfaces | Future extension; currently rules are handled inline |
| Nested array / wildcard validation (`items.*.name`) | Out of scope — add only when clearly needed |
| File upload validation (size, mime type) | Application layer |
| Cross-field rules (e.g. `confirmed`) | Out of scope for now |
