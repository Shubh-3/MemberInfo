# Vendor_MemberInfo

Magento 2 module that lets customers provide primary/spouse/child member
information during checkout, encrypts the sensitive fields at rest, and
gives admins password-gated, time-boxed, row-scoped access to view it.

Built against the task spec in `Magento 2 Practical Task.docx` (see the
original design doc `Magento2_MemberInfo_Module_Workflow.md` for the
architecture this was based on).

## Environment

| Component | Version |
|---|---|
| Magento | 2.4.9 (Open Source) |
| PHP | 8.5.2 |
| MySQL | 5.7.44 |
| Web server (dev) | PHP built-in server via `phpserver/router.php` |

PHP 8.5 runs the live site fine, but Magento 2.4.9 officially supports up to
PHP 8.3 — some CLI commands (`di:compile`) can hit deprecation-as-fatal
issues on 8.5 that don't reproduce on 8.3. Use 8.3 for CLI/setup commands
and either version for serving requests.

## Why this design

The task has five things pulling in different directions at once:
customer-facing UX (checkout), PII at rest, an admin surface with masked
data, and time-boxed access control — all inside a "no core
modification, everything server-side-authoritative" constraint. Every
major choice below exists to keep one of those concerns from leaking into
another.

### Product attributes as plain EAV booleans, not custom options
Has Spouse / Has Child gate *whether a sub-form exists at all* — they're
who-can-select-what settings, not price-affecting configurable input.
Magento's own custom options mechanism is for exactly that latter case
and would drag in a UI (admin custom-options tab) this task doesn't want.
A `boolean` EAV attribute (`Setup/Patch/Data/AddMemberProductAttributes.php`)
is the minimum structure that answers "is this enabled for this product."

### Member selection persisted as quote item options, not a side table
When a customer checks "Add Spouse" on the PDP, that selection needs to
survive add-to-cart, a cart edit, and order placement, and it needs to be
associated with *that specific item* if the same product appears in two
different orders. Magento's quote item options (the same mechanism custom
options use) already solve exactly this lifecycle — persisted with the
item, cloned to the order item automatically, no separate table to keep in
sync. `PersistMemberOptionsPlugin` writes `member_option_spouse` /
`member_option_child` as item options at add-to-cart time.

### A checkout step, not a payment-step field injection
Requirement 3 calls for a *named step* ("Member Information") positioned
after Shipping. Magento's checkout is a client-side jsLayout tree assembled
by `LayoutProcessorInterface`; there's no server-rendered form here. The
step is a Knockout component (`view/frontend/web/js/view/member-information.js`)
registered via `stepNavigator.registerStep()`, with `Block/Checkout/LayoutProcessor.php`
splicing it into the tree between the shipping and payment steps.

**Positioning gotcha worth documenting explicitly** (this cost real debug
time): the jsLayout `sortOrder` for step-level children is *not* a numeric
sort key. Magento's collection.js treats a bare number as a literal
array-splice index, so a fractional value like `1.5` (meant to land
"between" shipping=1 and payment=2) does not insert between them — it sets
a stray non-integer array property that renders in an unpredictable spot.
The supported way to position a step relative to a sibling is the
`{after: '<fully-qualified-component-name>'}` form, which does a name
lookup instead of an index guess. `LayoutProcessor.php` uses
`['after' => 'checkout.steps.shipping-step']`.

### Server-side is the only real gate; frontend validation is UX only
Requirement 4 is explicit that unselected-member data must never be
submitted, and validation must exist on both sides. The design treats the
frontend (`mage/validation` rules, self-clearing observables on deselect)
as UX-only and puts the actual security boundary entirely server-side:

- `MemberInfoManagement::saveForCartItem()` re-checks each item's
  `has_spouse`/`has_child` state at *save* time and silently drops any
  spouse/child payload for a type that isn't currently enabled — this is
  the authoritative filter, not a courtesy.
- `ValidateMemberInfoBeforeSubmitPlugin` re-checks at *order placement*
  time (`before` on `QuoteManagement::submit`) and blocks the order
  outright if primary data is missing or if staged data exists for a
  member type the item doesn't have enabled. This is defense-in-depth for
  the case where step 1's filter was somehow bypassed.

A client that lies about what's selected gets rejected at both checkpoints
before anything reaches the database.

### Two tables, not one, for staged-vs-placed member data
`vendor_memberinfo_quote` (staged, keyed by `quote_item_id`) and
`vendor_memberinfo` (final, keyed by `order_item_id`) are kept separate
rather than reusing one table with a nullable order reference. A quote can
be abandoned, edited, or merged; an order row, once it exists, should be
immutable. Keeping them apart means the staging table can be deleted
wholesale after a successful migration with no risk of deleting something
that should have survived, and the order table never has to represent "not
yet an order" as a state.

