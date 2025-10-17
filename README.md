# 🎬 Video Downloader avec Queue (Symfony)

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%2B-black)](https://symfony.com)
[![Redis](https://img.shields.io/badge/Redis-7.0%2B-red)](https://redis.io)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

Application web complète pour télécharger des vidéos et extraire de l'audio depuis YouTube, Vimeo et autres plateformes. Utilise Symfony Messenger avec Redis pour un traitement asynchrone en arrière-plan.

## ✨ Fonctionnalités

- 🎥 **Téléchargement de vidéos** : Multiples résolutions (4K, 1080p, 720p, 480p, 360p)
- 🎵 **Extraction audio** : MP3, M4A, WAV avec qualité personnalisable
- ⚡ **Traitement asynchrone** : File d'attente avec Redis pour ne pas bloquer l'interface
- 🔄 **Transcodage vidéo** : FFmpeg pour convertir et optimiser
- 📺 **Packaging HLS** : Création de streams adaptatifs multi-qualités
- 🖼️ **Génération de thumbnails** : Captures automatiques à intervalles réguliers
- 📊 **Suivi en temps réel** : Progression et statut des téléchargements
- 🚀 **Mode synchrone/asynchrone** : Choix entre traitement immédiat ou en arrière-plan
- 🧹 **Nettoyage automatique** : Suppression des fichiers temporaires expirés

## 🎯 Démo

![Demo Screenshot](docs/screenshot.png)

## 📋 Prérequis

### Windows (WAMP)
- Windows 10/11
- WAMP Server avec PHP 8.2+
- Composer
- Redis pour Windows
- FFmpeg
- yt-dlp

### Linux
- PHP 8.2+
- Composer
- Redis
- FFmpeg
- yt-dlp
- Apache/Nginx

## 🚀 Installation rapide (Windows)

### 1. Cloner le projet

```bash
cd C:\wamp64\www
git clone https://github.com/votre-username/video-downloader.git
cd video-downloader
```

### 2. Lancer l'installation automatique

```batch
# En tant qu'administrateur
install.bat
```

Le script va :
- ✅ Vérifier tous les prérequis
- ✅ Installer les dépendances Composer
- ✅ Créer la configuration .env.local
- ✅ Créer les dossiers nécessaires
- ✅ Configurer le fichier hosts

### 3. Installer les dépendances manquantes

Si des dépendances sont manquantes, installez-les :

**Redis :**
- Télécharger : https://github.com/tporadowski/redis/releases
- Extraire dans `C:\Redis\`

**FFmpeg :**
- Télécharger : https://www.gyan.dev/ffmpeg/builds/
- Extraire dans `C:\ffmpeg\`
- Ajouter `C:\ffmpeg\bin` au PATH

**yt-dlp :**
```bash
pip install yt-dlp
```

### 4. Configurer WAMP VirtualHost

**Méthode automatique :**
1. Cliquer sur l'icône WAMP
2. "Your VirtualHosts" → "VirtualHost Management"
3. Ajouter un VirtualHost :
   - Nom : `video-downloader.local`
   - Chemin : `C:/wamp64/www/video-downloader/public`

**Méthode manuelle :**

Éditer `httpd-vhosts.conf` et ajouter :

```apache
<VirtualHost *:80>
    ServerName video-downloader.local
    DocumentRoot "C:/wamp64/www/video-downloader/public"
    
    <Directory "C:/wamp64/www/video-downloader/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Redémarrer WAMP.

### 5. Démarrer l'application

```batch
# En tant qu'administrateur
start-app.bat
```

Le script va :
- ✅ Démarrer Redis
- ✅ Démarrer Apache (WAMP)
- ✅ Lancer 3 workers Messenger
- ✅ Ouvrir l'application dans le navigateur

**🎉 L'application est maintenant accessible sur : http://video-downloader.local**

## 🛑 Arrêter l'application

```batch
# En tant qu'administrateur
stop-app.bat
```

## 📁 Structure du projet

```
video-downloader/
├── src/
│   ├── Controller/
│   │   └── VideoDownloaderController.php    # API REST
│   ├── Message/                              # Messages de la queue
│   │   ├── VideoDownloadMessage.php
│   │   ├── AudioExtractionMessage.php
│   │   ├── HLSPackagingMessage.php
│   │   └── ThumbnailGenerationMessage.php
│   ├── MessageHandler/                       # Handlers asynchrones
│   │   ├── VideoDownloadMessageHandler.php
│   │   ├── AudioExtractionMessageHandler.php
│   │   ├── HLSPackagingMessageHandler.php
│   │   └── ThumbnailGenerationMessageHandler.php
│   └── Service/
│       ├── VideoDownloaderService.php        # Téléchargement avec yt-dlp
│       ├── FFmpegTranscodeService.php        # Transcodage FFmpeg
│       ├── HLSPackagingService.php           # Packaging HLS
│       ├── VideoOrchestrationService.php     # Orchestration globale
│       └── SessionStatusService.php          # Gestion des sessions Redis
├── templates/
│   └── video/
│       └── index.html.twig                   # Interface utilisateur
├── config/
│   └── packages/
│       └── messenger.yaml                    # Configuration Messenger
├── var/
│   ├── sessions/                             # Fichiers temporaires
│   └── log/                                  # Logs
├── install.bat                               # Installation automatique
├── start-app.bat                             # Démarrage de l'application
├── stop-app.bat                              # Arrêt de l'application
└── README.md
```

## 🔧 Configuration

### Variables d'environnement (.env.local)

```env
# Mode de développement
APP_ENV=dev
APP_SECRET=votre_secret_key

# Redis
MESSENGER_TRANSPORT_DSN=redis://127.0.0.1:6379/messages
REDIS_URL=redis://127.0.0.1:6379

# Stockage temporaire
TEMP_STORAGE_PATH=C:/wamp64/www/video-downloader/var/sessions

# Chemins des exécutables (optionnel si dans le PATH)
YTDLP_PATH=yt-dlp
FFMPEG_PATH=ffmpeg
FFPROBE_PATH=ffprobe
```

### Configuration Messenger (config/packages/messenger.yaml)

```yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 1000
        
        routing:
            'App\Message\VideoDownloadMessage': async
            'App\Message\AudioExtractionMessage': async
            'App\Message\HLSPackagingMessage': async
            'App\Message\ThumbnailGenerationMessage': async
```

## 📖 Utilisation

### Interface web

1. Accéder à http://video-downloader.local
2. Coller l'URL d'une vidéo (YouTube, Vimeo, etc.)
3. Cliquer sur "Analyser"
4. Choisir le format et la qualité
5. Cliquer sur "Télécharger"
6. Le fichier sera téléchargé automatiquement

### API REST

**Récupérer les informations d'une vidéo :**
```bash
curl -X POST http://video-downloader.local/api/video/info \
  -H "Content-Type: application/json" \
  -d '{"url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ"}'
```

**Télécharger une vidéo (mode asynchrone) :**
```bash
curl -X POST http://video-downloader.local/api/video/download \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "format": "best",
    "type": "video",
    "async": true
  }'
```

**Vérifier le statut d'un téléchargement :**
```bash
curl http://video-downloader.local/api/video/status/{sessionId}
```

**Extraire l'audio :**
```bash
curl -X POST http://video-downloader.local/api/video/download-audio \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "format": "mp3",
    "bitrate": "320k",
    "async": true
  }'
```

### Endpoints disponibles

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| POST | `/api/video/info` | Récupérer les infos d'une vidéo |
| POST | `/api/video/download` | Télécharger une vidéo |
| POST | `/api/video/download-audio` | Extraire l'audio |
| POST | `/api/video/hls` | Créer un package HLS |
| POST | `/api/video/thumbnails` | Générer des thumbnails |
| GET | `/api/video/status/{sessionId}` | Vérifier le statut |
| POST | `/api/video/cancel/{sessionId}` | Annuler une tâche |
| GET | `/api/video/sessions` | Lister les sessions actives |
| POST | `/api/video/cleanup` | Nettoyer les sessions expirées |

## 🔍 Monitoring

### Vérifier l'état des workers

```bash
php bin/console messenger:stats
```

### Voir les messages en échec

```bash
php bin/console messenger:failed:show
```

### Réessayer les messages en échec

```bash
php bin/console messenger:failed:retry
```

### Nettoyer les sessions manuellement

```bash
php bin/console app:cleanup-sessions
```

## 🐛 Dépannage

### Redis ne démarre pas

```bash
# Vérifier si le port 6379 est occupé
netstat -ano | findstr :6379

# Tuer le processus si nécessaire
taskkill /PID <PID> /F

# Redémarrer Redis
cd C:\Redis
redis-server.exe redis.windows.conf
```

### Workers ne traitent pas les messages

```bash
# Vérifier les logs
type var\log\dev.log

# Redémarrer les workers
stop-app.bat
start-app.bat
```

### Extension Redis PHP manquante

1. Télécharger la DLL depuis : https://pecl.php.net/package/redis
2. Copier `php_redis.dll` dans `C:\wamp64\bin\php\php8.2.12\ext\`
3. Ajouter dans `php.ini` : `extension=redis`
4. Redémarrer WAMP

### Problèmes de permissions

```bash
# Donner les permissions sur le dossier var/
icacls "C:\wamp64\www\video-downloader\var" /grant Everyone:F /T
```

### yt-dlp ne fonctionne pas

```bash
# Mettre à jour yt-dlp
pip install --upgrade yt-dlp

# Ou télécharger la dernière version
# https://github.com/yt-dlp/yt-dlp/releases/latest
```

### FFmpeg introuvable

Vérifier que FFmpeg est dans le PATH :
```bash
where ffmpeg
ffmpeg -version
```

Si non trouvé, ajouter `C:\ffmpeg\bin` au PATH système.

## 🔒 Sécurité

### Rate limiting

Pour éviter les abus, ajoutez un rate limiter dans le contrôleur :

```php
use Symfony\Component\RateLimiter\RateLimiterFactory;

public function __construct(
    private RateLimiterFactory $anonymousApiLimiter
) {}

public function download(Request $request): Response
{
    $limiter = $this->anonymousApiLimiter->create($request->getClientIp());
    
    if (!$limiter->consume(1)->isAccepted()) {
        return $this->json(['error' => 'Too many requests'], 429);
    }
    
    // ...
}
```

### Validation des URLs

Ajoutez une whitelist de domaines autorisés si nécessaire dans le service.

### Nettoyage automatique

Configurez une tâche planifiée Windows pour nettoyer les fichiers temporaires :

1. Ouvrir "Planificateur de tâches"
2. Créer une tâche de base
3. Déclencheur : Quotidien à 3h du matin
4. Action : `php bin/console app:cleanup-sessions`

## 📊 Performance

### Recommandations

- **Workers** : 2-4 workers par cœur CPU
- **Redis** : Configurer `maxmemory-policy allkeys-lru`
- **TTL Sessions** : 2 heures par défaut (ajustable dans `SessionStatusService.php`)
- **Cleanup** : Exécuter toutes les 30-60 minutes

### Optimiser FFmpeg

Pour un transcodage plus rapide, utilisez le preset `fast` ou `ultrafast` :

```php
// Dans FFmpegTranscodeService.php
$command[] = '-preset';
$command[] = 'fast'; // ou 'ultrafast'
```

### Augmenter le nombre de workers

Éditez `start-app.bat` et modifiez :
```batch
set WORKER_COUNT=5
```

## 🛠️ Développement

### Installation pour le développement

```bash
# Cloner le projet
git clone https://github.com/votre-username/video-downloader.git
cd video-downloader

# Installer les dépendances
composer install

# Créer la configuration
cp .env .env.local

# Démarrer le serveur Symfony
symfony server:start

# Dans un autre terminal, lancer les workers
php bin/console messenger:consume async -vv
```

### Tests

```bash
# Lancer les tests
php bin/phpunit

# Tests avec couverture
php bin/phpunit --coverage-html coverage
```

### Contribuer

Les contributions sont les bienvenues ! Veuillez :

1. Fork le projet
2. Créer une branche (`git checkout -b feature/AmazingFeature`)
3. Commit vos changements (`git commit -m 'Add AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📝 Commandes utiles

```bash
# Voir les routes disponibles
php bin/console debug:router

# Vider le cache
php bin/console cache:clear

# Voir les services disponibles
php bin/console debug:container

# Voir les messages en attente
php bin/console messenger:stats

# Réessayer les messages en échec
php bin/console messenger:failed:retry

# Nettoyer les sessions
php bin/console app:cleanup-sessions

# Voir les logs en temps réel (PowerShell)
Get-Content var\log\dev.log -Wait -Tail 50
```

## 🌐 Déploiement en production

### Prérequis production

- Serveur Linux (Ubuntu/Debian recommandé)
- PHP 8.2+ avec PHP-FPM
- Nginx ou Apache
- Redis
- Supervisord pour les workers
- Certificat SSL (Let's Encrypt)

### Installation production (Linux)

```bash
# Installer les dépendances
composer install --no-dev --optimize-autoloader

# Configurer l'environnement
APP_ENV=prod

# Générer le cache
php bin/console cache:warmup

# Configurer Supervisord pour les workers
sudo nano /etc/supervisor/conf.d/messenger-worker.conf
```

**Configuration Supervisord :**

```ini
[program:messenger-consume]
command=php /var/www/video-downloader/bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
user=www-data
numprocs=4
autostart=true
autorestart=true
startsecs=0
redirect_stderr=true
stdout_logfile=/var/log/messenger-worker.log
process_name=%(program_name)s_%(process_num)02d
```

```bash
# Redémarrer Supervisord
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start messenger-consume:*
```

## 📚 Technologies utilisées

- **Backend** : Symfony 6.4+
- **Queue** : Symfony Messenger + Redis
- **Téléchargement** : yt-dlp
- **Transcodage** : FFmpeg
- **Frontend** : Alpine.js + Tailwind CSS
- **Serveur Web** : Apache (WAMP) / Nginx

## 📄 License

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 👨‍💻 Auteur

**Votre Nom**
- GitHub: [@votre-username](https://github.com/votre-username)
- Email: votre.email@example.com

## 🙏 Remerciements

- [Symfony](https://symfony.com) - Framework PHP
- [yt-dlp](https://github.com/yt-dlp/yt-dlp) - Téléchargeur de vidéos
- [FFmpeg](https://ffmpeg.org) - Traitement multimédia
- [Redis](https://redis.io) - Base de données en mémoire
- [Alpine.js](https://alpinejs.dev) - Framework JavaScript léger
- [Tailwind CSS](https://tailwindcss.com) - Framework CSS

## 📞 Support

Si vous rencontrez des problèmes :

1. Consultez la section [Dépannage](#-dépannage)
2. Vérifiez les [Issues existantes](https://github.com/votre-username/video-downloader/issues)
3. Ouvrez une [nouvelle Issue](https://github.com/votre-username/video-downloader/issues/new)

## 🗺️ Roadmap

- [ ] Support de plus de plateformes (TikTok, Instagram, etc.)
- [ ] Interface d'administration
- [ ] Gestion multi-utilisateurs
- [ ] API key pour l'authentification
- [ ] Historique des téléchargements
- [ ] Téléchargement de playlists complètes
- [ ] Conversion de formats supplémentaires
- [ ] Mode sombre
- [ ] Application mobile (React Native)
- [ ] Docker Compose pour déploiement facile

## 📸 Screenshots

### Interface principale
![Interface principale](docs/screenshots/main-interface.png)

### Téléchargement en cours
![Téléchargement](docs/screenshots/download-progress.png)

### Extraction audio
![Audio](docs/screenshots/audio-extraction.png)

---

⭐ Si ce projet vous est utile, n'hésitez pas à lui donner une étoile sur GitHub !

## 🚀 Quick Start (TL;DR)

```batch
# Windows avec WAMP
git clone https://github.com/votre-username/video-downloader.git
cd video-downloader
install.bat
# Configurer le VirtualHost dans WAMP
start-app.bat
```

🎉 **L'application est prête sur http://video-downloader.local**