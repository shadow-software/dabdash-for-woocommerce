# One-shot strain / SEO enrich

Admin screen: **Settings → DabDash Strain Enrich**.

This is **not** the hourly customer sync (`dabdash_woo_pull`). It is a
manual, one-shot job:

1. Load published/private WooCommerce products.
2. Call tenant Catalog API `strain_lookup` (Packagist
   `CatalogApi::strainLookup`) — the same platform strain library vendors use
   on product create.
3. Match with estate prior art:
   - product title, then size-stripped base name (`Blue Dream - 3.5G` →
     `Blue Dream`)
   - case-insensitive **exact** name
   - else shortest **partial** prefix/contains hit
4. Write readable **long description** + **excerpt** from type, cannabinoids,
   and `short_description` (no keyword stuffing).
5. Stamp product meta: `_dabdash_woo_strain_id`, `_dabdash_woo_strain_name`,
   `_dabdash_woo_strain_confidence`, `_dabdash_woo_strain_enriched_at`.

## Dry-run

Default mode is **dry-run** — preview matches and generated copy with no
writes. Switch to **Apply** and confirm before overwriting descriptions.

## PHP / WDK note

Current `master` plugin header and `composer.json` require **PHP 8.5** (SDK
6.x line). Weed Delivery Kelowna’s live host still runs **PHP 8.3** with plugin
**1.1.0**. To run this job on WDK: raise the host to PHP 8.5 and upgrade the
plugin, **or** ship an 8.3-capable build (see client `PLAN.md` Phase 3/5).

## Run on a site

1. **Settings → DabDash Sync** — API base + token saved.
2. **Settings → DabDash Strain Enrich** — Dry-run first (optionally limit N).
3. Review matched / skipped / errors.
4. Apply with confirmation; use **exact-only** if partial matches look noisy.
