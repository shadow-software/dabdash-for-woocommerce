# WordPress.org submission — DabDash Sync for WordPress

Slug (intended): `dabdash-sync-for-wordpress`

## Ready

- [x] Plugin header + `readme.txt` Stable tag `1.0.0`
- [x] GPL-2.0-or-later LICENSE
- [x] External services section (DabDash tenant API)
- [x] Privacy model documented (never-fields, consent ratchet)
- [x] `.wordpress-org/` icons + banners + screenshot-1
- [x] Runtime SDK via Packagist (`shadow-software/dabdash-php-sdk` ^0.2)
- [x] `vendor/` shipped in release ZIPs (`composer install --no-dev`)
- [x] GitHub Release + WordPress.org deploy workflows
- [x] GitHub README matches Shadow Software plugin family (OG banner, About, Also by)

## Still needed before / during review

1. **SVN secrets** on the GitHub repo: `SVN_USERNAME` / `SVN_PASSWORD`
2. **Live screenshots** of Settings → DabDash Sync (replace banner placeholder if needed)
3. **Reviewer sandbox** — a throwaway DabDash tenant + token for Plugin Review
4. Confirm DabDash public URLs: terms, privacy, and tenant API docs
5. Submit via [WordPress.org Plugin Developer](https://wordpress.org/plugins/developers/add/) once the ZIP is tagged

## Installable ZIP

```bash
git tag 1.0.0 && git push origin 1.0.0
```

`release.yml` builds `dabdash-sync-for-wordpress.1.0.0.zip` with `vendor/`.
`deploy.yml` pushes to SVN when credentials exist.
