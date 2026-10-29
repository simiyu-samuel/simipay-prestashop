# Kopo Kopo module modernization audit

Reviewed: 29 October 2026.

Module baseline: `main`, commit `2d92dffe87d5ac4941cad960deea1749a518a6fb` (30 September 2024), module version 1.0.0.

Primary target confirmed by the owner: **PrestaShop 9.2, PHP 8.2+**. The client's theme and checkout are unknown. PrestaShop 8 support was initially requested but needs a separate compatibility decision: its documented PHP range ends at 8.1, so a PHP 8.2 minimum would exclude that supported configuration.

This is a source audit and redesign proposal. The module's executable source and dependencies have not been changed. No shop installation or payment-provider account was used.

## Recommendation

Keep Kopo Kopo as the payment provider and migrate to its documented **API v2 STK Push flow**. K2Connect remains its official SDK family; its name alone does not mean it is obsolete. Redesign payment tracking and callback processing before claiming production compatibility.

The official PHP SDK checkout obtained during this audit has tag **v2.0.0** at commit `1b4531617937b8269cd318436b47c861caf12b29` (15 May 2026). Its `src/K2.php` selects `/api/v2/`. The module currently locks v1.2.0 and Guzzle 6.5.8, but its own payment code uses cURL directly and does not load or call the bundled SDK.

The browser's cached Packagist page still displayed v1.4.0. Use the verified official v2.0.0 source as the migration reference, and check live Composer availability before selecting and locking a release. Do not simply run an unreviewed dependency update or pin an untagged development branch.

