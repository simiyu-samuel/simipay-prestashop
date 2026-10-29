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
| Merchant browser installation | Fresh official PrestaShop Classic 9.2.0 package; release ZIP selected in Module Manager's upload dialog; “Module installed!” displayed; Configure opens the sandbox form |
| Real sandbox authentication | Credentials saved through the browser; Kopo Kopo OAuth authentication succeeds; saving with blank secret inputs retains credentials and authentication still succeeds |
| Real sandbox failed payment | Browser checkout initiated a KES 22.18 request with the failure-simulation till `K000000`; the signed provider callback recorded `failed` / `provider_failed`; an authenticated provider GET confirmed `Failed` with insufficient-balance error; no order or receipt was created |
| Admin form CSRF | Corrected native Symfony token validation; regression accepts different masks of a valid token and rejects foreign-employee, malformed, empty and non-string tokens |
| Public demo HTTPS/access rules | Apache 2.4.66 behind ngrok HTTPS; configuration/log/implementation/documentation requests return 403, public module JavaScript returns 200 |
| Idempotency and storage | Duplicate initiation/callback protection, cross-connection named-lock exclusion/release, and provider-request uniqueness pass |
| Native upgrade | Core upgrade runner discovers and executes the 1.0.0 → 2.0.0 migration; legacy log/credentials/footer hook cleanup verified |
| Uninstall/reinstall | Financial records and saved credentials survive |
| HTTP boundary | 10 loopback checks pass: method/signature/JSON/body-size/amount rejection, signed duplicate callbacks without browser cookies, anonymous customer-status denial and scheduler authentication |
| Native HTTPS transport | Public Kopo Kopo sandbox homepage returns HTTP 302 with TLS verification enabled; redirect is not followed. No credentials used |

The real-platform smoke test caught and drove fixes for cart flag normalization (`"0"` versus `0`) and repeated registration of a legacy hook alias. Those checks remain in the integration suite.

Provider results in the domain/platform/HTTP tests are synthetic. The loopback HTTP test simulates TLS termination in a test-only router. A separate browser rehearsal used the official Classic 9.2.0-6.0 package, Hummingbird 2.1.2, PHP 8.4.16, MariaDB 10.4.34 and Apache behind a public ngrok HTTPS tunnel. The merchant ZIP upload and configuration screen passed. Access checks for `app/config/parameters.php`, a real application log, module implementation and module documentation returned 403; public module JavaScript returned 200. These checks cover this demo setup and do not certify a client's hosting or actual Kopo Kopo callback delivery. The demo has sample data and outgoing email disabled. Real sandbox credentials have now been saved through the admin form and OAuth authentication succeeds, including after saving again with blank secret fields. The first payment request and its outcome are recorded below.

The first real sandbox payment used `K000000`, which the sandbox dashboard identifies as a failed-transaction simulator. It failed as expected, and the module accepted the signed callback before any status reconciliation (`checked_at` remained null). A subsequent authenticated provider GET confirmed the same outcome. The demo was then configured with `1234567`, the nonzero value shown in the official STK guide, for the next scenario; this example value has not yet been verified as a successful payment or as a merchant-assigned till.

The browser save exposed an admin CSRF defect that rendering alone did not catch: masked Symfony tokens differ between requests and cannot be checked with string equality. The module now delegates verification to the native CSRF manager for the logged-in employee. `tests/admin-form.php` covers acceptance and rejection cases without changing shop settings or sending payments.

## Still required before production release

- Real Kopo Kopo sandbox success and signed success callback delivery for the merchant's account/till, plus additional delayed/cancelled/error scenarios. OAuth authentication and the sandbox's forced-failure callback are verified separately above.
- Full browser checkout acceptance on standard checkout and the native one-page checkout, including guest/registered customers, stock/email behavior, and the client's actual theme or checkout customization.
- Hosting-specific HTTPS/firewall/cache/scheduler and sensitive-file access verification.
- The configured PHP 8.2/8.3/8.4/8.5 GitHub Actions matrix actually running and passing after the commits are pushed. Local evidence here covers PHP 8.4.16 only.
- Maintainer release review and a controlled live acceptance payment before general availability.

A real API request and signed callback were exercised in the provider's sandbox, which simulates payments without a phone PIN prompt or real money movement. No production payment, transfer or refund was sent. No GitHub release or tag has been published by this implementation task. The package is an installable candidate, not a claim of completed merchant/provider acceptance.
