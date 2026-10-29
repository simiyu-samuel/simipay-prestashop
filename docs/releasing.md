# Releasing a merchant package

1. Confirm the current validation record and complete the staging/provider checklist in `docs/testing.md`. Do not publish the candidate as production-ready while provider acceptance is outstanding.
2. Update the module version in `kopokopo/kopokopo.php` and `kopokopo/config.xml`, the changelog, and any required versioned upgrade migration.
3. Run all syntax, domain, settings, integration and package checks. Keep the supported PHP/PrestaShop matrix honest.
4. Build from a clean, reviewed commit:

   ```sh
   php scripts/build.php
   php tests/package.php
   ```

   Output: `dist/kopokopo-<version>.zip` and `dist/kopokopo-<version>.zip.sha256`. The ZIP contains exactly one `kopokopo/` root with the complete module, license and install instructions. It excludes tests, Git metadata, old SDKs, runtime logs and secrets. Identical inputs on the same ZIP toolchain produce identical bytes.

5. Commit source and docs; do not commit generated ZIP files. Review the package by installing that ZIP into a clean staging store.
6. Once approved by the maintainer and acceptance is complete, tag `v<version>` and publish a GitHub release. The **Build merchant ZIP** workflow also builds/checks an artifact on matching tags or manual dispatch; it does not automatically publish a release.
7. Attach the `.zip` and `.sha256` files to the release, describe requirements and breaking changes, and distinguish release assets from GitHub's automatic source archives. If using the Actions artifact, extract the artifact wrapper and attach the inner module ZIP, not the wrapper.
8. Merchants upload that module ZIP through Module Manager and configure it in the back office.

Do not push tags, publish a release, or claim a remote CI/provider test passed merely because a local build succeeded. Checksum verification with `sha256sum -c kopokopo-<version>.zip.sha256` checks file integrity, not the trustworthiness of its publisher.