Sources: [official Kopo Kopo API reference](https://api-docs.kopokopo.com/), [official SDK guide](https://developers.kopokopo.com/pages/sdks.html), [official PHP SDK v2.0.0](https://github.com/kopokopo/k2-connect-php/tree/v2.0.0).

## Confirmed findings

Line references below refer to the original module baseline.

| Priority | Finding and consequence | Source |
| --- | --- | --- |
| Critical | Callback JSON is accepted without verifying `X-KopoKopo-Signature`. A claimed `Success` can reach paid-order creation without proving the sender is Kopo Kopo. | `kopokopo/controllers/front/callback.php:62–90` |
| Critical | The submitted `order_id` is actually used as a cart ID. Initiation charges the context cart's total but takes its callback cart reference from user input. Callback completion trusts query parameters for cart and currency rather than a stored payment attempt; amount, currency, till, and provider request ID are not matched against an expected payment. | `controllers/front/process.php:10–33`, `controllers/front/callback.php:12–15,79–90` |
| High | Authentication uses `https://api.kopokopo.com/oauth/token`, while STK requests use `https://sandbox.kopokopo.com/api/v1/incoming_payments`. The environments cannot be configured consistently. | `kopokopo/kopokopo.php:109,228` |
| High | Gateway and token errors are returned as nonempty strings. The process controller treats any truthy return as successful initiation, so even an HTTP 401 error displays the success screen. | `kopokopo/kopokopo.php:194–210,255–277`, `controllers/front/process.php:36–41` |
| High | No durable payment attempt records, provider request correlation, callback deduplication, or concurrency controls exist. Repeated and late callbacks cannot be handled reliably. | `kopokopo/kopokopo.php:173–206`, `controllers/front/callback.php:75–106` |
| High | Callback storage writes to `kopokopo_payment_status`, but there is no table creation or upgrade migration anywhere in the module. Failed callbacks can contain a null resource, which storage dereferences as an amount. | `kopokopo/kopokopo.php:28–40`, `controllers/front/callback.php:175–185` |
| High | Customer payment status is inferred from whether the cart is empty. Otherwise a request sleeps for 15 seconds and reports failure. Neither path establishes actual provider payment status. | `controllers/front/paymentstatus.php:9–38` |
| Medium | Initiation lacks explicit POST-only, CSRF, cart ownership, loaded-customer, active-module, completed-cart, and currency eligibility checks. No initiation rate limit or double-click protection exists. | `controllers/front/payment.php`, `controllers/front/process.php`, `kopokopo/kopokopo.php:80–91` |
| Medium | Phone normalization corrupts an already formatted `+254…` number by prepending another country code. Validation also needs to handle local `07…` and `01…` forms. | `kopokopo/kopokopo.php:115–117` |
| Medium | Full callbacks are written under the module's web directory, and a 161,838-byte callback log is tracked in Git. Server behavior determines whether it is downloadable. Audit and sanitize historical data before distributing the module; this review did not reproduce its contents. | `controllers/front/callback.php:143–160`, `kopokopo/logs/callback_log.txt` |
| Medium | Front-end code references a missing `views/js/payment_interceptor.js`. There is no declared PrestaShop compatibility range or automated test suite. The Composer installation scripts refer to a console path absent from the standalone module. | `kopokopo/kopokopo.php:73–77`, `kopokopo/composer.json` |
| Medium | Admin credentials are rendered in ordinary text fields, configuration validation is absent, and the custom configuration form has no explicit module CSRF check. Front-end templates contain unescaped interpolation and leftover JavaScript experiments. | `views/templates/admin/configure.tpl`, `views/templates/front/*.tpl` |

Paths in abbreviated source references are relative to `kopokopo/`.

## Verified API behavior

- Initiate STK Push with `POST /api/v2/incoming_payments` on the selected environment.
- Obtain the OAuth token on that same environment with the client credentials flow. Cache tokens automatically; merchants should not type access tokens or expiry times into settings.
- A successful initiation returns HTTP 201 with a `Location` header. Persist that resource URL and its request ID. Initiation is not payment confirmation.
- Completion arrives asynchronously. Querying the incoming-payment resource can reconcile pending requests when a callback is delayed.
- Authenticate the raw callback body before decoding and processing it. The official v2.0.0 SDK uses a hex SHA-256 HMAC keyed by `apiKey`, compared with `hash_equals` (`src/helpers/Auth.php`, called by `src/Webhooks.php`). The guide inconsistently mentions both API key and client secret; follow the verified SDK contract and confirm with a sandbox-signed callback.
- Treat duplicate deliveries as expected. Null payment resources on failed requests must be valid inputs.

Sources: [STK Push, callback, and status contracts](https://api-docs.kopokopo.com/#receive-m-pesa-payments), [webhook authentication and duplicates](https://developers.kopokopo.com/guides/webhooks/validating-webhooks.html), [SDK authentication source](https://github.com/kopokopo/k2-connect-php/blob/v2.0.0/src/helpers/Auth.php).

The review found a documented v2 replacement for the endpoint in this module. It did not establish a formal v1 retirement date or account-specific production eligibility.

## Proposed implementation

1. **Configuration and lifecycle.** Add a sandbox/production selector, environment-scoped credentials and token caches, validated till settings, masked secret inputs, CSRF protection, installation schema, upgrade migrations, and explicit compatibility metadata. Keep payment records on uninstall unless data deletion is explicitly requested.
2. **Provider adapter.** Put token retrieval, STK initiation, status reconciliation, and signature verification behind one adapter. Evaluate official SDK v2.0.0 with Guzzle 7 and module autoload isolation. Use explicit timeouts, structured results/exceptions, redacted logs, and bounded token refresh. Do not automatically retry an ambiguous STK POST: the provider may already have accepted it.
3. **Payment attempt storage.** Store an opaque attempt ID, shop/cart/customer IDs, expected amount in integer minor units, currency, till, environment, provider request ID, validated resource URL, transaction reference, state, and timestamps. Add unique provider-request and transaction constraints, plus serialized completion per cart. Retries get separate attempt records.
4. **Checkout initiation.** Derive cart identity and totals on the server, validate ownership and payment eligibility, require POST and CSRF validation, normalize the phone, and reject concurrent duplicate initiation. Freeze the expected payment amount and define how cart edits invalidate or supersede pending attempts. Initially restrict this method to KES unless additional currency support is verified.
5. **Callback processing.** Use a session-independent endpoint. Require POST, enforce a body-size limit, authenticate the raw bytes, validate the JSON schema, and correlate the request to the stored attempt. Check amount, currency, till, and transaction identity before completing payment. Lock state transitions and make retries idempotent; a late failure must never downgrade a verified success. Handle a callback that races the initiation response. Return HTTP errors for invalid input and acknowledge only after durable processing.
6. **Order completion.** Preserve the current deferred-order approach initially, creating the order only after verified payment. Use the stored customer secure key and cart, record the M-PESA reference, and protect against repeat/concurrent creation. Define handling for partial core failures, stock changes, cart edits, and a second real payment for an already-paid cart; expose these for reconciliation rather than discarding received money. A pending-order-first approach can be considered separately if the client's inventory policy requires stock reservation.
7. **Customer experience.** Replace the 15-second sleep and manual success guess with an authorized, nonblocking status endpoint and polling page. Present pending, confirmed, failed, and unresolved states clearly. Redirect to the native order-confirmation page after verified order creation. A browser timeout remains unresolved until provider reconciliation determines the outcome.
8. **Operational recovery.** Add an authenticated scheduled reconciliation entry point for unresolved attempts and a back-office view with redacted diagnostics. Reconciliation must also work after the shopper closes the browser. Restrict resource queries to the configured Kopo Kopo host and expected API paths.

## PrestaShop compatibility and validation

The current `PaymentModule` and `PaymentOption` foundation is still relevant in PrestaShop 9. Native one-page checkout uses the standard `paymentOptions` hook and supports ordinary action/form submission. Keep the initial implementation on that integration path; avoid introducing a self-submitting binary flow without implementing its required checkout handoff.

Use a module minimum of PHP 8.2 for the confirmed target. Test PHP 8.2–8.5 only on PrestaShop releases that officially support each runtime; a minimum version declaration alone does not prove support. The 9.2 release announcement reports support through PHP 8.5, while some developer documentation still contains older version/status text.

If PrestaShop 8 remains a requirement, explicitly add PHP 8.1 support and a separate test matrix, or maintain a separate compatible release. Do not advertise PrestaShop 8 compatibility for a module requiring PHP 8.2.

Sources: [PrestaShop 9 payment modules](https://devdocs.prestashop-project.org/9/modules/payment/), [9.2 one-page checkout integration](https://devdocs.prestashop-project.org/9/modules/checkout/module-developers/), [9.2 release announcement](https://build.prestashop-project.org/news/2026/prestashop-9-2-0-available/), [PrestaShop 8 PHP requirements](https://devdocs.prestashop-project.org/8/basics/installation/system-requirements/).

Required checks for the implementation:

- Fresh installation, configuration, upgrade from 1.0.0, disable/re-enable, and uninstall behavior.
- PrestaShop 9.2 with standard checkout and native one-page checkout; test the client's theme/checkout when identified.
- Successful STK payment; customer rejection; invalid phone; provider failure; expired token; token refresh; pending and late outcomes.
- HTTP error strings cannot become successful initiation; browser-supplied cart IDs cannot change the charged cart association.
- Missing/invalid signatures, malformed JSON, amount/currency/till mismatches, foreign request IDs, and unauthorized status queries.
- Duplicate, concurrent, and out-of-order callbacks; callback before initiation persistence; cart edits; double clicks; second real payment; partially completed order creation.
- Lost initiation responses, browser closure, delayed callbacks, reconciliation, and provider outages.
- Sandbox end-to-end payment and callback delivery, followed by staging verification on the client's actual stack before production release.

## Checks completed during this audit

- Cloned the owner's repository into the current workspace and reviewed all module-owned PHP, templates, Composer files, and installation behavior.
- All six module-owned PHP files pass `php -l` on local PHP 8.4.16. This is syntax validation only.
- `composer --no-plugins --no-scripts validate --no-check-publish` passes from `kopokopo/`, with a missing package-license warning. The repository itself includes an MIT license.
- An isolated PHP harness executed the original process controller with mocked PrestaShop dependencies. It reproduced both an HTTP 401 error being shown as successful initiation and a submitted cart reference being passed to the gateway despite differing from the context cart. No network or payment was involved.
- Checked official documentation and cloned the official SDK into a temporary audit directory to verify v2 endpoints, its v2.0.0 tag, and signature code.
- No dependency security audit, full PrestaShop runtime test, sandbox transaction, production transaction, or compatibility certification has been performed.

## Information still needed for release

- Client's exact PrestaShop 9.2 patch, actual PHP runtime, theme, and any checkout customization.
- A staging shop and separately configured Kopo Kopo sandbox application with a reachable HTTPS callback endpoint. Enter credentials through secure configuration, not chat or source control.
- Merchant production API access, till eligibility, and confirmation of the production setup. No communication has been sent to Kopo Kopo support during this review.
