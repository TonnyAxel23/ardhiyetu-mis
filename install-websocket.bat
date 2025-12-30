@echo off
REM -----------------------------------------------------------
REM ArdhiYetu Real-time Notification System - Windows installer
REM Save this file as install-websocket.bat and run from your
REM project root (where websocket-server.php lives).
REM -----------------------------------------------------------

REM --------- CONFIG - adjust if your XAMPP is installed elsewhere ----------
set "PHP_PATH=C:\xampp\php\php.exe"
set "MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe"
set "COMPOSER=composer"
REM If composer is not in PATH, set like:
REM set "COMPOSER=C:\ProgramData\ComposerSetup\bin\composer.bat"
REM -----------------------------------------------------------------------

echo Installing ArdhiYetu Real-time Notification System...
echo ====================================================

REM 1) Composer dependencies
echo.
echo 1. Installing Composer dependencies...
if exist "%PHP_PATH%" (
    echo Using PHP: %PHP_PATH%
) else (
    echo WARNING: PHP not found at %PHP_PATH%. Make sure PHP is in PATH or update PHP_PATH in this script.
)

REM Try running composer require (will fail if composer not found)
echo Running: %COMPOSER% require cboden/ratchet
%COMPOSER% require cboden/ratchet --no-interaction
if errorlevel 1 (
    echo.
    echo ERROR: Could not install cboden/ratchet via Composer. Check Composer and PHP.
) else (
    echo cboden/ratchet installed.
)

echo Running: %COMPOSER% require react/zmq
%COMPOSER% require react/zmq --no-interaction
if errorlevel 1 (
    echo.
    echo NOTE: react/zmq may fail if ZeroMQ or the PHP ZMQ extension is missing.
    echo If you don't need react/zmq, ignore this error. Otherwise install libzmq and PHP ZMQ extension.
) else (
    echo react/zmq installed.
)

REM 2) Create necessary directories
echo.
echo 2. Creating directories...
setlocal enabledelayedexpansion
set "DIRS=assets\sounds uploads\lands uploads\transfers logs"
for %%d in (%DIRS%) do (
    if not exist "%%~d" (
        mkdir "%%~d"
        echo Created: %%~d
    ) else (
        echo Exists: %%~d
    )
)

REM 3) Set permissions (basic)
echo.
echo 3. Setting permissions (granting Modify to the current user)...
for %%d in (uploads\lands uploads\transfers logs) do (
    icacls "%%~d" /grant "%username%:(OI)(CI)M" /T >nul 2>&1
    if errorlevel 0 (
        echo Permissions set for %%~d
    ) else (
        echo Could not set permissions for %%~d (you may need to run as Administrator)
    )
)

REM 4) Database updates - remind user and provide example command
echo.
echo 4. Database update (manual step)
echo Please run the database-updates.sql file using phpMyAdmin OR the mysql CLI.
echo Example (XAMPP): 
echo   "%MYSQL_PATH%" -u root -p YOUR_DB_NAME ^< database-updates.sql
echo Replace YOUR_DB_NAME with your database name and enter the root password when prompted.
echo If MySQL is in PATH, you can use: mysql -u root -p YOUR_DB_NAME ^< database-updates.sql

REM 5) Create startup script (Windows .bat)
echo.
echo 5. Creating startup script start-websocket.bat...
(
    echo @echo off
    echo REM start-websocket.bat - change the path below if needed
    echo cd /d "%%~dp0"
    echo REM Use full path to php if needed:
    echo "%PHP_PATH%" websocket-server.php
) > start-websocket.bat

echo Made: start-websocket.bat
echo Make it executable by double-clicking or running from CMD: start-websocket.bat

echo.
echo Installation complete!
echo ======================
echo Next steps:
echo 1. Run the SQL update: "%MYSQL_PATH%" -u root -p YOUR_DB_NAME ^< database-updates.sql
echo 2. Edit start-websocket.bat if you need to use a different PHP path.
echo 3. Start the WebSocket server (foreground): %PHP_PATH% websocket-server.php
echo 4. Or run: start-websocket.bat
echo.
echo For production, consider installing a Windows service wrapper or using NSSM / Task Scheduler to keep the server running.
echo.
pause
