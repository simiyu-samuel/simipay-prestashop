# Validation record

Recorded 29 October 2026; updated 30 October 2026 for the 2.0.3 reconciliation-metadata change and the first merchant's live-payment report. Earlier core browser/provider acceptance used 2.0.0. The 2.0.2 SSL correction passed regression checks and its PHP 8.2–8.5 release matrix. The new 2.0.3 payload regression passes locally and in the PHP 8.2–8.5 release matrix; its public ZIP is verified. A fresh 2.0.3 browser installation/upgrade and provider acceptance of its extra metadata have not been exercised.

## First merchant live-payment report — 30 October 2026

The maintainer received a merchant report of successful production checkout, PrestaShop paid orders/customer-account details/invoices, and received Kopo Kopo payments. Supplied screenshots show a **SIMIPAY (M-PESA)** order marked **Payment accepted** for **KES 10.00**, with a customer record and an invoice document, and a Kopo Kopo **Buygoods** transaction marked **Received** for **KES 10.00** on the same date. The provider transaction screen shows **Name: Not available**. The invoice's detailed contents and account-registration behavior are merchant-reported; they were not independently audited in this environment.

This supplies merchant-reported evidence for the live-payment happy path and closes the absence of any client live-success report. It is not a full production certification. The installed module version, PrestaShop/PHP/theme/checkout versions, the shop's payment-entry receipt match and the callback-versus-reconciliation path are unconfirmed. No client credentials, database access or new live payments were used for this review. Customer names, phone/email, receipt/order identifiers, merchant-identifying quotes and raw screenshots are not published. Unrelated payment-schedule screenshots are excluded from the evidence.

The report concerns the client's earlier installation, not the new 2.0.3 metadata. Existing code already sends checkout first/last name and email in `subscriber`. Version 2.0.3 adds the available checkout `customer_name` and reserved `order_reference` to request metadata, alongside `attempt_id`/`cart_id`. Local tests verify the actual serialized JSON in both environments, optional/Unicode names, repeat initiation and completion when the payer's name differs. These synthetic checks cannot establish which fields Kopo Kopo will show in its transaction dashboard. Its documented API does not guarantee that subscriber fields or metadata populate the transaction “Name” field. Provider-side display/metadata retention remains to be checked privately with Kopo Kopo and the merchant.

## Correction to earlier HTTPS validation

The original integration/demo setup explicitly added `PS_SSL_ENABLED_EVERYWHERE`, which PrestaShop 9 removed. This masked a module bug: normal shops with only `PS_SSL_ENABLED` were incorrectly rejected. Version 2.0.2 requires the native SSL setting alone. The new regression failed against the earlier guard and passes with the fix, including missing/stale legacy-key cases and rejection of disabled SSL. `tests/platform-requirements.php` also passed on the real local PrestaShop 9.2.0/PHP 8.4.16 setup with changes confined to in-process configuration caches. No database settings or payment data were changed. The destructive fixture now removes, rather than adds, the obsolete key. Earlier browser installation results therefore do not establish that the older packages install correctly on an unmodified fresh PrestaShop 9 shop.

## Completed locally

