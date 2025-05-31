# Novel API Documentation
**Backend Implementation Reference**

## Overview
This is a comprehensive Laravel-based API for a novel reading platform with user authentication, role-based access control, commenting system, rating system, email verification, and comprehensive rate limiting.

## Base URL
```
http://localhost:8000/api
```

## Authentication
The API uses Laravel Sanctum for authentication. Include the Bearer token in the Authorization header:
```
Authorization: Bearer {your_token_here}
```

## User Roles
- `0` - Regular User
- `1` - Admin

## 📧 Email Verification System

### Registration with Email Verification
**POST** `/auth/register`

Registers a new user and automatically sends email verification.

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response:**
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": null,
    "email_verified": false,
    "role": 0,
    "avatar": null,
    "bio": null,
    "is_admin": false
  },
  "token": "1|abc123...",
  "verification_notice": "Please check your email for verification link."
}
```

### Email Verification
**POST** `/auth/email/verify/{id}/{hash}`

Verifies a user's email address using the link sent via email.

**Parameters:**
- `id` - User ID
- `hash` - SHA1 hash of the user's email address

**Response (Success):**
```json
{
  "message": "Email verified successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2025-05-31T14:55:19.000000Z",
    "email_verified": true,
    "role": 0
  }
}
```

**Response (Already Verified):**
```json
{
  "message": "Email is already verified"
}
```

### Send Verification Email
**POST** `/auth/email/verification-notification`

Manually sends a verification email to the authenticated user.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "message": "Verification email sent successfully"
}
```

### Resend Verification Email
**POST** `/auth/email/resend-verification`

Resends verification email for unverified users.

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "message": "Verification email resent successfully"
}
```

## 🔐 Authentication Endpoints

### Login
**POST** `/auth/login`

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2025-05-31T14:55:19.000000Z",
    "email_verified": true,
    "role": 0,
    "avatar": null,
    "bio": null,
    "is_admin": false
  },
  "token": "2|def456..."
}
```

### Get User Profile
**GET** `/auth/me`

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2025-05-31T14:55:19.000000Z",
    "email_verified": true,
    "role": 0,
    "avatar": null,
    "bio": null,
    "is_admin": false,
    "last_login_at": "2025-05-31T14:53:16.000000Z",
    "created_at": "2025-05-31T14:53:05.000000Z"
  }
}
```

### Logout
**POST** `/auth/logout`

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "message": "Logged out successfully"
}
```

### Update Profile
**PUT** `/auth/profile`

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "name": "Updated Name",
  "bio": "My updated bio",
  "avatar": "https://example.com/avatar.jpg"
}
```

### Change Password
**PUT** `/auth/change-password`

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```

## 📚 Novel Endpoints

### Get All Novels
**GET** `/novels`

**Query Parameters:**
- `genre` - Filter by genre slug
- `status` - Filter by status (ongoing, completed, hiatus)
- `sort_by` - Sort by (popular, rating, latest, updated)
- `sort_order` - Sort order (asc, desc)

### Search Novels
**GET** `/novels/search?q={query}`

### Get Popular Novels
**GET** `/novels/popular`

### Get Latest Novels
**GET** `/novels/latest`

### Get Novel Details
**GET** `/novels/{slug}`

### Create Novel (Admin Only)
**POST** `/novels`

**Headers:** `Authorization: Bearer {admin_token}`

**Request Body:**
```json
{
  "title": "Amazing Novel",
  "author": "Great Author",
  "description": "An amazing story...",
  "cover_image": "https://example.com/cover.jpg",
  "status": "ongoing",
  "genres": [1, 2, 3]
}
```

## 💬 Comment System

### Get Comments for Novel
**GET** `/novels/{slug}/comments`

### Get Comments for Chapter
**GET** `/novels/{slug}/chapters/{chapterNumber}/comments`

### Create Comment
**POST** `/comments`

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "content": "Great chapter!",
  "novel_id": 1,
  "chapter_id": 5,
  "parent_id": null,
  "is_spoiler": false
}
```

### Vote on Comment
**POST** `/comments/{comment}/vote`

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "vote": "like"
}
```

## ⭐ Rating System

### Create Rating
**POST** `/ratings`

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "novel_id": 1,
  "rating": 5,
  "review": "Amazing novel, highly recommended!"
}
```

### Get Novel Ratings
**GET** `/novels/{slug}/ratings`

### Get User's Rating for Novel
**GET** `/novels/{slug}/my-rating`

**Headers:** `Authorization: Bearer {token}`

## 📖 Reading Progress

### Update Reading Progress
**PUT** `/reading-progress`

**Headers:** `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "novel_slug": "amazing-novel",
  "chapter_number": 5
}
```

### Get Reading Progress
**GET** `/reading-progress/{novel_slug}`

**Headers:** `Authorization: Bearer {token}`

### Get All User Reading Progress
**GET** `/reading-progress/user`

**Headers:** `Authorization: Bearer {token}`

## 🛡️ Middleware Protection

### Email Verification Middleware
Routes protected with `verified` middleware require users to have verified their email addresses.

**Error Response (Unverified Email):**
```json
{
  "message": "Your email address is not verified. Please check your email for verification link.",
  "requires_verification": true
}
```

### Admin Middleware
Routes protected with `admin` middleware require admin privileges.

**Error Response (Non-Admin):**
```json
{
  "message": "Access denied. Admin privileges required."
}
```

## 🔧 Error Responses

### Validation Error (422)
```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### Unauthorized (401)
```json
{
  "message": "Unauthenticated."
}
```

### Forbidden (403)
```json
{
  "message": "Access denied. Admin privileges required."
}
```

### Not Found (404)
```json
{
  "message": "Novel not found"
}
```

## 🚀 Getting Started

1. **Register a new account:**
   ```bash
   curl -X POST http://localhost:8000/api/auth/register \
     -H "Content-Type: application/json" \
     -d '{"name": "Your Name", "email": "your@email.com", "password": "password123", "password_confirmation": "password123"}'
   ```

2. **Verify your email** using the link sent to your email

3. **Login to get your token:**
   ```bash
   curl -X POST http://localhost:8000/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email": "your@email.com", "password": "password123"}'
   ```

4. **Use the token** in subsequent requests:
   ```bash
   curl -X GET http://localhost:8000/api/auth/me \
     -H "Authorization: Bearer YOUR_TOKEN_HERE"
   ```

## ⚙️ Configuration

### Email Configuration
Update your `.env` file with proper mail settings:

```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourapp.com"
MAIL_FROM_NAME="Your App Name"
```

### Google OAuth (Optional)
```env
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

## 📊 Features Summary

✅ **User Authentication & Authorization**
- Registration with email verification
- Login/Logout with Sanctum tokens
- Role-based access control (User/Admin)
- Profile management
- Password change functionality

✅ **Email Verification System**
- Automatic verification email on registration
- Manual verification email sending
- Email verification middleware
- Resend verification functionality

✅ **Novel Management**
- CRUD operations (Admin only for create/update/delete)
- Search and filtering
- Genre categorization
- Reading progress tracking

✅ **Interactive Features**
- Comment system with nested replies
- Comment voting (like/dislike)
- Novel rating and review system
- Reading progress tracking

✅ **Security Features**
- CSRF protection
- Input validation
- Secure password hashing
- Email verification requirements

✅ **Admin Features**
- Novel management
- Comment moderation
- User management capabilities
