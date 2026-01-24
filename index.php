<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Alley Cat Photo : PhotoStation</title>

  <link rel="stylesheet" type="text/css" href="/public/assets/css/acps.css">
  <script src="/public/assets/js/jquery-3.2.1.min.js"></script>
  <script src="/public/assets/js/acps_modal.js"></script>

  <style>
    :root{
      --nav-h: 120px;
      --cart-w: 200px;
      --gap: 20px;
      --bg: #0b0b0b;
    }

    /* Fullscreen kiosk-safe */
    html, body { height: 100%; margin: 0; background: var(--bg); }
    body { overflow: hidden; }

    /* Responsive shell */
    .shell{
      height: 100vh;
      width: 100vw;
      display: grid;
      grid-template-columns: 1fr var(--cart-w);
      grid-template-rows: var(--nav-h) 1fr;
      column-gap: var(--gap);
      row-gap: 0;
    }

    /* Areas */
    .nav { grid-column: 1 / 2; grid-row: 1 / 2; }
    .main{ grid-column: 1 / 2; grid-row: 2 / 3; }
    .cart{ grid-column: 2 / 3; grid-row: 1 / 3; }

    iframe{
      width: 100%;
      height: 100%;
      border: 0;
      display: block;
      background: #fff; /* prevents “black flash” while loading */
    }

    /* Touch + kiosk niceties */
    iframe { touch-action: manipulation; }
    * { -webkit-tap-highlight-color: transparent; }

    /* Narrow screens: cart becomes bottom panel */
    @media (max-width: 900px){
      :root{ --nav-h: 96px; } /* optional: slightly smaller nav on small screens */
      .shell{
        grid-template-columns: 1fr;
        grid-template-rows: var(--nav-h) 1fr min(35vh, 320px);
      }
      .nav  { grid-column: 1; grid-row: 1; }
      .main { grid-column: 1; grid-row: 2; }
      .cart { grid-column: 1; grid-row: 3; }
    }

    /* Optional: hide cart entirely on very small screens (uncomment if desired) */
    /*
    @media (max-width: 480px){
      .shell{ grid-template-rows: var(--nav-h) 1fr; }
      .cart{ display:none; }
    }
    */
  </style>
</head>

<body>
  <div class="shell">
    <div class="nav">
      <iframe
        id="menuFrame"
        name="menu"
        src="gallery_nav.php"
        scrolling="no"
        referrerpolicy="no-referrer"
      ></iframe>
    </div>

    <div class="main">  
      <iframe
        id="contentFrame"
        name="content"
        src="gallery.php"
        scrolling="auto"
        referrerpolicy="no-referrer"
      ></iframe>
    </div>

    <div class="cart">
      <iframe
        id="cartFrame"
        name="cart"
        src="cart.php"
        scrolling="auto"
        referrerpolicy="no-referrer"
      ></iframe>
    </div>
  </div>

<script>
    /**
     * KIOSK LOCKDOWN SCRIPT
     * Prevents right-click, text selection, drag-and-drop, and touch callouts.
     * Applies to the Parent window and injects into all IFRAMES.
     */
    (function() {

      // 1. The Protection Logic
      function applyKioskProtection(win) {
        if (!win || !win.document) return;
        const doc = win.document;

        // --- A. Inject Kiosk CSS ---
        // Checks if style already exists to avoid duplication on reloads
        if (!doc.getElementById('kiosk-css')) {
          const style = doc.createElement('style');
          style.id = 'kiosk-css';
          style.textContent = `
            /* DISABLE DEFAULT INTERACTIONS */
            *, *::before, *::after {
              -webkit-touch-callout: none !important; /* iOS/Android long press menu */
              -webkit-user-select: none !important;   /* Chrome/Safari text select */
              -moz-user-select: none !important;      /* Firefox text select */
              -ms-user-select: none !important;       /* IE/Edge text select */
              user-select: none !important;           /* Standard text select */
              -webkit-user-drag: none !important;     /* Webkit image drag */
              user-drag: none !important;             /* Standard image drag */
              
              /* PREVENT ZOOM, ALLOW SCROLL */
              touch-action: pan-y !important; 
            }

            /* RE-ENABLE INPUTS/LINKS INTERACTIONS */
            /* We must allow text selection inside inputs so users can see what they type */
            input, textarea, [contenteditable] {
              -webkit-user-select: text !important;
              user-select: text !important;
              cursor: auto !important;
            }
            
            /* Ensure links and buttons clearly look clickable but don't drag */
            a, button, input[type="submit"], input[type="button"] {
              cursor: pointer !important;
            }
            
            /* Hide scrollbars visually but allow scrolling? (Optional - remove if unwanted) */
            /* body::-webkit-scrollbar { display: none; } */
          `;
          if (doc.head) doc.head.appendChild(style);
          else doc.body.appendChild(style);
        }

        // --- B. JavaScript Event Blockers ---
        
        // Block Right Click / Context Menu
        doc.addEventListener('contextmenu', e => e.preventDefault(), { capture: true });
        
        // Block Dragging (Images, Links, Text)
        doc.addEventListener('dragstart', e => e.preventDefault(), { capture: true });
        
        // Block Selection Start (Fallback for CSS)
        // We filter this to allow selection inside inputs
        doc.addEventListener('selectstart', e => {
          const target = e.target;
          // Allow if the target is an input field
          if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') return;
          e.preventDefault();
        }, { capture: true });
      }

      // 2. Apply to Parent Window
      applyKioskProtection(window);

      // 3. Apply to All IFrames (and re-apply on load)
      const frames = document.querySelectorAll('iframe');
      frames.forEach(iframe => {
        // Apply immediately if already loaded
        try {
          if (iframe.contentWindow && iframe.contentWindow.document.readyState === 'complete') {
            applyKioskProtection(iframe.contentWindow);
          }
        } catch(e) { /* Ignore Cross-Origin errors if any */ }

        // Apply whenever the iframe loads a new page
        iframe.addEventListener('load', () => {
          try {
            applyKioskProtection(iframe.contentWindow);
          } catch (e) {
            console.warn('Cannot access iframe content (CORS restriction?):', iframe);
          }
        });
      });

      // 4. Preserve your existing URL param logic
      const params = new URLSearchParams(location.search);
      const content = params.get('content');
      const cart = params.get('cart');
      const menu = params.get('menu');

      if (menu)    document.getElementById('menuFrame').src = menu;
      if (content) document.getElementById('contentFrame').src = content;
      if (cart)    document.getElementById('cartFrame').src = cart;

    })();
  </script>
</body>
</html>