# ACPS90 Changelog

> *"The Dude abides, and so does version control."*

## [2026-01-24] - v9.0.1 - The Great Print Spooling Fix

### CRITICAL FIXES

#### 🖨️ Print Spooling Sequential Creation (PRODUCTION ISSUE)
- **Problem:** QR orders dumped ALL print files into spool folder simultaneously, causing printer overload and missed prints
- **Symptom:** Order 1038 (8x10 + first 2 5x7s) didn't print; all files arrived at once
- **Root Cause:** `checkout.php` created all JPGs in tight loop, then TXT file (metadata) at end
- **Fix:** 
  - TXT file written FIRST (line 308)
  - JPGs created one-at-a-time with 0.1s delays using `usleep(100000)` (line 313)
  - Spooler can now read station metadata before any files arrive
  - Files processed sequentially at controlled pace
- **Files:** `config/api/checkout.php` lines 308-318
- **Status:** ✅ TESTED - Prints now flow properly for QR orders

#### 💰 Receipt $0.00 Field Name Mismatch
- **Problem:** QR orders showing $0.00 on receipts
- **Root Cause:** JavaScript looking for wrong Square API response field
- **Fix:** Changed `data.amount` to `data.total_amount` in `public/assets/js/acps.js` line 337
- **Files:** `public/assets/js/acps.js`
- **Status:** ✅ TESTED - Amounts now display correctly

#### 📧 Email Queue Folder Naming
- **Problem:** Email spooler entries created under customer email address instead of order ID
- **Fix:** Changed `$txtEmail` to `$orderID` in `config/api/checkout.php` line 262
- **Impact:** Prevents orphaned mailer folders and improves organizational clarity
- **Files:** `config/api/checkout.php` line 262
- **Status:** ✅ FIXED - Email queues now organized by order ID

### VALIDATION & TESTING
- ✅ Verified sequential spooling with multiple test orders
- ✅ Confirmed print files flow to correct station (MS/FS) based on receipt metadata
- ✅ Tested receipt amounts match Square totals
- ✅ Verified email queue organization
- ✅ Updated sales CSV: 2 Cash ($41.00) / 24 Credit ($820.83) = $861.83 total

### DOCUMENTATION UPDATES
- ✅ Created comprehensive AGENTS.md (DevOps & Architecture)
- ✅ Created detailed SPOOLMASTER.md (Print/Email Queue Bible)
- ✅ Updated CHANGELOG.md (this file)
- ✅ Updated README.md with v9.0.1 changes and print spooling fix details

---

## [2026-01-23] - v9.0.0 - Complete System Rebranding & Checkout Unification

### MAJOR CHANGES

#### 🎨 Rebranding (AlleyCat PhotoStation V2 → ACPS90)
- Version bumped: 3.5.0 → 9.0.0
- Package renamed: acps-v2 → acps90
- All 20+ files updated with consistent branding
- ✅ 10/10 verification tests passed

#### 💳 Centralized Checkout API (`config/api/checkout.php`)
- **Unified Endpoint:** All payment methods (Cash/Square/QR/Terminal) route through single API
- **Order Creation:** Generates sequential order numbers
- **Receipt Generation:** Creates metadata-rich TXT files with station markers (MS/FS)
- **Auto-Spooling:** PAID orders immediately queue to printer/mailer
- **Sales Tracking:** Updates CSV and syncs to master server

#### 📧 GMailer OAuth2 Implementation
- **Auto-Token Refresh:** Background process automatically renews Google OAuth2 tokens
- **Async Email Sending:** Spooler triggers gmailer.php in background
- **Google Drive Upload:** Photos automatically uploaded to daily folders
- **Status:** Production-ready with lock file protection

#### 🎛️ Spooler Queue System (`config/api/spooler.php`)
- **Print Queue:** Processes printer spool every 1.5 seconds
- **Email Queue:** Manages mailer spooler with async gmailer.php execution
- **Station Detection:** Reads receipt metadata to route to Main (C:/orders) or Fire (R:/orders)
- **One-at-a-Time:** Moves exactly one file per tick to avoid printer overload

---

## [2026-01-17] - v3.7.0 - UI Modernization & Order Management

### Changed
- **Admin Dashboard**: Replaced the "View" dropdown with a new **Tab Navigation** system (Pending, Paid, Void, All) featuring real-time counters.
- **UI Styling**: Implemented uniform black pills for status indicators with distinct colored borders:
  - **Green**: Cash & Paid
  - **Blue**: Square (Credit)
  - **Red**: Void
  - **Grey**: Standard
- **Parsing Logic**: Enhanced `orders.php` to strictly identify "SQUARE ORDER" vs "CASH ORDER" and handle receipts that don't match the standard "DUE" regex, ensuring accurate payment type classification.
- **API Response**: `orders.php` now returns *all* orders for the day by default, allowing the frontend to handle filtering and counting instantly without server round-trips.
- **User Experience**: Removed "Paid/Void" action buttons for orders that are already paid or voided, reducing clutter and preventing accidental double-actions.

### Climax Notes
- The Order Manager is now tight, uniform, and responsive. The tabs let you switch views with a touch, and the pills are perfectly aligned for visual pleasure.

---

**Version 9.0.1 — "They're Gonna Kill That Rug"** — January 24, 2026
