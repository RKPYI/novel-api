# Telescope Authentication Guide

This guide explains how to authenticate and access Laravel Telescope in production.

## Overview

Telescope is protected in production and only accessible to administrators. We provide two authentication methods:

1. **Email/Password Login** - For admins with password-based accounts
2. **Google OAuth Login** - For admins using Google authentication

## Access Telescope

### Method 1: Direct Access (Local Development)

In local development, Telescope is accessible without authentication:

```
http://localhost:8000/telescope
```

### Method 2: Production Access

In production, you must authenticate first:

1. Go to the Telescope login page:
   ```
   https://your-domain.com/telescope/login
   ```

2. Choose your login method:
   - **Email/Password**: Enter your admin credentials
   - **Google**: Click "Continue with Google" button

3. After successful authentication, you'll be automatically redirected to Telescope

## Authentication Methods

### Email/Password Login

1. Navigate to `/telescope/login`
2. Enter your email and password
3. Click "Login to Telescope"
4. If you're an admin, you'll be redirected to `/telescope`

**Requirements:**
- Must be an admin user (`is_admin` = true)
- Must have a password set in the database

### Google OAuth Login

1. Navigate to `/telescope/login`
2. Click "Continue with Google" button
3. Authenticate with your Google account
4. After successful OAuth, you'll be redirected back to Telescope

**Requirements:**
- Must be an admin user (`is_admin` = true)
- Google email must match an admin account in the database

**Flow:**
```
/telescope/login
  → Click "Continue with Google"
  → /api/auth/google?telescope=true
  → Google OAuth consent screen
  → /api/auth/google/callback?state=telescope
  → /telescope/login-callback?token=xxx&user=yyy
  → /telescope (if admin)
```

## Admin User Setup

### Check if User is Admin

```sql
SELECT id, name, email, role, is_admin
FROM users
WHERE email = 'your-email@example.com';
```

### Make a User Admin

```sql
UPDATE users
SET is_admin = 1
WHERE email = 'admin@example.com';
```

Or via Laravel Tinker:

```php
php artisan tinker

$user = User::where('email', 'admin@example.com')->first();
$user->is_admin = true;
$user->save();
```

## Security Features

1. **Admin-Only Access**: Only users with `is_admin = true` can access Telescope
2. **Environment-Based Gates**: Local development allows all access, production requires admin
3. **Token Authentication**: Uses Laravel Sanctum tokens for API authentication
4. **CSRF Protection**: All forms include CSRF token validation
5. **Stateful OAuth**: Google OAuth maintains state to prevent CSRF attacks

## Troubleshooting

### "Access Denied" Error

**Cause**: User is not an admin

**Solution**:
```sql
UPDATE users SET is_admin = 1 WHERE email = 'your-email@example.com';
```

### Google Login Not Working

**Cause**: Google OAuth not configured

**Solution**: Check `.env` file:
```env
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/google/callback
```

### Forbidden Access in Production

**Cause**: Not authenticated or not an admin

**Solution**: Go to `/telescope/login` and authenticate

### "Invalid Credentials" Error

**Cause**: Wrong email or password

**Solution**:
- Verify email and password
- Reset password if needed
- Use Google OAuth if you don't have a password

## Technical Details

### TelescopeServiceProvider Gate

Located in `app/Providers/TelescopeServiceProvider.php`:

```php
protected function gate(): void
{
    Telescope::auth(function ($request) {
        // Allow all access in local environment
        if (app()->environment('local')) {
            return true;
        }

        // In production, only allow admins
        $user = $request->user();
        return $user && $user->isAdmin();
    });
}
```

### Google OAuth State Parameter

When logging in via Google for Telescope access:

1. The login page adds `?telescope=true` to the OAuth URL
2. The `AuthController` sets `state=telescope` in the OAuth request
3. After OAuth callback, the state is checked
4. If state is "telescope", user is redirected to `/telescope/login-callback`
5. The login page handles the callback and redirects to Telescope

### Routes

**Web Routes** (`routes/web.php`):
- `GET /telescope/login` - Login page
- `GET /telescope/login-callback` - OAuth callback handler (same view)

**API Routes** (`routes/api.php`):
- `POST /api/auth/login` - Email/password authentication
- `GET /api/auth/google` - Initiate Google OAuth
- `GET /api/auth/google/callback` - Handle Google OAuth callback

## Files Modified

1. `resources/views/telescope-login.blade.php` - Login page with both auth methods
2. `app/Http/Controllers/Auth/AuthController.php` - OAuth callback with Telescope state
3. `app/Http/Controllers/TelescopeAuthController.php` - Telescope login controller
4. `routes/web.php` - Telescope login routes
5. `app/Providers/TelescopeServiceProvider.php` - Admin-only access gate

## Examples

### Testing Email/Password Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

### Testing Admin Access

```php
php artisan tinker

// Check admin status
$user = User::find(1);
echo $user->isAdmin(); // Should return true

// Make user admin
$user->is_admin = true;
$user->save();
```

## Best Practices

1. **Limit Admin Users**: Only give admin access to trusted users
2. **Use Strong Passwords**: For email/password authentication
3. **Enable 2FA**: Use Google accounts with 2FA enabled
4. **Monitor Access**: Check Telescope logs regularly
5. **Rotate Tokens**: Periodically invalidate and regenerate tokens

## Production Deployment

Before deploying to production:

1. Ensure `APP_ENV=production` in `.env`
2. Set up at least one admin user
3. Configure Google OAuth credentials
4. Test both login methods
5. Verify admin-only access is enforced

```bash
# Test that non-admins cannot access
curl -I https://your-domain.com/telescope
# Should return: 403 Forbidden

# Test that admins can access after login
# Login first, then:
curl -I https://your-domain.com/telescope -H "Authorization: Bearer YOUR_TOKEN"
# Should return: 200 OK
```
