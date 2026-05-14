@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

set MAIN_FILE=my-village-hall.php
set CURRENT_VERSION=

for /f "tokens=2 delims=:" %%i in ('findstr /R /C:"^[ ]*\* Version:[ ]*" "%MAIN_FILE%"') do (
    set CURRENT_VERSION=%%i
    goto :trim_version
)

:trim_version
for /f "tokens=* delims= " %%i in ("!CURRENT_VERSION!") do set CURRENT_VERSION=%%i

if not defined CURRENT_VERSION (
    echo ERROR: Could not find current version in %MAIN_FILE%
    exit /b 1
)

for /f "delims=" %%i in ('powershell -NoProfile -ExecutionPolicy Bypass -Command "$version = ''!CURRENT_VERSION!''; if ($version -notmatch ''^\d+\.\d+\.\d+$'') { exit 1 }; $parts = $version.Split(''.''); $parts[2] = ([int]$parts[2] + 1); $parts -join ''.''"') do set NEXT_VERSION=%%i

if not defined NEXT_VERSION (
    echo ERROR: Failed to calculate next version from !CURRENT_VERSION!
    exit /b 1
)

echo Current version: !CURRENT_VERSION!
echo Next version: !NEXT_VERSION!

call "%~dp0build.bat" "!NEXT_VERSION!"
set BUILD_EXIT=!errorlevel!
exit /b !BUILD_EXIT!
