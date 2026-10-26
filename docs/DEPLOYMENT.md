# WTSBill ERP — Production Deployment Guide

**Document Version:** 2.0.0  
**Effective Date:** 2026-10-26  
**Target Runtime:** Native PHP 8.0+ / MySQL 8.0+ / MariaDB 10.4+  
**Architecture:** Framework-Free Zero-Dependency Native PHP Enterprise ERP  

---

## 1. System Requirements & Prerequisites

### 1.1 Server Specifications
- **Operating System:** Linux (Ubuntu 22.04 LTS / Debian 12 / RHEL 9) or Windows Server / XAMPP.
- **CPU & RAM:** Minimum 2 vCPUs, 4 GB RAM recommended for multi-tenant production.
- **Disk:** Fast NVMe storage recommended; minimum 20 GB free space for database growth and backups.

### 1.2 PHP Runtime
- **PHP Version:** PHP 8.0, 8.1, 8.2, or 8.3.
- **Required Extensions:**
  - `pdo`, `pdo_mysql` (Database connectivity)
  - `mbstring` (Multi-byte string handling)
  - `openssl` (Cryptographic tokens, CSRF entropy, password hashing)
  - `json` (REST API payload serialization)
  - `ctype` (Type validation)
  - `zlib` (Gzip output compression)
  - `opcache` (Production bytecode acceleration)

### 1.3 Database Server
- **MySQL:** 8.0.28+ OR **MariaDB:** 10.4.32+
- **Default Charset:** `utf8mb4`
- **Default Collation:** `utf8mb4_unicode_ci`
- **Max Allowed Packet:** Minimum `64M`
- **SQL Mode:** `STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION`

---

## 2. Directory Structure & Permissions

The application isolates all business logic and storage outside of the public document root:

```
/var/www/WTSBill/                <-- Root Directory (Protected from direct web access)
├── app/                         <-- Application code (Controllers, Models, Services)
├── config/                      <-- Application configurations
├── database/                    <-- Migrations, seeders, schema definitions
├── docs/                        <-- Architectural and deployment documentation
├── public/                      <-- WEB SERVER DOCUMENT ROOT (Only this directory is exposed)
│   ├── assets/                  <-- Static CSS, JS, SVG assets
│   ├── index.php                <-- Web front controller
│   └── .htaccess                <-- Apache rewrite & cache control
├── routes/                      <-- Route definitions (web.php, api.php)
├── storage/                     <-- Private runtime data
│   ├── cache/                   <-- View & query caches
│   ├── logs/                    <-- Application audit logs
│   └── sessions/                <-- Secure session storage
├── tests/                       <-- Automated verification suite
└── .env                         <-- Environment secrets (chmod 600)
```

### 2.1 File & Directory Permissions (Linux / Unix)
Run the following commands as root / sudo:
```bash
# Set base ownership to web server user (e.g. www-data)
chown -R www-data:www-data /var/www/WTSBill

# Standard directory and file permissions
find /var/www/WTSBill -type d -exec chmod 755 {} \;
find /var/www/WTSBill -type f -exec chmod 644 {} \;

# Make storage writeable by the web server
chmod -R 775 /var/www/WTSBill/storage
chmod -R 775 /var/www/WTSBill/public/uploads

# Protect environment secrets (read-only by owner)
chmod 600 /var/www/WTSBill/.env
```

---

## 3. Web Server Configurations

### 3.1 Apache VirtualHost (Recommended)
Configure your Apache virtual host file (e.g., `/etc/apache2/sites-available/wtsbill.conf`):

```apache
<VirtualHost *:80>
    ServerName billing.wtsbill.in
    Redirect permanent / https://billing.wtsbill.in/
</VirtualHost>

<VirtualHost *:443>
    ServerName billing.wtsbill.in
    DocumentRoot /var/www/WTSBill/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/billing.wtsbill.in/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/billing.wtsbill.in/privkey.pem

    <Directory /var/www/WTSBill/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Block access to hidden files and version control
    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/wtsbill_error.log
    CustomLog ${APACHE_LOG_DIR}/wtsbill_access.log combined
</VirtualHost>
```

Enable required modules and restart Apache:
```bash
a2enmod rewrite headers deflate ssl
a2ensite wtsbill.conf
systemctl reload apache2
```

### 3.2 Nginx Server Block (Alternative)
If using Nginx with PHP-FPM:

```nginx
server {
    listen 80;
    server_name billing.wtsbill.in;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name billing.wtsbill.in;
    root /var/www/WTSBill/public;
    index index.php index.html;

    ssl_certificate /etc/letsencrypt/live/billing.wtsbill.in/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/billing.wtsbill.in/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Static Asset Caching
    location ~* \.(css|js|jpg|jpeg|gif|png|ico|svg|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    # Application Routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM Handler
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
}
```

---

## 4. PHP & OPcache Production Tuning

In `/etc/php/8.2/fpm/php.ini` or `/etc/php/8.2/apache2/php.ini`:

```ini
; Error handling in production
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

; Memory and execution
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 10M
post_max_size = 12M

; Session security
session.use_strict_mode = 1
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"

; OPcache Bytecode Cache
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0 ; Set to 0 in production; invalidate on deployment
opcache.revalidate_freq = 0
opcache.save_comments = 1
```

---

## 5. Deployment Step-by-Step Procedure

### Step 1: Pre-Deployment Database Snapshot
Always perform a verified database backup prior to code deployment:
```bash
mysqldump -u root -p --single-transaction --routines --triggers wtsbill_db > /var/backups/wtsbill/wtsbill_pre_cutover_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Deploy Code
Clone or sync the verified repository into the release path:
```bash
cd /var/www/WTSBill
git fetch origin
git checkout tags/v2.0.0
```

### Step 3: Configure Environment
Copy `.env.example` to `.env` and verify all production settings:
```bash
cp .env.example .env
chmod 600 .env
```
Ensure `APP_ENV=production`, `APP_DEBUG=false`, and valid database credentials.

### Step 4: Verify Directory Permissions
```bash
chmod -R 775 storage
chmod -R 775 public/uploads
```

### Step 5: Execute Automated Verification Test Suite
Run the full 23-suite regression test in the target environment:
```bash
php tests/run_tests.php
```
**Gate Requirement:** All 300 assertions must pass (100% success rate) before proceeding.

### Step 6: Flush Bytecode and Cache
```bash
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache reset.\n'; }"
rm -rf storage/cache/*
```

### Step 7: Validate Live Health Endpoint
```bash
curl -f https://billing.wtsbill.in/api/health
```
Verify response contains `"status":"ok"` and `"database":"connected"`.
