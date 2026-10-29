# Releasing SIMIPAY merchant packages

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
6. Write `docs/releases/v<version>.md` with merchant installation steps, requirements, upgrade notes and validation limits. Once publication is authorized, create an annotated tag on the reviewed source commit and push the source and tag:

   ```sh
   git tag -a v2.0.2 -m "SIMIPAY (M-PESA) 2.0.2 pre-release"
   git push origin main
   git push origin v2.0.2
   ```

   Use the actual version being released. Never move a published tag to different code.

7. The **Release merchant ZIP** workflow checks PHP 8.2/8.3/8.4/8.5, confirms the tag matches the module manifest, builds and verifies the ZIP, and uploads a build artifact. On a pushed version tag, a separate job creates a draft GitHub pre-release with the module ZIP and checksum, then publishes it only after both assets are attached. Only that publication job receives `contents: write`; no personal access token needs to be committed. A manual workflow dispatch builds artifacts without publishing.
8. Verify the workflow succeeded, the release is public, and both assets download from `/releases/tag/v<version>`. Confirm the ZIP has a single `kopokopo/` root. Do not send merchants GitHub's automatic source archive or the Actions artifact wrapper.
9. Merchants download `kopokopo-<version>.zip`, upload it through Module Manager and configure **SIMIPAY (M-PESA)**. The maintainer can promote a pre-release after the remaining production acceptance checks pass. Future stable-release automation should be an explicit policy change, not an inferred consequence of a successful ZIP build.

Do not push tags, publish a release, or claim a remote CI/provider test passed merely because a local build succeeded. Checksum verification with `sha256sum -c kopokopo-<version>.zip.sha256` checks file integrity, not the trustworthiness of its publisher.

If publication fails after creating a draft, rerun the failed tag workflow: it can finish attaching assets to that draft. It refuses to overwrite assets of a public release. Diagnose failed checks before retrying; do not bypass the matrix or move the tag to hide a failure. GitHub CLI's [release command](https://cli.github.com/manual/gh_release_create) documents the publication flags used by the workflow.
