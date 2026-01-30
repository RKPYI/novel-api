# Production Deployment Guide

## 📋 Table of Contents
1. [Prerequisites](#prerequisites)
2. [Environment Setup](#environment-setup)
3. [Installation Steps](#installation-steps)
4. [Running with Octane (RoadRunner)](#running-with-octane-roadrunner)
5. [Process Management (Systemd)](#process-management-systemd)
6. [Reverse Proxy Setup (Nginx)](#reverse-proxy-setup-nginx)
7. [SSL/HTTPS Setup](#sslhttps-setup)
8. [Optimization Commands](#optimization-commands)
9. [Database Migration](#database-migration)
10. [Monitoring & Logging](#monitoring--logging)
11. [Security Checklist](#security-checklist)

---

## Prerequisites

### System Requirements
- **PHP**: 8.2 or higher
- **Database**: MySQL 8.0+ or PostgreSQL
- **Web Server**: Nginx (recommended)
- **Process Manager**: systemd
- **Memory**: Minimum 2GB RAM (4GB+ recommended)
- **Storage**: Minimum 10GB

### Required PHP Extensions
```bash
# Install required PHP extensions
sudo apt-get install -y \
    php8.2-cli \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-bcmath \
    php8.2-curl \
    php8.2-zip \
    php8.2-gd \
    php8.2-redis
```

---

## Environment Setup

### 1. Clone Repository
```bash
cd /var/www
sudo git clone https://github.com/RKPYI/novel-api.git
cd novel-api/backend
```

### 2. Set Permissions
```bash
sudo chown -R www-data:www-data /var/www/novel-api
sudo chmod -R 755 /var/www/novel-api
sudo chmod -R 775 storage bootstrap/cache
```

### 3. Create Production Environment File
```bash
cp .env.example .env
nano .env
```

**Production .env Configuration:**
```env
APP_NAME=NovelAPI
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY_HERE
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

LOG_CHANNEL=stack
LOG_LEVEL=error

# Database - Use production credentials
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=novel_production
DB_USERNAME=novel_user
DB_PASSWORD=strong_secure_password_here

# Session & Cache - Use Redis for production
SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_STORE=redis

# Queue
QUEUE_CONNECTION=redis

# Redis Configuration
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail - Configure your mail service
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io  # or your SMTP server
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum - Add your production domains
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,www.yourdomain.com

# Google OAuth (if using)
GOOGLE_CLIENT_ID=your_production_google_client_id
GOOGLE_CLIENT_SECRET=your_production_google_client_secret
GOOGLE_REDIRECT_URI=https://api.yourdomain.com/api/auth/google/callback

# Frontend URL
FRONTEND_URL=https://yourdomain.com

# Octane Configuration
OCTANE_SERVER=roadrunner

# Telescope - DISABLE in production
TELESCOPE_ENABLED=false
```

---

## Installation Steps

### 1. Install Composer Dependencies
```bash
composer install --optimize-autoloader --no-dev
```

### 2. Generate Application Key
```bash
php artisan key:generate
```

### 3. Install RoadRunner Binary
```bash
# Download and install RoadRunner
./vendor/bin/rr get-binary

# Or manually download
wget https://github.com/roadrunner-server/roadrunner/releases/download/v2023.3.7/roadrunner-2023.3.7-linux-amd64.tar.gz
tar -xzf roadrunner-2023.3.7-linux-amd64.tar.gz
sudo mv roadrunner-2023.3.7-linux-amd64/rr /usr/local/bin/rr
sudo chmod +x /usr/local/bin/rr
```

### 4. Create RoadRunner Configuration
Create `.rr.yaml` in your project root:

```yaml
version: "3"

server:
  command: "php artisan octane:start --server=roadrunner --host=127.0.0.1 --port=8000"
  
http:
  address: 127.0.0.1:8000
  max_request_size: 10
  middleware: ["gzip"]
  pool:
    num_workers: 4
    max_jobs: 0
    allocate_timeout: 60s
    destroy_timeout: 60s
  
logs:
  mode: production
  level: error
  encoding: json
  output: stderr
  
static:
  dir: "public"
  forbid: [".php", ".htaccess"]
```

---

## Running with Octane (RoadRunner)

### Option 1: Direct Command (Testing)
```bash
# Start Octane server
php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000 --workers=4

# Or using RoadRunner binary directly
rr serve -c .rr.yaml
```

### Option 2: Production with Systemd (Recommended)

Create systemd service file:
```bash
sudo nano /etc/systemd/system/novel-api.service
```

**Service Configuration:**
```ini
[Unit]
Description=Novel API - Laravel Octane
After=network.target mysql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/novel-api/backend
ExecStart=/usr/bin/php /var/www/novel-api/backend/artisan octane:start --server=roadrunner --host=127.0.0.1 --port=8000 --workers=4
Restart=always
RestartSec=3

# Environment variables
Environment="PATH=/usr/local/bin:/usr/bin:/bin"
Environment="APP_ENV=production"

# Resource limits
LimitNOFILE=65536
TimeoutStopSec=30

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=novel-api

[Install]
WantedBy=multi-user.target
```

**Enable and Start Service:**
```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable service to start on boot
sudo systemctl enable novel-api

# Start the service
sudo systemctl start novel-api

# Check status
sudo systemctl status novel-api

# View logs
sudo journalctl -u novel-api -f
```

**Service Management Commands:**
```bash
# Stop service
sudo systemctl stop novel-api

# Restart service
sudo systemctl restart novel-api

# Reload (graceful restart)
php artisan octane:reload
```

---

## Queue Workers Setup

**IMPORTANT:** Octane does NOT automatically run queue workers. You need to set up separate queue workers.

### Check if You're Using Queues
```bash
# Check for queued jobs
php artisan queue:monitor

# List failed jobs
php artisan queue:failed
```

### Option 1: Systemd Service (Recommended)

Create queue worker service:
```bash
sudo nano /etc/systemd/system/novel-api-queue.service
```

**Queue Worker Service Configuration:**
```ini
[Unit]
Description=Novel API Queue Worker
After=network.target mysql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/novel-api/backend
ExecStart=/usr/bin/php /var/www/novel-api/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=3

# Graceful shutdown
KillMode=mixed
KillSignal=SIGTERM
TimeoutStopSec=30

# Environment
Environment="PATH=/usr/local/bin:/usr/bin:/bin"
Environment="APP_ENV=production"

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=novel-api-queue

[Install]
WantedBy=multi-user.target
```

**Enable and start queue worker:**
```bash
# Reload systemd
sudo systemctl daemon-reload

# Enable queue worker
sudo systemctl enable novel-api-queue

# Start queue worker
sudo systemctl start novel-api-queue

# Check status
sudo systemctl status novel-api-queue

# View logs
sudo journalctl -u novel-api-queue -f
```

### Option 2: Supervisor (Alternative)

If you prefer Supervisor:
```bash
# Install Supervisor
sudo apt-get install supervisor

# Create configuration
sudo nano /etc/supervisor/conf.d/novel-api-queue.conf
```

**Supervisor Configuration:**
```ini
[program:novel-api-queue]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/novel-api/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/novel-api/backend/storage/logs/queue-worker.log
stopwaitsecs=3600
```

**Start with Supervisor:**
```bash
# Reload Supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Start workers
sudo supervisorctl start novel-api-queue:*

# Check status
sudo supervisorctl status

# Restart workers (after code deploy)
sudo supervisorctl restart novel-api-queue:*
```

### Queue Worker Commands

```bash
# Start queue worker (foreground - for testing)
php artisan queue:work

# Start with specific connection
php artisan queue:work redis

# Start with options
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600

# Listen (restarts automatically on code changes)
php artisan queue:listen

# Process only one job
php artisan queue:work --once

# Stop workers gracefully
php artisan queue:restart
```

### Environment Configuration for Queues

In your `.env` file:
```env
# Queue Configuration
QUEUE_CONNECTION=redis  # or 'database' if not using Redis

# Redis Queue Settings (if using Redis)
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default

# Database Queue Settings (if using database)
DB_QUEUE_CONNECTION=mysql
DB_QUEUE_TABLE=jobs
DB_QUEUE=default
```

### Monitoring Queue Health

```bash
# Monitor queues
php artisan queue:monitor redis:default,redis:notifications --max=100

# Check failed jobs
php artisan queue:failed

# Retry a failed job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Flush all failed jobs
php artisan queue:flush

# Prune old job batches (if using batching)
php artisan queue:prune-batches
```

### Important Notes:

1. **After Code Deployment:** Always restart queue workers
   ```bash
   # With systemd
   sudo systemctl restart novel-api-queue
   
   # Or gracefully
   php artisan queue:restart
   
   # With supervisor
   sudo supervisorctl restart novel-api-queue:*
   ```

2. **Scaling Workers:** Run multiple workers for better performance
   ```bash
   # In systemd, modify ExecStart or create multiple services
   # With supervisor, increase numprocs in config
   ```

3. **Queue Monitoring:** Set up monitoring to alert on failed jobs
   ```bash
   # Add to crontab for daily failed job alerts
   0 9 * * * cd /var/www/novel-api/backend && php artisan queue:failed | mail -s "Failed Queue Jobs" admin@yourdomain.com
   ```

---

## Reverse Proxy Setup (Nginx)

### Install Nginx
```bash
sudo apt-get install nginx
```

### Create Nginx Configuration
```bash
sudo nano /etc/nginx/sites-available/novel-api
```

**Nginx Configuration:**
```nginx
# HTTP to HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name api.yourdomain.com;
    
    # Redirect all HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

# HTTPS server
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.yourdomain.com;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/api.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Logging
    access_log /var/log/nginx/novel-api-access.log;
    error_log /var/log/nginx/novel-api-error.log;

    # Root directory
    root /var/www/novel-api/backend/public;
    index index.php index.html;

    # Client settings
    client_max_body_size 20M;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript 
               application/x-javascript application/xml+rss 
               application/json application/javascript;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Main location - proxy to Octane
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
        
        # Buffering
        proxy_buffering off;
        proxy_request_buffering off;
    }

    # Static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
}
```

**Enable Site:**
```bash
# Create symbolic link
sudo ln -s /etc/nginx/sites-available/novel-api /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Restart Nginx
sudo systemctl restart nginx
```

---

## SSL/HTTPS Setup

### Using Let's Encrypt (Free)

```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d api.yourdomain.com

# Auto-renewal (certbot sets this up automatically)
sudo certbot renew --dry-run

# Check renewal timer
sudo systemctl status certbot.timer
```

---

## Optimization Commands

### Run Before First Deploy
```bash
# 1. Cache configuration
php artisan config:cache

# 2. Cache routes
php artisan route:cache

# 3. Cache views
php artisan view:cache

# 4. Cache events
php artisan event:cache

# 5. Optimize autoloader
composer dump-autoload --optimize --classmap-authoritative
```

### Clear Caches (When Making Changes)
```bash
# Clear all caches
php artisan optimize:clear

# Or individually:
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear
php artisan cache:clear
```

---

## Database Migration

### Initial Setup
```bash
# Run migrations
php artisan migrate --force

# Seed initial data (genres, etc.)
php artisan db:seed --class=GenreSeeder --force
```

### For Updates
```bash
# Run new migrations
php artisan migrate --force

# If needed, rollback and re-migrate
php artisan migrate:rollback --force
php artisan migrate --force
```

---

## Monitoring & Logging

### Application Logs
```bash
# View Laravel logs
tail -f storage/logs/laravel.log

# View systemd service logs
sudo journalctl -u novel-api -f

# View Nginx access logs
sudo tail -f /var/log/nginx/novel-api-access.log

# View Nginx error logs
sudo tail -f /var/log/nginx/novel-api-error.log
```

### Performance Monitoring
```bash
# Check Octane workers
php artisan octane:status

# Monitor system resources
htop

# Monitor MySQL
sudo mysqladmin -u root -p processlist
sudo mysqladmin -u root -p status
```

### Log Rotation
Create `/etc/logrotate.d/novel-api`:
```
/var/www/novel-api/backend/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload novel-api
    endscript
}
```

---

## Security Checklist

### ✅ Pre-deployment Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong `APP_KEY` generated
- [ ] Database credentials secured
- [ ] `TELESCOPE_ENABLED=false` in production
- [ ] File permissions set correctly (755/775)
- [ ] HTTPS/SSL configured
- [ ] Firewall configured (UFW/iptables)
- [ ] Rate limiting enabled
- [ ] CORS properly configured
- [ ] Regular backups scheduled
- [ ] Environment file secured (not in git)

### Firewall Setup (UFW)
```bash
# Enable firewall
sudo ufw enable

# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Check status
sudo ufw status
```

### Database Security
```bash
# Create dedicated database user
mysql -u root -p

CREATE DATABASE novel_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'novel_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON novel_production.* TO 'novel_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Deployment Workflow

### Initial Deployment
```bash
# 1. Pull latest code
cd /var/www/novel-api/backend
sudo -u www-data git pull origin main

# 2. Install dependencies
sudo -u www-data composer install --optimize-autoloader --no-dev

# 3. Run migrations
sudo -u www-data php artisan migrate --force

# 4. Clear and cache
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# 5. Reload Octane
sudo -u www-data php artisan octane:reload
# OR restart service
sudo systemctl restart novel-api
```

### Zero-Downtime Deployment Script
Create `deploy.sh`:
```bash
#!/bin/bash
set -e

echo "🚀 Starting deployment..."

# Pull latest code
git pull origin main

# Install dependencies
composer install --optimize-autoload --no-dev

# Run migrations
php artisan migrate --force

# Clear old caches
php artisan optimize:clear

# Cache new configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Gracefully reload Octane workers
php artisan octane:reload

# Restart queue workers (IMPORTANT!)
php artisan queue:restart
# OR with systemd:
# sudo systemctl restart novel-api-queue

echo "✅ Deployment completed successfully!"
```

Make it executable:
```bash
chmod +x deploy.sh
```

---

## Troubleshooting

### Common Issues

**1. Octane won't start**
```bash
# Check if port is already in use
sudo lsof -i :8000

# Kill existing process
sudo kill -9 <PID>

# Check logs
sudo journalctl -u novel-api -n 50
```

**2. Permission denied errors**
```bash
# Fix permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

**3. 502 Bad Gateway**
```bash
# Check if Octane is running
sudo systemctl status novel-api

# Check Octane logs
sudo journalctl -u novel-api -f
```

**4. Database connection failed**
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

---

## Performance Tuning

### PHP Configuration
Edit `/etc/php/8.2/cli/php.ini`:
```ini
memory_limit = 512M
max_execution_time = 60
upload_max_filesize = 20M
post_max_size = 20M
```

### MySQL Optimization
Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:
```ini
[mysqld]
max_connections = 200
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
query_cache_type = 1
query_cache_size = 64M
```

### Redis Configuration
Edit `/etc/redis/redis.conf`:
```ini
maxmemory 256mb
maxmemory-policy allkeys-lru
```

---

## Backup Strategy

### Automated Backup Script
Create `/usr/local/bin/backup-novel-api.sh`:
```bash
#!/bin/bash
BACKUP_DIR="/backups/novel-api"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u novel_user -p'password' novel_production | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup uploaded files
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz /var/www/novel-api/backend/storage/app

# Keep only last 7 days
find $BACKUP_DIR -type f -mtime +7 -delete

echo "Backup completed: $DATE"
```

**Schedule with Cron:**
```bash
sudo crontab -e

# Add line for daily backup at 2 AM
0 2 * * * /usr/local/bin/backup-novel-api.sh
```

---

## Quick Reference Commands

```bash
# Start application
sudo systemctl start novel-api

# Stop application
sudo systemctl stop novel-api

# Restart application
sudo systemctl restart novel-api

# Reload Octane workers (zero downtime)
php artisan octane:reload

# Start queue workers
sudo systemctl start novel-api-queue

# Stop queue workers
sudo systemctl stop novel-api-queue

# Restart queue workers (after deployment)
sudo systemctl restart novel-api-queue
# OR gracefully:
php artisan queue:restart

# View application logs
sudo journalctl -u novel-api -f

# View queue worker logs
sudo journalctl -u novel-api-queue -f

# Check application status
sudo systemctl status novel-api
sudo systemctl status novel-api-queue

# Monitor queues
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Run migrations
php artisan migrate --force

# Clear all caches
php artisan optimize:clear

# Cache everything
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## Support & Resources

- **Laravel Documentation**: https://laravel.com/docs
- **Laravel Octane**: https://laravel.com/docs/octane
- **RoadRunner**: https://roadrunner.dev/docs
- **Application Repository**: https://github.com/RKPYI/novel-api

---

**Last Updated**: January 9, 2026