### Order association via `checkout_submit_all_after`, not `sales_order_place_after`
Requirement 6 needs each order item's member data isolated from every
other item in the same order, keyed correctly even for a multi-product
cart. The join key is `order_item_id`, which only exists once the order
has actually been *saved* — and `Magento\Sales\Model\Order::place()`
dispatches `sales_order_place_after` **before** `OrderRepository::save()`
runs (see `Magento\Sales\Model\Service\OrderService::place()`). An
observer on that event sees an order and order items with `entity_id = 0`.
`checkout_submit_all_after`, dispatched from
`QuoteManagement::placeOrderRun()` only after `submit()` (which internally
saves the order) returns, is the first point where real IDs exist. This
was found by direct reproduction, not by reading docs first — see the
Debugging Log below.

### Encryption via Magento's own `EncryptorInterface`, never a custom cipher
DOB and SSN are the two fields the task explicitly marks sensitive. Rolling
a custom AES implementation invites two failure modes Magento's own
encryptor already solves: key rotation (`crypt/key` in `app/etc/env.php`,
versioned) and consistent IV handling per encryption call. Only
`dob_encrypted`/`ssn_encrypted` columns exist — first/last name are stored
plain, matching the task's explicit sensitivity list.

### Admin reveal: password re-check without touching the session
This is the part with the most iteration, because two superficially
correct approaches both silently broke authentication state:

1. **`Backend\Model\Auth::login()`** — this is the *full sign-in flow*.
   On success it calls `Session::processLogin()`, which regenerates the
   session ID and every admin secret key including `form_key`, exactly as
   if a brand-new login had just happened. Re-verifying an already
   logged-in admin's password this way invalidated the form_key baked into
   the page that had just made the AJAX call, so the *next* POST (the
   Reveal call) failed CSRF validation, and a page refresh looked like "a
   different admin" logged in (it wasn't — it was session/key churn).

2. **`Magento\User\Model\User::authenticate()`** — doesn't touch the
   session directly, but internally dispatches
   `admin_user_authenticate_after`. `Magento_PageCache`'s `FlushFormKey`
   observer listens for exactly that event and nulls the current
   `form_key` as a side effect (it assumes "authenticate" always means a
   fresh login). Same symptom, different mechanism: the verify call
   succeeds, but silently poisons the very next request's CSRF token.

3. **What actually works**: `User::verifyIdentity($password)`, called
   directly on the admin user object already loaded in the backend session
   (`$this->backendAuthSession->getUser()`). It does the hash comparison
   plus active/role checks with *no event dispatch and no session
   mutation* — it's the private primitive `authenticate()` calls
   internally, without the event wrapper. `VerifyPassword.php` uses this.

### 15-minute row-scoped access via a hashed, single-purpose token
Requirements 8–9 need: server-side password re-verification, a 15-minute
window, and strict row isolation (verifying row 1 must never unlock row 2).
`AccessTokenManager` mints a CSPRNG token (`random_bytes(32)`), stores only
its SHA-256 hash plus `(admin_user_id, memberinfo_row_id, expires_at)`, and
returns the raw token to the browser exactly once. `isValid()` re-hashes
whatever's presented and checks it against the stored hash **for that
exact row** — a token minted for row 1 fails `isValid()` against row 2
outright, because the lookup itself is scoped by row, not just an
existence check. Expiry is a server-side timestamp comparison on every
call, never a client-side countdown. A cron job purges expired grants
(`PurgeExpiredAccessTokens`, every 15 minutes).

Client-side, `reveal-actions.js` caches a granted token in-memory (never
`localStorage`/`sessionStorage`) so a second click within the window skips
the password prompt — but the server still independently re-validates
scope and expiry on every `Reveal` call regardless of what the client
thinks it remembers.

### Grid: pivot query, not one-row-per-member-type
The spec's grid columns are one row per order item with Member/Spouse/Child
SSN and Spouse/Child name/DOB as separate columns — not one row per
(item, member_type) pair. `MemberInfoGrid\Collection::_initSelect()`
self-joins `vendor_memberinfo` twice (aliased `spouse`, `child`) against
the `primary` row per `order_item_id`, so `MemberInfoGridDataProvider` gets
exactly the row shape the grid needs without reshaping in PHP.

### Masking computed from ciphertext presence, not decrypted values
`MemberInfoGridDataProvider::maskPresence()` returns a fixed mask string
if the encrypted column is non-empty, and never calls the decryptor at
all. The real value is structurally incapable of reaching the browser on
this code path — masking isn't a display-layer choice made after
decrypting, it's the only thing that ever touches this data outside the
password-gated reveal flow.

