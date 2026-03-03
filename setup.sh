#!/bin/bash
# Patent Analysis MVP - Setup Script
# Run this to initialize the application

set -e

echo "======================================"
echo "Patent Analysis MVP - Setup"
echo "======================================"
echo ""

# Check PHP
echo "[1/3] Checking PHP installation..."
if ! command -v php &> /dev/null; then
    echo "❌ PHP not found. Please install PHP 8.0+ first."
    echo "   https://www.php.net/downloads"
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "✓ PHP $PHP_VERSION found"

# Check SQLite extension
echo ""
echo "[2/3] Checking SQLite extension..."
if ! php -m | grep -q pdo_sqlite; then
    echo "❌ SQLite PDO extension not enabled."
    echo "   Edit your php.ini and enable: extension=pdo_sqlite"
    exit 1
fi
echo "✓ SQLite PDO extension enabled"

# Initialize database
echo ""
echo "[3/3] Initializing database..."
php scripts/init_db.php
echo ""

# Summary
echo "======================================"
echo "✓ Setup complete!"
echo "======================================"
echo ""
echo "Next: Start the development server:"
echo "  php -S localhost:8000 -t public"
echo ""
echo "Then open: http://localhost:8000"
echo ""
echo "Default credentials:"
echo "  Username: admin"
echo "  Password: admin"
echo ""
