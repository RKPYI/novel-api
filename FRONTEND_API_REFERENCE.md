# Novel Reading Platform API Reference
**Frontend Developer's Complete Guide**

## 📋 Table of Contents
- [Getting Started](#getting-started)
- [Rate Limiting](#rate-limiting)
- [Authentication](#authentication)
- [Error Handling](#error-handling)
- [User Management](#user-management)
- [Novel Management](#novel-management)
- [Chapter Management](#chapter-management)
- [Comment System](#comment-system)
- [Rating System](#rating-system)
- [Reading Progress](#reading-progress)
- [Admin Operations](#admin-operations)
- [Frontend Integration Examples](#frontend-integration-examples)

---

## 🚀 Getting Started

### Base URL
```
http://localhost:8000/api
```

### Required Headers
```javascript
{
  'Accept': 'application/json',
  'Content-Type': 'application/json',
  'Authorization': 'Bearer {token}' // For authenticated endpoints
}
```

### Response Format
All API responses follow this structure:
```json
{
  "message": "Description of the response",
  "data": {...}, // or array
  "errors": {...} // Only present on validation errors
}
```

---

## ⚡ Rate Limiting

The API implements comprehensive rate limiting to prevent abuse and ensure fair usage.

### Rate Limit Types

| Operation Type | Per Minute | Per Hour | Scope |
|---|---|---|---|
| **Authentication** | 5 requests | 20 requests | Per IP |
| **Email Verification** | 3 requests | 10 requests | Per IP |
| **API Read** | 60 requests | 1000 requests | Per User/IP |
| **API Write** | 20 requests | 200 requests | Per User |
| **Search** | 30 requests | 300 requests | Per User/IP |
| **Admin Operations** | 30 requests | 200 requests | Per Admin User |

### Rate Limit Headers
When you hit a rate limit, you'll receive a `429 Too Many Requests` response with:
```javascript
{
  "message": "Too many login attempts. Please try again in 60 seconds.",
  "retry_after": 60,
  "type": "authentication_rate_limit"
}
```

### Check Rate Limit Status
```javascript
// GET /rate-limits/status
fetch('/api/rate-limits/status', {
  headers: { 'Authorization': 'Bearer ' + token }
})
.then(response => response.json())
.then(data => {
  console.log('Rate limit status:', data.rate_limits);
});
```

### Frontend Rate Limit Handling
```javascript
async function apiRequest(url, options = {}) {
  const response = await fetch(url, options);
  
  if (response.status === 429) {
    const data = await response.json();
    const retryAfter = data.retry_after || 60;
    
    // Show user-friendly message
    showRateLimitMessage(data.message, retryAfter);
    
    // Optionally retry after delay
    await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
    return apiRequest(url, options);
  }
  
  return response;
}

function showRateLimitMessage(message, retryAfter) {
  // Display notification to user
  alert(`${message} Please wait ${retryAfter} seconds.`);
}
```

---

## 🔐 Authentication

### Registration
```javascript
// POST /auth/register
const registerUser = async (userData) => {
  const response = await fetch('/api/auth/register', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      name: userData.name,
      email: userData.email,
      password: userData.password,
      password_confirmation: userData.password_confirmation
    })
  });
  
  const data = await response.json();
  
  if (response.ok) {
    // User registered, but needs email verification
    localStorage.setItem('auth_token', data.token);
    showEmailVerificationMessage();
  }
  
  return data;
};
```

**Response:**
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified": false,
    "role": 0,
    "is_admin": false
  },
  "token": "1|abc123def456..."
}
```

### Login
```javascript
// POST /auth/login
const loginUser = async (email, password) => {
  const response = await fetch('/api/auth/login', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ email, password })
  });
  
  const data = await response.json();
  
  if (response.ok) {
    localStorage.setItem('auth_token', data.token);
    return data.user;
  }
  
  throw new Error(data.message);
};
```

### Google OAuth
```javascript
// GET /auth/google - Redirect to Google OAuth
window.location.href = '/api/auth/google';

// Handle callback after Google auth
// POST /auth/google/callback
const handleGoogleCallback = async (code) => {
  const response = await fetch('/api/auth/google/callback', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ code })
  });
  
  const data = await response.json();
  
  if (response.ok) {
    localStorage.setItem('auth_token', data.token);
    return data.user;
  }
  
  throw new Error(data.message);
};
```

### Email Verification
```javascript
// POST /auth/email/verification-notification
const resendVerificationEmail = async () => {
  const response = await fetch('/api/auth/email/verification-notification', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    }
  });
  
  return response.json();
};
```

### Logout
```javascript
// POST /auth/logout
const logoutUser = async () => {
  await fetch('/api/auth/logout', {
    method: 'POST',
    headers: {
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    }
  });
  
  localStorage.removeItem('auth_token');
};
```

---

## ❌ Error Handling

### Standard Error Response
```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### Common HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden (email not verified, insufficient permissions)
- `404` - Not Found
- `409` - Conflict
- `422` - Validation Error
- `429` - Too Many Requests (Rate Limited)
- `500` - Internal Server Error

### Frontend Error Handling
```javascript
const handleApiResponse = async (response) => {
  const data = await response.json();
  
  switch (response.status) {
    case 200:
    case 201:
      return data;
      
    case 401:
      // Redirect to login
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
      break;
      
    case 403:
      if (data.message.includes('email')) {
        showEmailVerificationRequired();
      } else {
        showInsufficientPermissions();
      }
      break;
      
    case 422:
      showValidationErrors(data.errors);
      break;
      
    case 429:
      showRateLimitMessage(data.message, data.retry_after);
      break;
      
    default:
      showGenericError(data.message);
  }
  
  throw new Error(data.message);
};
```

---

## 👤 User Management

### Get Current User
```javascript
// GET /auth/me
const getCurrentUser = async () => {
  const response = await fetch('/api/auth/me', {
    headers: {
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    }
  });
  
  return response.json();
};
```

### Update Profile
```javascript
// PUT /auth/profile
const updateProfile = async (profileData) => {
  const response = await fetch('/api/auth/profile', {
    method: 'PUT',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    },
    body: JSON.stringify({
      name: profileData.name,
      bio: profileData.bio,
      avatar: profileData.avatar
    })
  });
  
  return response.json();
};
```

### Change Password
```javascript
// PUT /auth/change-password
const changePassword = async (passwords) => {
  const response = await fetch('/api/auth/change-password', {
    method: 'PUT',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    },
    body: JSON.stringify({
      current_password: passwords.current,
      password: passwords.new,
      password_confirmation: passwords.confirmation
    })
  });
  
  return response.json();
};
```

---

## 📚 Novel Management

### Get All Novels
```javascript
// GET /novels
const getNovels = async (filters = {}) => {
  const params = new URLSearchParams();
  
  if (filters.genre) params.append('genre', filters.genre);
  if (filters.status) params.append('status', filters.status);
  if (filters.sort_by) params.append('sort_by', filters.sort_by);
  if (filters.sort_order) params.append('sort_order', filters.sort_order);
  
  const response = await fetch(`/api/novels?${params}`);
  return response.json();
};
```

**Query Parameters:**
- `genre` - Filter by genre slug
- `status` - Filter by status (`ongoing`, `completed`, `hiatus`)
- `sort_by` - Sort by (`popular`, `rating`, `latest`, `updated`)
- `sort_order` - Sort order (`asc`, `desc`)

### Search Novels
```javascript
// GET /novels/search
const searchNovels = async (query) => {
  const params = new URLSearchParams({ q: query });
  const response = await fetch(`/api/novels/search?${params}`);
  return response.json();
};
```

### Get Popular Novels
```javascript
// GET /novels/popular
const getPopularNovels = async () => {
  const response = await fetch('/api/novels/popular');
  return response.json();
};
```

### Get Latest Novels
```javascript
// GET /novels/latest
const getLatestNovels = async () => {
  const response = await fetch('/api/novels/latest');
  return response.json();
};
```

### Get Novel Details
```javascript
// GET /novels/{slug}
const getNovelDetails = async (slug) => {
  const response = await fetch(`/api/novels/${slug}`);
  const data = await response.json();
  
  if (response.ok) {
    return data.novel;
  }
  
  throw new Error(data.message);
};
```

**Response:**
```json
{
  "message": "Novel details",
  "novel": {
    "id": 1,
    "title": "Epic Fantasy Adventure",
    "author": "Amazing Author",
    "description": "An incredible journey...",
    "cover_image": "https://example.com/cover.jpg",
    "rating": 4.5,
    "rating_count": 150,
    "views": 5000,
    "status": "ongoing",
    "slug": "epic-fantasy-adventure",
    "genres": [
      {"id": 1, "name": "Fantasy", "slug": "fantasy"},
      {"id": 2, "name": "Adventure", "slug": "adventure"}
    ],
    "chapters": [
      {
        "id": 1,
        "chapter_number": 1,
        "title": "The Beginning",
        "word_count": 2500
      }
    ]
  }
}
```

### Get Genres
```javascript
// GET /novels/genres
const getGenres = async () => {
  const response = await fetch('/api/novels/genres');
  return response.json();
};
```

---

## 📖 Chapter Management

### Get Novel Chapters
```javascript
// GET /novels/{novel:slug}/chapters
const getNovelChapters = async (novelSlug) => {
  const response = await fetch(`/api/novels/${novelSlug}/chapters`);
  return response.json();
};
```

### Get Chapter Content
```javascript
// GET /novels/{novel:slug}/chapters/{chapterNumber}
const getChapterContent = async (novelSlug, chapterNumber) => {
  const response = await fetch(`/api/novels/${novelSlug}/chapters/${chapterNumber}`);
  const data = await response.json();
  
  if (response.ok) {
    return {
      novel: data.novel,
      chapter: data.chapter
    };
  }
  
  throw new Error(data.message);
};
```

**Response:**
```json
{
  "message": "Chapter details",
  "novel": {
    "title": "Epic Fantasy Adventure",
    "slug": "epic-fantasy-adventure",
    "author": "Amazing Author"
  },
  "chapter": {
    "id": 1,
    "title": "The Beginning",
    "chapter_number": 1,
    "content": "Chapter content here...",
    "word_count": 2500,
    "previous_chapter": null,
    "next_chapter": 2
  }
}
```

---

## 💬 Comment System

### Get Comments
```javascript
// GET /novels/{novel:slug}/comments (for novel comments)
// GET /novels/{novel:slug}/chapters/{chapterNumber}/comments (for chapter comments)
const getComments = async (novelSlug, chapterNumber = null) => {
  const url = chapterNumber 
    ? `/api/novels/${novelSlug}/chapters/${chapterNumber}/comments`
    : `/api/novels/${novelSlug}/comments`;
    
  const response = await fetch(url);
  return response.json();
};
```

### Create Comment
```javascript
// POST /comments
const createComment = async (commentData) => {
  const response = await fetch('/api/comments', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    },
    body: JSON.stringify({
      novel_id: commentData.novelId,
      chapter_id: commentData.chapterId, // Optional for novel comments
      parent_id: commentData.parentId,   // Optional for replies
      content: commentData.content
    })
  });
  
  return response.json();
};
```

### Update Comment
```javascript
// PUT /comments/{comment}
const updateComment = async (commentId, content) => {
  const response = await fetch(`/api/comments/${commentId}`, {
    method: 'PUT',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    },
    body: JSON.stringify({ content })
  });
  
  return response.json();
};
```

### Delete Comment
```javascript
// DELETE /comments/{comment}
const deleteComment = async (commentId) => {
  const response = await fetch(`/api/comments/${commentId}`, {
    method: 'DELETE',
    headers: {
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    }
  });
  
  return response.json();
};
```

### Vote on Comment
```javascript
// POST /comments/{comment}/vote
const voteOnComment = async (commentId, voteType) => {
  const response = await fetch(`/api/comments/${commentId}/vote`, {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    },
    body: JSON.stringify({
      vote_type: voteType // 'like' or 'dislike'
    })
  });
  
  return response.json();
};
```

---

## ⭐ Rating System

### Get Novel Ratings
```javascript
// GET /novels/{novel:slug}/ratings
const getNovelRatings = async (novelSlug) => {
  const response = await fetch(`/api/novels/${novelSlug}/ratings`);
  return response.json();
};
```

### Create/Update Rating
```javascript
// POST /ratings
const rateNovel = async (novelId, rating, review = null) => {
  const response = await fetch('/api/ratings', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    },
    body: JSON.stringify({
      novel_id: novelId,
      rating: rating, // 1-5
      review: review  // Optional review text
    })
  });
  
  return response.json();
};
```

### Get User's Rating
```javascript
// GET /novels/{novel:slug}/my-rating
const getUserRating = async (novelSlug) => {
  const response = await fetch(`/api/novels/${novelSlug}/my-rating`, {
    headers: {
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    }
  });
  
  return response.json();
};
```

### Get All User Ratings
```javascript
// GET /my-ratings
const getAllUserRatings = async () => {
  const response = await fetch('/api/my-ratings', {
    headers: {
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    }
  });
  
  return response.json();
};
```

---

## 📊 Reading Progress

### Get Reading Progress
```javascript
// GET /reading-progress/{novel:slug}
const getReadingProgress = async (novelSlug) => {
  const response = await fetch(`/api/reading-progress/${novelSlug}`, {
    headers: {
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    }
  });
  
  return response.json();
};
```

### Update Reading Progress
```javascript
// PUT /reading-progress
const updateReadingProgress = async (novelSlug, chapterNumber) => {
  const response = await fetch('/api/reading-progress', {
    method: 'PUT',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    },
    body: JSON.stringify({
      novel_slug: novelSlug,
      chapter_number: chapterNumber
    })
  });
  
  return response.json();
};
```

### Get All User Reading Progress
```javascript
// GET /reading-progress/user
const getAllUserProgress = async () => {
  const response = await fetch('/api/reading-progress/user', {
    headers: {
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    }
  });
  
  return response.json();
};
```

---

## 🔧 Admin Operations

### Create Novel (Admin Only)
```javascript
// POST /novels
const createNovel = async (novelData) => {
  const response = await fetch('/api/novels', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    },
    body: JSON.stringify({
      title: novelData.title,
      author: novelData.author,
      description: novelData.description,
      cover_image: novelData.coverImage,
      status: novelData.status, // 'ongoing', 'completed', 'hiatus'
      genres: novelData.genreIds // Array of genre IDs
    })
  });
  
  return response.json();
};
```

### Update Novel (Admin Only)
```javascript
// PUT /novels/{slug}
const updateNovel = async (slug, novelData) => {
  const response = await fetch(`/api/novels/${slug}`, {
    method: 'PUT',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    },
    body: JSON.stringify(novelData)
  });
  
  return response.json();
};
```

### Delete Novel (Admin Only)
```javascript
// DELETE /novels/{slug}
const deleteNovel = async (slug) => {
  const response = await fetch(`/api/novels/${slug}`, {
    method: 'DELETE',
    headers: {
      'Authorization': 'Bearer ' + localStorage.getItem('auth_token')
    }
  });
  
  return response.json();
};
```

---

## 🎯 Frontend Integration Examples

### React Hook for API Calls
```javascript
import { useState, useEffect } from 'react';

const useApi = (url, options = {}) => {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        const token = localStorage.getItem('auth_token');
        
        const response = await fetch(url, {
          ...options,
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            ...(token && { 'Authorization': `Bearer ${token}` }),
            ...options.headers
          }
        });

        const result = await handleApiResponse(response);
        setData(result);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [url]);

  return { data, loading, error };
};

// Usage
const NovelList = () => {
  const { data, loading, error } = useApi('/api/novels?sort_by=popular');

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div>
      {data.novels.data.map(novel => (
        <div key={novel.id}>
          <h3>{novel.title}</h3>
          <p>by {novel.author}</p>
          <p>Rating: {novel.rating}/5</p>
        </div>
      ))}
    </div>
  );
};
```

### Vue.js Composition API Example
```javascript
import { ref, onMounted } from 'vue';

export function useNovels() {
  const novels = ref([]);
  const loading = ref(false);
  const error = ref(null);

  const fetchNovels = async (filters = {}) => {
    loading.value = true;
    error.value = null;
    
    try {
      const params = new URLSearchParams(filters);
      const response = await fetch(`/api/novels?${params}`);
      const data = await handleApiResponse(response);
      novels.value = data.novels.data;
    } catch (err) {
      error.value = err.message;
    } finally {
      loading.value = false;
    }
  };

  const searchNovels = async (query) => {
    loading.value = true;
    error.value = null;
    
    try {
      const response = await fetch(`/api/novels/search?q=${encodeURIComponent(query)}`);
      const data = await handleApiResponse(response);
      novels.value = data.novels;
    } catch (err) {
      error.value = err.message;
    } finally {
      loading.value = false;
    }
  };

  return {
    novels,
    loading,
    error,
    fetchNovels,
    searchNovels
  };
}
```

### JavaScript Authentication Manager
```javascript
class AuthManager {
  constructor() {
    this.token = localStorage.getItem('auth_token');
    this.user = null;
  }

  async login(email, password) {
    const response = await fetch('/api/auth/login', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ email, password })
    });

    const data = await handleApiResponse(response);
    
    this.token = data.token;
    this.user = data.user;
    localStorage.setItem('auth_token', this.token);
    
    return this.user;
  }

  async logout() {
    if (this.token) {
      await fetch('/api/auth/logout', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.token}`
        }
      });
    }

    this.token = null;
    this.user = null;
    localStorage.removeItem('auth_token');
  }

  async getCurrentUser() {
    if (!this.token) return null;

    try {
      const response = await fetch('/api/auth/me', {
        headers: {
          'Authorization': `Bearer ${this.token}`
        }
      });

      const data = await handleApiResponse(response);
      this.user = data.user;
      return this.user;
    } catch (error) {
      // Token might be invalid
      this.logout();
      return null;
    }
  }

  isAuthenticated() {
    return !!this.token;
  }

  isEmailVerified() {
    return this.user && this.user.email_verified;
  }

  isAdmin() {
    return this.user && this.user.is_admin;
  }
}

