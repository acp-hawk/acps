# ACPS90 - AlleyCat PhotoStation v9.0 - AI Coding Instructions

> Event photography kiosk system processing payments and managing photo sales workflows.
> **Version:** 9.0.2 | **Status:** Production | **PHP 8.3+** | **Session-based** | **AJAX-driven**

## Architecture Overview

### Core Stack
- **Frontend:** jQuery 3.2.1 + Custom AJAX modal system (`acps_modal.js`) + CSS Grid layout
- **Backend:** PHP 8.3 + Composer + PHP-Dotenv for env config
- **Payments:** Square SDK (QR codes) + eProcessingNetwork (card processing)
- **Email:** GMailer (OAuth2) with Google Drive integration + Monolog logging
- **Storage:** Session-based cart (no database), photos in `YYYY/MM/DD` directories
- **Actions:** Centralized API in `config/api/order_action.php` (PAID/VOID/EMAIL)

### Request Flow
```text
User (Kiosk) → index.php (Grid layout) → gallery.php (iframe) →
Add to cart → acps_modal.js opens cart_add.php AJAX → Shopping_Cart manages session →
Checkout → pay.php full-screen modal → config/api/checkout.php centralizes payment/email/spooling
```

## Key Patterns & Patterns

### 1. Shopping Cart Session Management
- **Location:** [shopping_cart.class.php](../shopping_cart.class.php) — pseudo-relational pricing model
- **Model:** Order codes = `{ProductType}-{PhotoID}` (e.g., `4x6-12345`, `EML-67890`)
- **Persistence:** PHP sessions — NOT database
- **Pricing Logic:**
  - Print base: 4×6 ($8), 5×7 ($12), 8×10 ($20)
  - Email: $15 standalone, $7 if 5+ total, $3 if bundled with print of same photo
  - **CRITICAL:** Bundle discounts applied in `cart.php`, NOT in class

**Example iteration pattern:**
```php
foreach ($Cart->items as $order_code => $quantity) {
    list($prod_code, $photo_id) = explode('-', $order_code);
    if ($quantity > 0) { /* process */ }
}
```

### 2. AJAX Modal System (v3.5+)
- **Architecture:** Parent window (`window.top`) owns all modals — prevents iframe nesting issues
- **Key Files:** [acps_modal.js](../public/assets/js/acps_modal.js), [acps.css](../public/assets/css/acps.css)
- **Two Modal Types:**
  - **Cart Modal:** 900px centered, for add/edit (calls `cart_add.php`)
  - **Checkout Modal:** Full-screen black overlay, for `pay.php` payment flow

**Entry Points:**
```javascript
// In gallery.php (iframe context) — calls parent via window.top
window.top.openCartModal('/cart_add.php?photo=12345');
window.top.closeCartModal(); // Reloads cart sidebar
window.top.openCheckoutModal(amount); // Full-screen payment
```

**Important:** After cart modifications, ALWAYS call `closeCartModal()` to trigger `cart.php` reload.

