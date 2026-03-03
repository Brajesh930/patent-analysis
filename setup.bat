@echo off
REM Patent Analysis MVP - Setup Script (Windows)
REM Run this to initialize the application

setlocal enabledelayedexpansion

echo ======================================
echo Patent Analysis MVP - Setup
echo ======================================
echo.

REM Check PHP
echo [1/3] Checking PHP installation...
where php >nul 2>nul
if errorlevel 1 (
    echo X PHP not found. Please install PHP 8.0+ first.
    echo   https://www.php.net/downloads
    exit /b 1
)

for /f "tokens=*" %%i in ('php -r "echo PHP_VERSION;"') do set PHP_VERSION=%%i
echo + PHP %PHP_VERSION% found
echo.

REM Check SQLite extension
echo [2/3] Checking SQLite extension...
php -m | findstr /i pdo_sqlite >nul
if errorlevel 1 (
    echo X SQLite PDO extension not enabled.
    echo   Edit your php.ini and enable: extension=pdo_sqlite
    exit /b 1
)
echo + SQLite PDO extension enabled
echo.

REM Initialize database
echo [3/3] Initializing database...
php scripts/init_db.php
echo.

REM Summary
echo ======================================
echo + Setup complete!
echo ======================================
echo.
echo Next: Start the development server:
echo   php -S localhost:8000 -t public
echo.
echo Then open: http://localhost:8000
echo.
echo Default credentials:
echo   Username: admin
echo   Password: admin
echo.

pause
