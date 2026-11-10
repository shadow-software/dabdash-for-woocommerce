# Order sync (Woo → DabDash)

Status: **implemented in plugin 1.2.1** (2026-11-10). Requires platform
`order_upsert` on the tenant REST/MCP surface (DabDash deploy).

## Goal

Keep WooCommerce revenue orders flowing into DabDash after the one-shot
`wordpress_commerce_import` entity=`orders` backfill, so Smart Lists and campaign
conversion attribution work while WP remains checkout.

## Payload contract

Same row shape as `WordpressOrderImportService` / `wordpress_commerce_import`:

- Idempotent key: `order_number = "WP-" . {wc_order_id}`
- Status map: WC `processing` → DabDash `preparing`; else `completed`
- Customer link: email match
- Line items: name/sku/qty/totals; unresolved catalog SKUs still create order rows
- Payment: `payment_method_title` / `payment_method`; `payment_status=paid`
- Notes: prefix `Imported from WooCommerce #{id}; original status=…`
- No product catalog sync

## Plugin shape

| Piece | Role |
|---|---|
| `Sync\OrderMapper` | `WC_Order` → import row array |
| `Sync\OrderPusher` | POST `/api/v1/tools/order_upsert` (raw HTTP until SDK regenerates) |
| `Sync\Hooks` | checkout processed, status changed, order updated → enqueue |
| `Sync\Queue` | AS hook `dabdash_woo_push_order` (+ immediate fallback) |

## Platform

- Tenant MCP/REST tool: `order_upsert` (`OrderUpsertTool`)
- Shared service: `App\Services\Wordpress\WordpressOrderImportService`
- Regenerating `openapi/tenant.json` + `shadow-software/dabdash-php-sdk` adds
  `OrdersApi::orderUpsert`; plugin may then switch off raw HTTP.

## PHP / release (WDK)

- Host `.io` is **PHP 8.3.x**. Plugin **1.2.1** requires **≥8.3** (relaxed from 8.5).
- Deploy 1.2.1 ZIP after DabDash production has `order_upsert`.

## Smoke (after deploy)

1. Backup DB + plugins on `.io`
2. Place test WC order → DabDash `WP-{id}` appears via `order_dashboard`
3. Status change processing→completed updates DabDash status
4. AS `dabdash_*` clean; customer pull still healthy
5. Smart List `purchase_count` preview includes the test buyer