### 3. Centralized API Hubs (v9.0.2)
- **Payment Hub:** [config/api/checkout.php](../config/api/checkout.php) — all payment paths converge here
- **Action Hub:** [config/api/order_action.php](../config/api/order_action.php) — centralized PAID/VOID/EMAIL logic
- **Handles:**
  - Order ID generation + receipt creation
  - Station Detection: Uses `REMOTE_ADDR` vs `IP_FIRE` for FS/MS identification
  - Square QR code payments (FS-##### reference)
  - eProcessingNetwork card processing
  - Watermark generation + spooler queuing
  - Email receipt sending (background job)

**Input:** POST with order data → **Output:** JSON `{ success: bool, orderID: string, message: string }`

### 4. Email & Print Queue System
- **GMailer:** [gmailer.php](../gmailer.php) — OAuth2-powered, auto-watermarks photos
- **Spooler:** [config/api/spooler.php](../config/api/spooler.php) — manages Print & Email queues separately
- **Queue Files:** `config/mail_queue.txt` (line-delimited JSON) + `autoprint_status.txt` (toggle)
- **Reliability:** 250ms spacing between file moves to prevent network corruption on `R:`.
- **Resilience:** Auto-retries with date rollover awareness (separate queue if spanning midnight)

### 5. Address Validation (USPS)
- **API:** [validate_address.php](../validate_address.php) — live USPS integration
- **Logic:** Only accepts exact match (code 31) + deliverable (DPV Y)
- **Error UX:** User-friendly "Address not found" messages (no raw JSON)

## Developer Workflows

### Local Development
```bash
# Environment setup
cp .env.example .env  # Edit with SQUARE_ACCESS_TOKEN, USPS_CLIENT_ID, LOCATION_NAME, etc.
composer install      # Load Square SDK, Monolog, PHPMailer, etc.

# Run locally (UniServer portable stack)
# Access: http://v2.acps.dev or http://localhost
```

### Common Tasks
| Task | Command/Path |
|------|--------------|
| Add new product | Edit [admin/config.php](../admin/config.php) pricing array |
| Debug API calls | Check [config/debug.php](../config/debug.php) Master Control console |
| View order history | [sales/index.php](../sales/index.php) + local CSV sync |
| Toggle auto-print | Flip `config/autoprint_status.txt` to '0' or '1' |
| Test payment | Use Square Sandbox with QR polling in `checkout.php` |

### Testing Checklist
1. **Cart flow:** Add/edit/remove items, verify session persistence
2. **Email-only orders:** Skip delivery step correctly (code in `pay.php`)
3. **Mailing address:** Test USPS validation (valid, invalid, edge cases)
4. **Station Routing:** Verify order originating from `IP_FIRE` is tagged as `FS`
5. **Payment retry:** Decline → retry modal → session restoration
6. **On-screen keyboard:** Forms shift 240px up; submit visible on 1080p displays

## Critical Integration Points

### Environment Variables (Required)
```ini
SQUARE_ACCESS_TOKEN=...        # Square payments
SQUARE_LOCATION_ID=...         # Square location for QR codes
IP_FIRE=192.168.2.126         # Fire Station Kiosk IP
USPS_CLIENT_ID=...             # USPS address API
USPS_CLIENT_SECRET=...         # USPS secret
LOCATION_NAME=Hawks Nest        # Displayed in UI
LOCATION_EMAIL=...@alleycatphoto.net
GOOGLE_DRIVE_FOLDER_ID=...    # GMailer upload destination (if used)
```

### File Dependencies
- **Cart calculations:** `shopping_cart.class.php` → `cart.php` (UI) → `config/api/checkout.php` (payment)
- **Order receipt:** `config/api/receipt.php` generates order summary
- **Action hub:** `config/api/order_action.php` manages status updates
- **Email delivery:** `gmailer.php` + `config/api/spooler.php` (async queue)
- **Photos:** Auto-imported to `photos/YYYY/MM/DD/` by `admin/importer/`

## Code Patterns Specific to This Project

### 1. Session Cart Item Updates
ALWAYS use `explode('-', $order_code)` to parse product type and photo ID. Never hardcode indices.

### 2. Price Calculation Stages
- **Stage 1:** Unit price from `Shopping_Cart::getItemPrice()`
- **Stage 2:** Bundle discounts in `cart.php` display (HTML comment: "5×4×6 for $25")
- **Stage 3:** Tax + surcharge in `pay.php` (NC 6.75% + 3.5% fee)

### 3. AJAX Content Loading
All cart modals load via `$.ajax()` from `acps_modal.js`. Never use iframes; always target `window.top` for modal placement.

### 4. State Restoration After Payment
Session keys `retry_email`, `retry_onsite`, `retry_name`, `retry_addr` store payment attempt context. Check `$_GET['retry']` flag in `pay.php`.

## Common Gotchas & Conventions

| Issue | Solution |
|-------|----------|
| Cart data lost after payment | Check session start in `checkout.php` top; session may timeout |
| Modal not appearing | Verify `acps_modal.js` loaded; check browser console for JS errors |
| Email not sending | Check `gmailer.php` OAuth token + Google Drive folder permission |
| Price mismatch | Verify email bundling logic in `shopping_cart.class.php` matches UI |
| USPS validation fails | Ensure city/state/ZIP populated; only exact matches (code 31) accepted |
| Photos not watermarked | Set `LOCATION_LOGO` env var path or verify `alley_logo.png` exists |
| Network corruption on R: | Ensure 250ms spacing in `spooler.php` is active |

## Files to Read First (Priority Order)
1. [config/api/order_action.php](../config/api/order_action.php) — Action Central hub
2. [shopping_cart.class.php](../shopping_cart.class.php) — Core pricing & session model
3. [config/api/checkout.php](../config/api/checkout.php) — Payment centralization
4. [public/assets/js/acps_modal.js](../public/assets/js/acps_modal.js) — Modal architecture
5. [pay.php](../pay.php) — Checkout flow & retry logic
6. [admin/config.php](../admin/config.php) — Pricing config + location setup

## Version Info
- **v9.0.2** (Feb 2026): Centralized action hub, IP-based station routing, network write safety
- **v9.0.0** (Jan 2026): Centralized checkout API, enhanced payment processing, improved admin UI
- **v3.5.0+**: Modal system overhaul (replaced VIBox iframe system)
- Uses **GitHub Actions CI/CD** for deployment to HAWK/MOON/ZIP servers

---
*For deployment, CI/CD, or authorization questions, see README.md. For API details, check config/ folder.*
