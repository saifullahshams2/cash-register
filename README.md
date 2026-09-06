# Cash Register & Point of Sale (POS) System

A lightweight manual cash register and POS application designed for anyone who sells products without needing a big or complicated setup. This app is built specifically for users who want to register their daily sales and keep clean records easily.

This is a manual cash register featuring cashier and admin accounts, simple product management (adding products), and sales analytics with export options (PDF & Excel).

---

## System Requirements

- **PHP**: 8.3 or higher (PHP 8.5 compatible)
- **Composer**: 2.x
- **Node.js & npm**: 18.x or higher *(only required if installing from source)*
- **Database Engine**: SQLite (default) or MySQL 8.0+ / MariaDB 10.4+

---

## Production Deployment

> ### ⚠️ Critical Nginx Configuration
> If you are using **Nginx**, your web server **document root must point directly to the `/public` directory** (e.g. `root /var/www/cash-register/public;`), **never** to the root project directory.
>
> Pointing the document root to the project root directory is a severe security vulnerability that exposes your `.env` file, database files, and PHP source code to the public web.

### Production Setup Options

Choose one of the two options below to deploy the application:

#### Option 1: Release ZIP Package (Recommended — Built-in Installer)
The release package comes with pre-compiled assets and dependencies, allowing you to install without Node/npm.

1. Download the latest release `.zip` archive from GitHub Releases.
2. Upload and extract the ZIP contents to your server directory (e.g., `/var/www/cash-register`).
3. Set the required directory permissions:
   ```bash
   chown -R www-data:www-data storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   ```
4. Point your web server document root to the `/public` folder.
5. Open your domain in your web browser (or go to `http://your-domain.com/install`) and follow the on-screen installer wizard to configure the database and create your admin account.

---

#### Option 2: Manual Setup from Source / Git

1. **Set directory permissions**:
   ```bash
   chown -R www-data:www-data storage bootstrap/cache
   chmod -R 775 storage bootstrap/cache
   ```
2. **Install dependencies & compile assets**:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm install && npm run build
   ```
3. **Run installation**:
   ```bash
   # SQLite:
   php artisan app:install --database=sqlite --admin-username=admin --admin-password=yourpassword

   # Or MySQL:
   php artisan app:install --database=mysql --admin-username=admin --admin-password=yourpassword --company="My Store"
   ```
4. **Cache configuration & routes for performance**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

## Development Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Setup environment & key
cp .env.example .env
php artisan key:generate

# 3. Build assets (or run 'npm run dev' for development)
npm run build

# 4. Migrate database & seed default accounts (or run headless installer)
php artisan migrate --seed

# Alternatively, run headless installer:
# php artisan app:install --database=sqlite --admin-username=admin --admin-password=password

# 5. Start development server
php artisan serve
```

### Useful Commands
- **Switch Database**: `php artisan db:switch sqlite --migrate` or `php artisan db:switch mysql --migrate`
- **Seed Demo Data**: `php artisan db:seed`
- **Run Tests**: `php artisan test`

---

## Default Accounts

When provisioned or seeded:

| Role | Username | Default Password | Landing Page |
|---|---|---|---|
| **Admin** | `admin` | `password` | `/admin` (Analytics & Products) |
| **Cashier** | `cashier` | `password` | `/pos` (Cash Register) |

---

## License

Open-source software licensed under the [MIT license](LICENSE).
