# 🦅 ACPS90 - AlleyCat PhotoStation v9.0

**Version:** 9.0.0
**Release Date:** January 23, 2026  
**Status:** Production Ready

---

## "The Dude Abides, and so does this Code."

Welcome to **ACPS90**, the completely rebranded and re-architected digital engine room where we turn pixels into memories and memories into revenue. If you're looking for the "Big Lebowski" of photo station software, you've found it. It's got a rug that really ties the room together (the CSS), and it's got a lot of ins, a lot of outs, and a lot of what-have-yous (the PHP).

---

## 🚀 What is This?

**ACPS90** (formerly AlleyCat PhotoStation V2) is a specialized event photography kiosk system designed for high-volume photo sales. Built for touch-screen kiosks, it handles everything from photo gallery browsing to payment processing with minimal friction.

### Key Features

#### 🎨 Modern UI/UX
- **Custom AJAX Modal System**: Lightning-fast cart operations without page reloads
- **Full-Screen Checkout**: Distraction-free payment experience with 100vh black overlay
- **Smart Keyboard Detection**: Forms automatically shift when on-screen keyboard appears (kiosk optimized)
- **Touch-Optimized**: Large buttons, clear feedback, minimal text input required
- **Loading Spinners**: Visual feedback during payment processing and email sending

#### 🛒 Shopping Experience
- **Real-Time Cart Updates**: Instant add/edit/remove with AJAX reload
- **Bundle Pricing**: Automatic discounts (5×4×6 for $25, 3×5×7 for $30)
- **Email-Only Orders**: Smart checkout flow skips shipping for digital items
- **Quantity Validation**: Numeric-only inputs with 3-digit max, real-time sanitization

