# Changelog

## 2.0.3 — pre-release

- Include the reserved PrestaShop order reference and, when available, the checkout customer name in incoming-payment request metadata. Existing subscriber first/last name and email fields remain populated.
- Add regression coverage for the serialized request in sandbox/production, optional/Unicode names, repeat initiation and callbacks from a different named payer. Names remain reconciliation context, never payment-authentication evidence.
- Document that Kopo Kopo controls the transaction “Name” display; this release does not promise to populate it or rewrite earlier transactions.
- Record the first merchant-reported live KES 10 payment, paid shop order, received provider transaction and reported customer/invoice behavior on 30 October 2026. Keep unconfirmed environment, receipt matching and remaining acceptance checks explicit.
- Ship current repository/help links in the merchant package and a native upgrade step without schema changes or credential resets.

## 2.0.2 — pre-release

- Fix false SSL installation failures and checkout unavailability on PrestaShop 9. Check its native `PS_SSL_ENABLED` setting without requiring the removed `PS_SSL_ENABLED_EVERYWHERE` key. SSL remains mandatory.
- Correct SSL setup instructions and document recovery for earlier packages; no database workaround or schema migration is required.
- Add fast and native-platform regression coverage for a missing/stale legacy key and disabled SSL. Stop introducing the removed key in the disposable integration fixture.

## 2.0.1 — pre-release

- Name the module **SIMIPAY (M-PESA)** in the admin, with Samuel Simiyu identified as developer and maintainer.
- Add original SVG/PNG artwork, portfolio attribution in the M-PESA customer flow, and scoped responsive branding assets.
- Package AUTHORS.md and NOTICE alongside the license and merchant instructions; add maintainer and support metadata.
- Add a native 2.0.0 → 2.0.1 upgrade for branding hooks without resetting credentials or financial history. The technical `kopokopo` identifier remains stable.
- Clarify that sandbox payments do not send a phone PIN prompt or move real money. Record real sandbox success/failure and native paid-order/receipt verification.
- Publish merchant ZIP and SHA-256 assets through the version-tag release workflow after the PHP matrix passes.

## 2.0.0 — candidate, not yet published

### Breaking requirements

- Target PrestaShop 9.2.x, PHP 8.2–8.5, single-store mode, HTTPS and KES.
- Re-enter environment-specific credentials after upgrading from 1.0.0. Resolve legacy requests before upgrading.

### Added and changed

- Kopo Kopo API v2 through a self-contained cURL adapter; no merchant Composer installation.
- Encrypted sandbox/production secrets, automatic token caching and an admin connection test.
- Persistent payment attempts, exact financial validation, signature verification, guarded order completion and receipt recording.
- Customer status polling, admin payment visibility, scheduled reconciliation and lost-response recovery.
- Installation/upgrade lifecycle, retained financial data, ZIP packaging, CI and contribution/install documentation.
- Validate masked admin CSRF tokens through Symfony rather than string equality; cover valid and invalid token cases.

### Removed

- Unused bundled SDK/Guzzle dependencies, tracked callback logs, missing checkout interceptor, session-based callbacks and the empty-cart/15-second payment-status guess.

## 1.0.0 — legacy (2024)

Initial community M-PESA module. Superseded by the version 2 redesign; no new compatibility claims are made for the legacy code.
