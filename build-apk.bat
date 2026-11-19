@echo off
chcp 65001 >nul
echo ========================================================
echo   🚀 Sroor Coffee ERP - Building Mobile Android Package
echo ========================================================

cd /d "%~dp0backend"

echo.
echo [1/3] Compiling Frontend Web Assets (Vite + Tailwind)...
call npm run build
if %ERRORLEVEL% neq 0 (
    echo ❌ Frontend compilation failed!
    pause
    exit /b %ERRORLEVEL%
)

echo.
echo [2/3] Syncing Web Assets & Plugins with Android Native Project...
call npx cap sync android
if %ERRORLEVEL% neq 0 (
    echo ❌ Capacitor Android sync failed!
    pause
    exit /b %ERRORLEVEL%
)

echo.
echo [3/3] Checking Android Studio / Gradle...
if exist "%~dp0backend\android\gradlew.bat" (
    cd /d "%~dp0backend\android"
    call gradlew.bat assembleDirectDebug
    if %ERRORLEVEL% equ 0 (
        echo.
        echo 🎉 APK Build Complete!
        echo 📦 APK Location: backend\android\app\build\outputs\apk\direct\debug\app-direct-debug.apk
    ) else (
        echo ⚠️ Open in Android Studio via: cd backend ^&^& npx cap open android
    )
)

echo.
echo ✅ Done!
pause