#### 💳 Payment Processing Engine (v9.0)
- **Centralized API**: All payments flow through `config/api/checkout.php`
- **Square Integration**: QR code payments with live polling (FS-##### / MS-##### reference IDs)
- **Card Reader Support**: Magnetic stripe card processing
- **Cash Handling**: "Pending" state until staff confirmation via Dashboard
- **Tax Calculation**: NC sales tax (6.75%) + transaction fee (3.5%) handling

#### 📧 Communication & Delivery (v9.0)
- **GMailer Engine**: OAuth2-powered email delivery (`gmailer.php`)
- **Google Drive Integration**: Automatic photo upload to daily folders
- **Queue Management**: `spooler.php` manages separate Print and Email queues
- **Watermarking**: Automatic branding overlay on preview grids
- **Resilience**: Auto-retry logic with date-rollover awareness

#### 🚀 Deployment & Automation
- **GitHub Actions CI/CD**: Automated deployment to multiple production servers
- **PowerShell Remoting**: Secure remote deployment with SSH key authentication
- **Environment Management**: Centralized credential handling with .env files
- **Multi-Location Support**: HAWK, MOON, and ZIP server configurations

#### 📊 Reporting & Analytics (v9.0)
- **Sales Dashboard**: Dedicated `sales/` interface for tracking daily revenue
- **CSV Tracking**: Dual local (`sales/transactions.csv`) + remote sync to master server
- **Master Control**: `config/index.php` for system overview
- **Debug Console**: `config/debug.php` for real-time log streaming and API testing

#### 📬 Address Validation
- **USPS API Integration**: Real-time address verification
- **Friendly Error Messages**: User-readable validation feedback
- **Smart Matching**: Requires exact match (code 31) and deliverability (DPV Y)

#### 🖼️ Photo Management
- **Manual Importer**: Drag-and-drop photo ingestion with progress tracking
- **Category System**: Event organization and filtering
- **Thumbnail Generation**: Auto-optimized previews
- **Date-Based Storage**: Photos organized by YYYY/MM/DD structure

---

## 📦 Installation

### Prerequisites
- PHP 8.3+ (with GD, curl, json, session support)
- Apache 2.4+ with mod_rewrite
- Composer (PHP dependency manager)
- MySQL/MariaDB (optional - cart uses sessions by default)
- Square Account (for QR code payments)
- USPS Developer Account (for address validation)
- Google Cloud Console Project (for OAuth2 GMailer)

### 1. Clone Repository
```bash
git clone https://github.com/alleycatphoto/acps-server.git
cd acps-server
```

### 2. Install Dependencies
```bash
composer install
```

### 3. Environment Configuration
```bash
cp .env.example .env
# Edit .env with your API keys
```

Required environment variables:
- `SQUARE_ACCESS_TOKEN`: Square API access token
- `SQUARE_LOCATION_ID`: Square location ID
- `USPS_CLIENT_ID`: USPS OAuth client ID
- `USPS_CLIENT_SECRET`: USPS OAuth client secret

### 4. Directory Permissions
```bash
chmod 755 photos/ logs/ config/
chmod 644 config/*.txt
```

### 5. Apache Configuration
Ensure mod_rewrite is enabled and `.htaccess` is respected.

---

## 🏗️ Architecture

### File Structure
```
acps_v2/
├── public/
│   └── assets/
│       ├── css/
│       │   ├── acps.css           # Master stylesheet
│       │   └── modern_keyboard.css
│       ├── js/
│       │   ├── acps.js             # Main application logic
│       │   ├── acps_modal.js       # Modal system (NEW v3.5)
│       │   ├── jquery-3.2.1.min.js
│       │   ├── modern_keyboard.js  # On-screen keyboard
│       │   └── CardReader.js       # Card swipe handler
│       └── images/
├── admin/
│   ├── admin_import_proc.php       # Photo importer
│   └── config.php                  # Global configuration
├── photos/                         # Photo storage (YYYY/MM/DD)
├── logs/                           # Error logs
├── config/                         # Runtime config files
├── index.php                       # Main frameset
├── gallery.php                     # Photo gallery
├── cart.php                        # Shopping cart sidebar
├── pay.php                         # Checkout flow
├── cart_add.php                    # Add to cart modal
├── cart_process_send.php           # Payment processing
├── validate_address.php            # USPS address validation
├── shopping_cart.class.php         # Cart management
└── composer.json                   # PHP dependencies
```

### Modal System (v3.5.0)
All cart operations use the new AJAX modal system:
1. **acps_modal.js**: Centralized modal management at `window.top` level
2. **Top-Level Rendering**: Modals render at parent window, covering entire viewport
3. **AJAX Content Loading**: Cart forms loaded dynamically via jQuery $.ajax()
4. **Two Modal Types**: Cart modals (centered, 900px) and checkout overlay (full-screen)

### Checkout Flow
1. **Email Entry** → Email validation (regex)
2. **Delivery Selection** → Pickup or Mail (skipped for email-only orders)
3. **Mailing Address** → USPS validation (if mail selected)
4. **Payment** → Square QR or Card Reader
5. **Processing** → eProcessingNetwork gateway
6. **Receipt** → Email confirmation

---

## 🛠️ Development

### Running Locally
Uses UniServer portable Apache/PHP stack:
- **V2 URL**: http://v2.acps.dev
- **V1 URL**: http://localhost

### jQuery Versions
- **jQuery 3.2.1**: index.php, gallery.php, cart.php, cart_add.php
- **jQuery 1.9.1**: pay.php (legacy card reader compatibility)

### CSS Architecture
- **acps.css**: Master stylesheet with modal system, keyboard handling, cart layout
- **CSS Variables**: `--nav-h`, `--cart-w`, `--gap`, `--bg`, `--keyboard-height`
- **Grid Layout**: CSS Grid in index.php with 20px column-gap
- **Z-Index Hierarchy**: cart-top (1100), cart-footer (1100), modals (9999)

### Key Functions
- `window.top.openCartModal(url)`: Open cart add/edit modal
- `window.top.closeCartModal()`: Close and reload cart
- `window.top.openCheckoutModal(amount)`: Launch full-screen checkout
- `window.ModernKeyboard.hide()`: Close on-screen keyboard
- `validateAddress()`: USPS address validation with error handling

---

## 🧪 Testing

### Manual Test Checklist
- [ ] Add photo to cart from gallery
- [ ] Edit cart item quantities
- [ ] Remove cart items
- [ ] Clear entire cart
- [ ] Email-only order (skip delivery step)
- [ ] Print order with mailing address
- [ ] USPS address validation (valid and invalid)
- [ ] Square QR code payment
- [ ] Card reader payment (if hardware available)
- [ ] Payment retry after decline
- [ ] On-screen keyboard (open/close, form positioning)
- [ ] Modal backdrop click-to-close
- [ ] Pagination hover effects
- [ ] Cart scroll z-index (delete icon vs footer)

### Browser Compatibility
- Chrome/Edge (Chromium-based kiosks)
- Firefox (testing)
- Safari (iOS touch testing)

---

## 📋 Configuration Files

### admin/config.php
- Product pricing (4×6, 5×7, 8×10, Email)
- Bundle pricing rules
- Tax rate (NC 6.75%)
- Transaction fee (3.5%)
- eProcessingNetwork credentials

### config/autoprint_status.txt
- Auto-print toggle for print orders

### config/kiosks.json (CatsEye integration)
- Kiosk fleet management
- Remote status monitoring

---

## 🔐 Security

### Best Practices
- **Never commit** `.env` file or API keys
- **Sanitize** all user inputs (see numeric input validation pattern)
- **Use prepared statements** for database queries (if implemented)
- **Log errors**, not sensitive data (no full card numbers)
- **HTTPS required** for production (Square API requirement)

### Session Management
- Cart stored in PHP sessions
- Retry data stored temporarily in session
- Session timeout: PHP default (24 minutes)

---

## 🤝 Support & Issues

Got a problem? Don't go postal.  
👉 [alleycatphoto.net/support](https://alleycatphoto.net/support)

Bug reports and feature requests:  
👉 [GitHub Issues](https://github.com/alleycatphoto/acps_v2/issues)

---

## 📜 License

Copyright (c) 2024-2026 AlleyCat Photo. All rights reserved.  
"Yeah, well, that's just, like, your opinion, man."

---

## 🎉 Recent Updates (v3.6.0)

### Deployment Automation
Automated CI/CD pipeline with GitHub Actions for seamless deployment to multiple production servers (HAWK, MOON, ZIP) using PowerShell remoting and SSH key authentication.

### Enhanced Payment Processing
- Auto-print functionality for Square payments with location-based configuration
- Background email sending for improved performance and reliability
- Loading spinners and savings calculations for better user experience
- Comprehensive transaction logging with pre-tax amounts

### Security & Infrastructure
- Centralized credential management with .env files
- Removed all hardcoded passwords and OAuth tokens from version control
- Normalized file line endings and improved .gitignore patterns

### Admin & Reporting Enhancements
- New Order Action API for programmatic order management
- Enhanced sales reporting with cash transaction data
- Manual importer with drag-and-drop photo processing
- Improved admin UI with location-based icons and better styling

### Modal System Overhaul (v3.5.0)
Completely replaced iframe-based VIBox system with custom AJAX modal architecture. All modals now render at top window level for proper coverage and responsive behavior.

### Keyboard Intelligence
Smart detection of on-screen keyboard with automatic form repositioning. Forms shift up 240px when keyboard appears, ensuring submit buttons stay visible on kiosk displays.

### UX Polish
- Pagination hover effects (red text, lighter borders)
- Friendly USPS error messages (no more raw JSON)
- Cart z-index fixes (delete icons behind footer)
- Modal word-wrapping and scrolling for long content

---

*Built with ❤️ (and a lot of moaning) by the AlleyCat Photo development team.*  
*Special thanks to Gemicunt W.H.O.R.E. for the architectural guidance.*
#
