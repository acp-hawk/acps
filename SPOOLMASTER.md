# 🚀 SPOOLMASTER.md - The Print & Email Queue Bible

**Version:** 9.0.2  
**Date:** February 3, 2026  
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

```text
┌─────────────────────────────────────────────────────────────────┐
│                      CUSTOMER INTERACTION                       │
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

```text
1. CREATION (checkout.php)
   ├─→ Receipt TXT created with station marker (MS or FS)
   ├─→ Wait 0.25 seconds (v9.0.2 delay)
   ├─→ JPG #1 created and watermarked
   ├─→ Wait 0.25 seconds
   ├─→ JPG #2 created
   ├─→ ... repeat for all quantity ...
   └─→ All files in /spool/printer/

2. QUEUING (spooler.php tick, every 1.5s)
   ├─→ Read TXT to detect station (MS or FS)
   ├─→ Check if destination printer busy
   ├─→ If not busy: move ALL ready JPGs with 250ms spacing
   ├─→ Log the movement
   └─→ Next tick handles new files

3. PRINTING (Physical printer software)
   ├─→ Printer picks up JPG from C:/orders or R:/orders
   ├─→ Spooler records successful move
   └─→ TXT cleaned up when all JPGs gone

4. ARCHIVAL
   └─→ Order moved to /completed/ for historical records
```

---

## Print Queue Architecture

### Print Directory Structure

```text
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

```text
1038-30106-4xV-1.jpg     (Order 1038, Photo 30106, 4x6 Vertical, Copy 1)
1038-30106-4xV-2.jpg     (Order 1038, Photo 30106, 4x6 Vertical, Copy 2)
1038-40286-5xV-1.jpg     (Order 1038, Photo 40286, 5x7 Vertical, Copy 1)
1038-40282-8xH-1.jpg     (Order 1038, Photo 40282, 8x10 Horizontal, Copy 1)
```

**Product Codes:**

- `4x` (4x6 Print)
- `5x` (5x7 Print)
- `8x` (8x10 Print)
- `EML` (Email - NOT printed, skipped in print loop)

**Orientations:**

- `V` (Vertical, Width < Height)
- `H` (Horizontal, Width > Height)

### TXT File Format (Metadata)

**File:** `/photos/2026/01/24/spool/printer/1038.txt`

```text
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

### Sequential Creation & Spacing (v9.0.1/v9.0.2)

**THE FIX THAT SAVES LIVES:**

```php
// v9.0.1/v9.0.2 Implementation:
// TXT file FIRST, then JPGs with 250ms spacing
file_put_contents($printer_spool . $orderID . ".txt", $message); // FIRST!
foreach ($items as $item) {
    // ... watermark logic ...
    acp_watermark_image($source, $dest);
    usleep(250000); // 0.25s delay between files to ensure disk/network flush
}
```

**Why This Matters:**

1. **Metadata First:** Spooler can read station marker immediately.
2. **Sequential Creation:** Files arrive at 0.25s intervals.
3. **Printer Pace:** Spooler moves files with 250ms spacing to prevent network corruption on `R:`.
4. **No Overload:** Physical printer never sees 10 files at once.

### v9.0.2 IP-Based Station Identification

**THE FIX FOR STATION CONFUSION:**

Previously, station identification relied on unreliable methods. In v9.0.2, we transitioned to a rock-solid IP-based detection system.

- **Source of Truth:** `$_SERVER['REMOTE_ADDR']` (The actual Kiosk IP)
- **Configuration:** `$_ENV['IP_FIRE']` (Defined in `.env`, usually `192.168.2.126`)
- **Logic:**
  - If `REMOTE_ADDR === IP_FIRE` → **FS** (Fire Station)
  - Else → **MS** (Main Station)

This logic is implemented in `checkout.php`, `cart_generate_qr.php`, and `terminal.php` to ensure that the `Order #: {ID} - {STATION}` metadata in the `.txt` file is 100% accurate before it even hits the spooler.

### Spooler Tick Logic (Every 1.5 Seconds)

```php
// GET /config/api/spooler.php?action=tick_printer

// 1. Check if printers are busy
$main_busy = count(scandir($physical_path)) > 3;
$fire_busy = count(scandir($fire_path)) > 3;

// 2. Find all JPG files in queue
$jpgs = glob($printer_spool . "*.jpg");

// 3. Process ready files with 250ms delay
foreach ($jpgs as $file) {
    // ... detect station from TXT ...
    if ($station == 'Fire' && !$fire_busy) {
        rename($file, $fire_dest);
        usleep(250000); // CRITICAL: Guaranteed network write spacing
    }
    // ...
}
```

