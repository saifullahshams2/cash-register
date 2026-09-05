# Cash Register & Point of Sale (POS) System

A modern, fast, and responsive Point of Sale (POS) and retail management application built with **Laravel 13**, **Livewire 4**, and **Tailwind CSS 4**. Designed for retail stores, supermarkets, convenience shops, and small-to-medium businesses.

---

## ⚠️ Important Deployment Notice: Nginx Configuration

> ### **CRITICAL REQUIREMENT FOR NGINX USERS**
> When deploying this application using **Nginx**, your web server **document root MUST point directly to the `/public` directory**, never to the project root directory.
>
> Setting the document root to the project root exposes critical files—including `.env` (environment and database credentials), SQLite database files, logs, and PHP source code—to the public web.
>
> **Correct:** `root /var/www/cash-register/public;`  
> **Incorrect:** `root /var/www/cash-register;`

### Production Nginx Server Block Example

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name pos.yourdomain.com;

    # ====================================================================
    # CRITICAL: Document root MUST point to the /public directory!
    # ====================================================================
    root /var/www/cash-register/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php index.html;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock; # Or your installed PHP-FPM version/socket
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Deny access to hidden files (.env, .git, etc.)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## Features

- **Point of Sale (POS) Terminal**:
  - Live product search and instant barcode scanner detection.
  - Interactive cart management, dynamic quantity updates, and line item removal.
  - Cash register tender modal with automatic change calculation and denomination shortcuts.
  - Instant thermal receipt formatting and printing.
- **Role-Based Access Control**:
  - **Cashier**: Direct access to the POS terminal (`/` or `/pos`); restricted from administrative dashboards.
  - **Admin**: Full access to analytics, cashier staff management, inventory control, and settings (`/admin`).
- **Inventory & Product Management**:
  - Product catalog with SKU, barcode, price, cost price, and stock levels.
  - Real-time stock decrementing upon completed transactions.
- **Reporting & Analytics**:
  - Sales summary metrics (daily, weekly, monthly volume and revenue).
  - Export sales reports to **PDF** and **Excel (.xlsx)** formats.
- **Dual Database Flexibility**:
  - Out-of-the-box support for both **SQLite** and **MySQL**.
  - Switch database drivers anytime using `php artisan db:switch`.
- **Self-Contained Installer**:
  - Guided web setup wizard (`/install`) for environment verification and initial provisioning.
  - Headless CLI setup command (`php artisan app:install`).

---

## System Requirements

- **PHP**: 8.3 or higher (PHP 8.5 compatible)
- **PHP Extensions**:
  - `pdo`, `pdo_sqlite` or `pdo_mysql`
  - `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`
- **Composer**: 2.x
- **Node.js & npm**: 18.x or higher
- **Database Engine**: SQLite (default zero-config) or MySQL 8.0+ / MariaDB 10.4+

---

## Quick Start (Development)

### 1. Clone & Install Dependencies
```bash
git clone https://github.com/your-username/cash-register.git
cd cash-register

# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

### 2. Configure Environment
```bash
# Create .env from example
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 3. Compile Assets
```bash
# For development with hot reloading
npm run dev

# Or build for production
npm run build
```

### 4. Run the Installation

You can install via the **Web Wizard** or the **CLI**:

#### Option A: Web Wizard
1. Start the development server:
   ```bash
   php artisan serve
   ```
2. Open `http://127.0.0.1:8000` in your browser.
3. You will automatically be directed to the web setup wizard (`/install`) to choose your database, set up store branding, and create the administrator account.

#### Option B: Command Line (Headless)
Run the install command directly:
```bash
# Install using SQLite:
php artisan app:install --database=sqlite --admin-username=admin --admin-password=password

# Or install using MySQL:
php artisan app:install --database=mysql --admin-username=admin --admin-password=password --company="My Store POS"
```

---

## Database Switching & Seeding

The application includes an Artisan command to easily switch between SQLite and MySQL:

```bash
# Switch to SQLite and run migrations
php artisan db:switch sqlite --migrate

# Switch to MySQL and run migrations
php artisan db:switch mysql --host=127.0.0.1 --database=cash_register --username=root --password=secret --migrate
```

### Seeding Demo Data
To populate the database with sample products and test accounts:
```bash
php artisan db:seed
```

### Default Accounts (When Seeded)

| Role | Username | Default Password | Default Landing Page |
|---|---|---|---|
| **Administrator** | `admin` | `password` | `/admin` (Dashboard) |
| **Cashier** | `cashier` | `password` | `/pos` (Cash Register) |

---

## Production Deployment Checklist

1. **Configure Nginx Document Root to `/public`**:
   - Verify `root /path/to/cash-register/public;` in your Nginx configuration.
2. **File Permissions**:
   Ensure the web server user has write permissions for `storage` and `bootstrap/cache`:
   ```bash
   chown -R www-data:www-data storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   ```
3. **Build Frontend Assets**:
   ```bash
   npm run build
   ```
4. **Optimize Laravel**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

## Testing

Run the automated test suite:
```bash
php artisan test
```

---

## Tech Stack

- **Backend**: [Laravel 13](https://laravel.com)
- **Reactivity**: [Livewire 4](https://livewire.laravel.com)
- **Frontend Styling**: [Tailwind CSS 4](https://tailwindcss.com)
- **Asset Bundler**: [Vite 8](https://vite.dev)
- **PDF Export**: [laravel-dompdf](https://github.com/barryvdh/laravel-dompdf)

---

## License

This software is open-sourced software licensed under the [MIT license](LICENSE).
