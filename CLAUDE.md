# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

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
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
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
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are one carve-out, since they exist specifically to be extended. A documented template-method-style base class (e.g. `Mailable`, meant to be configured via constructor-time subclassing) is the other — the owning module's `CLAUDE.md` must record it under Design Decisions.

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

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` unless `--namespace=` overrides it (`bignum` → `BigNum`,
`opcache` → `OPCache`, and `dotenv` → `Env` are existing exceptions the guess
gets wrong).

To bring in a module whose code already lives in its own repository instead of
generating a fresh skeleton, pass `--repo=` with a git URL:

```
php make_module.php <name> --repo=<git-url> [--namespace=Foo]
```

This runs `git submodule add <url> modules/<name>` instead of writing package
files, then applies the same monorepo wiring below. It is mutually exclusive
with `--services` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4`), `phpstan.neon`, `phpunit.xml`
(test suite **and** coverage source), and `packages.sh` (alphabetical position).

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — editing it marks all ~40
  `CLAUDE.md` copies as drifted at once, so the next `composer full` would fail for
  a brand-new module. The generator prints which ports to claim instead.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

Pass `--extensions` to merge PHP extension install blocks (apt packages plus `docker-php-ext-install`/`pecl` lines) directly into `docker/app/Dockerfile`, instead of hand-editing it afterward — supported extensions: `bcmath`, `gmp`, `gd`, `imagick`:

```
vendor/bin/docker-init --extensions=gmp,bcmath
vendor/bin/docker-init --extensions=gd,imagick
```