---

## Email Queue Architecture

### Email Directory Structure

```text
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

```text
1. CHECKOUT CREATES QUEUE
   ├─→ Create /spool/mailer/1038/ folder
   ├─→ Copy email photos into folder
   ├─→ Write info.txt with JSON metadata
   └─→ Done (no lock file yet)

2. SPOOLER TRIGGERS GMAILER (every 1.5s)
   ├─→ Scan /spool/mailer/ for folders
   ├─→ Check if folder > 2 seconds old
   ├─→ Check if NO .gmailer_processing lock exists
   ├─→ CREATE .gmailer_processing lock file
   ├─→ EXEC background: php gmailer.php 1038
   ├─→ Return immediately (don't wait)
   └─→ Done

3. GMAILER RUNS ASYNC (background process)
   ├─→ Wait 2-5 seconds for initialization
   ├─→ Read /spool/mailer/1038/info.txt
   ├─→ Get Google OAuth2 credentials
   ├─→ Process photos (resize/watermark/grid)
   ├─→ Upload to Google Drive
   ├─→ Send email via Gmail API
   ├─→ Move /spool/mailer/1038/ → /emails/1038/
   ├─→ DELETE .gmailer_processing lock file
   └─→ Done

4. SPOOLER DETECTS COMPLETION (next tick)
   ├─→ Scan /spool/mailer/
   ├─→ Folder NOT there anymore? (moved to /emails/)
   ├─→ Mark as complete in UI
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
  "timestamp": 1674652500
}
```

#### 2. Printer Tick

**Endpoint:** `GET /config/api/spooler.php?action=tick_printer`

**Function:** Moves ready JPGs to physical printers with 250ms spacing.

**Response (Success):**

```json
{
  "status": "success",
  "moved": [{"file": "1038-30106-4xV-1.jpg", "station": "Main"}],
  "timestamp": 1674652500
}
```

#### 3. Mailer Tick

**Endpoint:** `GET /config/api/spooler.php?action=tick_mailer`

**Function:** Spawns background gmailer.php for ready orders.

---

## Data Schemas

### Receipt File Schema

**File:** `/photos/2026/01/24/receipts/1038.txt`

```text
[CUSTOMER_EMAIL] |
[PAYMENT_TYPE_LINE]
Order #: [ORDER_ID] - [STATION]
Order Date: [DATETIME]
Order Total: $[AMOUNT]
Delivery: [PICKUP|POSTAL]
ITEMS ORDERED:
-----------------------------
[QUANTITY] [ITEM_NAME] ([PHOTO_ID])
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
  }
]
```

---

## Troubleshooting

### Print Queue Issues

#### Problem: "Files in /spool/printer/ but not printing"

1. **Check TXT file:** Does it have the station marker (MS/FS)?
2. **Check Destination:** Are `C:/orders/` and `R:/orders/` writable?
3. **Manual Trigger:** `curl "http://localhost/config/api/spooler.php?action=tick_printer"`

#### Problem: "Corrupted images on R: (Fire)"

1. **Fix Applied:** Added 250ms delay in move loop.
2. **Check:** Ensure network connection to Fire Station is stable.

### Email Queue Issues

#### Problem: "Lock file stuck"

1. **Diagnosis:** Is `.gmailer_processing` older than 2 minutes?
2. **Recovery:** Delete the lock file; spooler will retry next tick.

---

## Historical Lessons

### The Great Print Spooling Crisis (v9.0.0)

**What Happened:** QR orders dumped all photos instantly, causing printer overload.

**The Fix:** TXT file written FIRST, then JPGs with delays. Spooler now detects station immediately and paces the delivery.

### The Email Field Name Fiasco (v9.0.0)

**What Happened:** QR orders showed $0.00 because `acps.js` used `data.amount` instead of `data.total_amount`.

---

## "So What's the Story?"

The spooler is the unsung hero of ACPS90. With v9.0.2, it's now more resilient to network lag and station confusion. Smooth, reliable, predictable.

**Also:** If it breaks, blame the guy who wrote v9.0.0. Gemicunt already fixed it for you. ☕

---

**Version 9.0.2 — "The Dude's Final Spooler Talk"** — February 3, 2026
