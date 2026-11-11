# Tonyapps_B2bOrderApproval

Optional storefront order approval for Tonyapps_B2b company accounts on Magento Open Source.

## First release behavior

- Enabled per website under **Stores > Configuration > Tonyapps > B2B Order Approval**.
- Company members whose cart base subtotal meets the configured threshold must request approval from the cart page.
- The company administrator reviews the buyer, cart lines, and subtotal from **My Account > Order Approval Requests**.
- Approval allows the buyer to return to the cart and complete the normal Magento checkout. Approval does not place the order or authorize payment.
- Rejection supports an optional reason. The buyer and company administrator receive email notifications.
- Requests are tied to the quote's item, option, coupon, and base subtotal fingerprint. Checkout is refused if the cart has changed since approval.
- Company administrators can place orders without requesting approval. The existing Tonyapps_B2b enable switch and active company path are respected.

Approver roles and multi-level approval chains are not part of this first release; only the company administrator can approve or reject. The threshold is configured in the website's base currency. No payment data is copied into the approval table.

## 2026-10-30 review fixes

- The approval gate moved from `placeOrder()` to `QuoteManagement::submit()`, so PayPal Express and PaymentServices buttons are gated too; multishipping is gated by an observer on `checkout_type_multishipping_create_orders_single`. Admin-created orders are not gated.
- `/checkout` redirects to the cart with a notice while approval is missing (mini-cart and direct links no longer reach the payment step).
- The approval queue page is no longer full-page cached and shows the account menu; the menu link is shown only to the company administrator. The queue is paginated (50 per page).
- Approving a changed cart now emails the buyer the automatic rejection. Request emails greet the administrator and are no longer double-escaped.
- Duplicate submits are serialized on the quote row; the fingerprint check no longer leaves totals flagged as collected before payment import; the cart page computes the approval state once per request.

## 2026-11-09 QA fixes

- An approval is withdrawn as soon as the saved cart no longer matches it (lines changed or the cart emptied); it shows as *Rejected — Cart changed after approval.* Re-adding the same lines later needs a new request instead of reviving the old approval.
- The approval queue formats subtotals and line totals in the base currency and quantities without trailing zeros, and shows the order number (increment ID) instead of the internal order ID.

## 2026-11-09 email redesign

The request, approved and declined emails show the requested cart as a table (name, SKU, qty, line total; $0 lines
say *Free*) with its subtotal, the rejection reason in a highlighted box and a button. New template variables:
`items` and `subtotal`, built from the request's cart snapshot in the base currency.

## Emails

Screenshots of the emails as sent (Luma theme, 2026-11-09). Source images are in `docs/emails/`.

**Approval request (to the company administrator)**

![Approval request (to the company administrator)](docs/emails/approval-request.png)

**Order approved (to the buyer)**

![Order approved (to the buyer)](docs/emails/approval-approved.png)

**Order request declined (to the buyer)**

![Order request declined (to the buyer)](docs/emails/approval-declined.png)

## Install

Install `tonyapps/module-b2b-order-approval` with `tonyapps/module-b2b`, run `bin/magento setup:upgrade`, then enable the feature and set the minimum subtotal. Set the threshold to zero to require approval for every non-admin company order.

## Tests

Run the focused unit suite from the Magento installation root with `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/B2bOrderApproval/Test/Unit`.

## Redesigning the emails

Each email's template is chosen in **Stores › Configuration › Tonyapps › B2B Order Approval › Emails** (default and website scope). Pick a template made in **Marketing › Email Templates** or a design made
with the [Email Designer](https://github.com/tonyappsorg/magento2-email-designer) (`Tonyapps_EmailDesigner`) (**Marketing › Communications › Email Designer**). The default template is used until another one
is picked.

The template IDs follow core's config-path convention so the setting can offer the default: `tonyapps_b2borderapproval_{request,approved,rejected}` are now `tonyapps_b2b_order_approval_email_{request,approved,rejected}_template`. Theme overrides
keep working (they go by file name). A custom template created from the old ID keeps its content, but its *Template
Information* no longer names the default it was loaded from.
