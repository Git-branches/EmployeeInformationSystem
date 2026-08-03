@echo off
rem Stops the Employee Information System services.
rem Used by the "Stop System" shortcut and before uninstalling.
set "APPDIR=%~dp0"
set "APPDIR=%APPDIR:~0,-1%"
call "%APPDIR%\eis-service.bat" stop
if /I not "%~1"=="/silent" pause
