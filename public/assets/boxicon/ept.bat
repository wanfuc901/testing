@echo off
chcp 65001 >nul
setlocal ENABLEDELAYEDEXPANSION
cls

rem ===== EXPORT FOLDER STRUCTURE OF VINCINE =====
cd /d "%~dp0"
set "OUTPUT=VincentCinemas_structure.txt"

echo.
echo Exporting folder structure of VincentCinemas...
echo (Saved to: %OUTPUT%)
echo.

(
    echo ==============================================
    echo   VIN-CINE PROJECT STRUCTURE EXPORT
    echo   Date: %date% %time%
    echo   Computer: %COMPUTERNAME%
    echo   User: %USERNAME%
    echo ==============================================
    echo.
) > "%OUTPUT%"

tree /f /a >> "%OUTPUT%"

echo Done. Structure saved to "%OUTPUT%".
pause >nul
endlocal
