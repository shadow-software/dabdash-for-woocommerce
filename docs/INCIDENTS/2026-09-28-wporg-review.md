# WordPress.org review incident — DabDash 1.1.5

Date recorded: 2026-09-28
Review ID: `RMT dabdash-for-woocommerce/shadowsoftware/22Sep26/T2 26Sep26/4.2`
Trigger: manual WordPress.org review of `dabdash-for-woocommerce.1.1.5-sandbox.zip`

## Findings

WordPress.org reported a dead DabDash Terms URL, OpenAPI generator metadata in
the vendor archive, and remote customer data changing WordPress user email or
display name through the scheduled pull.

## Corrective actions

1. Changed the public legal links to `/terms-of-service` and `/privacy-policy`.
2. Removed `.openapi-generator` directories during release staging.
3. Made contact fields proposal-only during pulls. Local contact edits may still
   be proposed to DabDash; remote data cannot update WordPress user identity.
4. Bumped the corrected package to 1.1.6 and added regression assertions.

## Evidence

- Public legal URLs returned HTTP 200 after redirect resolution.
- Local gate passed: 27 PHPUnit tests / 66 assertions, PHPCS, PHPStan, PHP
  syntax checks, ZIP integrity, and forbidden-file scan.
- Corrected archive root is exactly `dabdash-for-woocommerce/`.

## Preventive control

Every future WordPress.org package must be built from a clean `--no-dev`
Composer install, passed through `.github/prune-vendor-dev.sh`, checked for
the exact slug root, and scanned for generator metadata before upload.

## Follow-up packaging incident

The first 1.1.6 replacement archive was staged manually without applying
`.distignore`, which leaked `.github/scripts/` and caused WordPress.org to
return `Error in file upload` listing those scripts. The replacement archive
was rebuilt with the repository release sequence (`composer install --no-dev`,
`rsync --exclude-from=.distignore`, vendor pruning, exact slug root) and its
contents were checked before upload.
