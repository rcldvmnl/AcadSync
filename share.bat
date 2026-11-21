@echo off
rem ==========================================================================================
rem  AcadSync: share the website online for a demo. Double-click this file.
rem
rem  1. Starts its own PHP server on port 8090 (your usual one on 8000 can keep running).
rem  2. Opens a Cloudflare quick tunnel to it and shows the public https link (copied for you).
rem  3. Press any key in this window, or close it, to stop sharing.
rem
rem  Demo data (the default) uses a separate database, data\demo.db, so your class's real
rem  database is never online. Settings are passed to this server only; .env is never changed:
rem  the reset code is never shown, error details are hidden, and the login cookie is HTTPS-only.
rem
rem  Skip the question:  share.bat demo   or   share.bat real
rem  Needs: XAMPP PHP (C:\xampp\php\php.exe) and cloudflared (winget install Cloudflare.cloudflared)
rem ==========================================================================================
setlocal EnableExtensions
title AcadSync - sharing online
cd /d "%~dp0"

set "PORT=8090"
set "LOG=%TEMP%\acadsync-share"
if not exist "%LOG%" mkdir "%LOG%"

rem ---- find PHP ---------------------------------------------------------------------------
set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP="
if not defined PHP for /f "delims=" %%i in ('where php 2^>nul') do if not defined PHP set "PHP=%%i"
if not defined PHP (
  echo PHP was not found. Install XAMPP, or put php.exe on your PATH.
  pause
  exit /b 1
)

rem ---- find cloudflared -------------------------------------------------------------------
set "CF="
for /f "delims=" %%i in ('where cloudflared 2^>nul') do if not defined CF set "CF=%%i"
if not defined CF if exist "%ProgramFiles(x86)%\cloudflared\cloudflared.exe" set "CF=%ProgramFiles(x86)%\cloudflared\cloudflared.exe"
if not defined CF if exist "%ProgramFiles%\cloudflared\cloudflared.exe" set "CF=%ProgramFiles%\cloudflared\cloudflared.exe"
if not defined CF (
  echo cloudflared was not found. Install it once with:
  echo     winget install Cloudflare.cloudflared
  pause
  exit /b 1
)

rem ---- the port must be free --------------------------------------------------------------
netstat -ano | findstr /R /C:"127.0.0.1:%PORT% .*LISTENING" /C:"0.0.0.0:%PORT% .*LISTENING" >nul
if not errorlevel 1 (
  echo Port %PORT% is already in use. Is AcadSync already being shared in another window?
  pause
  exit /b 1
)

rem ---- demo or real data ------------------------------------------------------------------
set "MODE=%~1"
if /i not "%MODE%"=="demo" if /i not "%MODE%"=="real" (
  echo.
  echo   What should people see?
  echo     [D] Demo data - a separate demo database ^(data\demo.db^). Safe to share.
  echo     [R] Real data - your class database. Anyone with the link can try the demo passwords.
  echo.
  choice /C DR /N /M "  Press D or R: "
  if errorlevel 2 (set "MODE=real") else (set "MODE=demo")
)
if /i "%MODE%"=="demo" (
  set "DB_PATH=data/demo.db"
  set "SEED_DEMO_DATA=true"
)

rem Safety settings for this server only (a real environment variable wins over .env)
set "SHOW_RESET_CODE=false"
set "APP_ENV=production"
set "SESSION_SECURE_COOKIE=true"

rem ---- 1. the server ----------------------------------------------------------------------
echo.
echo   Starting the server (%MODE% data) ...
start "" /B "%PHP%" -S 127.0.0.1:%PORT% router.php >"%LOG%\server.log" 2>&1
set /a TRIES=0
:waitserver
curl -s -o nul -m 2 "http://127.0.0.1:%PORT%/Frontend/index.html" && goto serverup
set /a TRIES+=1
if %TRIES% geq 20 (
  echo   The server did not start. Details: %LOG%\server.log
  goto stop
)
timeout /t 1 /nobreak >nul
goto waitserver
:serverup
rem The first API call creates (and seeds) the demo database, so the first visitor isn't kept waiting
curl -s -o nul -m 60 "http://127.0.0.1:%PORT%/api/auth.php"

rem ---- 2. the tunnel ----------------------------------------------------------------------
echo   Opening the tunnel ...
if exist "%LOG%\tunnel.log" del "%LOG%\tunnel.log"
if exist "%LOG%\url.txt" del "%LOG%\url.txt"
start "" /B "%CF%" tunnel --no-autoupdate --url "http://127.0.0.1:%PORT%" >"%LOG%\tunnel.log" 2>&1
powershell -NoProfile -ExecutionPolicy Bypass -Command "$f = '%LOG%\tunnel.log'; for ($i = 0; $i -lt 60; $i++) { if (Test-Path $f) { $t = Get-Content -Raw $f -ErrorAction SilentlyContinue; if ($t -match 'https://[a-z0-9-]+\.trycloudflare\.com' -and $t -match 'Registered tunnel connection') { $Matches = $null; [void]($t -match 'https://[a-z0-9-]+\.trycloudflare\.com'); $Matches[0]; break } }; Start-Sleep -Seconds 1 }" >"%LOG%\url.txt"
set "URL="
set /p URL=<"%LOG%\url.txt"
if not defined URL (
  echo   The tunnel did not open. Check your internet connection. Details: %LOG%\tunnel.log
  goto stop
)

rem ---- 3. share it ------------------------------------------------------------------------
<nul set /p "=%URL%" | clip
echo.
echo   ============================================================================
echo     AcadSync is online:
echo.
echo       %URL%
echo.
echo     The link is copied - paste it to share. It changes every time you run this.
if /i "%MODE%"=="demo" (
  echo     Showing DEMO data. Demo logins: juan.delacruz / password123, ana.bautista / password123
) else (
  echo     Showing your REAL class data.
)
echo   ============================================================================
echo.
echo   Keep this window open while people use it.
echo   Press any key here to stop sharing (or just close this window).
pause >nul

:stop
echo.
echo   Stopping ...
rem Only the server and tunnel this script started (both name 127.0.0.1:%PORT% on their command line)
powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { ($_.Name -eq 'php.exe' -or $_.Name -eq 'cloudflared.exe') -and $_.CommandLine -like '*127.0.0.1:%PORT%*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }"
echo   Sharing stopped. The link no longer works.
timeout /t 3 >nul
endlocal
