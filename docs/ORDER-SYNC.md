# Order sync (Woo → DabDash) — design

Status: **spec** (2026-11-10). Implementation blocked on a tenant API order-upsert
endpoint; PHP SDK today only exposes `OrdersApi::orderDashboard` (read).

## Goal

Keep WooCommerce revenue orders flowing into DabDash after the one-shot
`wordpress_commerce_import` entity=`orders` backfill, so Smart Lists and campaign
conversion attribution work while WP remains checkout.

## Payload contract (must match MCP importer)

Reuse the row shape already accepted by
`App\Mcp\Tools\WordpressCommerceImportTool::importOrders`:

- Idempotent key: `order_number = "WP-" . {wc_order_id}`
- Status map: WC `processing` → DabDash `preparing`; else `completed`
- Customer link: email match (plugin also has `_dabdash_woo_customer_id`)
- Line items: name/sku/qty/totals; unresolved catalog SKUs still create order rows
- Payment: `payment_method_title` / `payment_method`; `payment_status=paid`
- Notes: prefix `Imported from WooCommerce #{id}; original status=…`
- No product catalog sync

## Plugin shape (mirror customer sync)

| Piece | Role |
|---|---|
| `Sync\OrderMapper` | WC_Order → import row array |
| `Sync\OrderPusher` | POST upsert to DabDash (once API exists) |
| `Sync\Hooks` | `woocommerce_checkout_order_processed`, status transitions, admin save |
| `Sync\Queue` | Optional AS hook `dabdash_woo_push_order` + incremental backfill |
| Admin log | Structured option/log rows (not only `error_log`) |

## Platform prerequisite (7.2)

Extract importer core into a shared service (e.g. `WordpressOrderImportService`)
and expose a tenant MCP/REST tool such as `order_upsert` / `orders_import` with:

- `rows: OrderRow[]` (≤100)
- `dry_run` / `confirm`
- Same created/updated/skipped/unresolved receipt as MCP import

Regenerate `shadow-software/dabdash-php-sdk`, then wire `SdkFactory::orders()`.

## PHP / release (WDK)

- Host `.io` is **PHP 8.3.36**; `composer.json` on master requires **≥8.5**.
- Do **not** deploy current master ZIPs to WDK until Site Tools PHP bump **or**
  an 8.3-capable release channel (1.2.x with platform_check relaxed / dual build).
- Stay on installed **1.1.0** customers-only sync until order upsert ships.

## Smoke (after deploy)

1. Backup DB + plugins on `.io`
2. Place test WC order → DabDash `WP-{id}` appears
3. Status change processing→completed updates DabDash status
4. AS `dabdash_*` clean; customer pull still healthy
5. Smart List `purchase_count` preview includes the test buyer
