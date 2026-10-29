# Rehearsing the merchant installation

Use a fresh staging shop with PrestaShop 9.2.x, PHP 8.2–8.5, KES and the default Hummingbird theme. Keep this shop separate from real customers, orders and production credentials. The staging server needs the same extensions and HTTPS requirements as a client server; see [requirements](requirements.md).

## Prepare the demonstration

1. Install the complete official PrestaShop Classic package. A GitHub source checkout requires additional dependency and asset builds and is not the merchant installation package.
2. Enable HTTPS on every page, single-store mode and KES. Use sample products and a carrier available to a Kenyan delivery address. Disable outgoing email on a shared demonstration shop.
3. A development machine can use an HTTPS tunnel to its loopback web server. Keep the machine, database, web server and tunnel running throughout the payment test. A changed tunnel hostname requires updating the shop URL, generated rewrite rules and recovery command.
4. Use Apache/Nginx with the correct access rules. PHP's built-in server does not enforce `.htaccess` protection. Do not expose database ports, installers, configuration files or logs through the tunnel.
5. Build the merchant artifact with `php scripts/build.php`. Start with the module absent from the demonstration shop so the upload actually exercises a fresh installation.

## Follow the client’s steps

1. Sign in to the shop's back office.
2. Open **Modules > Module Manager > Upload a module**, and select `dist/kopokopo-2.0.2.zip` from this repository. Do not upload the repository source ZIP. The module appears as **SIMIPAY (M-PESA)** by Samuel Simiyu.
3. Wait for the successful installation message, then choose **Configure**.
4. Select **Sandbox (testing)** and enter the sandbox till, OAuth client ID, OAuth client secret and API key. Enter secrets directly in the admin form, not in an issue or a chat transcript.
5. Save, then choose **Test saved connection**. Verify that credentials are retained when secret fields are left blank during a later save.
6. Confirm native payment preferences allow M-PESA for KES, the test customer's country/group and the chosen carrier.
7. Open the storefront, add a sample product and complete checkout using a Kenyan address. Choose M-PESA and enter the sandbox test number supplied by Kopo Kopo.
8. Follow the provider's sandbox instructions. Verify the payment-status page, the resulting PrestaShop order, the exact amount and receipt. Repeat with a failed/cancelled and delayed payment.
9. Repeat with the native one-page checkout and, when known, the client’s theme and checkout customization.

Uploading the ZIP and opening configuration proves the installation path. Testing OAuth proves authentication. A complete provider payment with a verified callback proves the full integration; these are separate acceptance steps. Without valid sandbox credentials, stop before claiming payment acceptance.

Use the full [testing checklist](testing.md) to record the remaining scenarios and [validation record](validation.md) to distinguish completed checks from outstanding ones. The merchant-facing instructions are in [installation and configuration](installation.md).
