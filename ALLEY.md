# ALLEY: The AlleyCat Photo Station AI Assistant

**Version**: 1.0.0  
**Status**: Production Ready  
**Last Updated**: January 24, 2026

---

## Overview

**ALLEY** is an intelligent AI assistant powered by Google's Gemini 2.5-flash-latest model. She lives in the admin interface as a helpful red question-mark bubble and provides real-time support for:

- Order management (retrieve, reprint, cancel)
- Email delivery troubleshooting and resending
- Print queue diagnostics and recovery
- System health monitoring
- Staff and owner questions

ALLEY combines deep system knowledge with sharp, witty personality - channeling the comedic genius of Big Lebowski, Office Space, and Spaceballs to make admin work less of a drag.

---

## System Architecture

### 1. Core Components

```
Admin Interface (UI)
    ↓
Help Bubble Widget (admin/alley_bubble.html)
    ↓
Alley API Endpoint (config/api/alley.php)
    ↓
Gemini 2.5-flash-latest (Tool Calling)
    ↓
System Tools (File I/O, DB, APIs)
    ↓
Action Log (logs/alley_actions.json)
```

### 2. Tool-Calling Architecture

ALLEY uses JSON Schema-defined tools that Gemini can call autonomously:

**Tool Categories:**

#### A. Order Management Tools
- `get_order_details` - Retrieve full order info, status, items
- `reprint_order` - Move JPGs back to printer queue with fresh TXT
- `cancel_order` - Remove from queue and archive with reason
- `list_recent_orders` - Get last N orders with status
- `update_order_notes` - Add staff notes to order record

#### B. Email Queue Tools
- `get_email_queue_status` - Check pending/stuck emails
- `resend_email` - Re-queue email delivery via gmailer
- `check_email_logs` - Search gmailer_error.log for issues
- `get_email_history` - Retrieve delivery history for customer

#### C. Print Queue Tools
- `get_print_queue` - List pending prints with status
- `check_station_status` - Main (MS) or Fire (FS) printer readiness
- `force_queue_retry` - Retry failed print job
- `get_print_history` - Query print_history JSON log

#### D. System Diagnostics Tools
- `check_disk_space` - Monitor storage on key directories
- `verify_file_permissions` - Check access on spool dirs
- `get_system_logs` - Query error logs
- `test_api_connectivity` - Verify Square, ePN, Google APIs
- `get_configuration` - Read current env/settings

#### E. Recovery Tools
- `create_missing_txt_metadata` - Auto-generate TXT from receipt
- `recover_orphaned_jpg` - Manually route stuck JPG
- `clear_printer_queue` - Force-move all pending to archive
- `reset_autoprint_status` - Toggle print automation on/off

### 3. Knowledge Base

ALLEY has comprehensive knowledge of:

**Folder Structure:**
```
/photos/
├── 2026/01/24/
│   ├── raw/              (Original uploaded JPGs)
│   ├── spool/
│   │   ├── printer/      (Pending prints: *.jpg + *.txt)
│   │   ├── mailer/       (Pending emails)
│   │   └── completed/    (Archived completed jobs)
│   ├── receipts/         (Order receipts, also receipts/fire/)
│   ├── receipts/fire/    (Fire Station receipts)
│   └── orders.txt        (Order ID counter with lock)
├── photostock/           (Stock photos for galleries)
└── receipts/fire/        (Legacy fire station receipts)

/config/
├── api/
│   ├── checkout.php      (Order creation, spooling)
│   ├── spooler.php       (Queue watchdog)
│   ├── alley.php         (ALLEY agent endpoint)
│   └── ...
├── autoprint_status.txt  (1=enabled, 0=disabled)
└── ...

/logs/
├── print_history_*.json  (Print queue log)
└── alley_actions.json    (ALLEY activity log)
```

**Key APIs:**
- Square SDK: Payment verification and processing
- Google Drive OAuth2: Email delivery
- eProcessingNetwork: Credit card swipes
- Gemini 2.5-flash: ALLEY decision engine

**Critical Files:**
- `orders.txt` - Master order ID counter (LOCKED during increments)
- `autoprint_status.txt` - Print automation toggle
- `print_history_*.json` - Immutable print job log
- `info.txt` in spool dirs - Queue metadata

### 4. Data Flow

#### Order Creation Flow
```
Frontend (cart) → checkout.php
  ├─ Verify payment (Square/ePN)
  ├─ Generate Order ID
  ├─ Build receipt (includes "Order #: NNN - MS/FS")
  ├─ Save TXT metadata with station marker
  ├─ Create JPG files with watermark + delay
  ├─ Queue email IF paid
  └─ Queue prints IF autoprint enabled

Spooler (recurring tick)
  ├─ Scan /spool/printer/ for JPGs
  ├─ Read TXT to detect MS (C:/orders) or FS (R:/orders)
  ├─ Check printer availability
  ├─ Move ONE file per tick
  └─ Log to print_history.json
```

