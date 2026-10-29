# SIMIPAY (M-PESA) identity and credits

## Names

| Context | Name |
| --- | --- |
| Project and Module Manager | **SIMIPAY (M-PESA)** |
| Customer payment option | **Pay with M-PESA** |
| Developer and maintainer | **Samuel Simiyu** |
| Customer attribution | **Powered by Samuel Simiyu** |
| Portfolio destination | `https://simiyu-samuel.vercel.app/` |
| Technical module folder, class, routes and ZIP prefix | `kopokopo` |

Keep the technical identifier stable: it connects the installation, upgrades, order history, settings, callback routes and payment records. Branding does not require merchants to rename files or migrate their database.

## Visual assets

The original SIMIPAY S mark is in `kopokopo/logo.svg`. The 128 × 128 `kopokopo/logo.png` is the packaged Module Manager icon. Keep the vector source alongside the PNG for contributors. Primary green is `#146b45`, the light accent is `#b4f3ce`, and text is `#24392f`.

The icon is original SIMIPAY artwork, rather than an M-PESA, Kopo Kopo or PrestaShop logo. Customer pages use the recognizable payment name M-PESA. Scoped CSS styles only module content, with visible keyboard focus and inherited shop typography.

## Visible developer credit

The shared attribution partial appears in checkout payment details, the phone form, payment status and the payment confirmation. The developer's name opens the portfolio in a new tab with `noopener noreferrer`. The admin configuration identifies the developer, installed version and API integration.

These are ordinary links. There are no tracking pixels, remote logo requests, license activation calls, injected store-wide footers or customer/order data in the portfolio URL. Visiting the portfolio is a deliberate user action.

## Project provenance

- Keep the maintainer, portfolio and canonical source consistent in the README, Composer metadata, AUTHORS.md and NOTICE.
- Include the license, credits and install instructions in merchant ZIPs.
- Publish reviewed version tags, a changelog, validation notes and ZIP checksums when a release is approved. A checksum verifies bytes; it does not by itself authenticate a publisher.
- Credit community work through Git history and release notes. Direct support and security reports to the documented repository channels.
- Do not describe the module as an official or certified provider product, or claim compatibility that has not been tested.

The existing MIT license remains unchanged. Visible credit is included by default; this branding change adds no attribution lock, remote kill switch or anti-removal mechanism. Preserving clear provenance and a useful maintained project is the approach used here.
