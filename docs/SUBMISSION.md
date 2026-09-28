> Workspace first-pass gate (read before upload):
> `/home/shadow/Source/wordpress/docs/wporg/FIRST-PASS-CHECKLIST.md`

# WordPress.org submission — DabDash Sync for WooCommerce

Slug: **`dabdash-for-woocommerce`**

## Ready

- [x] Plugin header + `readme.txt` Stable tag `1.0.1`
- [x] Requires WooCommerce (`Requires Plugins: woocommerce`)
- [x] GPL-2.0-or-later LICENSE
- [x] External services section (DabDash tenant API + field list)
- [x] Privacy model (never-fields, consent ratchet, uninstall meta cleanup)
- [x] `.wordpress-org/` icons + banners + screenshot-1
- [x] Runtime SDK via Packagist (`shadow-software/dabdash-php-sdk` ^0.2)
- [x] `vendor/` + `composer.json` shipped in release ZIPs
- [x] No custom plugin updater
- [x] Strict Plugin Check CI job
- [x] Slug/name free of the restricted term “wordpress”

## Current review state

- **Corrected version:** 1.1.6
- **Review finding:** RMT `dabdash-for-woocommerce/shadowsoftware/22Sep26/T2` (received 2026-09-26)
- **Corrected package:** built locally with the exact slug root `dabdash-for-woocommerce/`; upload remains a WordPress.org web-form action.

## Resolved review findings

- [x] DabDash Terms URL corrected to `/terms-of-service`.
- [x] DabDash Privacy URL corrected to `/privacy-policy`.
- [x] OpenAPI generator metadata removed from the runtime vendor distribution.
- [x] Remote pulls can no longer change WordPress user display names or email addresses; local contact edits remain proposal-only outbound sync.
- [x] PHPUnit, PHPCS, PHPStan, syntax checks, and clean runtime package verification passed locally.
- [x] Full incident record: `docs/INCIDENTS/2026-09-28-wporg-review.md`.

## Still needed before / during review

1. **SVN secrets** — `SVN_USERNAME` / `SVN_PASSWORD` on the GitHub repo
2. **Live Settings screenshot** (replace banner placeholder if still present)
3. **Reviewer sandbox** — `000-creds/.env.plugin-sandboxes` (`shadow-sandbox` tenant + token)
4. Confirm DabDash public URLs: terms, privacy

## Installable ZIP

```bash
git tag 1.0.0 && git push origin 1.0.0
```
