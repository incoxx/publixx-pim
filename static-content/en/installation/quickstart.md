---
title: Quick Start
---

# Quick Start

This guide takes you from an empty system to a locally running Publixx PIM in just a few steps. It is aimed at developers who want to set up the system quickly and start working productively right away.

::: tip Prerequisite
Make sure all dependencies described in the [Requirements](./requirements) are installed before you begin.
:::

## 1. Clone the Repository

```bash
git clone git@github.com:publixx/publixx-pim.git
cd publixx-pim
```

## 2. Install PHP Dependencies

Install all backend packages with Composer:

```bash
composer install
```

This command installs Laravel 11 and all required PHP packages. The first installation may take a few minutes.

## 3. Install Frontend Dependencies

The Vue.js frontend is located in the `pim-frontend` subdirectory. Navigate there and install the npm packages:

```bash
cd pim-frontend
npm install
cd ..
```

## 4. Environment Configuration

Copy the example configuration and adjust the values to your local environment:

```bash
cp .env.example .env
php artisan key:generate
```

Open the `.env` file and configure at least the following values:

### Application

```dotenv
APP_NAME="Publixx PIM"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
```

### Database

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=publixx_pim
DB_USERNAME=root
DB_PASSWORD=your_password
```

Create the database if it doesn't exist yet:

```bash
mysql -u root -p -e "CREATE DATABASE publixx_pim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Redis

```dotenv
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### Sanctum (API Authentication)

```dotenv
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000
```

## 5. Set Up the Database

Run the migrations to create the database schema, then start the seeders to set up base data like roles, permissions, and the admin user:

```bash
php artisan migrate
php artisan db:seed
```

::: info Default Credentials
After seeding, an admin user is available. The credentials are displayed in the console during seeding. Make note of them.
:::

## 6. Create Storage Link

Laravel needs a symbolic link to serve publicly accessible files (media, uploads) from the `storage` directory:

```bash
php artisan storage:link
```

## 7. Build Frontend (Optional for Production)

If you want to serve the frontend as static files (e.g., for a production-like test), build it and copy the output to the `public` directory:

```bash
cd pim-frontend
npm run build
cp -r dist/* ../public/
cd ..
```

::: warning Note
For local development, use the Vite development server instead (see Step 8). Building is only necessary for production or production-like environments.
:::

## 8. Start Development Servers

Start the backend and frontend in two separate terminals:

**Terminal 1 — Laravel Backend:**

```bash
php artisan serve
```

The backend is now available at `http://localhost:8000`.

**Terminal 2 — Vite Frontend (Development Mode):**

```bash
cd pim-frontend
npm run dev
```

The frontend is now available at `http://localhost:5173` with Hot Module Replacement (HMR) for fast development cycles.

**Terminal 3 — Queue Worker (Optional):**

For processing background tasks (import, export), start the queue worker:

```bash
php artisan horizon
```

The Horizon dashboard is available at `http://localhost:8000/horizon`.

## 9. Verify Installation

Open `http://localhost:5173` in your browser. You should see the Publixx PIM login page. Log in with the credentials created during seeding.

### Checklist

| Check | Expected Result |
|---|---|
| Login page is displayed | Frontend build and API connection work |
| Login with admin credentials | Authentication and database connection work |
| Dashboard loads | SPA routing and API endpoints work |
| Horizon dashboard accessible | Redis connection and queue system work |

## Common Issues

### CORS Errors in Browser

Make sure `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` in the `.env` file are correctly set to the frontend URL.

### Database Connection Fails

Check if the MySQL service is running and the credentials in `.env` are correct:

```bash
php artisan db:monitor
```

### Redis Connection Fails

Check if Redis is running:

```bash
redis-cli ping
# Expected response: PONG
```

### Storage Permission Issues

```bash
chmod -R 775 storage bootstrap/cache
chown -R $USER:www-data storage bootstrap/cache
```

## Next Step

For production deployment, read the [Deployment](./deployment) guide.