// Usage
const auth = new AuthManager();

// On app startup
auth.getCurrentUser().then(user => {
  if (user) {
    console.log('User is logged in:', user);
  }
});
```

### Rate Limit Handler
```javascript
class RateLimitHandler {
  constructor() {
    this.retryDelays = new Map();
  }

  async handleRequest(url, options = {}) {
    // Check if we're in a retry delay
    const retryUntil = this.retryDelays.get(url);
    if (retryUntil && Date.now() < retryUntil) {
      const remainingTime = Math.ceil((retryUntil - Date.now()) / 1000);
      throw new Error(`Rate limited. Try again in ${remainingTime} seconds.`);
    }

    try {
      const response = await fetch(url, options);
      
      if (response.status === 429) {
        const data = await response.json();
        const retryAfter = (data.retry_after || 60) * 1000;
        
        // Store retry delay
        this.retryDelays.set(url, Date.now() + retryAfter);
        
        throw new Error(data.message || 'Rate limit exceeded');
      }

      // Clear retry delay on successful request
      this.retryDelays.delete(url);
      
      return response;
    } catch (error) {
      throw error;
    }
  }

  getRemainingDelay(url) {
    const retryUntil = this.retryDelays.get(url);
    if (!retryUntil) return 0;
    
    return Math.max(0, Math.ceil((retryUntil - Date.now()) / 1000));
  }
}

