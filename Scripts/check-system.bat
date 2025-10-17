@echo off
:: ============================================
:: Script de diagnostic du système
:: Video Downloader
:: ============================================

title Video Downloader - System Check
color 0E

:: Charger la configuration
if exist "%~dp0config.bat" (
    call "%~dp0config.bat"
) else (
    echo [ATTENTION] Fichier config.bat non trouve - utilisation des valeurs par defaut
    set PHP_PATH=C:\wamp64\bin\php\php8.2.12\php.exe
    set REDIS_PATH=C:\Redis\redis-server.exe
    set REDIS_CLI=C:\Redis\redis-cli.exe
    set PROJECT_DIR=%~dp0
)

echo.
echo ========================================
echo   VIDEO DOWNLOADER - DIAGNOSTIC
echo ========================================
echo.

set TOTAL_CHECKS=0
set PASSED_CHECKS=0
set FAILED_CHECKS=0
set WARNING_CHECKS=0

:: ============================================
:: SECTION 1: Vérification des exécutables
:: ============================================
echo [SECTION 1] Verification des executables
echo ----------------------------------------
echo.

:: Vérifier PHP
set /a TOTAL_CHECKS+=1
if exist "%PHP_PATH%" (
    echo [OK] PHP trouve: %PHP_PATH%
    "%PHP_PATH%" -v | findstr "PHP"
    set /a PASSED_CHECKS+=1
) else (
    echo [ERREUR] PHP non trouve: %PHP_PATH%
    set /a FAILED_CHECKS+=1
)
echo.

:: Vérifier Composer
set /a TOTAL_CHECKS+=1
where composer >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] Composer trouve
    for /f "tokens=*" %%i in ('composer --version') do echo     %%i
    set /a PASSED_CHECKS+=1
) else (
    echo [ERREUR] Composer non trouve dans le PATH
    set /a FAILED_CHECKS+=1
)
echo.

:: Vérifier Redis
set /a TOTAL_CHECKS+=1
if exist "%REDIS_PATH%" (
    echo [OK] Redis trouve: %REDIS_PATH%
    set /a PASSED_CHECKS+=1
) else (
    echo [ERREUR] Redis non trouve: %REDIS_PATH%
    set /a FAILED_CHECKS+=1
)
echo.

:: Vérifier yt-dlp
set /a TOTAL_CHECKS+=1
where yt-dlp >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] yt-dlp trouve
    for /f "tokens=*" %%i in ('yt-dlp --version') do echo     Version: %%i
    set /a PASSED_CHECKS+=1
) else (
    echo [ATTENTION] yt-dlp non trouve - telechargement ne fonctionnera pas
    set /a WARNING_CHECKS+=1
)
echo.

:: Vérifier FFmpeg
set /a TOTAL_CHECKS+=1
where ffmpeg >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] FFmpeg trouve
    for /f "tokens=*" %%i in ('ffmpeg -version ^| findstr "ffmpeg version"') do echo     %%i
    set /a PASSED_CHECKS+=1
) else (
    echo [ATTENTION] FFmpeg non trouve - transcodage ne fonctionnera pas
    set /a WARNING_CHECKS+=1
)
echo.

:: Vérifier FFprobe
set /a TOTAL_CHECKS+=1
where ffprobe >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] FFprobe trouve
    set /a PASSED_CHECKS+=1
) else (
    echo [ATTENTION] FFprobe non trouve
    set /a WARNING_CHECKS+=1
)
echo.

timeout /t 2 /nobreak >nul

:: ============================================
:: SECTION 2: Extensions PHP
:: ============================================
echo [SECTION 2] Extensions PHP requises
echo ----------------------------------------
echo.

cd /d "%PROJECT_DIR%"

:: Liste des extensions requises
set EXTENSIONS=curl fileinfo mbstring openssl redis sockets

