# Contributing

Thank you for improving this community module. Bug fixes, documentation, translations, features and additional compatibility are welcome.

## Supported baseline

The maintained target for version 2 is PrestaShop 9.2.x, PHP 8.2–8.5, KES and single-store mode. Merchants meet these requirements; do not lower the minimum runtime or add client-specific hacks silently. A compatibility proposal must explicitly document its additional target and include meaningful runtime/checkout coverage.

## Development

1. Fork the repository and create a focused branch.
2. Use PHP 8.2–8.5 with cURL, JSON, OpenSSL and ZIP. There are no external runtime packages to install.
3. Copy/symlink `kopokopo/` into an isolated PrestaShop 9.2 staging shop's `modules/` directory. Use dummy data and sandbox credentials entered through the admin configuration.
4. Run `php scripts/check.php`, `php tests/run.php`, and `php tests/settings.php`.
5. Build with `php scripts/build.php` and verify with `php tests/package.php`. Never hand-build a release by zipping the repository root.
6. Follow `docs/testing.md` for affected checkout and recovery flows. Record exactly which runtime/theme/checkout/provider scenarios you tested.

Use `tests/prestashop.php` for the opt-in integration smoke test against a disposable installed shop. It creates test data and must never run against production. Its environment variables and requirements are documented in the testing guide.

## Code changes

Keep API transport, domain rules and PrestaShop integration separate. Maintain callback signature checks, request/amount/currency/till matching, idempotency and the no-blind-retry rule for STK POSTs. Protect financial records through failures and upgrades. Add regression tests for behavior that can change whether a customer is charged or an order is created.

Escape template values, use translated merchant/customer strings, and never log credentials or complete callback payloads. Do not commit `vendor/`, generated ZIPs, credentials, customer data or runtime logs. Add schema changes through explicit versioned migrations; never reset financial tables as an upgrade shortcut.

Keep commits focused and explain the user-visible outcome. A PR should describe the problem, final behavior, tests performed, and any compatibility limitation. Do not claim a provider integration passed sandbox testing without actually exercising it.

## Features and compatibility

Open a feature issue before a large redesign so the tradeoffs can be discussed. For PHP/PrestaShop compatibility, include a matrix of exact versions, installation/upgrade results and checkout tests. For a theme or third-party checkout, explain which native contract is insufficient and keep the integration optional and tested.

The maintainer can decline unsupported targets rather than making every merchant's install more complicated. The merchant ZIP must remain upload-and-configure.

## Security and conduct

Follow [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). Report vulnerabilities privately using `SECURITY.md`. Keep discussions respectful, focused on evidence and welcoming to contributors of every experience level. Harassment and sharing private customer/merchant information are not acceptable; maintainers may remove such content or restrict participation.

Contributions are accepted under the repository's MIT license. By submitting a contribution, you confirm you have the right to contribute it under that license.
