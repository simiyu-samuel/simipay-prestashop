# Kopo Kopo M-PESA for PrestaShop

Accept M-PESA payments using Kopo Kopo API v2 STK Push. Customers enter a Kenyan mobile number, approve the prompt on their phone, and receive an order confirmation after the payment has been verified.

**Version 2.0 is a modernization candidate.** Automated tests and packaging are available; a real Kopo Kopo sandbox transaction and the store-specific acceptance checklist must pass before a production release. See [validation status](docs/validation.md).

## For merchants

Upload the **module ZIP** through PrestaShop's Module Manager, install it, and enter your Kopo Kopo credentials in its configuration page. No source editing, Composer, Node.js, or command-line installation is required.

| Requirement | Supported target |
| --- | --- |
| PrestaShop | 9.2.x |
| PHP | 8.2–8.5, on a version supported by your PrestaShop installation |
| Currency and phone numbers | KES; Kenyan mobile numbers (`07…`, `01…`, `254…`, `+254…`) |
| Store setup | Single store; standard payment-option checkout integration |
| Hosting | HTTPS on every page, outbound HTTPS/cURL, inbound public HTTPS callbacks |
| PHP extensions | All PrestaShop extensions, including cURL, JSON, and OpenSSL |
| Database | PrestaShop-compatible MySQL/MariaDB with InnoDB and named locks (`GET_LOCK`) |
| Provider | Kopo Kopo merchant account, eligible till, OAuth application and API signing key |

PrestaShop 8, PHP 8.1 and earlier, multistore, other currencies, custom checkout integrations, and automatic refunds are outside this release's supported scope. Upgrade the server to these requirements; the module does not modify the store's platform or theme. Contributors can propose additional support with its own tests and documentation.

1. Check [server requirements](docs/requirements.md).
2. Follow the [installation and configuration guide](docs/installation.md).
3. Run the [checkout acceptance checklist](docs/testing.md) in staging and sandbox.
4. Use [troubleshooting and payment recovery](docs/troubleshooting.md) if needed.

Get merchant packages from [GitHub Releases](https://github.com/simiyu-samuel/Prestashop-Lipa-Na-Mpesa-Module/releases) once published. Choose `kopokopo-<version>.zip`, **not** GitHub's automatically generated “Source code” ZIP. Until a release is published, maintainers can build the same package with `php scripts/build.php`.

## What the module handles

- Separate sandbox and production settings; an admin connection test; automatic OAuth token caching.
- Credentials encrypted in the database with the shop's application key; secret fields never redisplay saved values.
- Signed callbacks, exact amount/currency/till matching, stored payment attempts, and guarded order creation.
- Duplicate and late callbacks; unresolved requests without automatically sending another STK prompt.
- Customer status polling; native PrestaShop order confirmation; M-PESA receipt attached to the order payment.
- An admin payment list, status checks, lost-response recovery, and a protected reconciliation endpoint for hosting schedulers.
- Installation schema and upgrade handling. Financial records survive uninstall/reinstall.

## For contributors

```sh
php scripts/check.php
php tests/run.php
php tests/settings.php
php scripts/build.php
php tests/package.php
```

Developer machines need PHP 8.2–8.5 and the listed extensions; ZIP builds also need `ext-zip`. Composer validation is optional locally and runs in CI. There are no third-party runtime dependencies. The module uses the documented API v2 contract directly through cURL; K2Connect v2.0.0 is the signature and endpoint reference, not a bundled dependency.

See [CONTRIBUTING.md](CONTRIBUTING.md), [architecture](docs/architecture.md), and [release procedure](docs/releasing.md). Feature requests and compatibility contributions are welcome. Please include the exact PrestaShop/PHP/theme/checkout versions when reporting a bug; never include credentials, customer phone numbers, or raw callbacks.

## Project layout

```text
kopokopo/             Uploadable module source
  controllers/front/  Checkout, callback, status and reconciliation endpoints
  src/Api/            Native cURL transport and Kopo Kopo API v2 client
  src/Domain/         Payment validation and state transitions
  src/Infrastructure/ PrestaShop database, settings and order integration
  upgrade/            Versioned upgrade scripts
  views/              Admin and customer templates and polling script
scripts/              Validation and reproducible ZIP builder
tests/               Automated tests (not shipped in the merchant ZIP)
docs/                 Merchant, contributor and release documentation
.github/              CI, issue forms and pull-request template
```

## License and credits

[MIT](LICENSE). Created by Samuel Simiyu; contributions are welcome. This is an independent community module, not an official PrestaShop or Kopo Kopo product.

Payment contract: [Kopo Kopo API documentation](https://api-docs.kopokopo.com/). Platform integration: [PrestaShop payment modules](https://devdocs.prestashop-project.org/9/modules/payment/) and [native one-page checkout](https://devdocs.prestashop-project.org/9/modules/checkout/module-developers/).
