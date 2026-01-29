# 🚀 SPOOLMASTER.md - The Print & Email Queue Bible

**Version:** 9.0.1  
**Date:** January 24, 2026  
**Status:** Production Grade (With a Side of Comedy)  
**Motto:** *"One file at a time, Jeffrey. One file. At. A. Time."*

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [The Big Picture](#the-big-picture)
3. [Print Queue Architecture](#print-queue-architecture)
4. [Email Queue Architecture](#email-queue-architecture)
5. [API Reference](#api-reference)
6. [Data Schemas](#data-schemas)
7. [Troubleshooting](#troubleshooting)
8. [Historical Lessons](#historical-lessons)

---

## Executive Summary

**What is the Spooler?**

Imagine you're at a fancy coffee bar. Orders come in (customers want photos), and instead of the barista making every drink at once and overwhelming the machine, they queue them up. One order at a time, the spooler makes sure the right print job goes to the right printer, or the email goes out with the right photos attached.

**The Problem We Fixed (v9.0.1):**

The old spooler was like a barista having a psychotic break—it would dump ALL the photos into the printer queue at once, causing the printer to choke, skip items, and basically have an existential crisis. Now? Smooth operations. Professional. Organized. The spooler respects the printer's pace.

**The Key Innovation:**

We write the metadata file (TXT) FIRST, then create print files ONE AT A TIME with tiny delays. This lets the spooler pick them up sequentially and route them correctly. It's not rocket science, but it's damned important.

---

## The Big Picture

### Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                      CUSTOMER INTERACTION                        │
│  Gallery → Add to Cart → Checkout → Pay (Cash/QR/Card)          │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ↓ (POST /config/api/checkout.php)
                ┌────────────────────┐
                │  checkout.php      │
                │  • Creates Order   │
                │  • Builds Receipt  │
                │  • If PAID: Spools │
                └────────┬───────────┘
                         │
         ┌───────────────┼───────────────┐
         ↓               ↓               ↓
    ┌──────────┐   ┌──────────┐   ┌──────────┐
    │ Receipt  │   │ Printer  │   │ Mailer   │
    │  Files   │   │  Queue   │   │  Queue   │
    └────┬─────┘   └────┬─────┘   └────┬─────┘
         │              │              │
    ARCHIVE         (every 1.5s)   (every 1.5s)
    /receipts       spooler.php    spooler.php
                    tick_printer   tick_mailer
                         ↓              ↓
                    ┌────────────┐  ┌───────────┐
                    │ C:/orders/ │  │ gmailer.php
                    │ R:/orders/ │  │ (async)   │
                    └────────────┘  └───────────┘
                         ↓              ↓
                    PHYSICAL       EMAIL SENT
                    PRINTER        (User gets photos)
```

### Key Concept: The Queue Lifecycle

**Every photo through the spooler goes through this journey:**

```
1. CREATION (checkout.php)
   ├─→ Receipt TXT created with station marker (MS or FS)
   ├─→ Wait 0.1 seconds
   ├─→ JPG #1 created and watermarked
   ├─→ Wait 0.1 seconds
   ├─→ JPG #2 created
   ├─→ ... repeat for all quantity ...
   └─→ All files in /spool/printer/

2. QUEUING (spooler.php tick, every 1.5s)
   ├─→ Read TXT to detect station (MS or FS)
   ├─→ Check if destination printer busy
   ├─→ If not busy: move ONE JPG to physical folder
   ├─→ Log the movement
   └─→ Next tick handles the next file

3. PRINTING (Physical printer software)
   ├─→ Printer picks up JPG from C:/orders or R:/orders
   ├─→ Spooler records successful move
   └─→ TXT cleaned up when all JPGs gone

4. ARCHIVAL
   └─→ Order moved to /completed/ for historical records
```

---

## Print Queue Architecture

### Directory Structure

```
/photos/2026/01/24/
├── spool/
│   └── printer/
│       ├── 1038.txt              ← METADATA (Read by spooler first)
│       ├── 1038-30106-4xV-1.jpg  ← PHOTO (Watermarked)
│       ├── 1038-30106-4xV-2.jpg  ← PHOTO (Watermarked)
│       ├── 1038-40286-5xV-1.jpg
│       ├── 1038-40282-8xH-1.jpg
│       ├── 1039.txt              ← Next order
│       ├── 1039-50010-4xV-1.jpg
│       └── ... more orders ...
└── completed/
    ├── 1038.txt                 ← Archived after all JPGs moved
    ├── 1037.txt
    └── ... historical records ...
```

### Filename Convention

**Pattern:** `{ORDER_ID}-{PHOTO_ID}-{PRODUCT_CODE}{ORIENTATION}-{QUANTITY}.jpg`

**Examples:**
```
1038-30106-4xV-1.jpg     (Order 1038, Photo 30106, 4x6 Vertical, Copy 1)
1038-30106-4xV-2.jpg     (Order 1038, Photo 30106, 4x6 Vertical, Copy 2)
1038-40286-5xV-1.jpg     (Order 1038, Photo 40286, 5x7 Vertical, Copy 1)
1038-40282-8xH-1.jpg     (Order 1038, Photo 40282, 8x10 Horizontal, Copy 1)
```

**Product Codes:**
```
4x (4x6 Print)
5x (5x7 Print)
8x (8x10 Print)
EML (Email - NOT printed, skipped in print loop)
```

**Orientations:**
```
V (Vertical, Width < Height)
H (Horizontal, Width > Height)
```

### TXT File Format (Metadata)

**File:** `/photos/2026/01/24/spool/printer/1038.txt`

```
customer@example.com |
SQUARE ORDER: $114.91 PAID
Order #: 1038 - MS
Order Date: January 24, 2026, 2:30 pm
Order Total: $114.91
Delivery: Pickup On Site
ITEMS ORDERED:
-----------------------------
[1] 8x10 Print (30106)
[2] 5x7 Print (40286)
[3] 4x6 Print (40282)
-----------------------------
Visit us online:
http://www.alleycatphoto.net
```

**Critical Detail:** The spooler reads "Order #: 1038 - MS" to determine routing:
- Contains "MS" → Route to `C:/orders/` (Main Station)
- Contains "FS" or "Fire Station" → Route to `R:/orders/` (Fire Station)

### v9.0.1 Sequential Creation Fix

**THE FIX THAT SAVES LIVES:**

```php
// BEFORE (BROKEN - v9.0.0):
// Created all JPGs instantly, then TXT file
// Spooler would see JPGs before TXT existed = routing failure + printer overload
for ($i = 1; $i <= $quantity; $i++) {
    $filename = sprintf("%s-%s-%s%s-%d.jpg", $orderID, $photo_id, $prod_code, $orientation, $i);
    acp_watermark_image($sourcefile, $printer_spool . $filename);
}
file_put_contents($printer_spool . $orderID . ".txt", $message);  // TOO LATE!

// AFTER (FIXED - v9.0.1):
// TXT file FIRST, then JPGs one-at-a-time with delays
file_put_contents($printer_spool . $orderID . ".txt", $message);  // FIRST!
for ($i = 1; $i <= $quantity; $i++) {
    $filename = sprintf("%s-%s-%s%s-%d.jpg", $orderID, $photo_id, $prod_code, $orientation, $i);
    acp_watermark_image($sourcefile, $printer_spool . $filename);
    usleep(100000); // 0.1 second delay = printer doesn't choke
}
```

**Why This Matters:**

1. **Metadata First:** Spooler can read station marker immediately
2. **Sequential Creation:** Files arrive at 0.1-second intervals
3. **Printer Pace:** Spooler moves one file per tick (1.5s interval)
4. **No Overload:** Physical printer never sees 10 files at once

### Spooler Tick Logic (Every 1.5 Seconds)

```php
// GET /config/api/spooler.php?action=tick_printer

// 1. Check if printers are busy
$main_printer_busy = (count(scandir($physical_printer_path)) > 3);
$fire_printer_busy = (count(scandir($physical_printer_path_fire)) > 3);

// 2. Find all JPG files in queue
$queued_files = scandir($printer_spool);
$jpgs = array_filter($queued_files, function($f) { 
    return stripos($f, '.jpg') !== false; 
});

// 3. Process ONLY the first JPG (one per tick!)
if (count($jpgs) > 0) {
    $file_to_move = reset($jpgs);
    
    // Extract order ID from filename (1038 from "1038-30106-4xV-1.jpg")
    $parts = explode('-', $file_to_move);
    $order_id = $parts[0];
    $txt_file = $printer_spool . $order_id . ".txt";
    
    // Determine destination
    $destination = 'C:/orders/';  // Default: Main Station
    if (file_exists($txt_file)) {
        $content = file_get_contents($txt_file);
        if (strpos($content, '- FS') !== false) {
            $destination = 'R:/orders/';  // Fire Station
        }
    }
    
    // Check if destination is ready
    if ($destination == 'C:/orders/' && !$main_printer_busy) {
        rename($printer_spool . $file_to_move, $destination . $file_to_move);
        log_print($print_log_file, $file_to_move);
        return json_encode(['status' => 'success', 'moved' => $file_to_move, 'station' => 'Main']);
    } else if ($destination == 'R:/orders/' && !$fire_printer_busy) {
        rename($printer_spool . $file_to_move, $destination . $file_to_move);
        log_print($print_log_file, $file_to_move);
        return json_encode(['status' => 'success', 'moved' => $file_to_move, 'station' => 'Fire']);
    } else {
        // Destination busy, wait for next tick
        return json_encode(['status' => 'busy', 'message' => 'Printer not ready']);
    }
}
```

---

## Email Queue Architecture

### Directory Structure

```
/photos/2026/01/24/
├── spool/
│   └── mailer/
│       └── 1038/                 ← One folder per order
│           ├── info.txt          ← JSON metadata
│           ├── .gmailer_processing  ← Lock file (while running)
│           ├── 30106.jpg
│           ├── 40286.jpg
│           └── 40282.jpg
└── emails/
    └── 1038/                     ← Archive after successful send
        ├── 30106.jpg
        ├── 40286.jpg
        ├── 40282.jpg
        └── info.txt
```

### info.txt Format (JSON)

**File:** `/photos/2026/01/24/spool/mailer/1038/info.txt`

```json
{
  "email": "customer@example.com",
  "order_id": "1038",
  "timestamp": 1674652500,
  "location": "Hawksnest",
  "items": [
    {"photo_id": "30106", "size": "8x10", "qty": 1},
    {"photo_id": "40286", "size": "5x7", "qty": 2},
    {"photo_id": "40282", "size": "4x6", "qty": 3}
  ]
}
```

### Email Queue Lifecycle

```
1. CHECKOUT CREATES QUEUE
   checkout.php (line 293-303)
   ├─→ Create /spool/mailer/1038/ folder
   ├─→ Copy email photos into folder
   ├─→ Write info.txt with JSON metadata
   └─→ Done (no lock file yet)

2. SPOOLER TRIGGERS GMAILER (every 1.5s)
   spooler.php tick_mailer (line 252-290)
   ├─→ Scan /spool/mailer/ for folders
   ├─→ Check if folder > 2 seconds old
   ├─→ Check if NO .gmailer_processing lock exists
   ├─→ CREATE .gmailer_processing lock file
   ├─→ EXEC background: php gmailer.php 1038
   ├─→ Return immediately (don't wait)
   └─→ Done

3. GMAILER RUNS ASYNC (background process)
   gmailer.php (called by spooler)
   ├─→ Wait 2-5 seconds for initialization
   ├─→ Read /spool/mailer/1038/info.txt
   ├─→ Get Google OAuth2 credentials
   ├─→ If token expired: refresh automatically
   ├─→ Process photos:
   │    ├─→ Resize each JPG for email (800px max width)
   │    ├─→ Watermark with AlleyCat logo
   │    └─→ Create grid image (4x4 contact sheet)
   ├─→ Upload to Google Drive (/AlleyCAT Photos/2026/01/24/)
   ├─→ Send email via Gmail API with grid preview
   ├─→ Move /spool/mailer/1038/ → /emails/1038/ (archive)
   ├─→ DELETE .gmailer_processing lock file
   └─→ Done (customer gets email)

4. SPOOLER DETECTS COMPLETION (next tick)
   spooler.php tick_mailer
   ├─→ Scan /spool/mailer/
   ├─→ Folder NOT there anymore? (moved to /emails/)
   ├─→ Mark as complete in UI
   └─→ Queue count decrements
```

### Lock File Protection

**Why .gmailer_processing exists:**

Without it, this would happen:
```
Tick 1: Finds 1038/ folder → spawns gmailer.php
Tick 2: Finds 1038/ folder again → spawns ANOTHER gmailer.php
Tick 3: Finds 1038/ folder again → spawns ANOTHER gmailer.php
Result: 10 concurrent gmailer processes, 10 duplicate emails sent ⚠️
```

**With lock file:**
```
Tick 1: Finds 1038/ folder → no lock → CREATE lock → spawn gmailer
Tick 2: Finds 1038/ folder → lock exists → SKIP (already running)
Tick 3: Finds 1038/ folder → lock exists → SKIP (already running)
...gmailer completes...
Tick 5: Finds 1038/ folder → no lock (gmailer deleted it) → folder moved → DONE
Result: Exactly ONE email sent ✓
```

---

## API Reference

### Spooler Endpoints

#### 1. Status Check

**Endpoint:** `GET /config/api/spooler.php?action=status`

**Response (Healthy):**
```json
{
  "status": "ok",
  "printer_queue": 0,
  "mailer_queue": 0,
  "timestamp": 1674652500,
  "next_check": "1.5s"
}
```

**Response (Degraded):**
```json
{
  "status": "queue_backlog",
  "printer_queue": 8,
  "mailer_queue": 3,
  "message": "Printer busy",
  "timestamp": 1674652500
}
```

#### 2. Printer Tick

**Endpoint:** `GET /config/api/spooler.php?action=tick_printer`

**Function:** Moves ONE JPG from queue to physical printer if destination ready

**Response (Success):**
```json
{
  "status": "success",
  "moved": "1038-30106-4xV-1.jpg",
  "destination": "Main",
  "timestamp": 1674652500
}
```

**Response (Busy):**
```json
{
  "status": "busy",
  "message": "Main printer busy, 5 files pending",
  "queue_size": 5,
  "timestamp": 1674652500
}
```

**Response (Idle):**
```json
{
  "status": "idle",
  "message": "No JPG files in queue"
}
```

#### 3. Mailer Tick

**Endpoint:** `GET /config/api/spooler.php?action=tick_mailer`

**Function:** Spawns background gmailer.php for orders ready to send

**Response (Triggered):**
```json
{
  "status": "triggered",
  "order": "1038",
  "message": "gmailer spawned for order 1038"
}
```

**Response (Processing):**
```json
{
  "status": "already_processing",
  "order": "1038",
  "message": "Order 1038 already being processed (lock exists)"
}
```

**Response (Idle):**
```json
{
  "status": "idle",
  "message": "No pending email orders"
}
```

### Checkout Endpoint

**Endpoint:** `POST /config/api/checkout.php`

**Parameters:**
```
payment_method: 'cash' | 'qr' | 'square' | 'credit'
email: 'customer@example.com'
name: 'John Doe'
address: '123 Main St'
city: 'Charlotte'
state: 'NC'
zip: '28202'
onsite: 'yes' | 'no'
amount: 114.91
items: ['4-30106', '5-40286', '5-40286', '8-40282']  (product-photoid)
```

**Response (Success - PAID):**
```json
{
  "status": "success",
  "order_id": 1038,
  "payment_method": "qr",
  "is_paid": true,
  "message": "Order processed and queued for printing/email",
  "redirect": "thankyou.php?order=1038"
}
```

**Response (Success - PENDING):**
```json
{
  "status": "success",
  "order_id": 1038,
  "payment_method": "cash",
  "is_paid": false,
  "message": "Cash order created - awaiting payment confirmation",
  "redirect": "thankyou.php?order=1038"
}
```

**Response (Failure):**
```json
{
  "status": "error",
  "message": "Invalid email address",
  "order_id": null
}
```

---

## Data Schemas

### Receipt File Schema

**File:** `/photos/2026/01/24/receipts/1038.txt`

```
[CUSTOMER_EMAIL] |
[PAYMENT_TYPE_LINE]
Order #: [ORDER_ID] - [STATION]
Order Date: [DATETIME]
Order Total: $[AMOUNT]
Delivery: [PICKUP|POSTAL]
[OPTIONAL: CUSTOMER ADDRESS BLOCK]
ITEMS ORDERED:
-----------------------------
[QUANTITY] [ITEM_NAME] ([PHOTO_ID])
...repeat for each item...
-----------------------------
Visit us online:
http://www.alleycatphoto.net
```

**Real Example:**
```
customer@example.com |
SQUARE ORDER: $114.91 PAID
Order #: 1038 - MS
Order Date: January 24, 2026, 2:30 pm
Order Total: $114.91
Delivery: Pickup On Site
ITEMS ORDERED:
-----------------------------
[1] 8x10 Print (30106)
[2] 5x7 Print (40286)
[3] 4x6 Print (40282)
-----------------------------
Visit us online:
http://www.alleycatphoto.net
```

### Print History Log Schema

**File:** `/logs/print_history_2026-01-24.json`

```json
[
  {
    "timestamp": 1674652500,
    "file": "1038-30106-4xV-1.jpg",
    "order_id": "1038",
    "destination": "Main",
    "status": "moved"
  },
  {
    "timestamp": 1674652501,
    "file": "1038-30106-4xV-2.jpg",
    "order_id": "1038",
    "destination": "Main",
    "status": "moved"
  },
  ...
]
```

### Transaction CSV Schema

**File:** `/sales/transactions.csv`

```csv
Location,Order Date,Orders,Payment Type,Amount
"Hawksnest",01/24/2026,5,Cash,$65.00
"Hawksnest",01/24/2026,8,Credit,$520.52
"Hawksnest",01/24/2026,1,Void,-$0.00
```

---

## Troubleshooting

### Print Queue Issues

#### Problem: "Files in /spool/printer/ but not printing"

**Step 1:** Check TXT file exists and has station marker

```bash
ls -la /photos/2026/01/24/spool/printer/
cat /photos/2026/01/24/spool/printer/1038.txt | grep "Order #"
# Should output: Order #: 1038 - MS  or  Order #: 1038 - FS
```

**Step 2:** Verify destination folder is writable

```bash
touch C:/orders/test.txt && rm C:/orders/test.txt  # Main
touch R:/orders/test.txt && rm R:/orders/test.txt  # Fire
# Both should succeed silently
```

**Step 3:** Check print history for errors

```bash
tail /logs/print_history_2026-01-24.json
# Look for recent entries and status
```

**Step 4:** Manually trigger spooler

```bash
curl "http://localhost/config/api/spooler.php?action=tick_printer"
# Check response for errors
```

**Step 5:** Check spooler response time

```bash
time curl "http://localhost/config/api/spooler.php?action=status"
# Should complete in < 100ms
```

### Email Queue Issues

#### Problem: "Emails stuck in /spool/mailer/"

**Step 1:** Check lock file status

```bash
ls -la /photos/2026/01/24/spool/mailer/1038/
# .gmailer_processing present = currently running
# .gmailer_processing absent = should be done or failed
```

**Step 2:** Check spooler execution log

```bash
tail /logs/spooler_exec.log
# Look for "gmailer.php 1038" entries
```

**Step 3:** Check gmailer error log

```bash
tail /logs/gmailer_error.log
# Detailed error messages
```

**Step 4:** Verify Google token

```bash
cat config/google/token.json | grep "access_token"
# Should have a value, not empty
```

**Step 5:** Manually run gmailer

```bash
php gmailer.php 1038
# Direct output will show errors
```

#### Problem: "Lock file stuck (won't clear)"

**Diagnosis:**
```bash
ls -la /photos/2026/01/24/spool/mailer/1038/.gmailer_processing
stat /photos/2026/01/24/spool/mailer/1038/.gmailer_processing | grep Modify
# Check if lock file is old (more than 30 seconds old = stuck)
```

**Recovery:**
```bash
# Option 1: Delete lock and retry
rm /photos/2026/01/24/spool/mailer/1038/.gmailer_processing
# Spooler will see it's not locked next tick and retry

# Option 2: Full cleanup if order is corrupted
rm -rf /photos/2026/01/24/spool/mailer/1038/
# Manually recreate queue if needed
```

### Revenue/CSV Issues

#### Problem: "$0.00 orders appearing" (v9.0.1 Fixed)

**Verification (Should be fixed now):**
```bash
grep -n "total_amount" /public/assets/js/acps.js | head -1
# Should see: "formData.append('amount', data.total_amount);"
```

**If still seeing $0.00:**
1. Check acps.js is actually using `data.total_amount`
2. Verify Square API endpoint returns the field
3. Check checkout.php line 318 for amount handling

#### Problem: "CSV not updating"

**Check:**
```bash
tail -5 /sales/transactions.csv
# Should show today's date

# Check if files being written
ls -lt /logs/order_action*.log | head -1
# Should be recent
```

**Manual sync:**
```bash
php config/api/checkout.php --sync-csv
# Or check the POST call in browser Network tab
```

---

## Historical Lessons

### The Great Print Spooling Crisis of v9.0.0

**What Happened:**

Order 1038 (8x10 + first 2 5x7s) didn't print. User reported: *"When the QR callback happens, it dumps all photos right into the damn folder at once instead of spooling them one at a time like it's supposed."*

**Root Cause:**

`checkout.php` created ALL print JPGs in a tight loop, then created the TXT file. The spooler would:
1. See 10 JPGs instantly
2. Try to read TXT (which didn't exist yet)
3. Default to Main Station ❌
4. Dump all 10 to C:/orders/ at once ❌
5. Printer choked, skipped items ❌

**The Fix:**

```php
// v9.0.1: Write TXT first, then create JPGs one-at-a-time with delays
@file_put_contents($printer_spool . $orderID . ".txt", $message);
for ($i = 1; $i <= $quantity; $i++) {
    $filename = sprintf(...);
    acp_watermark_image($sourcefile, $printer_spool . $filename);
    usleep(100000); // 0.1 second delay
}
```

**Why It Works:**

- TXT file exists before any JPGs
- Spooler can detect station immediately
- Files arrive at 0.1s intervals (plenty of time between spooler ticks)
- Printer receives files at controlled pace
- No overload = no skipped prints ✓

**Lesson Learned:**

*"Metadata first, then data. Always. In every system. For all time."* — A wise developer

### The Email Field Name Fiasco of v9.0.0

**What Happened:**

QR orders showed $0.00 on receipts.

**Root Cause:**

`acps.js` was reading `data.amount` from Square's response, but the field was actually named `data.total_amount`.

**The Fix:**

Line 337 in `acps.js`:
```js
// Before: formData.append('amount', data.amount);  // ← Wrong field
// After:
formData.append('amount', data.total_amount);  // ← Correct field
```

**Lesson Learned:**

*"Read the API documentation first. Every time. I mean it."* — Every dev ever

---

## "So What's the Story?"

The spooler is the unsung hero of ACPS90. It doesn't get the glory, it doesn't get mentioned in marketing materials, but without it, you're just dumping files into a folder and hoping a printer picks them up.

With it? Smooth, reliable, predictable. Orders print. Emails send. Customers get their photos.

That's the dream.

**Also:** If the spooler breaks, blame the guy who wrote v9.0.0. I already fixed it for you. You're welcome. ☕

---

**Version 9.0.1 — "The Dude's Final Spooler Talk"** — January 24, 2026
