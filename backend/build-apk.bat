@echo off
setlocal enabledelayedexpansion
echo ====================================================
echo   Building Android APK via Capacitor.js - Cloud ERP POS
echo ====================================================

:: Set Java 17 Home & Android SDK
for /d %%i in ("C:\Program Files\Microsoft\jdk-17*") do set "JAVA_HOME=%%i"
if not defined JAVA_HOME (
    for /d %%i in ("C:\Program Files\Java\jdk-17*") do set "JAVA_HOME=%%i"
)
if not defined JAVA_HOME (
    for /d %%i in ("C:\Program Files\Eclipse Adoptium\jdk-17*") do set "JAVA_HOME=%%i"
)

set "ANDROID_HOME=C:\Android\Sdk"
set "ANDROID_SDK_ROOT=C:\Android\Sdk"
set "PATH=%JAVA_HOME%\bin;C:\Android\Sdk\cmdline-tools\latest\bin;C:\Android\Sdk\platform-tools;%PATH%"

echo [1/3] Building frontend assets with Vite...
call npm run build
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Vite build failed!
    pause
    exit /b %ERRORLEVEL%
)

echo.
echo [2/3] Syncing Capacitor Android assets...
call npx cap sync android
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Capacitor sync failed!
    pause
    exit /b %ERRORLEVEL%
)

echo.
echo [3/3] Compiling Android APK with Gradle...
cd android
call gradlew.bat assembleDebug
cd ..

echo.
if exist "android\app\build\outputs\apk\debug\app-debug.apk" (
    if not exist "dist" mkdir dist
    copy /y "android\app\build\outputs\apk\debug\app-debug.apk" "dist\cloud-erp-pos-debug.apk" >nul
    copy /y "android\app\build\outputs\apk\debug\app-debug.apk" "cloud-erp-pos-debug.apk" >nul
    echo ====================================================
    echo   BUILD SUCCESS! APK ready at:
    echo   - backend\dist\cloud-erp-pos-debug.apk
    echo   - backend\cloud-erp-pos-debug.apk
    echo ====================================================
) else (
    echo [WARNING] Gradle finished. Check android/app/build/outputs/apk/ for output.
)

pause
