# DabDash Sync — plan

## Done (1.0.0)

- Bootstrap + Lifecycle + Settings (token not autoloaded; API host allowlist)
- `Api\SdkFactory` on Packagist `shadow-software/dabdash-php-sdk` ^0.2
- Sync loop: FieldMap / Resolver / LinkMap / Applier / Puller / Pusher / Queue
- WooCommerce hooks stamp `_dabdash_local_modified` for upward contact/consent
- Uninstall removes options + `_dabdash_*` usermeta
- WP.org slug `dabdash-for-woocommerce` (no “wordpress” trademark term)
- No custom GitHub updater — updates via WordPress.org only
- Plugin Check (strict) in CI

## Next

1. Live Settings screenshots for `.wordpress-org/`
2. Reviewer sandbox tenant + token
3. Optional OAuth connect UX if DabDash adds a browser flow
