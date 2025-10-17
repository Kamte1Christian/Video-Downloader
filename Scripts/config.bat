@echo off
:: ============================================
:: Fichier de configuration centralisé
:: Modifiez les chemins selon votre installation
:: ============================================

:: ============================================
:: CHEMINS PHP ET WAMP
:: ============================================

:: Chemin vers PHP (dans WAMP)
set PHP_PATH=C:\wamp64\bin\php\php8.2.12\php.exe

:: Nom du service Apache WAMP
set APACHE_SERVICE=wampapache64

:: Nom du service MySQL WAMP (optionnel)
set MYSQL_SERVICE=wampmysqld64

:: ============================================
:: CHEMINS REDIS
:: ============================================

:: Répertoire d'installation de Redis
set REDIS_DIR=C:\Redis

:: Chemin vers l'exécutable Redis Server
set REDIS_PATH=%REDIS_DIR%\redis-server.exe

:: Chemin vers l'exécutable Redis CLI
set REDIS_CLI=%REDIS_DIR%\redis-cli.exe

:: Fichier de configuration Redis
set REDIS_CONF=%REDIS_DIR%\redis.windows.conf

:: ============================================
:: CHEMINS FFMPEG
:: ============================================

:: Répertoire d'installation de FFmpeg (optionnel si dans PATH)
set FFMPEG_DIR=C:\ffmpeg\bin

:: ============================================
:: CONFIGURATION DE L'APPLICATION
:: ============================================

:: Répertoire du projet (automatique)
set PROJECT_DIR=%~dp0

:: Nom de domaine local
set DOMAIN_NAME=video-downloader.local

:: Nombre de workers Messenger à lancer
set WORKER_COUNT=3

:: Temps limite pour un worker (en secondes)
set WORKER_TIME_LIMIT=3600

:: Limite mémoire pour un worker
set WORKER_MEMORY_LIMIT=256M

:: ============================================
:: CONFIGURATION REDIS
:: ============================================

:: Host Redis
set REDIS_HOST=127.0.0.1

:: Port Redis
set REDIS_PORT=6379

:: Base de données Redis pour Messenger
set REDIS_DB_MESSENGER=0

:: Base de données Redis pour les sessions
set REDIS_DB_SESSIONS=1

:: ============================================
:: CONFIGURATION DES LOGS
:: ============================================

:: Activer les logs détaillés (true/false)
set VERBOSE_MODE=true

:: Répertoire des logs
set LOG_DIR=%PROJECT_DIR%var\log

:: ============================================
:: CONFIGURATION AVANCÉE
:: ============================================

:: Délai entre le démarrage de chaque worker (secondes)
set WORKER_START_DELAY=2

:: TTL des sessions (en secondes) - 7200 = 2 heures
set SESSION_TTL=7200

:: Activer le mode debug (true/false)
set DEBUG_MODE=false

:: ============================================
:: NE PAS MODIFIER EN DESSOUS
:: ============================================

:: Vérifier que les chemins existent
if not exist "%PHP_PATH%" (
    echo [ERREUR] PHP non trouve: %PHP_PATH%
    echo Veuillez modifier PHP_PATH dans config.bat
    exit /b 1
)

if not exist "%REDIS_PATH%" (
    echo [ERREUR] Redis non trouve: %REDIS_PATH%
    echo Veuillez modifier REDIS_PATH dans config.bat
    exit /b 1
)

:: Créer les dossiers nécessaires s'ils n'existent pas
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"
if not exist "%PROJECT_DIR%var\sessions" mkdir "%PROJECT_DIR%var\sessions"
if not exist "%PROJECT_DIR%var\cache" mkdir "%PROJECT_DIR%var\cache"

:: Message de confirmation
if "%1"=="show" (
    echo.
    echo ========================================
    echo   CONFIGURATION
    echo ========================================
    echo.
    echo PHP_PATH           : %PHP_PATH%
    echo REDIS_PATH         : %REDIS_PATH%
    echo PROJECT_DIR        : %PROJECT_DIR%
    echo DOMAIN_NAME        : %DOMAIN_NAME%
    echo WORKER_COUNT       : %WORKER_COUNT%
    echo WORKER_TIME_LIMIT  : %WORKER_TIME_LIMIT%
    echo WORKER_MEMORY_LIMIT: %WORKER_MEMORY_LIMIT%
    echo.
)