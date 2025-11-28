@echo off
chcp 65001 >nul
setlocal ENABLEDELAYEDEXPANSION
cls

cd /d "%~dp0"
set "OUTPUT=structure.txt"

echo.
echo Scanning project folder structure...
echo (Saving to: %OUTPUT%)
echo.

(
    echo ==============================================
    echo   PROJECT STRUCTURE EXPORT
    echo   Time: %date% %time%
    echo   Computer: %COMPUTERNAME%
    echo   User: %USERNAME%
    echo ==============================================
    echo.
) > "%OUTPUT%"


tree /f /a >> "%OUTPUT%"

echo Done! Structure saved to "%OUTPUT%".
pause >nul
endlocal
