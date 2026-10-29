# Security policy

Version 2 targets PrestaShop 9.2.x and PHP 8.2–8.5. The legacy 1.0 implementation has known unsafe callback and status handling and should not be used for new deployments. Version 2 remains a candidate until the documented release checks pass.

Please report vulnerabilities through [GitHub's private vulnerability reporting](https://github.com/simiyu-samuel/simipay-prestashop/security/advisories/new) if enabled. If it is unavailable, open an issue asking the maintainer for a private reporting channel **without publishing vulnerability details or secrets**. No response-time guarantee is implied for this volunteer project.

Include affected versions, impact, reproduction steps and a proposed fix if available. Use synthetic data. Do not send real credentials, customer records, phone numbers, complete callbacks or the shop's application key.

The module verifies raw-body HMAC signatures using the API key, validates incoming payment identity and financial details, confines provider URLs to known HTTPS hosts, and avoids automatic STK POST retries. Credentials and tokens are encrypted at rest with a key derived from the shop secret. Host/database access and backups must still be protected.

Historical repository commits and old release archives may contain callback logs. Removing logs from the current tree does not remove them from Git history. Treat historical artifacts as potentially containing private data; review them before redistribution. History rewriting and provider credential rotation, if warranted by a separate data review, require coordinated maintainer action and are not performed by ordinary module upgrades.