#### Email Delivery Flow
```
Spooler scans /spool/mailer/
  ├─ For each order folder
  ├─ Check info.txt for email address
  ├─ Call gmailer.php (Google Drive OAuth2)
  ├─ Log success/error to gmailer logs
  └─ Move to completed/ on success

Failures logged to:
  ├─ gmailer_error.log
  └─ print_history*.json (email entries)
```

---

## Order Anatomy

### Receipt/TXT Format
```
email@address.com |
SQUARE ORDER: $27.62 PAID
Order #: 1056 - FS
Order Date: January 24, 2026, 1:57 pm
Order Total: $27.62
Delivery: Pickup On Site
ITEMS ORDERED:
-----------------------------
[1] 4x6 Print (20459)
[1] 4x6 Print (40347)
-----------------------------
Visit us online:
http://www.alleycatphoto.net
```

**Key Elements:**
- `Order #: NNN - MS/FS` = Station marker (read by spooler)
- `SQUARE ORDER: $X PAID` or `CASH ORDER: $X DUE` = Payment status
- Photo IDs in parentheses = Links to /raw/PHOTO_ID.jpg

### JPG Filename Convention
```
{ORDER_ID}-{PHOTO_ID}-{PRODUCT_CODE}{ORIENTATION}-{COPY_NUMBER}.jpg

Example: 1056-20459-4x6H-1.jpg
  ORDER_ID:       1056
  PHOTO_ID:       20459 (links to /raw/20459.jpg)
  PRODUCT_CODE:   4x6 (4x6H, 4x6V, 5x7H, 5x7V, 8x10H, 8x10V, EML)
  ORIENTATION:    H/V (determined from image size)
  COPY_NUMBER:    1 (if qty > 1, increments)
```

---

## Common Issues & ALLEY Solutions

### Issue 1: "JPGs in spool but no TXT file"

**Root Cause:** checkout.php failed to create TXT (file_put_contents error or directory permission)

**ALLEY Recovery:**
1. Calls `create_missing_txt_metadata(order_id)` tool
2. Reads receipt from /receipts/ORDER_ID.txt
3. Copies to /spool/printer/ORDER_ID.txt
4. Next spooler tick routes the JPGs correctly

**Prevention:** Enhanced checkout.php with fallback and error logging

### Issue 2: "Order printed to wrong station"

**Root Cause:** Race condition - spooler found JPGs before TXT existed, defaulted to Main Station

**ALLEY Recovery:**
1. Detect order in wrong location (C:/orders vs R:/orders)
2. Call `recover_orphaned_jpg()` to move files
3. Update print_history log

**Prevention:** 50ms sync delay after TXT write + defensive skip in spooler

### Issue 3: "Email didn't send"

**Root Cause:** Gmailer failure, Google Drive OAuth2 expired, or no images found

**ALLEY Recovery:**
1. Query gmailer_error.log via `check_email_logs()`
2. Verify OAuth2 token freshness
3. Check /spool/mailer/ORDER_ID/ for actual JPGs
4. Call `resend_email(order_id)` if safe

### Issue 4: "Only 4 of 5 prints came out"

**Root Cause:** Printer jam, out of paper, or network hiccup during one JPG transfer

**ALLEY Recovery:**
1. Query print_history.json
2. Identify which JPG is missing
3. Clear physical printer jam
4. Call `force_queue_retry()` for the missing file
5. Verify printer is back online

---

## ALLEY Personality

ALLEY speaks with witty irreverence, channeling:

- **Big Lebowski**: "Yeah, well, that's just like, uh, the printer's opinion, man"
- **Office Space**: "That would be greeeeeat" (when fixing issues)
- **Spaceballs**: "The combination is: 1, 2, 3, 4, 5! That's the same combination I have on my luggage!"

**Tone Guide:**
- Helpful but not saccharine
- Geeky references encouraged
- Self-aware about system limitations
- Genuine care for getting things done

**Example Responses:**
- Order recovered: "Your order is back in the queue, boss. Looks like we found a printing bug and squashed it."
- Email stuck: "That email's stuck harder than a CD in an Office Space printer. Let me yeet it back to the mailer queue."
- System healthy: "All systems nominal. The spooler is flowing, the printers are printing, and nobody's throwing staplers."

---

## Logging & Audit Trail

### Action Log Schema (`logs/alley_actions.json`)

```json
{
  "timestamp": 1769281952,
  "action": "create_missing_txt_metadata",
  "order_id": "1056",
  "parameters": {},
  "result": "success",
  "details": "TXT file created from receipt, routed to Fire Station",
  "user_context": "admin:john.doe",
  "session_id": "sess_abc123xyz"
}
```

**Logged Actions:**
- `get_order_details` - Query only (no side effects)
- `reprint_order` - Side effect (queue changed)
- `cancel_order` - Side effect (order removed)
- `resend_email` - Side effect (email re-queued)
- `force_queue_retry` - Side effect (file moved)
- `create_missing_txt_metadata` - Recovery action
- `recover_orphaned_jpg` - Recovery action
- `clear_printer_queue` - Destructive action (logged with warning)

