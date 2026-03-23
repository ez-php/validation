# ez-php/validation

Validation module for the [ez-php framework](https://github.com/ez-php/framework) — rule-based validator with database-backed `unique`/`exists` rules and optional i18n support.

[![CI](https://github.com/ez-php/validation/actions/workflows/ci.yml/badge.svg)](https://github.com/ez-php/validation/actions/workflows/ci.yml)

## Requirements

- PHP 8.5+
- ez-php/framework 0.*

## Installation

```bash
composer require ez-php/validation
```

Optionally install [ez-php/i18n](https://github.com/ez-php/i18n) for localised error messages:

```bash
composer require ez-php/i18n
```

## Usage

```php
use EzPhp\Validation\Validator;

$validator = Validator::make(
    data: ['email' => 'alice@example.com', 'age' => 17],
    rules: [
        'email' => 'required|email',
        'age'   => 'required|integer|min:18',
    ],
);

if ($validator->fails()) {
    $errors = $validator->errors();
    // ['age' => ['The age must be at least 18.']]
}

// Or throw on failure:
$validator->validate(); // throws ValidationException
```

## Supported rules

| Rule | Description |
|------|-------------|
| `required` | Field must be present and non-empty |
| `string` | Must be a string |
| `integer` | Must be an integer |
| `email` | Must be a valid email address |
| `min:n` | Minimum value (numeric) or length (string) |
| `max:n` | Maximum value (numeric) or length (string) |
| `regex:/pattern/` | Must match the given regex |
| `unique:table,column` | Value must not exist in the given DB column |
| `exists:table,column` | Value must exist in the given DB column |
| `confirmed` | Value must match `{field}_confirmation` in the input |
| `same:other` | Value must equal the value of `other` field |
| `different:other` | Value must differ from the value of `other` field |

## With i18n

Pass a `Translator` instance to receive messages in the configured locale:

```php
$translator = $app->make(\EzPhp\I18n\Translator::class);

$validator = Validator::make($data, $rules, translator: $translator);
```

## License

MIT — [Andreas Uretschnig](mailto:andreas.uretschnig@gmail.com)