## Data flow (happy path)

```
PDP: customer checks Spouse/Child (rendered only if the product's
  has_spouse/has_child attribute is enabled)
  -> PersistMemberOptionsPlugin writes member_option_spouse/child
     as quote item options on Quote::addProduct()

Checkout, Member Information step (after Shipping):
  -> MemberInfoConfigProvider tells the step, per item, which
     sub-forms to show (has_spouse/has_child from the item's own options)
  -> customer fills Primary (always) + Spouse/Child (if shown)
  -> frontend validation (required, SSN format, DOB not in future) -- UX only
  -> POST /V1/carts/mine/member-information/:itemId (or /guest-carts/:cartId/...)
  -> MemberInfoManagement::saveForCartItem() re-checks has_spouse/has_child
     server-side, validates, encrypts DOB/SSN, writes vendor_memberinfo_quote

Place Order:
  -> ValidateMemberInfoBeforeSubmitPlugin (before QuoteManagement::submit)
     blocks the order if primary data is missing or orphaned member types
     are staged
  -> QuoteManagement::placeOrderRun() submits the quote, saves the order
     (real order_id / order_item_id now exist), dispatches
     checkout_submit_all_after
  -> AssociateMemberInfoWithOrderObserver moves rows from
     vendor_memberinfo_quote (quote_item_id-keyed) to vendor_memberinfo
     (order_item_id-keyed), then deletes the staged rows

Admin grid (Sales > Member Information):
  -> shows Order Increment ID + masked SSN/DOB + plain names, one row
     per order item, pivoted from the primary/spouse/child rows
  -> "Reveal" prompts for the admin's own password (masked field, native
     Magento prompt widget)
  -> VerifyPassword: verifyIdentity() against the session's own user,
     grants a 15-minute token scoped to (admin_user_id, order_item_id)
  -> Reveal: re-validates the token against that exact row_id + expiry,
     returns decrypted fields only on success
```

## Files by responsibility

| Concern | Files |
|---|---|
| Module registration | `registration.php`, `etc/module.xml`, `composer.json` |
| Product attributes | `Setup/Patch/Data/AddMemberProductAttributes.php` |
| DB schema | `Setup/Patch/Schema/CreateMemberInfoTables.php` |
| PDP checkboxes | `Block/Product/View/MemberOptions.php`, `view/frontend/layout/catalog_product_view.xml`, `view/frontend/templates/product/view/member-options.phtml` |
| Quote item option persistence | `Plugin/Quote/PersistMemberOptionsPlugin.php`, `Plugin/Quote/AddMemberOptionExtensionAttributesPlugin.php`, `etc/extension_attributes.xml` |
| Encryption | `Model/Encryptor/MemberDataEncryptor.php` |
| Data model / repository | `Api/Data/MemberInfoInterface.php`, `Model/MemberInfo.php`, `Model/MemberInfoRepository.php`, `Api/MemberInfoRepositoryInterface.php` |
| Save API (customer + guest) | `Api/MemberInfoManagementInterface.php`, `Model/MemberInfoManagement.php`, `Api/GuestMemberInfoManagementInterface.php`, `Model/GuestMemberInfoManagement.php`, `etc/webapi.xml` |
| Backend validation | `Model/Validator/MemberInfoValidator.php`, `Plugin/Sales/ValidateMemberInfoBeforeSubmitPlugin.php` |
| Checkout step UI | `Block/Checkout/LayoutProcessor.php`, `Model/MemberInfoConfigProvider.php`, `view/frontend/web/js/view/member-information.js`, `view/frontend/web/template/checkout/member-information.html`, `view/frontend/requirejs-config.js` |
| Order association | `Observer/AssociateMemberInfoWithOrderObserver.php`, `etc/events.xml` |
| Quote/order resource models | `Model/ResourceModel/MemberInfoQuote.php`, `Model/ResourceModel/MemberInfoOrder.php` |
| Admin grid | `Ui/Component/Listing/MemberInfoGridDataProvider.php`, `Model/ResourceModel/MemberInfoGrid.php`, `Model/ResourceModel/MemberInfoGrid/Collection.php`, `Model/MemberInfoGridRow.php`, `view/adminhtml/ui_component/memberinfo_listing.xml` |
| Admin reveal flow | `Model/AccessToken/AccessTokenManager.php`, `Controller/Adminhtml/MemberInfo/VerifyPassword.php`, `Controller/Adminhtml/MemberInfo/Reveal.php`, `Ui/Component/Listing/Column/RevealAction.php`, `view/adminhtml/web/js/grid/columns/reveal-actions.js` |
| Admin page shell / menu / ACL | `Controller/Adminhtml/MemberInfo/Index.php`, `view/adminhtml/layout/vendor_memberinfo_memberinfo_index.xml`, `etc/adminhtml/menu.xml`, `etc/adminhtml/routes.xml`, `etc/acl.xml` |
| Token cleanup | `Cron/PurgeExpiredAccessTokens.php`, `etc/crontab.xml` |
| DI wiring | `etc/di.xml`, `etc/frontend/di.xml` |

