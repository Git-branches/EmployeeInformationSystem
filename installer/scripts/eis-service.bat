@echo off
rem ============================================================
rem  Employee Information System - service controller
rem  Usage: eis-service.bat start ^| stop ^| status
rem  Runs from the install folder; no hard-coded paths.
rem ============================================================
setlocal EnableDelayedExpansion

set "APPDIR=%~dp0"
set "APPDIR=%APPDIR:~0,-1%"
set "HTTPD=%APPDIR%\stack\apache\bin\httpd.exe"
set "MYSQLD=%APPDIR%\stack\mariadb\bin\mysqld.exe"
set "MYSQLADMIN=%APPDIR%\stack\mariadb\bin\mysqladmin.exe"

if /I "%~1"=="start"  goto :start
if /I "%~1"=="stop"   goto :stop
if /I "%~1"=="status" goto :status
echo Usage: %~nx0 [start^|stop^|status]
exit /b 1

rem ------------------------------------------------------------
:start
echo Starting database...
call :isRunning mysqld.exe
if "!RUNNING!"=="0" (
    start "" /B "%MYSQLD%" --defaults-file="%APPDIR%\stack\mariadb\my.ini"
    call :waitForDb
)

echo Starting web server...
call :isRunning httpd.exe
if "!RUNNING!"=="0" (
    start "" /B "%HTTPD%" -f "%APPDIR%\stack\apache\conf\httpd.conf"
    ping -n 3 127.0.0.1 >nul
)
echo Ready at http://localhost:8080/
exit /b 0

rem ------------------------------------------------------------
:stop
echo Stopping web server...
"%HTTPD%" -f "%APPDIR%\stack\apache\conf\httpd.conf" -k stop >nul 2>&1
taskkill /IM httpd.exe /F >nul 2>&1

echo Stopping database...
"%MYSQLADMIN%" --port=3307 --host=127.0.0.1 -u root shutdown >nul 2>&1
if errorlevel 1 taskkill /IM mysqld.exe /F >nul 2>&1
echo Stopped.
exit /b 0

rem ------------------------------------------------------------
:status
call :isRunning httpd.exe
echo Apache running: !RUNNING!
call :isRunning mysqld.exe
echo MariaDB running: !RUNNING!
exit /b 0

rem ------------------------------------------------------------
:isRunning
set "RUNNING=0"
tasklist /FI "IMAGENAME eq %~1" 2>nul | find /I "%~1" >nul && set "RUNNING=1"
exit /b 0

rem Wait until the database answers (max ~30s)
:waitForDb
for /L %%i in (1,1,30) do (
    "%APPDIR%\stack\mariadb\bin\mysql.exe" --port=3307 --host=127.0.0.1 -u root -e "SELECT 1" >nul 2>&1
    if not errorlevel 1 exit /b 0
    ping -n 2 127.0.0.1 >nul
)
exit /b 1