for %%e in (%EXTENSIONS%) do (
    set /a TOTAL_CHECKS+=1
    "%PHP_PATH%" -m | findstr /i "%%e" >nul
    if !errorLevel! equ 0 (
        echo [OK] Extension %%e activee
        set /a PASSED_CHECKS+=1
    ) else (
        echo [ERREUR] Extension %%e manquante
        set /a FAILED_CHECKS+=1
    )
)
echo.

timeout /t 2 /nobreak >nul

:: ============================================
:: SECTION 3: Services en cours d'exécution
:: ============================================
echo [SECTION 3] Services en cours d'execution
echo ----------------------------------------
echo.

:: Vérifier Redis
set /a TOTAL_CHECKS+=1
tasklist /FI "IMAGENAME eq redis-server.exe" 2>NUL | find /I /N "redis-server.exe">NUL
if %errorLevel% equ 0 (
    echo [OK] Redis en cours d'execution
    "%REDIS_CLI%" ping >nul 2>&1
    if !errorLevel! equ 0 (
        echo     Redis repond au PING
    ) else (
        echo     [ATTENTION] Redis ne repond pas au PING
    )
    set /a PASSED_CHECKS+=1
) else (
    echo [ATTENTION] Redis n'est pas en cours d'execution
    set /a WARNING_CHECKS+=1
)
echo.

:: Vérifier Apache
set /a TOTAL_CHECKS+=1
sc query wampapache64 2>nul | find "RUNNING" >nul
if %errorLevel% equ 0 (
    echo [OK] Apache (WAMP) en cours d'execution
    set /a PASSED_CHECKS+=1
) else (
    echo [ATTENTION] Apache (WAMP) n'est pas en cours d'execution
    set /a WARNING_CHECKS+=1
)
echo.

:: Vérifier les Workers
set /a TOTAL_CHECKS+=1
tasklist /FI "WINDOWTITLE eq Messenger Worker*" 2>NUL | find /I /N "cmd.exe">NUL
if %errorLevel% equ 0 (
    echo [OK] Workers Messenger en cours d'execution
    for /f %%i in ('tasklist /FI "WINDOWTITLE eq Messenger Worker*" ^| find /c "cmd.exe"') do echo     Nombre de workers: %%i
    set /a PASSED_CHECKS+=1
) else (
    echo [ATTENTION] Aucun worker Messenger en cours d'execution
    set /a WARNING_CHECKS+=1
)
echo.

timeout /t 2 /nobreak >nul

:: ============================================
:: SECTION 4: Structure du projet
:: ============================================
echo [SECTION 4] Structure du projet
echo ----------------------------------------
echo.

:: Vérifier les dossiers
set FOLDERS=var var\sessions var\log var\cache config src public

for %%f in (%FOLDERS%) do (
    set /a TOTAL_CHECKS+=1
    if exist "%%f" (
        echo [OK] Dossier %%f existe
        set /a PASSED_CHECKS+=1
    ) else (
        echo [ERREUR] Dossier %%f manquant
        set /a FAILED_CHECKS+=1
    )
)
echo.

:: Vérifier les fichiers importants
set FILES=composer.json .env config\packages\messenger.yaml

for %%f in (%FILES%) do (
    set /a TOTAL_CHECKS+=1
    if exist "%%f" (
        echo [OK] Fichier %%f existe
        set /a PASSED_CHECKS+=1
    ) else (
        echo [ERREUR] Fichier %%f manquant
        set /a FAILED_CHECKS+=1
    )
)
echo.

:: Vérifier vendor
set /a TOTAL_CHECKS+=1
if exist "vendor" (
    echo [OK] Dependances Composer installees
    set /a PASSED_CHECKS+=1
) else (
    echo [ERREUR] Dependances Composer non installees
    echo     Executez: composer install
    set /a FAILED_CHECKS+=1
)
echo.

timeout /t 2 /nobreak >nul

:: ============================================
:: SECTION 5: Configuration
:: ============================================
echo [SECTION 5] Configuration
echo ----------------------------------------
echo.

