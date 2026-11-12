# Tonyapps_B2bOrderApproval — notes for AI agents and developers

Read this before changing the module. Workspace-wide rules are in ../AGENTS.md.

Package `tonyapps/module-b2b-order-approval` v0.1.0, proprietary. Depends on `Tonyapps_B2b` (company accounts).

## Status

- First release built: optional storefront order approval for Tonyapps_B2b company accounts.
- 2026-10-30 review fixes, 2026-11-09 QA fixes and 2026-11-09 email redesign applied (see Change history).

## Architecture

First-release behaviour:

- Enabled per website. Company members whose cart **base subtotal** meets the configured threshold must request
  approval from the cart page (`Block/Cart/RequestApproval`, `Controller/Cart/Submit`, `cart/request-approval.phtml`).
- The company administrator reviews buyer, cart lines and subtotal at **My Account > Order Approval Requests**
  (route `b2borderapproval/approval/index`, `Block/Approval/Requests`, `Controller/Approval/{Index,Approve,Reject}`).
  The menu link (`Block/Account/ApprovalLink`) is shown only to the company administrator. The queue is paginated
  (50 per page), not full-page cached, and shows the account menu.
- Approval lets the buyer return to the cart and complete normal Magento checkout. It does **not** place the order or
  authorise payment. Rejection takes an optional reason. Buyer and company administrator get email notifications
  (`Model/Notifier`).
- A request is tied to a fingerprint of the quote's items, options, coupon and base subtotal (`Model/CartFingerprint`).
  Checkout is refused if the cart changed since approval.
- Company administrators place orders without approval. The existing Tonyapps_B2b enable switch and active-company
  path are respected (`Model/OrderApprovalPolicy::requiresApproval()`).
- `Model/ApprovalService` holds the flow; storefront cart state is one of `CHECKOUT_OPEN`, `CHECKOUT_NEEDS_APPROVAL`,
  `CHECKOUT_APPROVED`, computed once per request on the cart page.

Gates:

| Where | Class | Notes |
|---|---|---|
| `Magento\Quote\Model\QuoteManagement::submit()` (around, sortOrder 10, global `di.xml`) | `Plugin/QuoteManagementPlugin` | Covers onepage, PayPal Express, PaymentServices buttons. Skipped in `adminhtml` area: admin-created orders are not gated. |
| `checkout_type_multishipping_create_orders_single` | `Observer/EnforceMultishippingApproval` | Multishipping gate. |
| `checkout_submit_all_after` | `Observer/MarkMultishippingApprovalOrdered` | Marks the request `ordered` for multishipping. |
| `controller_action_predispatch_checkout_index_index` (frontend) | `Observer/RedirectCheckoutUntilApproved` | `/checkout` redirects to the cart with a notice while approval is missing (mini-cart and direct links don't reach payment). |
| `Magento\Checkout\Block\Onepage\Link` (frontend) | `Plugin/CheckoutLinkPlugin` | `afterIsPossibleOnepageCheckout`: hides the cart checkout button unless the cart state allows it. |
| `sales_quote_save_after` | `Observer/InvalidateChangedApproval` | Withdraws an approval as soon as the saved cart no longer matches it. |

Interplay: `Tonyapps_B2bQuotes` plugs `QuoteManagement::submit()` at sortOrder 20; approval still applies to a
negotiated cart above the threshold (the fingerprint includes prices).

## Data model

`tonyapps_b2b_order_approval` (`Model/ResourceModel/ApprovalRequest::TABLE`):

| Column | Notes |
|---|---|
| `entity_id` | PK |
| `quote_id`, `store_id`, `company_id`, `customer_id` | requester |
| `requester_name`, `requester_email` | |
| `base_subtotal` | decimal(20,4), requested base subtotal |
| `cart_hash` | varchar(64), approved cart fingerprint |
| `cart_snapshot` | text, requested cart contents (feeds email `items`/`subtotal`) |
| `status` | `pending` (default), `approved`, `rejected`, `ordered` |
| `approver_customer_id`, `order_id` | nullable |
| `comment` | rejection reason / comment |
| `created_at`, `updated_at` | |

Indexes: (`quote_id`, `status`), (`company_id`, `status`). No FKs. No payment data is copied into this table.

## Configuration and ACL

| Path | Default | Scope |
|---|---|---|
| `tonyapps_b2b_order_approval/general/enabled` | `0` | default, website |
| `tonyapps_b2b_order_approval/general/minimum_subtotal` | `0` (= every non-admin company order) | default, website; base currency |
| `tonyapps_b2b_order_approval/email/request_template` | `tonyapps_b2b_order_approval_email_request_template` | default, website |
| `tonyapps_b2b_order_approval/email/approved_template` | `tonyapps_b2b_order_approval_email_approved_template` | default, website |
| `tonyapps_b2b_order_approval/email/rejected_template` | `tonyapps_b2b_order_approval_email_rejected_template` | default, website |

- Admin section: **Stores > Configuration > Tonyapps > B2B Order Approval** (General, Emails). ACL
  `Tonyapps_B2bOrderApproval::config`.
- Email templates (`etc/email_templates.xml`, area frontend): `approval_request.html`, `approval_approved.html`,
  `approval_rejected.html`. Template variables include `items` and `subtotal`, built from the request's cart
  snapshot in base currency.
- Templates can be replaced by a **Marketing › Email Templates** template or a `Tonyapps_EmailDesigner` design
  (**Marketing › Communications › Email Designer**).
- Template IDs follow core's config-path convention so the setting can offer the default:
  `tonyapps_b2borderapproval_{request,approved,rejected}` were renamed to
  `tonyapps_b2b_order_approval_email_{request,approved,rejected}_template`. Theme overrides keep working (they go by
  file name). A custom template created from the old ID keeps its content, but its *Template Information* no longer
  names the default it was loaded from.

## Decisions and constraints

- Only the company administrator approves/rejects; no approver roles, no multi-level chains (first release scope).
- Threshold is in the website's base currency.
- Approval is pre-checkout; it does not place the order or authorise payment. No payment data stored.
- Admin-created orders are deliberately not gated.
- Re-adding the same lines after an approval was withdrawn needs a new request; the old approval is not revived.
- Duplicate submits are serialised on the quote row.

## Known limitations and open points

- No approver roles or multi-level approval chains.
- No purchase-order status in admin; the whole flow lives on the storefront.

## Tests

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Tonyapps/B2bOrderApproval/Test/Unit
```

- Unit tests cover blocks, `ApprovalService`, `CartFingerprint`, `Notifier`, `OrderApprovalPolicy`, the resource
  model, observers, plugins, account-page layout and email templates.
- Integration test: `Test/Integration/Model/ApprovalFlowTest.php` (run with Magento's integration PHPUnit config).

## Change history

### 2026-11-09 email redesign

- Request, approved and declined emails show the requested cart as a table (name, SKU, qty, line total; $0 lines say
  *Free*) with its subtotal, the rejection reason in a highlighted box, and a button. New template variables `items`
  and `subtotal`, from the request's cart snapshot in base currency.
- Screenshots (Luma theme, 2026-11-09) in `docs/emails/` (`approval-request.png`, `approval-approved.png`,
  `approval-declined.png`), shown in README.
- Email template IDs renamed (see Configuration and ACL).

### 2026-11-09 QA fixes

- An approval is withdrawn as soon as the saved cart no longer matches it (lines changed or cart emptied); it shows as
  *Rejected — Cart changed after approval.* Re-adding the same lines later needs a new request.
- The queue formats subtotals and line totals in base currency and quantities without trailing zeros, and shows the
  order number (increment ID) instead of the internal order ID.

### 2026-10-30 review fixes

- Approval gate moved from `placeOrder()` to `QuoteManagement::submit()`, so PayPal Express and PaymentServices
  buttons are gated; multishipping gated by an observer on `checkout_type_multishipping_create_orders_single`.
  Admin-created orders are not gated.
- `/checkout` redirects to the cart with a notice while approval is missing.
- Queue page no longer full-page cached and shows the account menu; menu link only for the company administrator;
  queue paginated (50 per page).
- Approving a changed cart now emails the buyer the automatic rejection. Request emails greet the administrator and are
  no longer double-escaped.
- Duplicate submits serialised on the quote row; the fingerprint check no longer leaves totals flagged as collected
  before payment import; the cart page computes the approval state once per request.