## Installation

From the Magento root (`/Applications/MAMP/htdocs/mage249` in this
environment), with PHP 8.3 on the `PATH` for CLI commands:

```bash
php=/Applications/MAMP/bin/php/php8.5.2.3.30/bin/php

$php bin/magento module:enable Vendor_MemberInfo
$php -dmemory_limit=-1 bin/magento setup:upgrade
$php -dmemory_limit=-1 bin/magento setup:di:compile   # optional in default mode; required for production mode
$php bin/magento cache:flush
```

`setup:upgrade` runs both patches (`AddMemberProductAttributes`,
`CreateMemberInfoTables`), so no separate schema step is needed.

**If a controller/model constructor signature changes** after the module
is already installed, `cache:flush` alone is not enough — Magento's
compiled DI metadata (`generated/code/`, `generated/metadata/`) caches the
old constructor argument list and will throw a `TypeError` on the next
request. Clear it explicitly:

```bash
rm -rf generated/code/* generated/metadata/*
$php bin/magento cache:flush
```

### Verify the install

```bash
$php bin/magento module:status Vendor_MemberInfo
# Vendor_MemberInfo : Module is enabled
```

```sql
SHOW TABLES LIKE 'vendor_memberinfo%';
-- vendor_memberinfo, vendor_memberinfo_access, vendor_memberinfo_quote

SELECT attribute_code FROM eav_attribute WHERE attribute_code IN ('has_spouse', 'has_child');
-- both rows present
```

### Post-install setup

1. **Product**: edit any simple product, enable Has Spouse / Has Child
   under the General attribute group.
2. **Admin ACL**: grant `Vendor_MemberInfo::view` (grid) and/or
   `Vendor_MemberInfo::reveal_sensitive` (password/reveal) to the relevant
   admin role — Administrators has both by default via `Magento_Backend::all`.
3. **Cron**: confirm Magento's cron is running so
   `vendor_memberinfo_purge_expired_tokens` actually fires every 15
   minutes (`bin/magento cron:run` manually if testing without a cron
   daemon).

## Manual test checklist

- **PDP**: attribute disabled → checkbox absent. Attribute enabled →
  checkbox present, selectable.
- **Checkout**: step appears between Shipping and Payment/Review. Sub-forms
  match what was selected on the PDP. Deselecting-then-reselecting on a
  second visit to the PDP is reflected correctly (config provider re-reads
  from the quote item's own options each page load).
- **Order placement**: blocked with a clear error if primary data was
  never saved for an item.
- **Multi-item order**: two products with different member info in the
  same order — grid shows two separate rows, no cross-contamination.
- **Encryption**: `SELECT dob_encrypted, ssn_encrypted FROM vendor_memberinfo`
  — never plaintext.
- **Admin grid**: SSN/DOB columns masked by default; names and Order
  Increment ID plain.
- **Reveal flow**: wrong password rejected; correct password reveals data;
  a second click within 15 minutes on the *same* row skips the prompt; a
  token from row A rejected when tried against row B (can't be triggered
  from the UI, but `AccessTokenManager::isValid()` enforces it structurally);
  after 15 minutes, next Reveal re-prompts.

## Debugging log (grid page rendered blank)

Kept here because the fix isn't obvious from the file list alone. The
admin grid page loaded the header/menu/footer but the entire content
region was empty, with no PHP or JS error anywhere. Root cause: Magento
names layout XML files after the **route id**, not the URL frontName. The
route is declared as:

```xml
<route id="vendor_memberinfo" frontName="memberinfo">
```

so the generated layout handle is `vendor_memberinfo_memberinfo_index`,
not `memberinfo_memberinfo_index`. The file was originally named for the
frontName and silently never merged — confirmed by temporarily logging
`$resultPage->getLayout()->getUpdate()->getHandles()` inside the
controller, which printed the real handle name directly. Renamed to
`view/adminhtml/layout/vendor_memberinfo_memberinfo_index.xml` and the
page rendered immediately.

A second, smaller bug in the same area: the grid's `<dataSource>` node was
missing `component="Magento_Ui/js/grid/provider"`. Without it, the page
shell renders but the client-side component that actually issues the
`mui/index/render` AJAX call never gets instantiated — grid stays empty
with no error, since nothing ever fails, nothing ever asks either.
