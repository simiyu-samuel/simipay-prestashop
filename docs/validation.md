# Validation record

Recorded 29 October 2026 for the version 2.0.0 candidate.

## Completed locally

| Check | Result |
| --- | --- |
| Module, tests and build scripts | PHP syntax checks pass |
| Payment/domain/API adapter tests | 24 tests pass on PHP 8.4.16 |
| Configuration encryption tests | Round-trip, initialization, tampering and environment/shop isolation pass |
| Composer metadata | Strict validation passes; no third-party runtime dependencies |
| Merchant package | Correct `kopokopo/` root, required files, exclusions, deterministic rebuild and SHA-256 checks pass |
| Front-end script | JavaScript syntax check passes |
| PrestaShop integration | Real PrestaShop 9.2.0, PHP 8.4.16, MariaDB 10.4.34 and official Hummingbird 2.1.2 |
| Real lifecycle and core flow | Built ZIP extracted and installed through native module methods; encrypted settings, database persistence, native order creation and receipt recording pass |
| Native checkout preferences | Direct initiation honors the native payment-option restrictions; disabling the test carrier is rejected |
| Native UI/templates | Admin configuration renders; customer and payment-return templates compile |
| Idempotency and storage | Duplicate initiation/callback protection, cross-connection named-lock exclusion/release, and provider-request uniqueness pass |
| Native upgrade | Core upgrade runner discovers and executes the 1.0.0 → 2.0.0 migration; legacy log/credentials/footer hook cleanup verified |
| Uninstall/reinstall | Financial records and saved credentials survive |
| HTTP boundary | 10 loopback checks pass: method/signature/JSON/body-size/amount rejection, signed duplicate callbacks without browser cookies, anonymous customer-status denial and scheduler authentication |
| Native HTTPS transport | Public Kopo Kopo sandbox homepage returns HTTP 302 with TLS verification enabled; redirect is not followed. No credentials used |

The real-platform smoke test caught and drove fixes for cart flag normalization (`"0"` versus `0`) and repeated registration of a legacy hook alias. Those checks remain in the integration suite.

Provider results in the domain/platform/HTTP tests are synthetic. The loopback HTTP test simulates TLS termination in a test-only router; it does not certify public HTTPS, a reverse proxy, Apache/Nginx access rules or actual Kopo Kopo delivery. Native module methods were exercised against the built ZIP; a visual browser test of Module Manager's upload dialog is still outstanding.

## Still required before production release

- Real Kopo Kopo sandbox authentication, STK success/failure and signed callback delivery for the merchant's account/till.
- Full browser checkout acceptance on standard checkout and the native one-page checkout, including guest/registered customers, stock/email behavior, and the client's actual theme or checkout customization.
- Hosting-specific HTTPS/firewall/cache/scheduler and sensitive-file access verification.
- The configured PHP 8.2/8.3/8.4/8.5 GitHub Actions matrix actually running and passing after the commits are pushed. Local evidence here covers PHP 8.4.16 only.
- Maintainer release review and a controlled live acceptance payment before general availability.

No real STK prompt, transfer or refund was sent. No GitHub release or tag has been published by this implementation task. The package is an installable candidate, not a claim of completed merchant/provider acceptance.