// Usage
const rateLimitHandler = new RateLimitHandler();

async function safeApiCall(url, options) {
  try {
    return await rateLimitHandler.handleRequest(url, options);
  } catch (error) {
    if (error.message.includes('Rate limited')) {
      showNotification(error.message, 'warning');
    }
    throw error;
  }
}
```

---

## 📱 Mobile-Specific Considerations

### Offline Support
```javascript
// Service worker for caching novels
self.addEventListener('fetch', event => {
  if (event.request.url.includes('/api/novels/')) {
    event.respondWith(
      caches.match(event.request).then(response => {
        return response || fetch(event.request).then(fetchResponse => {
          const responseClone = fetchResponse.clone();
          caches.open('novels-cache').then(cache => {
            cache.put(event.request, responseClone);
          });
          return fetchResponse;
        });
      })
    );
  }
});
```

### Reading Progress Sync
```javascript
class ReadingProgressSync {
  constructor() {
    this.pendingUpdates = [];
    this.isOnline = navigator.onLine;
    
    window.addEventListener('online', () => {
      this.isOnline = true;
      this.syncPendingUpdates();
    });
    
    window.addEventListener('offline', () => {
      this.isOnline = false;
    });
  }

  async updateProgress(novelSlug, chapterNumber) {
    const update = { novelSlug, chapterNumber, timestamp: Date.now() };
    
    if (this.isOnline) {
      try {
        await updateReadingProgress(novelSlug, chapterNumber);
        // Store locally as backup
        localStorage.setItem(`progress_${novelSlug}`, JSON.stringify(update));
      } catch (error) {
        // Store for later sync
        this.pendingUpdates.push(update);
        localStorage.setItem('pending_progress_updates', JSON.stringify(this.pendingUpdates));
      }
    } else {
      // Store for later sync
      this.pendingUpdates.push(update);
      localStorage.setItem('pending_progress_updates', JSON.stringify(this.pendingUpdates));
    }
  }

