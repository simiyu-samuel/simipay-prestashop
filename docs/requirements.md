# Server and account requirements

Give this page to your hosting provider before installation. The supported baseline is PrestaShop **9.2.x** on PHP **8.2–8.5**. Older environments need an upgrade; the module does not downgrade its requirements for an individual shop.

## Hosting provider checklist

- Meet all [PrestaShop 9 system requirements](https://devdocs.prestashop-project.org/9/basics/installation/system-requirements/). Use the PHP compatibility range for the installed PrestaShop patch. The module additionally enforces PHP 8.2–8.5.
- Enable cURL, JSON and OpenSSL. Keep the server CA certificate bundle current so outbound TLS verification succeeds.
- Use HTTPS with a publicly trusted certificate. Enable **SSL** under **Shop Parameters > General**. PrestaShop 9 uses this one setting for the whole shop; it no longer has a separate “SSL on all pages” setting. Do not serve checkout through HTTP.
- Allow outbound HTTPS to `sandbox.kopokopo.com` and `api.kopokopo.com`.
- Allow Kopo Kopo to POST JSON to the module callback URL, preserving the raw body and `X-KopoKopo-Signature` header. Do not place login gates, bot challenges, page caches or browser redirects in front of callbacks.
- Allow the shop database user to create the module's InnoDB table and use MySQL/MariaDB `GET_LOCK` and `RELEASE_LOCK`. An intermediary must preserve connection affinity while a named lock is held. Transaction-pooling proxies are not supported.
- Keep the PHP request timeout at least 90 seconds for reconciliation and ensure ordinary checkout requests can run for at least 35 seconds. Maintain accurate server time for OAuth expiry.
- Schedule the protected reconciliation command from the module's admin page every minute. Use your hosting control panel's scheduler or ask the host to do it. The command token is private. Callbacks remain the primary confirmation path.
- Back up the database and PrestaShop application secret together. Restoring the database without the same `_COOKIE_KEY_` prevents decryption of module credentials and requires reconfiguration.
- Support a single-store installation. Multistore is deliberately rejected in this version.

The module contains Apache access rules denying implementation files, templates, configuration and old logs. With Nginx, your host must deny direct access to sensitive module files and folders, for example before the general PHP/static handlers:

```nginx
location ~* ^/modules/kopokopo/.*\.(php|tpl|json|lock|log|txt|md)$ {
    deny all;
}
location ~* ^/modules/kopokopo/(src|upgrade|vendor|logs)/ {
    deny all;
}
```

Adjust the prefix if PrestaShop is installed in a subdirectory. Callback routes go through PrestaShop's public `index.php`, not a PHP file inside the module directory. Keep `views/js/status.js` accessible.

## Kopo Kopo account

Obtain an eligible Kopo Kopo till and application credentials from your own account. You need the till number, OAuth client ID, OAuth client secret, and API key used for callback signing. The API key and OAuth client secret are separate fields. Sandbox and production credentials are separate; production access may require Kopo Kopo approval.

The current integration uses [API v2 incoming payments](https://api-docs.kopokopo.com/#receive-m-pesa-payments). This project does not guarantee merchant account eligibility or provider uptime. Confirm account activation and till access directly with Kopo Kopo if authentication succeeds but initiation is rejected.

## Checkout and currency

The payment method appears for KES carts when configured and active. It uses normal `PaymentOption` action submission, which is the integration path documented for both standard checkout and the native PrestaShop 9.2 one-page checkout. The client's actual theme and any third-party checkout must pass staging acceptance; support is not claimed solely because the method appears in the list.

The store does not need custom code. Contributions for older versions, multistore, other currencies or checkout-specific features must include their own compatibility tests.
