# Test Setup Guide

This project includes feature tests for:

- Authentication
- Novel module
- Chapter module

## Why tests are currently failing

Your current failure (`could not find driver`) means PHP is missing the SQLite PDO extension while `phpunit.xml` is configured to run tests with:

- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`

## Option A (recommended): Enable SQLite for tests

Use this when you want fast in-memory test runs.

```bash
sudo apt update
sudo apt install php8.2-sqlite3
php -m | grep -i sqlite
php artisan test
```

If your PHP version is not `8.2`, install the matching package (for example `php8.3-sqlite3`).

## Option B: Run tests with MySQL (no sqlite extension required)

Use this if your environment already has MySQL running.

1. Create a test database (example: `novel_api_test`).
2. Run tests with overridden env values:

```bash
DB_CONNECTION=mysql \
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_DATABASE=novel_api_test \
DB_USERNAME=root \
DB_PASSWORD= \
php artisan test
```

## Run only the new module tests

```bash
php artisan test --filter='AuthModuleTest|NovelModuleTest|ChapterModuleTest'
```

## Coverage of the new module suites

### `AuthModuleTest`

- Register success/failure
- Duplicate email
- Invalid login
- Login token issuance
- Login concurrency-like repeated token creation
- Password change rejection for wrong current password

### `NovelModuleTest`

- Create/update authorization
- Validation boundary (`title > 255`)
- Search required query
- Bulk delete mixed ownership behavior

### `ChapterModuleTest`

- Create draft chapter
- Validation boundary (`chapter_number < 1`)
- Duplicate chapter number conflict (parallel-like requests)
- Public visibility rules for unpublished chapters
- Repeated read view increment
- Pending update flow for published chapter edits
- Bulk delete ownership/novel integrity checks
