<p align="center">
  <img src="./public/rantale-dark.svg" alt="Rantale logo" width="180" />
</p>

# Rantale API

Rantale API is the Laravel backend that powers the Rantale reading platform. It provides the public catalog, authentication, reading features, notifications, and author/editor/admin workflows that support the web client and future integrations.

## Overview

This service handles the platform's data and business logic for a novel publishing and reading experience, including:

- public novel discovery, search, recommendations, and chapter browsing
- user authentication and profile management with Sanctum
- reading progress, library tracking, ratings, and comments
- author workflow for publishing novels, volumes, and chapters
- editor review and approval flows for chapter quality control
- admin moderation and application management
- contact submissions and notification delivery

## Live services

- Frontend: https://rantale.ranggadk.com
- API base URL: https://api.randk.tech
- Health check: https://api.randk.tech/api/health

## Core capabilities

- Laravel 12 API built on PHP 8.2
- Role-aware endpoints for users, authors, editors, and admins
- Sanctum-based authentication and social login support
- Media upload handling for avatars and novel covers
- Structured chapter and volume management with workflow states
- Search, sorting, recommendations, and related-content APIs
- Queue, cache, and background processing support for production workloads

## Tech stack

- PHP 8.2
- Laravel 12
- Sanctum for API authentication
- Socialite for Google login
- Octane + RoadRunner for high-performance PHP execution
- MySQL-compatible database
- Laravel queues and cache for background jobs and session storage

## Prerequisites

Before you begin, make sure you have:

- PHP 8.2+
- Composer
- a MySQL-compatible database
- a configured `.env` file for your local or staging environment

## Quick start

1. Install PHP dependencies:

   ```bash
   composer install
   ```

2. Copy the environment template:

   ```bash
   cp .env.example .env
   ```

3. Generate the app key and configure your local database values in `.env`:

   ```bash
   php artisan key:generate
   ```

4. Run database migrations:

   ```bash
   php artisan migrate
   ```

5. Start the API:

   ```bash
   php artisan serve
   ```

6. Verify the API is responding:

   ```bash
   curl http://localhost:8000/api/health
   ```

> [!important]
> The backend expects a compatible frontend and valid database configuration. Make sure your environment variables are set before running the app in local or production mode.

## Environment variables

The API uses Laravel environment configuration for database, auth, mail, cache, and OAuth settings. Key variables include:

```bash
APP_NAME=NovelAPI
APP_ENV=local
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=novel_db
DB_USERNAME=root
DB_PASSWORD=

FRONTEND_URL=http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000,127.0.0.1,127.0.0.1:8000

GOOGLE_CLIENT_ID=your_google_client_id_here
GOOGLE_CLIENT_SECRET=your_google_client_secret_here
GOOGLE_REDIRECT_URI=https://your.api.url/api/auth/google/callback
```

## Available commands

```bash
php artisan serve
php artisan test
php artisan migrate
php artisan queue:listen
php artisan storage:link
```

## Documentation

Project references and API guides are included in the backend root:

- `COMPREHENSIVE_API_DOCUMENTATION.md`
- `FRONTEND_API_REFERENCE.md`
- `CONTACT_API_DOCUMENTATION.md`
- `IMAGE_UPLOAD_DOCUMENTATION.md`
- `PRODUCTION_DEPLOYMENT.md`
- `CHAPTER_WORKFLOW_DOCUMENTATION.md`

## Project structure

```text
backend/
├─ app/
│  ├─ Console/
│  ├─ Helpers/
│  ├─ Http/
│  ├─ Models/
│  ├─ Notifications/
│  ├─ Providers/
│  └─ Services/
├─ bootstrap/
├─ config/
├─ database/
├─ public/
├─ resources/
├─ routes/
│  └─ api.php
├─ storage/
├─ tests/
├─ .env.example
├─ artisan
├─ composer.json
├─ phpunit.xml
├─ README.md
└─ vite.config.js
```

## API organization

The main API routes are organized by domain and access level:

- public content: novels, chapters, genres, comments, ratings
- authentication: register/login/logout/profile management
- role-protected endpoints: author, editor, admin
- user-specific features: library, reading progress, notifications
- contact and moderation flows for support and administration

Key route entry points live in `routes/api.php`, with controllers grouped under `app/Http/Controllers` and request validation in `app/Http/Requests`.

## Security and production notes

- Sanctum protects authenticated API routes.
- Role middleware enforces author, editor, and admin access boundaries.
- Email verification can be enforced for sensitive write actions.
- Public health checks are intentionally separated from protected endpoints.

> [!tip]
> Use the production deployment guide in `PRODUCTION_DEPLOYMENT.md` for environment hardening, domain configuration, caching, and deployment checks.

## Related repositories

- Frontend: https://github.com/RKPYI/novel-frontend
- Backend API: this repository
