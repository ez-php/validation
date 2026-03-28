# Changelog

All notable changes to `ez-php/validation` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.2.0] — 2026-03-28

### Changed
- Updated `ez-php/contracts` dependency constraint to `^1.2`

---

## [v1.0.1] — 2026-03-25

### Changed
- Tightened all `ez-php/*` dependency constraints from `"*"` to `"^1.0"` for predictable resolution

---

## [v1.0.0] — 2026-03-24

### Added
- `Validator` — rule-based input validator; accepts an array of data and a rule map; returns `ValidationResult` with passes/failures and error messages
- Built-in rules: `required`, `string`, `integer`, `float`, `boolean`, `email`, `url`, `min`, `max`, `between`, `in`, `not_in`, `regex`, `confirmed`, `nullable`, `array`, `date`
- Database rules: `unique` and `exists` backed by `DatabaseInterface`; optional when the database module is not present
- i18n integration — rule error messages resolved via `TranslatorInterface` when available; falls back to built-in English messages
- `ValidationException` — thrown with a structured `errors()` map when validation fails; integrates with the exception handler for automatic 422 JSON responses
- `ValidationServiceProvider` — binds the validator factory with optional database and translator dependencies
