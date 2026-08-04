@echo off
rem ============================================================
rem  Employee Information System - service controller
rem  Usage: eis-service.bat start ^| stop ^| status
rem  Runs from the install folder; no hard-coded paths.
rem
rem  Only processes started from THIS install folder are ever
rem  touched, so a Laragon/XAMPP stack on the same PC is safe.
rem ============================================================
setlocal EnableDelayedExpansion

set "APPDIR=%~dp0"
set "APPDIR=%APPDIR:~0,-1%"
set "HTTPD=%APPDIR%\stack\apache\bin\httpd.exe"
set "MYSQLD=%APPDIR%\stack\mariadb\bin\mysqld.exe"
set "MYSQL=%APPDIR%\stack\mariadb\bin\mysql.exe"
set "MYSQLADMIN=%APPDIR%\stack\mariadb\bin\mysqladmin.exe"

if /I "%~1"=="start"  goto :start
if /I "%~1"=="stop"   goto :stop
if /I "%~1"=="status" goto :status
echo Usage: %~nx0 [start^|stop^|status]
exit /b 1

rem ------------------------------------------------------------
:start
call :countOurs mysqld.exe
if "!OURS!"=="0" (
    echo Starting database...
    call :launch start-db
)
rem Wait for the database to accept connections before the web server needs it
for /L %%i in (1,1,30) do (
    if not defined DBUP (
        "%MYSQL%" --port=3307 --host=127.0.0.1 -u root -e "SELECT 1" >nul 2>&1
        if not errorlevel 1 (set "DBUP=1") else (ping -n 2 127.0.0.1 >nul)
    )
)

call :countOurs httpd.exe
if "!OURS!"=="0" (
    echo Starting web server...
    call :launch start-web
    ping -n 4 127.0.0.1 >nul
)
echo Ready at http://localhost:8080/
exit /b 0

rem ------------------------------------------------------------
:stop
echo Stopping web server...
"%HTTPD%" -f "%APPDIR%\stack\apache\conf\httpd.conf" -k stop >nul 2>&1
ping -n 3 127.0.0.1 >nul
call :killOurs httpd.exe

rem Only shut the database down when the running server is OURS. mysqladmin
rem targets a TCP port, not a particular installation, so sending it blindly
rem could stop a different copy of the system listening on the same port.
call :countOurs mysqld.exe
if "!OURS!"=="0" (
    echo Database is not running from this installation - leaving it alone.
    goto :stopped
)

echo Stopping database...
"%MYSQLADMIN%" --port=3307 --host=127.0.0.1 -u root shutdown >nul 2>&1

rem Give the server up to ~30 s to close its files cleanly
for /L %%i in (1,1,15) do (
    call :countOurs mysqld.exe
    if "!OURS!"=="0" goto :stopped
    ping -n 3 127.0.0.1 >nul
)
call :killOurs mysqld.exe
ping -n 3 127.0.0.1 >nul

:stopped
echo Stopped.
exit /b 0

rem ------------------------------------------------------------
:status
call :countOurs httpd.exe
echo Apache processes from this install: !OURS!
call :countOurs mysqld.exe
echo Database processes from this install: !OURS!
exit /b 0

rem ------------------------------------------------------------
rem Count running processes started from THIS install folder.
rem The work is done by eis-proc.ps1 so no PowerShell code has to be
rem quoted inside a FOR /F command - that nesting is unreliable in batch.
:countOurs
set "OURS="
for /f "usebackq delims=" %%n in (`powershell -NoProfile -ExecutionPolicy Bypass -File "%APPDIR%\eis-proc.ps1" -Action count -Name %~1 -Root "%APPDIR%"`) do set "OURS=%%n"
if not defined OURS set "OURS=0"
exit /b 0

rem Force-stop only the processes from THIS install folder.
:killOurs
powershell -NoProfile -ExecutionPolicy Bypass -File "%APPDIR%\eis-proc.ps1" -Action kill -Name %~1 -Root "%APPDIR%" >nul 2>&1
exit /b 0

rem Launch a service detached, so this console can close immediately.
:launch
powershell -NoProfile -ExecutionPolicy Bypass -File "%APPDIR%\eis-proc.ps1" -Action %~1 -Root "%APPDIR%" >nul 2>&1
exit /b 0
