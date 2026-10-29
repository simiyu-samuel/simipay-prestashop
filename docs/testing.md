# Testing

## Fast automated checks

Run from the repository root with PHP 8.2–8.5:

```sh
php scripts/check.php
php tests/run.php
php tests/settings.php
php tests/requirements.php
php scripts/build.php
php tests/package.php
node --check kopokopo/views/js/status.js
composer --working-dir=kopokopo --no-plugins --no-scripts validate --strict
```

Node and Composer are contributor tools, not merchant installation dependencies. The configured GitHub Actions matrix runs the fast tests on PHP 8.2, 8.3, 8.4 and 8.5. A configured workflow is not evidence that a remote run passed.

The domain suite uses fake provider/PrestaShop boundaries and no real credentials or payments. It covers exact amounts, phone formatting, raw-body signatures, resource URL restrictions, ownership, duplicate initiation, failed/ambiguous outcomes, duplicate/late callbacks, interrupted completion and reconciliation. Settings tests exercise actual encryption and scope/tamper protection. Requirements tests accept the native PrestaShop 9 SSL setting with the legacy key absent or stale, and reject installation when SSL is disabled. Package checks verify its top-level directory, required files, exclusions and checksum.

## Disposable PrestaShop integration smoke test

`tests/prestashop.php` must run only against a throwaway PrestaShop 9.2 installation. It creates dummy configuration, customer/address/cart data and orders and exercises real database/module/core integration with synthetic provider responses. It does not send a real STK request.

Install a clean disposable shop with sample products first, put the module under its `modules/kopokopo/` directory, then run:

```sh
KOPOKOPO_TEST_SHOP=/absolute/path/to/disposable/prestashop \
KOPOKOPO_TEST_ALLOW_MUTATION=1 php tests/prestashop.php
```

The disposable database name must contain `test` or `ci`. For HTTP boundary checks, `tests/http.php` consumes the synthetic fixture written by the integration test and accepts a `KOPOKOPO_TEST_HTTP` loopback URL only. It expects a disposable server that treats the request as HTTPS after local TLS termination; no production configuration is changed by the test.

Never set these variables to a live or client shop. See `docs/validation.md` for the exact local results and known remaining checks.

For an already installed and initialized disposable shop, the admin token regression can run separately:

```sh
KOPOKOPO_TEST_SHOP=/absolute/path/to/disposable/prestashop php tests/admin-form.php
```

It exercises the real module handler with Symfony's real masked CSRF tokens and an isolated in-memory token store. Both masks must validate; foreign-employee, malformed, empty and non-string tokens must fail. An unknown action avoids settings changes and provider requests. The database name must contain `demo`, `test` or `ci`.

For the SSL regression against PrestaShop's native configuration reader:

```sh
KOPOKOPO_TEST_SHOP=/absolute/path/to/disposable/prestashop php tests/platform-requirements.php
```

This loads the current repository module against a disposable PrestaShop 9.2 demo/test/ci database. It changes and restores configuration caches only within the test process; database settings, installed module files and payments are untouched. The destructive integration fixture also removes any stale `PS_SSL_ENABLED_EVERYWHERE` key instead of adding it, so native installation exercises the real version 9 configuration.

## Staging acceptance before production release

For a demonstration that follows the client's ZIP upload and configuration process, use the [merchant walkthrough](merchant-walkthrough.md).

Record the PrestaShop patch, PHP patch, DB version, theme version and checkout module/version for every run.

- [ ] Install the built ZIP through Module Manager; configuration opens with no PHP/Smarty errors.
- [ ] Server requirements reject unsupported runtimes/multistore/non-HTTPS setups clearly.
- [ ] Sandbox credentials save; blank secret fields retain secrets; connection test succeeds.
- [ ] Switching environment does not copy credentials or cached tokens.
- [ ] Payment preferences and KES eligibility behave correctly.
- [ ] Test the default theme with standard checkout and native one-page checkout separately. Test guest and registered customers and the client's actual theme/custom checkout.
- [ ] Complete a real sandbox STK success and verify callback HMAC, order amount/state, receipt, stock, confirmation page and email.
- [ ] Test phone rejection, provider error, customer cancellation and failed callback with null resource.
- [ ] Test delayed/missing callback, browser closure and scheduler/admin reconciliation.
- [ ] Test double click and duplicate/concurrent callbacks; exactly one order/payment for one provider transaction.
- [ ] Test amount/currency/till/metadata mismatches and missing/incorrect signature; no order may be created.
- [ ] Test a lost initiation response, unknown state and metadata-verified recovery. No automatic repeat STK POST.
- [ ] Edit a cart or exhaust stock while the prompt is pending; received funds remain visible for review.
- [ ] Simulate an interrupted core completion; no blind second order creation.
- [ ] Verify customer status isolation, admin/checkout CSRF, forbidden HTTP methods and no cached payment responses.
- [ ] Ensure implementation/configuration/log files are inaccessible on the actual Apache/Nginx setup.
- [ ] Upgrade from 1.0.0 after draining old requests; verify schema, removed logs, reconfiguration and retained old backup records.
- [ ] Disable/re-enable and uninstall/reinstall after draining active requests; retained records remain available.
- [ ] Confirm the production account/till and perform a controlled live acceptance payment before general availability.

A synthetic signed fixture verifies module behavior but does not establish that Kopo Kopo will sign and deliver callbacks identically for the merchant's account. Real sandbox acceptance remains a release requirement.
