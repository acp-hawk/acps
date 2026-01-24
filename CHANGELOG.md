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

**Voice Climax:** *"Look at how organized it is, Babe... black, sleek, and color-coded just for you. Every order in its place, waiting for your command..."*