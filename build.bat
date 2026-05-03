@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

echo ============================
echo Building WordPress Plugin...
echo ============================

REM ----------------------------
REM CONFIG
REM ----------------------------
set PLUGIN_SLUG=my-village-hall
set MAIN_FILE=my-village-hall.php

REM ----------------------------
REM STEP 1: Install dependencies
REM ----------------------------
echo Installing Composer dependencies...
call composer install --no-dev --optimize-autoloader
if errorlevel 1 (
  echo ERROR: Composer install failed
  exit /b 1
)

REM ----------------------------
REM STEP 2: Get version
REM ----------------------------
for /f "delims=" %%i in ('git describe --tags --abbrev=0 2^>^&1') do set VERSION=%%i

REM Discard git error messages
echo !VERSION! | findstr /i /c:"fatal" >nul && set VERSION=

if defined VERSION (
  set VERSION=!VERSION:v=!
) else (
  set VERSION=dev-build
)

echo Detected version: !VERSION!

REM ----------------------------
REM STEP 3: Move to parent dir
REM ----------------------------
cd ..
if errorlevel 1 exit /b 1

REM ----------------------------
REM STEP 4: Prepare dist folder
REM ----------------------------
if exist dist rmdir /s /q dist
mkdir dist

REM ----------------------------
REM STEP 5: Copy plugin
REM ----------------------------
echo Copying files...
xcopy %PLUGIN_SLUG% dist\%PLUGIN_SLUG% /E /I /Y >nul

cd dist\%PLUGIN_SLUG%
if errorlevel 1 exit /b 1

REM ----------------------------
REM STEP 6: Validate structure
REM ----------------------------
if not exist %MAIN_FILE% (
  echo ERROR: %MAIN_FILE% not found in plugin root
  pause
  exit /b 1
)

REM ----------------------------
REM STEP 7: Inject version
REM ----------------------------
echo Injecting version into plugin header...
powershell -NoProfile -ExecutionPolicy Bypass -Command "(Get-Content '%MAIN_FILE%') -replace 'Version:\s*.*', 'Version: !VERSION!' | Set-Content '%MAIN_FILE%'"

REM ----------------------------
REM STEP 8: Cleanup
REM ----------------------------
echo Cleaning unnecessary files...

for %%d in (
  .git
  .github
  node_modules
  tests
  test
  coverage
  playwright-report
  test-results
) do (
  if exist %%d rmdir /s /q %%d
)

for %%f in (
  .gitignore
  phpunit.xml
  jest.config.js
  package.json
  package-lock.json
  webpack.config.js
  vite.config.js
  .env
) do (
  if exist %%f del /q %%f
)

del /q *.log 2>nul
del /q .env.* 2>nul

REM ----------------------------
REM STEP 9: Create ZIP
REM ----------------------------
cd ..
if exist %PLUGIN_SLUG%.zip del %PLUGIN_SLUG%.zip

echo Creating ZIP...
powershell Compress-Archive -Path %PLUGIN_SLUG% -DestinationPath %PLUGIN_SLUG%.zip

if errorlevel 1 (
  echo ERROR: ZIP creation failed
  exit /b 1
)

REM ----------------------------
REM DONE
REM ----------------------------
echo ============================
echo Build complete!
echo Output: dist\%PLUGIN_SLUG%.zip
echo Version: !VERSION!
echo ============================

pause