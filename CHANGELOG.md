# Changelog

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

### Removed

- Unused bundled SDK/Guzzle dependencies, tracked callback logs, missing checkout interceptor, session-based callbacks and the empty-cart/15-second payment-status guess.

## 1.0.0 — legacy (2024)

Initial community M-PESA module. Superseded by the version 2 redesign; no new compatibility claims are made for the legacy code.