| Check | Result |
| --- | --- |
| Module, tests and build scripts | PHP syntax checks pass |
| Payment/domain/API adapter tests | 26 tests pass on PHP 8.4.16, including the new serialized metadata regression |
| Configuration encryption tests | Round-trip, initialization, tampering and environment/shop isolation pass |
| PrestaShop 9 SSL regression | Fast guard/install/upgrade tests and the native 9.2.0 configuration reader accept SSL enabled with the removed key absent/stale; SSL disabled is rejected |
| Composer metadata | Strict validation passes; no third-party runtime dependencies |
| GitHub release checks | PHP 8.2, 8.3, 8.4 and 8.5 checks, packaging and publication passed in [run 40914332014](https://github.com/simiyu-samuel/simipay-prestashop/actions/runs/40914332014) |
| SSL patch release checks | Version 2.0.2 PHP 8.2–8.5 checks, packaging and publication passed in [run 40916474008](https://github.com/simiyu-samuel/simipay-prestashop/actions/runs/40916474008) |
| Reconciliation metadata release checks | Version 2.0.3 PHP 8.2–8.5 checks, packaging and publication passed in [run 41028849477](https://github.com/simiyu-samuel/simipay-prestashop/actions/runs/41028849477) |
| Merchant package | Correct `kopokopo/` root, required files, exclusions, deterministic rebuild and SHA-256 checks pass |
| Front-end script | JavaScript syntax check passes |
| PrestaShop integration | Real PrestaShop 9.2.0, PHP 8.4.16, MariaDB 10.4.34 and official Hummingbird 2.1.2 |
| Real lifecycle and core flow | Built ZIP extracted and installed through native module methods; encrypted settings, database persistence, native order creation and receipt recording pass |
| Native checkout preferences | Direct initiation honors the native payment-option restrictions; disabling the test carrier is rejected |
| Native UI/templates | Admin configuration renders; customer and payment-return templates compile |
| Merchant browser installation | Fresh official PrestaShop Classic 9.2.0 package; release ZIP selected in Module Manager's upload dialog; “Module installed!” displayed; Configure opens the sandbox form |
| Real sandbox authentication | Credentials saved through the browser; Kopo Kopo OAuth authentication succeeds; saving with blank secret inputs retains credentials and authentication still succeeds |
| Real sandbox failed payment | Browser checkout initiated a KES 22.18 request with the failure-simulation till `K000000`; the signed provider callback recorded `failed` / `provider_failed`; an authenticated provider GET confirmed `Failed` with insufficient-balance error; no order or receipt was created |
| Real sandbox successful payment | Browser checkout with sandbox till `1234567` completed through the signed callback; native order is paid for KES 22.18, a matching KES 22.18 payment and M-PESA receipt are persisted, and the shopper reached native order confirmation |
| Admin form CSRF | Corrected native Symfony token validation; regression accepts different masks of a valid token and rejects foreign-employee, malformed, empty and non-string tokens |
| Public demo HTTPS/access rules | Apache 2.4.66 behind ngrok HTTPS; configuration/log/implementation/documentation requests return 403, public module JavaScript returns 200 |
| Idempotency and storage | Duplicate initiation/callback protection, cross-connection named-lock exclusion/release, and provider-request uniqueness pass |
| Native upgrade | Core upgrade runner discovers and executes the 1.0.0 → 2.0.0 migration; legacy log/credentials/footer hook cleanup verified |
| Uninstall/reinstall | Financial records and saved credentials survive |
| HTTP boundary | 10 loopback checks pass: method/signature/JSON/body-size/amount rejection, signed duplicate callbacks without browser cookies, anonymous customer-status denial and scheduler authentication |
| Native HTTPS transport | Public Kopo Kopo sandbox homepage returns HTTP 302 with TLS verification enabled; redirect is not followed. No credentials used |

The real-platform smoke test caught and drove fixes for cart flag normalization (`"0"` versus `0`) and repeated registration of a legacy hook alias. Those checks remain in the integration suite.

Provider results in the domain/platform/HTTP tests are synthetic. The loopback HTTP test simulates TLS termination in a test-only router. A separate browser rehearsal used the official Classic 9.2.0-6.0 package, Hummingbird 2.1.2, PHP 8.4.16, MariaDB 10.4.34 and Apache behind a public ngrok HTTPS tunnel. The merchant ZIP upload and configuration screen passed. Access checks for `app/config/parameters.php`, a real application log, module implementation and module documentation returned 403; public module JavaScript returned 200. These checks cover this demo setup and do not certify a client's hosting or actual Kopo Kopo callback delivery. The demo has sample data and outgoing email disabled. Real sandbox credentials have now been saved through the admin form and OAuth authentication succeeds, including after saving again with blank secret fields. The first payment request and its outcome are recorded below.

The first real sandbox payment used `K000000`, which the sandbox dashboard identifies as a failed-transaction simulator. It failed as expected, and the module accepted the signed callback before any status reconciliation (`checked_at` remained null). A subsequent authenticated provider GET confirmed the same outcome. A second browser payment used sandbox example till `1234567` and completed successfully through the signed callback, also without reconciliation. The persisted native order is paid and its payment amount and receipt match the attempt. The shopper reached native confirmation. This validates that sandbox scenario; the example value is not a merchant-assigned production till. Outgoing email remains disabled, so mail delivery has not been certified.

The browser save exposed an admin CSRF defect that rendering alone did not catch: masked Symfony tokens differ between requests and cannot be checked with string equality. The module now delegates verification to the native CSRF manager for the logged-in employee. `tests/admin-form.php` covers acceptance and rejection cases without changing shop settings or sending payments.

## Still required before production release

- Additional real sandbox delayed/cancelled/error scenarios and the merchant's account/till acceptance. The exercised success and forced-failure callbacks are verified separately above.
- Fresh 2.0.3 ZIP installation/upgrade through Module Manager, including retained pending requests/settings and the merchant's actual checkout. Earlier branding rehearsals were stopped at the maintainer's request; the new patch has not had a full browser rehearsal.
- Full browser checkout acceptance on standard checkout and the native one-page checkout, including guest/registered customers, stock/email behavior, and the client's actual theme or checkout customization.
- Hosting-specific HTTPS/firewall/cache/scheduler and sensitive-file access verification.
- Provider retention/display of the new metadata and controlled 2.0.3 live revalidation, including matching the native order payment receipt/amount to Kopo Kopo.
- Exact client module/platform/runtime/theme/checkout versions and additional failure/recovery scenarios before broad production claims.

A real API request and signed callback were exercised in the provider's sandbox, which simulates payments without a phone PIN prompt or real money movement. No production payment, transfer or refund was sent by the local test environment; the later merchant-reported live payment is recorded above. The release is an installable pre-release, not a claim of completed production acceptance. The public [v2.0.1 pre-release](https://github.com/simiyu-samuel/simipay-prestashop/releases/tag/v2.0.1) contains the merchant ZIP and checksum. Both public assets were downloaded after publication and verified for checksum, module identity, version, credits and the single `kopokopo/` root. The immutable tag identifies source commit `9eed3fa`; this post-publication record does not move that tag.

Published ZIP SHA-256: `247b5fc2ca1ce244cae769324ba9b4acfe45a3e0a12926ac2a0a21b69195acf1`.

## Published SSL correction

The [v2.0.2 pre-release](https://github.com/simiyu-samuel/simipay-prestashop/releases/tag/v2.0.2) supersedes 2.0.1 for installation. Its immutable tag identifies source commit `e8915a2`. Both public assets were downloaded after publication; the ZIP has 58 entries under the single `kopokopo/` root, is 48,399 bytes, and every entry matches the reviewed local build byte-for-byte. Its checksum verifies, and the packaged requirements guard contains the corrected SSL check. No browser branding/upgrade rehearsal or production acceptance is implied by these checks.

Published **2.0.2** ZIP SHA-256: `1aa16a130cbdbdc39df454a1a3388e6dffc9956c4ebfb403ffde2c83fa656ac2`.

## Repository rename

The canonical repository is now [`simiyu-samuel/simipay-prestashop`](https://github.com/simiyu-samuel/simipay-prestashop). Maintained source/docs links, Composer metadata, issue contact links, the admin installation-guide link, both published release descriptions and the local Git remote were updated. Published tags and assets were retained. Both versions' ZIP and checksum downloads were verified through the new repository URLs against the original SHA-256 values above; the previous 2.0.2 download URL was also checked and still resolves to the original ZIP. The release workflow already derives its repository from `github.repository`. The technical module identifier remains `kopokopo`. Composer strict validation, PHP syntax/manifest checks and the check for remaining old-name references in tracked files passed.

## Published reconciliation update

The [v2.0.3 pre-release](https://github.com/simiyu-samuel/simipay-prestashop/releases/tag/v2.0.3) identifies source commit `a67d1cb`. The public ZIP and checksum were downloaded after publication and verified: 59 entries under one `kopokopo/` root, 49,280 bytes, with every entry matching the tested local build byte-for-byte. The packaged module has the new metadata, versioned upgrade and current repository/install links. Existing public tags and assets were not replaced.

Published **2.0.3** ZIP SHA-256: `6b3a09d3e5380a0b4d20158ee5825fe6006ece49d17e14c02e5668a4c473f7f3`.
