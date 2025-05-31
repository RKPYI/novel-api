# Novel Reading Platform API

A comprehensive Laravel-based REST API for a novel reading platform with user authentication, commenting system, rating system, and reading progress tracking.

## 📚 Documentation

### For Frontend Developers
**[📖 FRONTEND_API_REFERENCE.md](./FRONTEND_API_REFERENCE.md)**
- Complete integration guide with JavaScript examples
- React, Vue.js, and vanilla JavaScript implementations
- Rate limiting handling strategies
- Authentication management
- Mobile-specific considerations
- Error handling patterns
- Ready-to-use code snippets

### For Backend Developers
**[🔧 API_DOCUMENTATION.md](./API_DOCUMENTATION.md)**
- Technical implementation details
- Endpoint specifications
- Database relationships
- Laravel-specific features
- Server configuration

## 🚀 Quick Start for Frontend

```javascript
// Basic API call example
const response = await fetch('/api/novels/popular', {
  headers: {
    'Accept': 'application/json',
    'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
  }
});

const data = await response.json();
console.log(data.novels);
```

## 🔑 Key Features

### ✅ Authentication & Authorization
- Email/password registration and login
- Google OAuth integration
- Email verification system
- Role-based access control (User/Admin)
- Secure JWT token management via Laravel Sanctum

### ✅ Novel Management
- Browse novels with filtering and sorting
- Search by title, author, description
- Genre categorization
- Reading progress tracking
- Popular and latest novel endpoints

### ✅ Interactive Features
- Comment system with nested replies
- Comment voting (like/dislike)
- Novel rating and review system
- User reading progress tracking

### ✅ Rate Limiting & Security
- Comprehensive rate limiting (5 different tiers)
- CSRF protection
- Input validation
- Secure password hashing
- Email verification requirements

### ✅ Admin Features
- Novel CRUD operations
- Comment moderation
- User management
- Admin-specific rate limits

## 🛡️ Rate Limiting

| Operation | Per Minute | Per Hour | Scope |
|-----------|------------|----------|-------|
| Authentication | 5 | 20 | Per IP |
| API Read | 60 | 1000 | Per User/IP |
| API Write | 20 | 200 | Per User |
| Search | 30 | 300 | Per User/IP |
| Admin | 30 | 200 | Per Admin |

## 🔗 Endpoint Categories

### Public Endpoints (No Authentication)
- Browse novels, chapters, comments
- Search functionality
- Popular/latest novels
- Rate limit status

### User Endpoints (Authentication Required)
- Create comments and ratings
- Reading progress tracking
- Profile management
- Personal library

### Admin Endpoints (Admin Role Required)
- Novel management (CRUD)
- Comment moderation
- User management

### Email Verification Required
- Some comment actions
- Premium features (if implemented)

## 🎯 Frontend Integration

### Authentication Flow
1. Register user → Email verification sent
2. User clicks email link → Email verified
3. Login → Receive JWT token
4. Store token → Use for authenticated requests

### Error Handling
```javascript
// Handle rate limiting
if (response.status === 429) {
  const data = await response.json();
  showMessage(`Rate limited: ${data.message}`);
  setTimeout(() => retryRequest(), data.retry_after * 1000);
}

// Handle email verification
if (response.status === 403 && data.message.includes('email')) {
  showEmailVerificationDialog();
}
```

### Real-time Features (Recommended)
- WebSocket integration for live comments
- Push notifications for new chapters
- Reading progress synchronization

## 🔧 Development Setup

### Prerequisites
- PHP 8.1+
- Laravel 11
- MySQL/SQLite
- Composer

### Installation
```bash
# Clone repository
git clone <repository-url>
cd novel-api

# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate
php artisan db:seed

# Start development server
php artisan serve
```

### Testing the API
```bash
# Test rate limits
curl -H "Accept: application/json" http://localhost:8000/api/rate-limits

# Test novel listing
curl -H "Accept: application/json" http://localhost:8000/api/novels

# Test search
curl -H "Accept: application/json" "http://localhost:8000/api/novels/search?q=fantasy"
```

## 📱 Mobile App Considerations

### Offline Support
- Cache novels and chapters for offline reading
- Sync reading progress when online
- Queue comments/ratings for later submission

### Performance
- Implement pagination for large lists
- Use image lazy loading for covers
- Cache search results
- Implement pull-to-refresh

### User Experience
- Handle rate limiting gracefully
- Show loading states
- Provide offline indicators
- Implement dark mode

## 🤝 Contributing

### For Frontend Developers
1. Review [FRONTEND_API_REFERENCE.md](./FRONTEND_API_REFERENCE.md)
2. Test endpoints with provided examples
3. Report any issues or missing endpoints
4. Suggest UI/UX improvements

### For Backend Developers
1. Review [API_DOCUMENTATION.md](./API_DOCUMENTATION.md)
2. Follow Laravel best practices
3. Maintain rate limiting configurations
4. Update documentation for new endpoints

## 📞 Support

- **Issues**: Create GitHub issues for bugs
- **Features**: Submit feature requests
- **Questions**: Contact the development team
- **Rate Limits**: Contact support for higher limits

---

**Happy Coding! 🚀**