**Retention:** All logs retained indefinitely for audit

---

## Tool Specification (JSON Schema)

### Tool: `get_order_details`

```json
{
  "name": "get_order_details",
  "description": "Retrieve complete details for an order including items, payment, and current status",
  "parameters": {
    "type": "object",
    "properties": {
      "order_id": {
        "type": "string",
        "description": "Order ID (e.g., '1056')"
      }
    },
    "required": ["order_id"]
  }
}
```

**Returns:**
```json
{
  "order_id": "1056",
  "email": "awoodell91@icloud.com",
  "payment_method": "square",
  "amount": 27.62,
  "status": "printing",
  "station": "FS",
  "items": [
    {"photo_id": "20459", "product": "4x6", "qty": 1},
    {"photo_id": "40347", "product": "4x6", "qty": 1}
  ],
  "created_at": 1769281952,
  "receipt_path": "/photos/2026/01/24/receipts/1056.txt",
  "txt_metadata_exists": true,
  "jpg_count_in_queue": 0,
  "jpg_count_at_printer": 5,
  "email_sent": false
}
```

### Tool: `reprint_order`

```json
{
  "name": "reprint_order",
  "description": "Move an order's JPGs back to printer queue for reprinting",
  "parameters": {
    "type": "object",
    "properties": {
      "order_id": {"type": "string"},
      "force_station": {
        "type": "string",
        "enum": ["MS", "FS"],
        "description": "Override station detection (optional)"
      }
    },
    "required": ["order_id"]
  }
}
```

### Tool: `resend_email`

```json
{
  "name": "resend_email",
  "description": "Re-queue an order for email delivery",
  "parameters": {
    "type": "object",
    "properties": {
      "order_id": {"type": "string"},
      "email_override": {
        "type": "string",
        "description": "Send to different email (optional)"
      }
    },
    "required": ["order_id"]
  }
}
```

### Tool: `check_print_history`

```json
{
  "name": "check_print_history",
  "description": "Query print history for troubleshooting",
  "parameters": {
    "type": "object",
    "properties": {
      "order_id": {"type": "string"},
      "date": {
        "type": "string",
        "description": "YYYY-MM-DD format (defaults to today)"
      },
      "limit": {"type": "integer", "default": 50}
    },
    "required": []
  }
}
```

---

## Integration Points

### Admin Interface Integration

**Location:** `admin/index.php`

Add to template footer:
```html
<!-- ALLEY Help Bubble -->
<div id="alley-bubble" class="alley-help-bubble">?</div>
<script src="/public/assets/js/alley-bubble.js"></script>
```

### API Endpoint

**Route:** `POST /config/api/alley.php`

```json
{
  "session_id": "sess_abc123xyz",
  "user": "john.doe",
  "message": "Can you reprint order 1056?",
  "context": {
    "page": "orders",
    "selected_order": "1056"
  }
}
```

---

## Security Considerations

1. **Authentication:** Only admin users can access ALLEY
2. **Rate Limiting:** Max 10 requests/minute per session
3. **Destructive Actions:** Require confirmation (cancel, clear queue)
4. **Audit Trail:** All actions logged with user/timestamp
5. **API Keys:** Gemini API key stored in .env, never exposed
6. **File Access:** System runs with restricted PHP user (www-data)

---

## Future Enhancements

- [ ] Machine learning order prediction (busy hours, popular photos)
- [ ] Automated anomaly detection (unusual patterns, errors)
- [ ] SMS notifications for critical failures
- [ ] Webhook integration for Slack alerts
- [ ] Admin chat history and saved responses
- [ ] A/B testing for messaging variants
- [ ] Multi-language support

---

## Support & Troubleshooting

**ALLEY isn't responding:**
1. Check `/logs/alley_actions.json` for errors
2. Verify Gemini API key in `.env`
3. Check `error_log` for PHP errors
4. Test API endpoint directly: `curl -X POST /config/api/alley.php`

**Tools not working:**
1. Verify tool definitions in `config/api/alley.php`
2. Check file permissions on `/photos/` and `/config/`
3. Test individual tools via CLI

**Performance issues:**
1. Monitor Gemini API response time
2. Check system logs for file I/O bottlenecks
3. Consider caching frequent queries

---

## Quick Reference: Most Common ALLEY Requests

| Problem | ALLEY Command |
|---------|---------------|
| Order didn't print | "Reprint order 1056" |
| Email stuck | "Check email status for 1056" |
| Printer offline | "Check fire station status" |
| JPG in spool but no metadata | "Create missing TXT for 1056" |
| Too many failures | "Show me print errors from today" |
| Disk almost full | "Check disk space on all systems" |
| Autoprint broken | "Reset autoprint status" |
| Customer wants refund | "Cancel order 1056 and log reason" |

---

**Made with ❤️ and a sense of humor for the hardest working photo ops team.**