When run from a module directory inside this monorepo, any requested extension not already present is also merged into the shared root `docker/app/Dockerfile` — the container `composer full` at the root actually runs against, distinct from the module's own standalone image.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/` (application template) | 3308 | 6383 (`REDIS_PORT`) | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| `ez-php/event-store` | 3311 | — | — |
| **next free** | **3312** | **6384** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. `ez-php/cache`, `ez-php/queue`, and `ez-php/rate-limiter` map it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project and the `ez-php/` application template are the two exceptions, since both have no host/container split and use `REDIS_PORT` for both (the template's other in-container Redis settings — `CACHE_REDIS_PORT`, `QUEUE_REDIS_PORT`, `RATE_LIMITER_REDIS_PORT` — stay fixed at `6379` regardless, same as every other module).

> This table tracks only MySQL, Redis, and Meilisearch ports — the three services shared across multiple modules where a collision is otherwise easy to introduce. `ez-php/mail`'s Mailpit service is the one other module with published host ports: SMTP `1025` and web UI `8025`, mapped through `MAILPIT_SMTP_HOST_PORT`/`MAILPIT_API_HOST_PORT` in `modules/mail/docker-compose.yml` (mirroring the `*_HOST_PORT` pattern above), documented in `modules/mail/.env.example`. It isn't a table column because no other module runs Mailpit, so there is nothing to collide with — but a new module adding its own single-use service's ports should likewise parameterize them and document the defaults in its own `.env.example` rather than adding a column here.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/validation

Rule-based input validator with optional database and translator integration.

---

## Source Structure

```
src/
├── Validator.php                — Rule-based validator; lazy execution; optional DB and Translator
├── RuleInterface.php            — Interface for custom rule objects
├── ConditionalRule.php          — Value object for Rule::when(); holds condition + nested rules
├── Rule.php                     — Static factory: Rule::when($condition, $rules)
├── ValidationException.php      — Thrown by validate(); carries field → messages error map
├── AuthorizationException.php   — Thrown by FormRequest when authorize() returns false
├── FormRequest.php              — Auto-validating request value object; authorize() + rules() + validated()
└── ValidationServiceProvider.php — Binds a no-op Validator placeholder to the container

tests/
├── TestCase.php                        — Base PHPUnit test case
├── ValidatorTest.php                   — Covers all built-in rules, fails/passes/errors/validate, DB rules, translator
├── CustomRuleTest.php                  — Covers RuleInterface integration (pass, fail, placeholder, mixed rules)
├── ConditionalRuleTest.php             — Covers 'sometimes' modifier and Rule::when() (bool + closure conditions)
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

**Rules** are passed as `array<string, string|list<string|RuleInterface|ConditionalRule>>`. The pipe-separated string form is equivalent to an array of strings. Custom rule objects and conditional rules can only be used in the array form:

```php
// string form (built-in rules only):
['email' => 'required|email', 'age' => 'integer|min:18']

// array form (built-in rules, custom objects, conditional rules):
['email' => ['required', 'email'], 'code' => ['required', new Uppercase()]]
['name'  => ['sometimes', 'required', 'string']]
['age'   => ['required', Rule::when($isAdult, ['integer', 'min:18'])]]
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
| `confirmed` | `confirmed` | Fails unless `{field}_confirmation` in data equals the value |
| `same` | `same:other` | Fails unless value equals the value of `other` field |
| `different` | `different:other` | Fails if value equals the value of `other` field |
| `date` | `date` | Fails if value is not a valid date parseable by `strtotime()`; skipped if absent |
| `date_format` | `date_format:Y-m-d` | Fails if value does not match the given PHP date format exactly; skipped if absent |
| `before` | `before:date` | Fails if value is not strictly before the reference date; skipped if absent |
| `after` | `after:date` | Fails if value is not strictly after the reference date; skipped if absent |
| `file` | `file` | Fails if value is not a valid `$_FILES` upload (`UPLOAD_ERR_OK`); skipped if absent |
| `image` | `image` | Fails if upload MIME type is not `image/*`; skipped if absent |
| `mimes` | `mimes:jpg,png` | Fails if upload file extension is not in the comma-separated list; skipped if absent |
| `max_size` | `max_size:n` | Fails if upload size exceeds `n` kilobytes; skipped if absent or not a valid upload |
| `dimensions` | `dimensions:min_width=N,...` | Fails if image dimensions violate constraints (`width`, `height`, `min_width`, `max_width`, `min_height`, `max_height`); skipped if absent |
| `sometimes` | `sometimes` | Field-level modifier: skip all rules for this field if its key is absent from the data array |
| `boolean` | `boolean` | Fails unless value is `bool`, `0`/`1`, or `'0'`/`'1'`; skipped if absent |
| `not_in` | `not_in:a,b,c` | Fails if value is one of the comma-separated values; skipped if absent |
| `uuid` | `uuid` | Fails unless value matches the canonical 8-4-4-4-12 hex UUID format; skipped if absent |
| `alpha` | `alpha` | Fails unless value is letters only (`\pL`, Unicode-aware); skipped if absent |
| `alpha_num` | `alpha_num` | Fails unless value is letters and numbers only (`\pL\pN`); skipped if absent |
| `alpha_dash` | `alpha_dash` | Fails unless value is letters, numbers, dashes, and underscores only; skipped if absent |
| `distinct` | `distinct` | Fails if an array value contains duplicate elements (`SORT_REGULAR` comparison); skipped if absent or not an array |

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

**Custom rules** — any object implementing `RuleInterface` can be passed in the array form. The Validator calls `passes($field, $value)` and, on failure, calls `message()` and replaces `:field` with the field name. Custom rules always run regardless of whether the value is absent or empty — implement that guard inside `passes()` if needed.

---

### Rule (`src/Rule.php`) and ConditionalRule (`src/ConditionalRule.php`)

`Rule` is a static factory for conditional rule sets:

```php
// Bool condition:
Rule::when(true, ['required', 'string'])
Rule::when($isPremium, 'integer|max:50')

// Closure condition (evaluated during validation):
Rule::when(fn () => $user->isAdmin(), ['required', 'string'])
```

`Rule::when()` returns a `ConditionalRule` value object. When the Validator encounters one, it calls `isActive()` — if true, each nested rule is dispatched through the same logic as top-level rules (built-in strings, `RuleInterface` objects). If false, the entire nested set is skipped.

**`sometimes` vs `Rule::when()`:**

| Feature | `sometimes` | `Rule::when()` |
|---|---|---|
| Scope | Entire field — all rules skipped | Nested rules only |
| Condition | Key absent from data | Any bool or closure |
| Position | String in rules array | Object in array |
| Pipe-string form | ✓ `'sometimes\|required'` | ✗ array only |

---

### RuleInterface (`src/RuleInterface.php`)

Implement to define a custom validation rule:

```php
class Uppercase implements RuleInterface
{
    public function passes(string $field, mixed $value): bool
    {
        return is_string($value) && strtoupper($value) === $value;
    }

    public function message(): string
    {
        return 'The :field must be uppercase.';
    }
}

$v = Validator::make($data, ['code' => ['required', new Uppercase()]]);
```

The `:field` placeholder in the message is replaced with the field name before the error is recorded.

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
- **Unknown string rules throw `RuntimeException`** — The `match` in `applyRule()` has a `default => throw` branch. Misspelled built-in rule names are caught immediately at runtime.
- **`sometimes` is a field-level modifier, not a value rule** — It is consumed at the top of the field loop and never reaches `applyRule()`. It is intentionally absent from the `match` statement to keep its semantics distinct.
- **`sometimes` checks `array_key_exists`, not `isset`** — A field present with a `null` value is treated as present. Only a fully absent key triggers the skip.
- **`ConditionalRule` does not support `sometimes` internally** — Nesting `sometimes` inside `Rule::when()` has no defined semantics and will be passed to `applyRule()` where it is silently filtered (no-op). Use `sometimes` only at the field level.
- **`Rule::when()` closures are evaluated during `run()`** — Closures are not called at construction time. This makes them safe to capture runtime state.
- **Custom `RuleInterface` objects always receive the raw value** — Unlike some built-in rules, custom rules are not skipped for absent/empty values. If a rule should be optional, guard against `null`/`''` inside `passes()`.
- **Custom rule messages use `:field` replacement** — The same `:placeholder` pattern used by built-in messages. Only `:field` is substituted; custom rules cannot currently use other placeholders (e.g. `:min`). If needed, bake the values into the message string at construction time.
- **Error messages use `:placeholder` syntax** — Consistent with the `ez-php/i18n` `Translator`. When adding new rules, define both a `validation.<key>` translation key and a fallback template in `fallbackMessage()`.
- **`ValidationException` extends `EzPhpException`** — This ties the package to `ez-php/framework`. If standalone use is needed in the future, this dependency should be reconsidered.
- **`date`/`before`/`after` use `strtotime()`** — Accepts any string parseable by PHP's `strtotime()`. The reference date in `before`/`after` is also parsed via `strtotime()`, so natural-language dates like `'tomorrow'` are valid.
- **`date_format` uses strict matching** — `DateTime::createFromFormat()` is called and the result is re-formatted; both the parse and the round-trip must succeed. This rejects partial matches (e.g. `'2024-01'` against `'Y-m-d'`).
- **`dimensions` uses `getimagesize()`** — The file must be a valid image readable by GD's `getimagesize()`. Binary validation is done against the actual uploaded file content, not the MIME type reported by the browser. If `getimagesize()` returns `false`, a dimensions error is recorded.
- **File rules skip silently on absent/empty values** — `file`, `image`, `mimes`, `max_size`, `dimensions` all return early when value is `null`/`''`. Combine with `required` to enforce presence.
- **Cross-field rules (`confirmed`, `same`, `different`) read from the top-level data array** — They do not support dot-notation references to nested fields.
- **Nested field paths and wildcard expansion** — `'address.city'` resolves via dot notation; `'items.*.name'` expands to one path per array index. Both work with all rules.

---

## Testing Approach

- **No external infrastructure for most rules** — `required`, `string`, `integer`, `email`, `min`, `max`, `regex`, `confirmed`, `same`, `different`, `date`, `date_format`, `before`, `after` are fully testable in-process with no DB or translator.
- **DB rules require a live database** — `unique` and `exists` tests must use a real `Database` instance (via `DatabaseTestCase` from `ez-php/framework` tests, or a test-specific SQLite database). Do not mock the database for these rules.
- **File upload rules use fake `$_FILES` arrays** — Construct `['tmp_name' => '/path', 'name' => 'foo.jpg', 'type' => 'image/jpeg', 'size' => 1024, 'error' => UPLOAD_ERR_OK]`. For `dimensions`, the `tmp_name` must point to a real image file (e.g. created with GD in `setUp`).
- **Translator tests** — Pass an inline anonymous-class `Translator` or a real `Translator` pointing at a `sys_get_temp_dir()` lang directory. Assert that error messages reflect the translated strings.
- **Test absent-value skip behaviour** — Confirm that type rules (`string`, `email`, `date`, etc.) produce no errors when the field is absent or empty, and that `required` does.
- **Test `validate()` throws** — Assert `ValidationException` is thrown, and that `$e->errors()` contains the expected field → messages structure.
- **Custom rule tests** — Use inline anonymous classes implementing `RuleInterface`. Test pass, fail, `:field` replacement, combination with built-in rules, and multiple custom rules on the same field.
- **`sometimes` tests** — Verify the key-absent skip (including when `required` would otherwise fail), key-present normal validation, and the pipe-string form. Also verify that a key present with `null` is not skipped.
- **`Rule::when()` tests** — Cover `true`/`false` bool, closure returning `true`/`false`, pipe-string rules, array rules, nested `RuleInterface` objects, and `validate()` throws/passes accordingly.
- **`#[UsesClass]` required** — PHPUnit is configured with `beStrictAboutCoverageMetadata=true`. Declare indirectly used classes with `#[UsesClass]`. Note: `RuleInterface` is an interface and is not a valid coverage target — do not add `#[UsesClass(RuleInterface::class)]`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| HTTP 422 response rendering | Application exception handler or base controller |
| Form request objects (auto-validation on inject) | Application layer |
| Sanitisation / data transformation | Application layer (validate first, then transform) |
| Bundled built-in rule classes (e.g. `Required`, `Email`) | Rules stay inline in `Validator`; only the interface lives here |
| File upload validation beyond MIME/extension/size/dimensions | Application layer |
| Cross-field validation beyond `confirmed`/`same`/`different` | Application layer |
