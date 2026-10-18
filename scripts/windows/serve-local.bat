@echo off
rem Local development server for the marketplace on Windows (docs/operations/local-development.md).
rem Starts the project's MariaDB (127.0.0.1:3307) when it is not running, then serves the app with
rem PHP 8.4 on http://localhost:8001. Nothing global is changed. Edit the three paths below if your
rem installation differs. Stop with Ctrl+C (MariaDB keeps running; stop-marketplace-db.bat stops it).
setlocal
set "PHP=C:\php8.4\php.exe"
set "MARIADB_START=C:\mariadb10.11\start-marketplace-db.bat"
set "PORT=8001"

cd /d "%~dp0..\.."

if not exist "%PHP%" (
    echo PHP 8.4 not found at %PHP%. Edit scripts\windows\serve-local.bat.
    exit /b 1
)

netstat -ano | findstr /r /c:"127.0.0.1:3307 .*LISTENING" >nul
if errorlevel 1 (
    echo Starting MariaDB...
    call "%MARIADB_START%"
    timeout /t 5 /nobreak >nul
)

findstr /b /c:"APP_URL=http://localhost:%PORT%" .env >nul
if errorlevel 1 (
    echo.
    echo WARNING: APP_URL in .env is not http://localhost:%PORT%. Links, styles and photos use APP_URL,
    echo so set APP_URL=http://localhost:%PORT% in .env ^(port 8000 is used by Dev Talk^).
    echo.
)

if not exist "public\build\manifest.json" (
    echo No asset build found: run "npm run build" first.
    exit /b 1
)

echo Marketplace on http://localhost:%PORT%
"%PHP%" artisan serve --host=127.0.0.1 --port=%PORT%
