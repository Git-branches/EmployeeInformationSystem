@echo off
rem ============================================================
rem  Employee Information System - first-run database setup
rem  Initialises the bundled database data directory and imports
rem  the schema. Safe to re-run: an existing database is kept.
rem ============================================================
setlocal EnableDelayedExpansion

set "APPDIR=%~dp0"
set "APPDIR=%APPDIR:~0,-1%"
set "DBDIR=%APPDIR%\stack\mariadb"
set "MYSQL=%DBDIR%\bin\mysql.exe"
set "DATADIR=%DBDIR%\data"
set "SQLFILE=%APPDIR%\www\employee_information_system.sql"
set "LOG=%APPDIR%\install.log"
set "TMPOUT=%APPDIR%\stack\tmp\dbcheck.txt"

call :log "database setup started"

rem ---- 1. Initialise the data directory on a fresh install ----
if exist "%DATADIR%\mysql" (
    echo Existing database found - keeping it.
    call :log "datadir already present, skipping init"
    goto :startserver
)

echo Preparing database files. This may take a minute...
if exist "%DBDIR%\bin\mysql_install_db.exe" (
    rem --- MariaDB
    "%DBDIR%\bin\mysql_install_db.exe" --datadir="%DATADIR%" >> "%LOG%" 2>&1
) else (
    rem --- MySQL 8
    if exist "%DATADIR%" rmdir /S /Q "%DATADIR%" >nul 2>&1
    "%DBDIR%\bin\mysqld.exe" --defaults-file="%DBDIR%\my.ini" --initialize-insecure --datadir="%DATADIR%" >> "%LOG%" 2>&1
)
if not exist "%DATADIR%\mysql" (
    call :log "ERROR: data directory init failed"
    echo Database preparation failed. See install.log for details.
    exit /b 1
)
call :log "data directory initialised"

:startserver
rem ---- 2. Start the server, creating the loopback root account.
rem MySQL/MariaDB create only root@localhost during initialisation, so a TCP
rem client from 127.0.0.1 is refused with ERROR 1130. --init-file runs this
rem SQL as root while the server starts, which fixes that once and for all.
set "INITSQL=%DBDIR%\eis-init.sql"
> "%INITSQL%" echo CREATE USER IF NOT EXISTS 'root'@'127.0.0.1';
>>"%INITSQL%" echo GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
>>"%INITSQL%" echo FLUSH PRIVILEGES;

echo Starting database service...
start "" /B "%DBDIR%\bin\mysqld.exe" --defaults-file="%DBDIR%\my.ini" --init-file="%INITSQL%"

set "READY=0"
for /L %%i in (1,1,45) do (
    if "!READY!"=="0" (
        "%MYSQL%" --port=3307 --host=127.0.0.1 -u root -e "SELECT 1" >nul 2>&1
        if not errorlevel 1 (set "READY=1") else (ping -n 2 127.0.0.1 >nul)
    )
)
if "!READY!"=="0" (
    call :log "ERROR: database did not accept connections"
    echo The database service did not start. See install.log for details.
    exit /b 1
)
call :log "database service ready on port 3307"

rem ---- 3. Import the schema only when the database is not there yet.
rem The result is written to a file and read back - nesting quotes inside
rem a FOR /F command is fragile in batch and can abort the script.
if not exist "%APPDIR%\stack\tmp" mkdir "%APPDIR%\stack\tmp" >nul 2>&1
set "DBEXISTS="
"%MYSQL%" --port=3307 --host=127.0.0.1 -u root -N -B -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'employee_information_system'" > "%TMPOUT%" 2>>"%LOG%"
if exist "%TMPOUT%" set /p DBEXISTS=<"%TMPOUT%"
if "%DBEXISTS%"=="" set "DBEXISTS=0"
call :log "existing database count: %DBEXISTS%"

if "%DBEXISTS%"=="0" (
    echo Importing database...
    "%MYSQL%" --port=3307 --host=127.0.0.1 -u root < "%SQLFILE%" >> "%LOG%" 2>&1
    if errorlevel 1 (
        call :log "ERROR: import failed"
        echo Database import failed. See install.log for details.
        exit /b 1
    )
    call :log "database imported"
) else (
    echo Database already exists - no import needed.
    call :log "database already existed, import skipped"
)

rem ---- 4. Confirm the import really produced the tables ----
set "TABLES="
"%MYSQL%" --port=3307 --host=127.0.0.1 -u root -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'employee_information_system'" > "%TMPOUT%" 2>>"%LOG%"
if exist "%TMPOUT%" set /p TABLES=<"%TMPOUT%"
if "%TABLES%"=="" set "TABLES=0"
del "%TMPOUT%" >nul 2>&1

if %TABLES% LSS 8 (
    call :log "ERROR: expected 8 tables, found %TABLES%"
    echo Database verification failed. See install.log for details.
    exit /b 1
)

rem ---- 5. Shut the temporary server down again.
rem The launcher starts the services properly; leaving this instance running
rem would also keep the installer's console handle open.
"%DBDIR%\bin\mysqladmin.exe" --port=3307 --host=127.0.0.1 -u root shutdown >nul 2>&1

call :log "verified %TABLES% tables - database setup finished OK"
echo Database ready.
exit /b 0

rem ------------------------------------------------------------
:log
echo [%DATE% %TIME%] %~1 >> "%LOG%"
exit /b 0
