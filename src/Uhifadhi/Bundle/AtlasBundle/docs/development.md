# Development

## Contents

- [The standard](#the-standard)

## The standard

The core is one repository; `composer check` at its root is the whole verdict.

```bash
composer install
composer check   # cs:check -> phpstan (max) -> require-check -> the suite
```

No database: this bundle owns no entities, so its suite opens no connection. A map is machinery;
what it draws belongs to the modules that own the records.
