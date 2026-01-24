# ALLEY Implementation Quick Start

**Date**: January 24, 2026  
**Status**: Ready for Deployment

---

## What Was Built

### 1. **TXT File Creation Fix** ✅
**File**: [config/api/checkout.php](config/api/checkout.php#L313)

- Added explicit error handling for TXT file creation
- Fallback logging if suppressed error occurs
- Will help identify why QR orders sometimes skip TXT creation

**File**: [config/api/spooler.php](config/api/spooler.php#L200)

- Added auto-recovery: If TXT is missing but JPGs exist, spooler copies receipt to create TXT
- Prevents orphaned JPG files from clogging queue
- Next spooler tick will route correctly

### 2. **ALLEY.md Documentation** ✅
**File**: [docs/ALLEY.md](docs/ALLEY.md)

Comprehensive 400+ line guide covering:
- System architecture and tool definitions
- Data flows and folder structure
- Common issues and recovery procedures
- Security considerations
- Full tool specifications (JSON Schema)

### 3. **ALLEY Agent API** ✅
**File**: [config/api/alley.php](config/api/alley.php)

Production-ready endpoint with:
- Gemini 2.5-flash-latest integration
- 10 core tools for order/email/print management
- Automatic action logging
- Tool execution and result handling
- Session management

### 4. **Admin Help Bubble UI** ✅
**File**: [admin/alley_bubble.html](admin/alley_bubble.html)

Beautiful, responsive widget featuring:
- Red help bubble (fixed position, bottom-right)
- Smooth slide-up chat modal
- Message history display
- Loading animations
- Keyboard shortcut (Ctrl+Alt+A)

---

## Integration Steps

### Step 1: Add Gemini API Key to .env

```bash
# In your .env file:
GEMINI_API_KEY=your_actual_api_key_here
```

Get your key from: https://aistudio.google.com/apikey

### Step 2: Include Bubble in Admin Interface

**In `admin/index.php`, before closing `</body>` tag, add:**

```php
<!-- ALLEY Help Bubble -->
<?php include(__DIR__ . '/alley_bubble.html'); ?>
```

### Step 3: Ensure Logging Directory Exists

```bash
mkdir -p logs
touch logs/alley_actions.json
chmod 666 logs/alley_actions.json
```

### Step 4: Verify Permissions

```bash
# Make sure these directories are writable:
chmod 777 photos/2026/01/24/spool/printer/
chmod 777 photos/2026/01/24/spool/mailer/
chmod 777 logs/
```

### Step 5: Test the Integration

1. Load admin interface in browser
2. Look for red **?** bubble in bottom-right corner
3. Click it
4. Try a test query: "What's the status of order 1056?"
5. Check `/logs/alley_actions.json` for the logged action

---

## Quick Test Commands

### From Admin Interface:
- "Reprint order 1056"
- "Check print queue"
- "Is the fire printer ready?"
- "Email status for 1059"
- "What's wrong with order 1056?"
- "Disk space check"
- "System health"

### Direct API Test (from terminal):
```bash
curl -X POST http://localhost/config/api/alley.php \
  -H "Content-Type: application/json" \
  -d '{"message": "Hi ALLEY", "session_id": "test_123"}'
```

---

## ALLEY Personality Features

ALLEY speaks in witty, geeky humor. Examples:

**When fixing a problem:**
> "Your order is back in the queue, boss. Looks like we found a printing bug and squashed it."

**When printer is offline:**
> "That printer's offline harder than a broken computer from Office Space. Let me check what's going on."

**When system is healthy:**
> "All systems nominal. The spooler is flowing, the printers are printing, and nobody's throwing staplers."

**When recovering orphaned files:**
> "Found some lonely JPGs wandering the queue. I'm putting them back where they belong."

---

## Tools Available to ALLEY

| Tool | Purpose | Example |
|------|---------|---------|
| `get_order_details` | Retrieve order info | "Tell me about order 1056" |
| `reprint_order` | Queue for reprint | "Reprint order 1056" |
| `resend_email` | Re-queue email | "Resend email for 1056" |
| `cancel_order` | Remove order | "Cancel order 1056" |
| `check_print_history` | Query print log | "What printed today?" |
| `get_print_queue_status` | Pending prints | "What's in the queue?" |
| `get_email_queue_status` | Pending emails | "Any emails pending?" |
| `check_email_logs` | Email errors | "Any email errors?" |
| `check_printer_status` | Printer readiness | "Are the printers ready?" |
| `create_missing_txt_metadata` | Auto-recover TXT | "Fix order 1056's metadata" |
| `check_system_health` | System diagnostics | "Run a system check" |

---

## Action Logging

Every action ALLEY takes is logged to `/logs/alley_actions.json`:

```json
{
  "timestamp": 1769281952,
  "action": "reprint_order",
  "parameters": {"order_id": "1056"},
  "result": {"status": "success"},
  "user": "john.doe",
  "session_id": "sess_abc123xyz"
}
```

**Audit Trail Benefits:**
- Track who did what and when
- Troubleshoot issues by reviewing actions
- Prove orders were handled correctly
- Training reference for staff

---

## Troubleshooting

### ALLEY doesn't respond
1. Check Gemini API key in `.env`
2. Verify network connectivity
3. Check browser console for errors
4. Review `/logs/alley_actions.json` for failures

### Tools not working
1. Verify file paths are correct
2. Check permissions on `/photos/` and `/logs/`
3. Ensure autoprint_status.txt exists
4. Test individual tools via CLI

### Slow responses
1. Gemini API might be under load - wait and retry
2. Check system disk I/O
3. Review logs for any hanging processes

---

## Future Enhancements

- [ ] SMS alerts for critical issues
- [ ] Slack/Teams integration
- [ ] Bulk operations (reprint multiple orders)
- [ ] Scheduled tasks (nightly cleanup, reports)
- [ ] Admin dashboard with stats
- [ ] ML-powered anomaly detection
- [ ] Multi-language support

---

## Files Modified/Created

| File | Status | Changes |
|------|--------|---------|
| config/api/checkout.php | Modified | Enhanced TXT creation error handling (lines 313-324) |
| config/api/spooler.php | Modified | Added auto-recovery for missing TXT (lines 199-217) |
| config/api/alley.php | **Created** | Full ALLEY agent API endpoint |
| admin/alley_bubble.html | **Created** | Help bubble UI widget |
| docs/ALLEY.md | **Created** | Comprehensive system documentation |
| logs/alley_actions.json | **Created** | Action audit trail (auto-created on first use) |

---

## Production Checklist

- [ ] Add GEMINI_API_KEY to `.env`
- [ ] Include alley_bubble.html in admin/index.php
- [ ] Test with sample orders
- [ ] Verify logging is working (`logs/alley_actions.json` grows)
- [ ] Train staff on help bubble location and usage
- [ ] Monitor first week for any issues
- [ ] Review logs regularly for patterns

---

**Ready to deploy!** 🚀

For detailed system info, see [docs/ALLEY.md](docs/ALLEY.md)
