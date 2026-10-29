# Troubleshooting and payment recovery

Always compare the module's payment reference with Kopo Kopo's incoming-payment record and metadata. Do not request a second payment just because a browser timed out.

| Symptom | Check |
| --- | --- |
| Module will not install | PrestaShop 9.2.x, PHP 8.2–8.5, cURL/OpenSSL, SSL on every page, single store and database permissions. |
| No M-PESA method in checkout | Saved credentials in the selected environment; KES currency; module active; country/group/carrier/payment preferences. |
| Connection test fails | Client ID/secret for the selected environment; outbound HTTPS, DNS, CA certificates, provider availability and application access. |
| Authentication passes, initiation fails | Till eligibility, environment, Kopo Kopo application permissions and valid Kenyan mobile number. Connection testing does not validate the API signing key. |
| No phone prompt | Provider/sandbox scenario, phone number, mobile availability, or an unresolved initiation. Check the attempt before retrying. |
| Stays pending | Callback delivery, signature header/key, public HTTPS URL, proxy/firewall/cache rules, provider status and scheduled reconciliation. |
| Callback gets 401 | Incorrect API signing key or modified raw body/signature header. Use the API key field, not the OAuth client secret. |
| Callback gets 422 | Request ID/metadata or payment amount, currency, till, or resource status mismatch. Inspect provider records privately; do not bypass validation. |
| Callback gets 503 | Database lock contention or transient processing/storage failure. Let delivery/reconciliation retry; inspect the attempt and hosting logs. |
| Review after successful debit | Cart/stock changed, another payment created the order, or core order creation was interrupted. Reconcile the order and funds manually. |
| Secrets fail after migration | Restore the original shop `_COOKIE_KEY_` along with the database, or re-enter credentials. Never expose that key in a ticket. |

## States

- **Initiating:** attempt stored before the provider request.
- **Pending:** request accepted; payment has not been confirmed.
- **Unknown:** the request may have been accepted, but its response was lost. A later signed callback can still resolve it. The same cart is blocked from a new prompt.
- **Confirmed / completing:** provider funds verified; order creation is underway. An interrupted completion moves to review rather than being blindly repeated.
- **Completed:** the module confirmed payment and all created order totals/statuses.
- **Failed:** provider/request rejection; the shopper may return to checkout to retry. A late verified success is still handled.
- **Resolved:** the merchant recorded a provider-checked manual outcome and audit note. This does not itself issue a refund or create an order. A later success goes to review.
- **Review:** the merchant must reconcile funds and the order. The module retains the attempt and receipt and does not request another charge.

## Check a pending request

Open **Modules > Module Manager > Kopo Kopo M-PESA > Configure**. Copy the reference from **Recent payment attempts**, enter it under **Payment recovery**, and select **Check provider status**. The protected scheduler uses the same verification path.

## Recover a lost initiation response

If the state is unknown and no provider request UUID was saved, find the incoming request in Kopo Kopo using the module reference in its `attempt_id` metadata. Enter that reference and the provider UUID under **Payment recovery**, then select **Recover provider request**. The module queries the selected provider environment and verifies the metadata before linking the request. It never accepts an arbitrary URL.

If no provider request exists or you cannot identify it conclusively, confirm the result with Kopo Kopo before asking the shopper to try again. Do not alter the database to make an unresolved request look unpaid.

## Reconcile review cases

Compare the verified M-PESA receipt with Kopo Kopo and the shop's orders for the cart/reference. If an order already exists or core processing partly completed, finish the necessary order/accounting work in PrestaShop. If fulfillment is impossible, handle the refund through your established merchant process; this module does not issue refunds.

An order is created only after verified payment. The cart is not reserved while waiting for an STK response. A cart edit or stock change can require merchant review after the debit, and must not cause an automatic second debit. After confirming the outcome with Kopo Kopo and finishing any order/refund work, open **Record a manual resolution** in the module configuration. Select the verified outcome, enter an audit note without customer details or secrets, and confirm the action. This releases the cart without altering the provider or creating an order; the record and employee attribution are retained. A later success is retained for review rather than silently discarded.

Review records are retained; they are not automatically retried or deleted.

## Safe support reports

Include module version, PrestaShop version, PHP version, theme/checkout name, state and a description with reproduction steps. Do not post OAuth credentials, signing keys, scheduler tokens, phone numbers, customer details or complete callbacks. Use the private reporting process in `SECURITY.md` for vulnerabilities.