  async syncPendingUpdates() {
    const stored = localStorage.getItem('pending_progress_updates');
    if (stored) {
      this.pendingUpdates = JSON.parse(stored);
    }

    for (const update of this.pendingUpdates) {
      try {
        await updateReadingProgress(update.novelSlug, update.chapterNumber);
      } catch (error) {
        console.error('Failed to sync reading progress:', error);
        break; // Stop syncing if one fails
      }
    }

    this.pendingUpdates = [];
    localStorage.removeItem('pending_progress_updates');
  }
}
```

---

## 🔍 Advanced Search Implementation

```javascript
class NovelSearch {
  constructor() {
    this.searchHistory = JSON.parse(localStorage.getItem('searchHistory') || '[]');
    this.searchCache = new Map();
    this.debounceTimer = null;
  }

  async search(query, filters = {}) {
    // Return cached result if available
    const cacheKey = JSON.stringify({ query, filters });
    if (this.searchCache.has(cacheKey)) {
      return this.searchCache.get(cacheKey);
    }

    try {
      const params = new URLSearchParams({ q: query, ...filters });
      const response = await fetch(`/api/novels/search?${params}`);
      const data = await handleApiResponse(response);

      // Cache the result
      this.searchCache.set(cacheKey, data);
      
      // Add to search history
      this.addToHistory(query);

      return data;
    } catch (error) {
      console.error('Search failed:', error);
      throw error;
    }
  }

