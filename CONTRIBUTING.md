# Contributing

Open an issue before non-trivial changes. Consent and verification rules are
load-bearing — a sync bug here can re-subscribe someone or forge ID state.

## Setup

```bash
composer install
composer ci
```

Runtime dependency: `shadow-software/dabdash-php-sdk` from Packagist (`^0.2`).

## Gate

| Command | |
| --- | --- |
| `composer lint` | WPCS + PHP 8.1+ |
| `composer stan` | PHPStan level 6 |
| `composer test` | PHPUnit (Brain Monkey) |

## Rules

- DabDash is canonical for verification and loyalty.
- Consent merges **most-restrictive**, never last-write-wins.
- Never mirror `NEVER` fields into WordPress.
- New dealer/tenant API calls go through `DabDashSync\Api\SdkFactory`.
