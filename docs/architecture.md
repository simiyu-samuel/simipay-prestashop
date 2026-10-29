# Architecture and payment safety

## Boundaries

`src/Api` implements Kopo Kopo API v2 with native cURL. It uses one HTTPS environment for both OAuth and payment requests, bounded timeouts, verified TLS and allowlisted incoming-payment resource URLs. It never follows redirects or automatically retries an STK POST. A GET may refresh an expired token once.

`src/Domain` normalizes Kenyan phone numbers, parses amounts in integer minor units, checks signed callback data, and controls attempt state transitions. `src/Infrastructure` adapts these rules to PrestaShop configuration, MySQL/MariaDB and native order creation. Module-local autoloading avoids third-party dependency collisions with the shop.

Front controllers handle the payment page, POST initiation, read-only customer status, callbacks and protected scheduled reconciliation. Admin configuration uses PrestaShop's AdminModules token; checkout initiation uses an HMAC token tied to the current customer/cart/secure key. Attempt status requires the owning customer session and shop. Provider callbacks use HMAC, independently of a browser session.

## Initiation

The server selects cart/customer/amount/currency and validates eligibility, address ownership, KES, stock and product access. A cart-scoped database named lock serializes initiation and callbacks. The attempt is persisted before provider I/O, with an opaque ID sent in metadata and the callback URL. The provider's HTTP 201 resource URL is retained.

Any open/completed/review attempt prevents another initiation on that cart. Definitively rejected attempts allow retry; a customer has at most ten attempts per hour. An ambiguous response does not prove failure: it remains unknown, blocks repeat initiation and can be resolved by a signed callback or verified provider-resource recovery.

## Confirmation and orders

The raw callback body is authenticated using the API key and `X-KopoKopo-Signature`. The payment type, request ID, attempt metadata, decimal amount, KES currency, till, received-resource status and receipt are validated. Provider-resource GET responses go through the same financial validation.

Unique database constraints protect provider request IDs and M-PESA receipts. A named cart lock serializes order completion. Confirmed funds and the receipt are saved before invoking core order creation. A stored order reference and a cart fingerprint protect the association. The core call receives the original customer secure key, stored currency and transaction ID.

Orders are created only after verified success. Stock is not reserved during the phone prompt. Cart edits, unavailable stock, another order, or an interrupted core operation move the attempt to review with its financial record retained. Core order creation has side effects outside a single database transaction; the module never blindly replays an interrupted `completing` operation. Merchants resolve fulfillment/refunds manually through their normal processes.

A valid duplicate callback does not create another order. A late failure cannot downgrade verified payment. A late success after failure is still recorded; if another payment already created an order, that additional payment requires review.

## Stored data

The `PREFIX_kopokopo_attempt` InnoDB table stores attempt ID; shop/cart/customer/currency IDs; expected amount in minor units; currency/till/environment; a cart fingerprint; provider request ID/URL; M-PESA receipt; state/reason and employee-attributed manual resolution note; order ID/reference; and UTC timestamps. No phone number, subscriber name/email, full callback or access token is stored in this table.

Provider credentials and cached OAuth tokens live in PrestaShop configuration, scoped by environment and shop, encrypted using AES-256-GCM with a key derived from `_COOKIE_KEY_` and authenticated context for each field. Access tokens are internal. The reconciliation secret is sent in an HTTP header. Uninstall deliberately preserves records and encrypted settings. Operators control retention through secure database maintenance after reconciliation and backups.

Despite per-shop storage boundaries, multistore is not supported or advertised in this release. Encryption protects a database-only copy; a host administrator with the shop's application key can decrypt settings.

## Recovery and extension points

Callbacks are primary. An authenticated POST scheduler checks up to ten due attempts with a bounded run budget. Admin status checks use the same domain service. A lost response with no resource UUID requires an operator to find the request at Kopo Kopo, then link it through metadata-verified admin recovery.

Review cases are retained rather than automatically retried. An admin may explicitly record a provider-checked manual outcome and audit note; this releases the cart but does not move money or create orders. Later success is retained for review. Automatic refunds, alternate providers, additional currencies, multistore, older runtimes and checkout-specific behavior require explicit contributions with tests. See the historical modernization audit for the original defects; it describes the baseline before this implementation.