:: Vérifier .env.local
set /a TOTAL_CHECKS+=1
if exist ".env.local" (
    echo [OK] Fichier .env.local existe
    set /a PASSED_CHECKS+=1
) else (
    echo [ATTENTION] Fichier .env.local manquant
    echo     Copiez .env en .env.local et configurez-le
    set /a WARNING_CHECKS+=1
)
echo.

:: Vérifier le fichier hosts
set /a TOTAL_CHECKS+=1
findstr /C:"video-downloader.local" C:\Windows\System32\drivers\etc\hosts >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] Entree dans le fichier hosts
    set /a PASSED_CHECKS+=1
) else (
    echo [ATTENTION] Entree manquante dans le fichier hosts
    echo     Ajoutez: 127.0.0.1    video-downloader.local
    set /a WARNING_CHECKS+=1
)
echo.

timeout /t 2 /nobreak >nul

:: ============================================
:: SECTION 6: Connectivité Redis
:: ============================================
echo [SECTION 6] Test de connectivite Redis
echo ----------------------------------------
echo.

set /a TOTAL_CHECKS+=1
if exist "%REDIS_CLI%" (
    "%REDIS_CLI%" ping >nul 2>&1
    if !errorLevel! equ 0 (
        echo [OK] Connexion Redis reussie
        
        :: Tester SET/GET
        "%REDIS_CLI%" SET test_key "test_value" >nul 2>&1
        for /f "tokens=*" %%i in ('"%REDIS_CLI%" GET test_key') do set TEST_VALUE=%%i
        "%REDIS_CLI%" DEL test_key >nul 2>&1
        
        if "!TEST_VALUE!"=="test_value" (
            echo [OK] Operations Redis fonctionnelles
        )
        
        :: Voir les statistiques Redis
        echo.
        echo Statistiques Redis:
        for /f "tokens=*" %%i in ('"%REDIS_CLI%" INFO stats ^| findstr "total_connections_received total_commands_processed"') do echo     %%i
        
        set /a PASSED_CHECKS+=1
    ) else (
        echo [ERREUR] Impossible de se connecter a Redis
        echo     Verifiez que Redis est demarre
        set /a FAILED_CHECKS+=1
    )
) else (
    echo [ERREUR] Redis CLI non trouve
    set /a FAILED_CHECKS+=1
)
echo.

timeout /t 2 /nobreak >nul

:: ============================================
:: SECTION 7: Permissions
:: ============================================
echo [SECTION 7] Permissions des dossiers
echo ----------------------------------------
echo.

set /a TOTAL_CHECKS+=1
echo test > var\sessions\write_test.tmp 2>nul
if exist "var\sessions\write_test.tmp" (
    del var\sessions\write_test.tmp >nul 2>&1
    echo [OK] Ecriture dans var\sessions possible
    set /a PASSED_CHECKS+=1
) else (
    echo [ERREUR] Impossible d'ecrire dans var\sessions
    echo     Executez: icacls var /grant Everyone:F /T
    set /a FAILED_CHECKS+=1
)
echo.

:: ============================================
:: RÉSUMÉ
:: ============================================
echo.
echo ========================================
echo   RESUME DU DIAGNOSTIC
echo ========================================
echo.
echo Total de verifications: %TOTAL_CHECKS%
echo [OK]        Reussies  : %PASSED_CHECKS%
echo [ATTENTION] Warnings  : %WARNING_CHECKS%
echo [ERREUR]    Echecs    : %FAILED_CHECKS%
echo.

if %FAILED_CHECKS% equ 0 (
    if %WARNING_CHECKS% equ 0 (
        echo [SUCCES] Votre systeme est pret !
        echo.
        echo Vous pouvez lancer l'application avec: start-app.bat
        color 0A
    ) else (
        echo [AVERTISSEMENT] Systeme fonctionnel avec quelques avertissements
        echo.
        echo Certaines fonctionnalites peuvent ne pas marcher
        color 0E
    )
) else (
    echo [ERREUR] Des problemes critiques ont ete detectes
    echo.
    echo Veuillez corriger les erreurs avant de lancer l'application
    color 0C
)

echo.
echo ========================================
echo.

pause