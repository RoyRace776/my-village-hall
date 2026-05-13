@echo off
REM Get the current version from my-village-hall.php
for /f "tokens=*" %%a in ('findstr "Version:" my-village-hall.php') do (
    for /f "tokens=2 delims=:" %%b in ("%%a") do (
        echo Current Version:%%b
        goto :end
    )
)
:end
