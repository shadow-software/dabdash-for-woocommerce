=== DabDash Sync for WooCommerce ===
Contributors: shadowsoftware
Donate link: https://shadowsoftware.com/
Tags: dabdash, woocommerce, customer-sync, loyalty, consent
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.5
Stable tag: 1.1.1
WC requires at least: 8.2
WC tested up to: 11.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keeps WooCommerce customers in step with DabDash — verification, loyalty, and marketing consent — with DabDash as the source of truth.

== Description ==

DabDash Sync connects a WooCommerce store to a
[DabDash](https://dabdash.com/) tenant so storefront-facing flags stay honest:

* ID / medical / email / phone verification timestamps
* Loyalty balance and coupon eligibility
* Marketing consent (email and SMS) with a **most-restrictive** merge — an
  unsubscribe on either side always wins

DabDash is canonical. WooCommerce may propose contact edits; it cannot forge
verification or medical state. Sensitive fields (ID images, medical record
numbers, date of birth) never land in `wp_usermeta`.

Runtime API calls use the official Packagist package
[`shadow-software/dabdash-php-sdk`](https://packagist.org/packages/shadow-software/dabdash-php-sdk).

This plugin is free and open source. It is developed and maintained by
[Shadow Software LLC](https://shadowsoftware.com/), and its full source code is
public on [GitHub](https://github.com/shadow-software/dabdash-for-woocommerce).

= Documentation and source code =

* Documentation: https://github.com/shadow-software/dabdash-for-woocommerce#readme
* Source code and releases: https://github.com/shadow-software/dabdash-for-woocommerce
* Report a bug or request a feature: https://github.com/shadow-software/dabdash-for-woocommerce/issues

= How it works =

1. Enter your DabDash tenant API base URL (a `*.dabdash.com` host) and access
   token under **Settings → DabDash Sync**.
2. Review the field-map diagnostics (canonical / contact / consent / never).
3. Enable sync. Confirm you understand customer fields will be sent to DabDash.
4. An hourly background job pulls customers, links them to WooCommerce users,
   applies DabDash-owned fields, and queues contact/consent proposals upward
   when WordPress holds a newer local edit.

= What it does not do =

It does not store ID document images, medical documents, or date of birth in
WordPress. It does not invent verification state. It does not send passwords.

== Installation ==

1. Install from the WordPress.org directory (or a GitHub Release ZIP), then
   activate. WooCommerce must be active.
2. Go to **Settings → DabDash Sync**.
3. Enter your tenant API base URL and access token.
4. Enable sync when ready.

== Frequently Asked Questions ==

= Does this send customer passwords or ID documents to WordPress? =

No. Passwords, ID images, medical documents, and date of birth are classified
**never** and are not mirrored.

= What happens if someone unsubscribes in WooCommerce? =

Consent is a ratchet. When a customer updates their account, checks out, or
their marketing meta changes, the plugin stamps a local-modified time and the
next sync proposes the opt-out upward to DabDash so both platforms converge on
the restrictive value.

= Do I need WooCommerce? =

Yes. This plugin requires WooCommerce 8.2+.

== Screenshots ==

1. Settings — connect a DabDash tenant and review field-map diagnostics.

== External services ==

This plugin connects to your **DabDash** tenant API — a host you configure under
`*.dabdash.com` or `*.dabdash.app` (local hosts only when `WP_DEBUG` is on).
Nothing is sent until an API base URL and access token are saved and **Enable
background sync** is checked.

**1. Tenant API (your DabDash host, via shadow-software/dabdash-php-sdk)**

* **What it is for:** keeping WooCommerce customers in step with DabDash
  verification, loyalty, and marketing consent. DabDash is the source of truth
  for verification and loyalty; consent merges most-restrictive.
* **When it is called:** on an Action Scheduler / WP-Cron pull (hourly by
  default) and when a tracked local profile field changes (push proposal).
* **What is sent (contact / consent proposals only):** name, email, phone;
  email / SMS marketing opt-out flags. The plugin never pushes ID documents,
  medical record numbers, dates of birth, or passwords.
* **What is received:** verification timestamps, loyalty / coupon flags,
  consent state, and the DabDash customer id linked on the WordPress user.
* Transport: HTTPS JSON via the Packagist SDK (Guzzle). Tokens are stored with
  `autoload` disabled.

**Terms and privacy**

* DabDash Terms: https://dabdash.com/terms
* DabDash Privacy Policy: https://dabdash.com/privacy
* Shadow Software Terms: https://shadowsoftware.com/terms
* Shadow Software Privacy Policy: https://shadowsoftware.com/privacy

== Changelog ==

= 1.1.1 =
* Updated bundled `shadow-software/dabdash-php-sdk` to 6.1.1.

= 1.1.0 =
* Usermeta keys use the `dabdash_woo_` prefix family (`_dabdash_woo_*`) for
  WordPress.org uniqueness; legacy `_dabdash_*` keys still read/uninstall.
* External services section expanded; Plugin Review sandbox guidance on settings.
* Host allowlist messaging includes `*.dabdash.app`.

= 1.0.1 =
* Updated bundled `shadow-software/dabdash-php-sdk` to 6.1.0.

= 1.0.0 =
* Production sync loop via Action Scheduler (or WP-Cron) using
  `shadow-software/dabdash-php-sdk` from Packagist.
* WooCommerce hooks stamp local edits for upward contact/consent proposals.
* Host allowlist for API base URLs; token stored without autoload.
* WordPress.org packaging (slug `dabdash-for-woocommerce`).

== Upgrade Notice ==

= 1.0.1 =
Usermeta keys renamed to `_dabdash_woo_*`. Existing `_dabdash_*` values are
still read until rewritten by sync.

= 1.0.0 =
First public release. Requires WooCommerce and a DabDash tenant token.
