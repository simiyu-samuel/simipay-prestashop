# Installation and configuration

**SIMIPAY (M-PESA)** is developed and maintained by [Samuel Simiyu](https://simiyu-samuel.vercel.app/). Its technical module name and ZIP prefix are `kopokopo`.

No code editing or Composer is needed. Your host handles [server requirements](https://github.com/simiyu-samuel/Prestashop-Lipa-Na-Mpesa-Module/blob/main/docs/requirements.md); you configure the module in PrestaShop.

## 1. Prepare the shop

1. Back up the store files and database.
2. Confirm PrestaShop 9.2.x, PHP 8.2–8.5, KES currency, and single-store mode.
3. In **Shop Parameters > General**, enable SSL and SSL on all pages.
4. Start on a staging shop with a public HTTPS URL. A local/private URL cannot receive Kopo Kopo callbacks.
5. Obtain sandbox application credentials and the sandbox till from Kopo Kopo.
6. In **Shipping > Carriers**, enable a delivery or collection option for your customers' delivery zone (Kenya is in **Africa** by default), customer groups and product weight/price range. Checkout needs an available carrier before it can reach payment. Sample carriers may cover only Europe or North America.

## 2. Upload and install

1. Download `kopokopo-<version>.zip` from this project's GitHub release assets. Do not select GitHub's “Source code” ZIP.
2. In the PrestaShop back office, open **Modules > Module Manager > Upload a module**.
3. Select the ZIP. Its top-level folder is `kopokopo/`; leave the ZIP intact for this upload.
4. Find **SIMIPAY (M-PESA)** and select **Configure**. If installation reports unmet requirements, ask your host to fix those requirements first.

If your hosting upload limit prevents ZIP upload, ask the host to raise the limit. An alternative is extracting the package into the store's `modules/` directory, retaining the `kopokopo` folder name, and installing it through Module Manager.

## 3. Configure sandbox

1. Keep **Environment: Sandbox (testing)** selected.
2. Enter **Till number**, **OAuth client ID**, **OAuth client secret**, and **API key (callback signing key)**.
3. Select **Save settings**. Secret fields become empty after saving; this is intentional. Leaving them blank on a later save keeps the stored secrets.
4. Select **Test saved connection**. Success verifies OAuth authentication only; a full checkout is still needed to verify the till, signing key and callback delivery.
5. Under **Payment > Payment Methods / Preferences**, ensure the method is enabled for the relevant country, customer groups and carrier, and KES currency is enabled for it.

Access tokens and expiry times are managed automatically. Do not enter them manually.

## 4. Check checkout and callbacks

1. Place a staging order with KES currency and select **Pay with M-PESA**.
2. Enter a Kenyan test mobile number and follow Kopo Kopo's sandbox instructions. The sandbox simulates payments; no money moves and no phone PIN prompt is sent. Only production sends the actual M-PESA prompt.
3. Watch the payment-status page. It should confirm the order only after a verified provider success.
4. Check the PrestaShop order, amount, M-PESA receipt, stock and confirmation email. Duplicate delivery must not create a second order.
5. Test rejection/failure and delayed confirmation, plus the store's actual theme/checkout. Follow the complete checklist in `docs/testing.md` in the repository.

The callback URL is attached automatically to each payment request. You do not edit a PHP file or manually create a webhook subscription for this STK callback flow. The host must allow its public HTTPS route and signature header.

Our local sandbox acceptance used `K000000` for the provider's forced failure and `1234567` for a successful simulation. These are sandbox test values, not production till credentials. For production, use your own eligible till supplied by Kopo Kopo. See the [provider sandbox guide](https://developers.kopokopo.com/guides/sandbox-setup/).

## 5. Enable recovery checks

In **Configure > Payment recovery**, copy the private `curl` command and ask your host to schedule it every minute. Use an HTTPS URL. The scheduler uses a header token, not a token in a public URL. Do not paste that command into public support tickets.

Callbacks confirm payments normally. Scheduled reconciliation checks requests that remain pending even if the shopper has closed the browser. **Check provider status** in the admin page provides an on-demand alternative. A request whose initiation response was lost may need **Recover provider request**; see the troubleshooting guide.

## 6. Switch to production

1. Complete the staging acceptance checklist and confirm production access with Kopo Kopo.
2. Choose **Production (real payments)** and select **Save settings**. This first save switches environments and loads that environment's saved settings.
3. Enter the production till and credentials, then save again. Sandbox credentials are never copied to production.
4. Test the saved connection. Confirm that the live hostname receives callbacks and the scheduler uses the live URL/token.
5. Perform a controlled live acceptance payment and verify the order and receipt before opening the method to customers.

Do not change an environment's credentials or till while it has unresolved payment attempts. Resolve those attempts first so callbacks remain verifiable. Do not disable/uninstall the module while requests are unresolved: PrestaShop does not dispatch module controllers for disabled modules.

## Upgrading from 2.0.0 to 2.0.1

Back up the shop, upload `kopokopo-2.0.1.zip` through Module Manager and run the offered upgrade. The display name becomes **SIMIPAY (M-PESA)** and the branding asset hook is registered. Credentials, payment history, existing orders, callback routes and the `kopokopo` folder name are retained. No reconfiguration is required for this branding upgrade.

## Upgrading from 1.0.0

Version 2.0 requires upgrading the store/server first. Stop offering the old method, resolve all old STK requests against the provider, and back up the shop. Old unsigned callbacks must not be carried into the new flow.

Upload the new ZIP through Module Manager and run the offered module upgrade. The upgrade creates the new attempt table, removes the legacy callback log, unregisters the old footer script, and clears the ambiguous legacy credentials/tokens. Configure sandbox/production credentials again in the admin page; the old hard-coded environments cannot be migrated safely by guessing.

Version 2 does not import the old ad hoc status table. Keep any old financial records in your secure backup. Remove obsolete `modules/kopokopo/vendor/` files from an older deployment if the module manager retained them; version 2 never uses or ships them. The former debug PHP endpoint is overwritten by a 404 tombstone. Historical logs must not remain publicly accessible, including in old backup archives.

## Uninstall and data retention

Uninstalling removes the payment method from PrestaShop but retains its payment-attempt table and encrypted settings. Reinstalling reuses them. Database backups must include them for accounting and recovery.

Permanent data removal is a deliberate maintenance operation after reconciling all payments, meeting your retention policy and taking a backup. It is not triggered by uninstall or reset. See `docs/architecture.md` for the stored fields. A paid payment requiring review is never silently discarded.
