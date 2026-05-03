@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"
set ROOT_DIR=%cd%

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
set RELEASE_MODE=0
set VERSION=

if not "%~1"=="" (
  set RELEASE_MODE=1
  set VERSION=%~1
  set VERSION=!VERSION:v=!

  echo Requested release version: !VERSION!
  powershell -NoProfile -ExecutionPolicy Bypass -Command "if ('!VERSION!' -match '^\d+\.\d+\.\d+$') { exit 0 } else { exit 1 }"
  if errorlevel 1 (
    echo ERROR: Invalid version format "!VERSION!"
    echo Expected format: X.Y.Z (example: 3.0.3^)
    exit /b 1
  )
) else (
  for /f "delims=" %%i in ('git describe --tags --abbrev=0 2^>^&1') do set VERSION=%%i

  REM Discard git error messages
  echo !VERSION! | findstr /i /c:"fatal" >nul && set VERSION=

  REM Normalize leading "v" from tag names (e.g. v1.2.3 -> 1.2.3)
  if defined VERSION set VERSION=!VERSION:v=!

  REM Fallback to plugin header version if no valid git tag was found
  if not defined VERSION (
    for /f "tokens=2 delims=:" %%i in ('findstr /R /C:"^[ ]*\* Version:[ ]*" "%MAIN_FILE%"') do set VERSION=%%i
    for /f "tokens=* delims= " %%i in ("!VERSION!") do set VERSION=%%i
  )

  if not defined VERSION set VERSION=dev-build
)

echo Detected version: !VERSION!

REM ----------------------------
REM STEP 3: Update source version for release builds
REM ----------------------------
if "!RELEASE_MODE!"=="1" (
  echo Updating version metadata in %MAIN_FILE%...
  powershell -NoProfile -ExecutionPolicy Bypass -Command "$path='%MAIN_FILE%'; $content=Get-Content -Path $path -Raw; $content=$content -replace '(?m)^(\s*\*\s*Version:\s*).*$','$1!VERSION!'; $content=$content -replace '(?m)^(define\(\s*''MYVH_VERSION''\s*,\s*'').*?(''\s*\)\s*;)$','$1!VERSION!$2'; $utf8NoBom=New-Object System.Text.UTF8Encoding($false); [System.IO.File]::WriteAllText($path,$content,$utf8NoBom)"
  if errorlevel 1 (
    echo ERROR: Failed to update version metadata in %MAIN_FILE%
    exit /b 1
  )
)

REM ----------------------------
REM STEP 4: Move to parent dir
REM ----------------------------
cd ..
if errorlevel 1 exit /b 1

REM ----------------------------
REM STEP 5: Prepare dist folder
REM ----------------------------
if exist dist rmdir /s /q dist
mkdir dist

REM ----------------------------
REM STEP 6: Copy plugin
REM ----------------------------
echo Copying files...
xcopy %PLUGIN_SLUG% dist\%PLUGIN_SLUG% /E /I /Y >nul

cd dist\%PLUGIN_SLUG%
if errorlevel 1 exit /b 1

REM ----------------------------
REM STEP 7: Validate structure
REM ----------------------------
if not exist %MAIN_FILE% (
  echo ERROR: %MAIN_FILE% not found in plugin root
  pause
  exit /b 1
)

REM ----------------------------
REM STEP 8: Inject version into distributable
REM ----------------------------
echo Injecting version metadata into distributable...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$path='%MAIN_FILE%'; $content=Get-Content -Path $path -Raw; $content=$content -replace '(?m)^(\s*\*\s*Version:\s*).*$','$1!VERSION!'; $content=$content -replace '(?m)^(define\(\s*''MYVH_VERSION''\s*,\s*'').*?(''\s*\)\s*;)$','$1!VERSION!$2'; $utf8NoBom=New-Object System.Text.UTF8Encoding($false); [System.IO.File]::WriteAllText($path,$content,$utf8NoBom)"
if errorlevel 1 (
  echo ERROR: Failed to inject version metadata into distributable
  exit /b 1
)

REM ----------------------------
REM STEP 9: Cleanup
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
REM STEP 10: Create ZIP
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
REM STEP 11: Create git tag for release builds
REM ----------------------------
if "!RELEASE_MODE!"=="1" (
  echo Creating git commit and tag...
  pushd "%ROOT_DIR%"

  git rev-parse --is-inside-work-tree >nul 2>nul
  if errorlevel 1 (
    echo ERROR: Not a git repository. Cannot create release tag.
    popd
    exit /b 1
  )

  git rev-parse "v!VERSION!" >nul 2>nul
  if not errorlevel 1 (
    echo ERROR: Tag v!VERSION! already exists.
    popd
    exit /b 1
  )

  git add "%MAIN_FILE%"
  git diff --cached --quiet -- "%MAIN_FILE%"
  if errorlevel 1 (
    git commit -m "chore: release v!VERSION!" -- "%MAIN_FILE%"
    if errorlevel 1 (
      echo ERROR: Failed to create release commit.
      popd
      exit /b 1
    )
  ) else (
    echo %MAIN_FILE% is unchanged; skipping release commit.
  )

  git tag -a "v!VERSION!" -m "Release v!VERSION!"
  if errorlevel 1 (
    echo ERROR: Failed to create git tag v!VERSION!.
    popd
    exit /b 1
  )

  popd
  echo Created git tag: v!VERSION!
  echo To push: git push origin main --tags
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