=== DabDash Sync for WordPress ===
Contributors: shadowsoftware
Donate link: https://shadowsoftware.com/
Tags: dabdash, cannabis, customer-sync, loyalty, consent
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keeps WordPress customers in step with DabDash — verification, loyalty, and marketing consent — with DabDash as the source of truth.

== Description ==

DabDash Sync connects a WordPress (or WooCommerce) site to a
[DabDash](https://dabdash.com/) tenant so storefront-facing flags stay honest:

* ID / medical / email / phone verification timestamps
* Loyalty balance and coupon eligibility
* Marketing consent (email and SMS) with a **most-restrictive** merge — an
  unsubscribe on either side always wins

DabDash is canonical. WordPress may propose contact edits; it cannot forge
verification or medical state. Sensitive fields (ID images, medical record
numbers, date of birth) never land in `wp_usermeta`.

This plugin is free and open source. It is developed and maintained by
[Shadow Software LLC](https://shadowsoftware.com/), and its full source code is
public on [GitHub](https://github.com/shadow-software/dabdash-sync-for-wordpress).

= Documentation and source code =

* Documentation: https://github.com/shadow-software/dabdash-sync-for-wordpress#readme
* Source code and releases: https://github.com/shadow-software/dabdash-sync-for-wordpress
* Report a bug or request a feature: https://github.com/shadow-software/dabdash-sync-for-wordpress/issues

= How it works =

1. Enter your DabDash tenant API base URL and access token under
   **Settings → DabDash Sync**.
2. Review the field-map diagnostics (canonical / contact / consent / never).
3. Enable sync. An hourly background job pulls customers, links them to WordPress
   users, applies DabDash-owned fields, and queues contact/consent proposals
   upward.

= What it does not do =

It does not store ID document images, medical documents, or date of birth in
WordPress. It does not invent verification state. It does not send passwords.

== Installation ==

1. Install from a GitHub Release ZIP (or from the WordPress.org directory once
   listed), then activate the plugin.
2. Go to **Settings → DabDash Sync**.
3. Enter your tenant API base URL and access token.
4. Enable sync when ready.

== Frequently Asked Questions ==

= Does this send customer passwords or ID documents to WordPress? =

No. Passwords, ID images, medical documents, and date of birth are classified
**never** and are not mirrored.

= What happens if someone unsubscribes in WooCommerce? =

Consent is a ratchet: the opt-out survives and is proposed upward to DabDash so
the platforms converge on the restrictive value.

= Do I need WooCommerce? =

No. The plugin syncs WordPress users. WooCommerce customer accounts work because
they are WordPress users.

== Screenshots ==

1. Settings — connect a DabDash tenant and review field-map diagnostics.

== External services ==

This plugin connects to your **DabDash** tenant API (the base URL you configure).
It sends and receives only the customer fields listed in the field map, after you
save credentials and enable sync.

Terms: https://dabdash.com/terms
Privacy: https://dabdash.com/privacy

== Changelog ==

= 1.0.0 =
* Production sync loop: hourly pull via Action Scheduler (or WP-Cron), customer
  link map, FieldMap/Resolver-driven apply, and upward contact/consent proposals.
* Packagist runtime dependency on `shadow-software/dabdash-php-sdk` ^0.2.

= 0.1.0 =
* Scaffold: settings screen, field map + resolver, GitHub self-updater, Composer
  dependency on `shadow-software/dabdash-php-sdk`.

== Upgrade Notice ==

= 1.0.0 =
Enables background customer sync when credentials are saved and sync is on.

= 0.1.0 =
Initial scaffold release.
