@echo off
:: ============================================
:: Script de démarrage complet de l'application
:: Video Downloader avec Queue
:: ============================================

title Video Downloader - Startup Script
color 0A

:: Charger la configuration
if exist "%~dp0config.bat" (
    call "%~dp0config.bat"
) else (
    echo [ERREUR] Fichier config.bat non trouve
    pause
    exit /b 1
)

echo.
echo ========================================
echo   VIDEO DOWNLOADER - DEMARRAGE
echo ========================================
echo.

:: Vérifier les droits administrateur
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERREUR] Ce script necessite les droits administrateur
    echo Faites un clic droit et selectionnez "Executer en tant qu'administrateur"
    pause
    exit /b 1
)

echo [INFO] Repertoire du projet: %PROJECT_DIR%
echo [INFO] Configuration chargee depuis config.bat
echo.

:: ============================================
:: ETAPE 1: Vérifier les dépendances
:: ============================================
echo [1/6] Verification des dependances...
echo.

echo [OK] PHP trouve: %PHP_PATH%

:: Vérifier Composer
where composer >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERREUR] Composer non trouve dans le PATH
    pause
    exit /b 1
)
echo [OK] Composer trouve

:: Vérifier yt-dlp
where yt-dlp >nul 2>&1
if %errorLevel% neq 0 (
    echo [ATTENTION] yt-dlp non trouve - le telechargement ne fonctionnera pas
) else (
    echo [OK] yt-dlp trouve
)

:: Vérifier FFmpeg
where ffmpeg >nul 2>&1
if %errorLevel% neq 0 (
    echo [ATTENTION] FFmpeg non trouve - le transcodage ne fonctionnera pas
) else (
    echo [OK] FFmpeg trouve
)

echo [OK] Redis trouve: %REDIS_PATH%

echo.
timeout /t 2 /nobreak >nul

:: ============================================
:: ETAPE 2: Démarrer Redis
:: ============================================
echo [2/6] Demarrage de Redis...
echo.

:: Vérifier si Redis est déjà en cours d'exécution
tasklist /FI "IMAGENAME eq redis-server.exe" 2>NUL | find /I /N "redis-server.exe">NUL
if %errorLevel% equ 0 (
    echo [INFO] Redis est deja en cours d'execution
) else (
    echo [INFO] Demarrage de Redis...
    start "Redis Server" "%REDIS_PATH%" "%REDIS_CONF%"
    timeout /t 3 /nobreak >nul
    
    :: Vérifier que Redis a bien démarré
    "%REDIS_CLI%" ping >nul 2>&1
    if %errorLevel% equ 0 (
        echo [OK] Redis demarre avec succes
    ) else (
        echo [ERREUR] Redis n'a pas demarre correctement
        pause
        exit /b 1
    )
)

echo.
timeout /t 1 /nobreak >nul

:: ============================================
:: ETAPE 3: Démarrer Apache (WAMP)
:: ============================================
echo [3/6] Demarrage d'Apache...
echo.

:: Vérifier si Apache est en cours d'exécution
sc query %APACHE_SERVICE% | find "RUNNING" >nul
if %errorLevel% equ 0 (
    echo [INFO] Apache est deja en cours d'execution
) else (
    echo [INFO] Demarrage d'Apache...
    net start %APACHE_SERVICE% >nul 2>&1
    if %errorLevel% equ 0 (
        echo [OK] Apache demarre avec succes
    ) else (
        echo [ATTENTION] Impossible de demarrer Apache automatiquement
        echo Veuillez demarrer WAMP manuellement
    )
)

echo.
timeout /t 1 /nobreak >nul

:: ============================================
:: ETAPE 4: Vérifier l'installation Composer
:: ============================================
echo [4/6] Verification de l'installation Composer...
echo.

cd /d "%PROJECT_DIR%"

if not exist "vendor" (
    echo [INFO] Installation des dependances Composer...
    composer install --no-interaction
    if %errorLevel% neq 0 (
        echo [ERREUR] Echec de l'installation Composer
        pause
        exit /b 1
    )
    echo [OK] Dependances installees
) else (
    echo [OK] Dependances deja installees
)

echo.
timeout /t 1 /nobreak >nul

:: ============================================
:: ETAPE 5: Créer les dossiers nécessaires
:: ============================================
echo [5/6] Creation des dossiers necessaires...
echo.

if not exist "var\sessions" (
    mkdir "var\sessions"
    echo [OK] Dossier var\sessions cree
)

if not exist "var\log" (
    mkdir "var\log"
    echo [OK] Dossier var\log cree
)

if not exist "var\cache" (
    mkdir "var\cache"
    echo [OK] Dossier var\cache cree
)

echo.
timeout /t 1 /nobreak >nul

:: ============================================
:: ETAPE 6: Démarrer les Workers Messenger
:: ============================================
echo [6/6] Demarrage des workers Messenger...
echo.

:: Tuer les anciens workers s'ils existent
taskkill /FI "WINDOWTITLE eq Messenger Worker*" /F >nul 2>&1

:: Démarrer les nouveaux workers
for /L %%i in (1,1,%WORKER_COUNT%) do (
    start "Messenger Worker %%i" cmd /k "cd /d "%PROJECT_DIR%" && "%PHP_PATH%" bin/console messenger:consume async --time-limit=%WORKER_TIME_LIMIT% --memory-limit=%WORKER_MEMORY_LIMIT% -vv"
    echo [OK] Worker %%i demarre
    timeout /t %WORKER_START_DELAY% /nobreak >nul
)

echo.
echo ========================================
echo   DEMARRAGE TERMINE !
echo ========================================
echo.
echo [INFO] Application accessible sur:
echo        http://%DOMAIN_NAME%
echo        ou http://localhost/video-downloader/public
echo.
echo [INFO] %WORKER_COUNT% workers Messenger sont actifs
echo.
echo [INFO] Pour arreter l'application, executez: stop-app.bat
echo.
echo [INFO] Pour voir la configuration, executez: config.bat show
echo.
echo Appuyez sur une touche pour ouvrir l'application dans le navigateur...
pause >nul

:: Ouvrir dans le navigateur
start http://%DOMAIN_NAME%

exit /b 0