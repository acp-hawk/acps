#!/bin/bash
# ALLEY Deployment Helper Script
# Automates final integration step and verification

set -e

echo "🤖 ALLEY Deployment Helper - January 24, 2026"
echo "=============================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Check if .env has GEMINI_API_KEY
echo "📋 Step 1: Checking environment configuration..."
if grep -q "GEMINI_API_KEY" .env; then
    echo -e "${GREEN}✓${NC} GEMINI_API_KEY found in .env"
else
    echo -e "${RED}✗${NC} GEMINI_API_KEY not found in .env"
    echo "   Add this line to .env:"
    echo "   GEMINI_API_KEY=your_api_key_here"
    exit 1
fi

# 2. Check if alley.php exists
echo ""
echo "📋 Step 2: Checking ALLEY API endpoint..."
if [ -f "config/api/alley.php" ]; then
    echo -e "${GREEN}✓${NC} config/api/alley.php exists"
else
    echo -e "${RED}✗${NC} config/api/alley.php not found"
    exit 1
fi

# 3. Check if alley_bubble.html exists
echo ""
echo "📋 Step 3: Checking help bubble widget..."
if [ -f "admin/alley_bubble.html" ]; then
    echo -e "${GREEN}✓${NC} admin/alley_bubble.html exists"
else
    echo -e "${RED}✗${NC} admin/alley_bubble.html not found"
    exit 1
fi

# 4. Create logs directory
echo ""
echo "📋 Step 4: Creating logs directory..."
mkdir -p logs
if [ -f "logs/alley_actions.json" ]; then
    echo -e "${GREEN}✓${NC} logs/alley_actions.json exists"
else
    echo "[]" > logs/alley_actions.json
    echo -e "${GREEN}✓${NC} Created logs/alley_actions.json"
fi
chmod 666 logs/alley_actions.json

# 5. Check admin/index.php integration
echo ""
echo "📋 Step 5: Checking admin/index.php integration..."
if grep -q "alley_bubble.html" admin/index.php; then
    echo -e "${GREEN}✓${NC} ALLEY bubble already integrated in admin/index.php"
else
    echo -e "${YELLOW}⚠${NC} ALLEY bubble not found in admin/index.php"
    echo ""
    echo "   To integrate, add this before </body> tag in admin/index.php:"
    echo ""
    echo "   <!-- ALLEY Help Bubble Integration -->"
    echo "   <?php include(__DIR__ . '/alley_bubble.html'); ?>"
    echo ""
    echo "   Would you like me to add it automatically? (y/n)"
    read -r response
    
    if [ "$response" = "y" ] || [ "$response" = "Y" ]; then
        # Backup the file
        cp admin/index.php admin/index.php.backup
        echo -e "${YELLOW}→${NC} Backed up to admin/index.php.backup"
        
        # Add the include before </body>
        sed -i '/<\/body>/i\  <!-- ALLEY Help Bubble Integration -->\n  <?php include(__DIR__ . '"'"'/alley_bubble.html'"'"'); ?>\n' admin/index.php
        echo -e "${GREEN}✓${NC} ALLEY bubble integrated into admin/index.php"
    fi
fi

# 6. Verify directory permissions
echo ""
echo "📋 Step 6: Checking directory permissions..."
for dir in logs photos photos/2026/01/24/spool/printer photos/2026/01/24/spool/mailer; do
    if [ -d "$dir" ]; then
        if [ -w "$dir" ]; then
            echo -e "${GREEN}✓${NC} $dir is writable"
        else
            echo -e "${YELLOW}⚠${NC} $dir may not be writable"
            chmod 777 "$dir" 2>/dev/null || true
        fi
    fi
done

# 7. Final check
echo ""
echo "📋 Step 7: Final deployment check..."
all_good=true

if grep -q "GEMINI_API_KEY" .env; then
    echo -e "${GREEN}✓${NC} Environment configured"
else
    echo -e "${RED}✗${NC} Environment not configured"
    all_good=false
fi

if [ -f "config/api/alley.php" ]; then
    echo -e "${GREEN}✓${NC} API endpoint deployed"
else
    echo -e "${RED}✗${NC} API endpoint missing"
    all_good=false
fi

if [ -f "admin/alley_bubble.html" ]; then
    echo -e "${GREEN}✓${NC} Bubble UI deployed"
else
    echo -e "${RED}✗${NC} Bubble UI missing"
    all_good=false
fi

if grep -q "alley_bubble.html" admin/index.php; then
    echo -e "${GREEN}✓${NC} Integration complete"
else
    echo -e "${YELLOW}⚠${NC} Integration incomplete"
fi

if [ -f "logs/alley_actions.json" ]; then
    echo -e "${GREEN}✓${NC} Logging ready"
else
    echo -e "${RED}✗${NC} Logging not ready"
    all_good=false
fi

# Summary
echo ""
echo "=============================================="
if [ "$all_good" = true ]; then
    if grep -q "alley_bubble.html" admin/index.php; then
        echo -e "${GREEN}🚀 ALLEY is ready for deployment!${NC}"
        echo ""
        echo "Next steps:"
        echo "1. Clear browser cache (Ctrl+Shift+Delete)"
        echo "2. Load admin interface"
        echo "3. Look for red ? bubble in bottom-right"
        echo "4. Try query: \"Check print queue\""
        echo "5. Monitor logs: tail -f logs/alley_actions.json"
    else
        echo -e "${YELLOW}⚠ Almost ready! Just need to integrate bubble.${NC}"
        echo ""
        echo "Run this again to auto-integrate, or:"
        echo "Edit admin/index.php and add before </body>:"
        echo "<?php include(__DIR__ . '/alley_bubble.html'); ?>"
    fi
else
    echo -e "${RED}❌ Issues detected. See above.${NC}"
fi

echo ""
echo "📚 Documentation: See ALLEY_QUICKSTART.md and ALLEY.md"
echo "🐛 Issues? Check logs/alley_actions.json"
echo ""
