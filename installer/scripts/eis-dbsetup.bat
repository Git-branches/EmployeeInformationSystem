@echo off
rem ============================================================
rem  Employee Information System - first-run database setup
rem  Initialises the bundled MariaDB/MySQL data directory and
rem  imports the schema. Safe to re-run: it never touches an
rem  existing database.
rem ============================================================
setlocal EnableDelayedExpansion

set "APPDIR=%~dp0"
set "APPDIR=%APPDIR:~0,-1%"
set "DBDIR=%APPDIR%\stack\mariadb"
set "DATADIR=%DBDIR%\data"
set "SQLFILE=%APPDIR%\www\employee_information_system.sql"
set "LOG=%APPDIR%\install.log"

echo [%DATE% %TIME%] database setup started >> "%LOG%"

rem ---- 1. Initialise the data directory if this is a fresh install
if exist "%DATADIR%\mysql" (
    echo Existing database found - keeping it.
    echo [%DATE% %TIME%] datadir already present, skipping init >> "%LOG%"
) else (
    echo Preparing database files. This may take a minute...
    if exist "%DBDIR%\bin\mysql_install_db.exe" (
        rem --- MariaDB
        "%DBDIR%\bin\mysql_install_db.exe" --datadir="%DATADIR%" >> "%LOG%" 2>&1
    ) else (
        rem --- MySQL 8
        if exist "%DATADIR%" rmdir /S /Q "%DATADIR%" >nul 2>&1
        "%DBDIR%\bin\mysqld.exe" --defaults-file="%DBDIR%\my.ini" --initialize-insecure --datadir="%DATADIR%" >> "%LOG%" 2>&1
    )
    if errorlevel 1 (
        echo [%DATE% %TIME%] ERROR: data directory init failed >> "%LOG%"
        echo Database preparation failed. See install.log for details.
        exit /b 1
    )
)

rem ---- 2. Start the server temporarily
echo Starting database service...
start "" /B "%DBDIR%\bin\mysqld.exe" --defaults-file="%DBDIR%\my.ini"

set "READY=0"
for /L %%i in (1,1,40) do (
    "%DBDIR%\bin\mysql.exe" --port=3307 --host=127.0.0.1 -u root -e "SELECT 1" >nul 2>&1
    if not errorlevel 1 (
        set "READY=1"
        goto :dbup
    )
    ping -n 2 127.0.0.1 >nul
)
:dbup
if "!READY!"=="0" (
    echo [%DATE% %TIME%] ERROR: database did not start >> "%LOG%"
    echo The database service did not start. See install.log for details.
    exit /b 1
)

rem ---- 3. Import the schema only when the database is absent
for /f %%c in ('""%DBDIR%\bin\mysql.exe" --port=3307 --host=127.0.0.1 -u root -N -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='employee_information_system'""') do set "DBEXISTS=%%c"

if "!DBEXISTS!"=="0" (
    echo Importing database...
    "%DBDIR%\bin\mysql.exe" --port=3307 --host=127.0.0.1 -u root < "%SQLFILE%" >> "%LOG%" 2>&1
    if errorlevel 1 (
        echo [%DATE% %TIME%] ERROR: import failed >> "%LOG%"
        echo Database import failed. See install.log for details.
        exit /b 1
    )
    echo [%DATE% %TIME%] database imported >> "%LOG%"
) else (
    echo Database already exists - no import needed.
    echo [%DATE% %TIME%] database already existed, import skipped >> "%LOG%"
)

echo [%DATE% %TIME%] database setup finished OK >> "%LOG%"
echo Database ready.
exit /b 0
