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

## Install

Install `tonyapps/module-b2b-order-approval` with `tonyapps/module-b2b`, run `bin/magento setup:upgrade`, then enable the feature and set the minimum subtotal. Set the threshold to zero to require approval for every non-admin company order.

## Tests

Run the focused unit suite from the Magento installation root with `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/B2bOrderApproval/Test/Unit`.