  debounceSearch(callback, delay = 300) {
    return (query, filters) => {
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => {
        callback(query, filters);
      }, delay);
    };
  }

  addToHistory(query) {
    if (query.length < 2) return;
    
    this.searchHistory = this.searchHistory.filter(item => item !== query);
    this.searchHistory.unshift(query);
    this.searchHistory = this.searchHistory.slice(0, 10); // Keep last 10 searches
    
    localStorage.setItem('searchHistory', JSON.stringify(this.searchHistory));
  }

  getSearchHistory() {
    return this.searchHistory;
  }

  clearHistory() {
    this.searchHistory = [];
    localStorage.removeItem('searchHistory');
  }
}

// Usage
const novelSearch = new NovelSearch();

const debouncedSearch = novelSearch.debounceSearch(async (query, filters) => {
  if (query.length >= 2) {
    const results = await novelSearch.search(query, filters);
    displaySearchResults(results.novels);
  }
});

document.getElementById('searchInput').addEventListener('input', (e) => {
  debouncedSearch(e.target.value, getSelectedFilters());
});
```

---

## 🎨 UI Component Examples

### Novel Card Component (React)
```jsx
const NovelCard = ({ novel, onRead, onAddToLibrary }) => {
  const [isLoading, setIsLoading] = useState(false);

  const handleRead = async () => {
    setIsLoading(true);
    try {
      await onRead(novel.slug);
    } catch (error) {
      console.error('Failed to start reading:', error);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="novel-card">
      <img 
        src={novel.cover_image || '/placeholder-cover.jpg'} 
        alt={novel.title}
        className="novel-cover"
      />
      <div className="novel-info">
        <h3 className="novel-title">{novel.title}</h3>
        <p className="novel-author">by {novel.author}</p>
        <div className="novel-rating">
          <StarRating rating={novel.rating} />
          <span>({novel.rating_count} reviews)</span>
        </div>
        <div className="novel-genres">
          {novel.genres.map(genre => (
            <span key={genre.id} className="genre-tag">
              {genre.name}
            </span>
          ))}
        </div>
        <p className="novel-description">
          {novel.description.substring(0, 150)}...
        </p>
        <div className="novel-actions">
          <button 
            onClick={handleRead}
            disabled={isLoading}
            className="btn-primary"
          >
            {isLoading ? 'Loading...' : 'Read Now'}
          </button>
          <button 
            onClick={() => onAddToLibrary(novel.id)}
            className="btn-secondary"
          >
            Add to Library
          </button>
        </div>
      </div>
    </div>
  );
};
```

---

This comprehensive API reference provides everything a frontend developer needs to build a modern novel reading platform. The API is designed with rate limiting, proper error handling, and real-world usage patterns in mind.

For any questions or additional endpoints needed, please refer to the API source code or contact the backend development team.
