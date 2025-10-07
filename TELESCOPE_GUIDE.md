# 🔭 Laravel Telescope - Your Performance Monitoring Tool

## ✅ What is Telescope?

Laravel Telescope is a powerful debugging and monitoring assistant for Laravel applications. It provides insight into:
- 📊 All HTTP requests and responses
- 🔍 Database queries (with N+1 detection)
- 🐛 Exceptions and errors
- 📝 Application logs
- ⚡ Cache operations
- 🎯 Queue jobs and more

**It's already installed and running!**

---

## 🚀 Quick Access

### Open Telescope Dashboard
```
http://localhost:8000/telescope
```

That's it! No additional setup needed.

---

## 📊 What You Can Monitor

### 1. **Requests Tab** 🎯 MOST IMPORTANT
Shows every API request with:
- ✅ Response time
- ✅ Memory usage
- ✅ Status code
- ✅ Full request/response data

**How to use:**
1. Make API requests to your application
2. Open Telescope → Requests tab
3. Click any request to see details

### 2. **Queries Tab** 🔍 PERFORMANCE CRITICAL
Shows all database queries with:
- ✅ SQL query text
- ✅ Execution time
- ✅ Query bindings
- ✅ Slow query highlighting (>50ms in red)
- ✅ Duplicate query detection

**How to identify N+1 queries:**
1. Make a request to an endpoint
2. Click on the request in Telescope
3. Look for the "Queries" badge - should show 1-10 queries
4. If you see 20+ similar queries, you likely have an N+1 problem

**Example of Good Performance:**
```
GET /api/reading-progress/user
└── Queries (1)
    └── SELECT * FROM reading_progress WHERE user_id = ? ...
```

**Example of N+1 Problem (BEFORE our fix):**
```
GET /api/reading-progress/user
└── Queries (21) ⚠️
    └── SELECT * FROM reading_progress WHERE user_id = ?
    └── SELECT COUNT(*) FROM chapters WHERE novel_id = 1
    └── SELECT COUNT(*) FROM chapters WHERE novel_id = 2
    └── SELECT COUNT(*) FROM chapters WHERE novel_id = 3
    ... (18 more times!)
```

### 3. **Exceptions Tab** 🐛
Shows all exceptions and errors:
- Full stack traces
- Request context when error occurred
- Easy debugging

### 4. **Logs Tab** 📝
Shows all application logs:
- Performance warnings
- Critical issues
- Custom log messages

### 5. **Models Tab** 🗄️
Shows Eloquent model events:
- Created/Updated/Deleted models
- Model hydration (queries)
- Useful for tracking data changes

### 6. **Cache Tab** 💾
Shows cache operations:
- Cache hits/misses
- Keys accessed
- Values stored

### 7. **Jobs Tab** ⚙️
Shows queue jobs:
- Job status
- Execution time
- Failed jobs

---

## 🎯 How to Use Telescope for Performance Analysis

### Step 1: Identify Slow Endpoints

1. **Access Telescope**: `http://localhost:8000/telescope`

2. **Go to Requests tab**

3. **Look for red indicators**:
   - Red badge = Slow request (>500ms)
   - Orange badge = Moderate (200-500ms)
   - Green badge = Fast (<200ms)

4. **Click on a slow request** to see details

### Step 2: Analyze Query Count

1. In the request detail, look at the **Queries** badge

2. **Good performance**: 1-10 queries
3. **Needs optimization**: 11-20 queries
4. **Critical issue**: 21+ queries

### Step 3: Identify Slow Queries

1. Click the **Queries** tab in Telescope

2. Look for **red highlighted queries** (>50ms)

3. Click on a slow query to see:
   - Full SQL
   - Bindings
   - Execution time
   - Where it was called from

### Step 4: Find N+1 Queries

1. Look for **repeated similar queries** in a single request

2. Example pattern to watch for:
   ```sql
   SELECT * FROM novels WHERE id = 1
   SELECT * FROM chapters WHERE novel_id = 1
   SELECT * FROM chapters WHERE novel_id = 2
   SELECT * FROM chapters WHERE novel_id = 3
   ```

3. **Solution**: Use eager loading
   ```php
   // ❌ Bad (N+1)
   $novels = Novel::all();
   foreach ($novels as $novel) {
       echo $novel->chapters->count();
   }
   
   // ✅ Good (1 query)
   $novels = Novel::withCount('chapters')->get();
   ```

---

## 📈 Monitoring Best Practices

### Daily Monitoring Routine

1. **Morning Check**:
   ```
   - Open Telescope
   - Check Requests tab for any red flags
   - Review Exceptions tab for errors
   ```

2. **After Code Changes**:
   ```
   - Test the affected endpoints
   - Check Telescope for query count
   - Verify no new slow queries appear
   ```

3. **Before Deployment**:
   ```
   - Review all endpoints in Telescope
   - Ensure no >20 query endpoints
   - Check for exceptions
   ```

### Performance Targets

| Metric | Good | Acceptable | Needs Work |
|--------|------|------------|------------|
| Query Count | 1-10 | 11-15 | 16+ |
| Response Time | <200ms | 200-500ms | >500ms |
| Slow Queries | 0 | 1-2 | 3+ |
| Exceptions | 0 | Handled | Unhandled |

---

## 🔧 Telescope Features for Developers

### 1. Filter by Endpoint
```
In Telescope Requests tab:
- Type: api/reading-progress
- See all requests to that endpoint
- Compare performance over time
```

### 2. Monitor Specific User
```
In Telescope:
- Click on a request
- See user_id in request data
- Track performance for specific users
```

### 3. Tag Requests for Monitoring
```php
// In your code
Telescope::tag(function () {
    return ['important', 'checkout-flow'];
});

// Then filter by tag in Telescope
```

### 4. View Recent Activity
```
Telescope shows last 24 hours by default
- Older entries are pruned automatically
- Configure retention in config/telescope.php
```

---

## 🎨 Telescope UI Overview

```
┌─────────────────────────────────────────────────┐
│ 🔭 Telescope                                    │
├─────────────────────────────────────────────────┤
│ Requests │ Commands │ Schedule │ Jobs │ ...    │
├─────────────────────────────────────────────────┤
│ GET /api/reading-progress/user    95ms   1 query│ ✅
│ GET /api/library                  180ms  4 query│ ✅
│ GET /api/novels/search            850ms 21 query│ ⚠️
│ POST /api/ratings                 120ms  3 query│ ✅
├─────────────────────────────────────────────────┤
│ Click any request to see full details           │
│ - Request headers                                │
│ - Request body                                   │
│ - Response data                                  │
│ - All queries executed                           │
│ - Timeline of events                             │
└─────────────────────────────────────────────────┘
```

---

## 🎯 Example: Analyzing the Optimizations We Made

### Before Optimization (You would have seen in Telescope):

**GET /api/reading-progress/user** (User with 20 novels)
```
⚠️ 850ms | 21 queries

Queries:
1. SELECT * FROM reading_progress WHERE user_id = 1
2. SELECT COUNT(*) FROM chapters WHERE novel_id = 1
3. SELECT COUNT(*) FROM chapters WHERE novel_id = 2
4. SELECT COUNT(*) FROM chapters WHERE novel_id = 3
... (18 more!)
```
**Status**: 🔴 CRITICAL - N+1 query problem

### After Optimization (Current state):

**GET /api/reading-progress/user**
```
✅ 95ms | 1 query

Queries:
1. SELECT * FROM reading_progress 
   WHERE user_id = 1 
   WITH novel.total_chapters
```
**Status**: ✅ EXCELLENT - Fixed!

---

## 🚨 Common Issues & Solutions

### Issue 1: Telescope Shows No Data

**Solution:**
```bash
# Clear cache
php artisan config:clear
php artisan cache:clear

# Make sure you're accessing the right URL
http://localhost:8000/telescope
```

### Issue 2: Telescope Access Denied

**Solution:**
The gate in `TelescopeServiceProvider` is configured to:
- Allow everyone in local environment
- Only allow admins in production

If you're getting access denied locally, check `.env`:
```env
APP_ENV=local  # Should be 'local'
```

### Issue 3: Too Much Data / Slow Telescope

**Solution:**
```bash
# Prune old entries
php artisan telescope:prune --hours=24

# Or clear all
php artisan telescope:clear
```

---

## 📚 Quick Reference Commands

```bash
# Clear Telescope data
php artisan telescope:clear

# Prune entries older than 24 hours
php artisan telescope:prune --hours=24

# Prune entries older than 7 days
php artisan telescope:prune --hours=168

# Publish Telescope assets
php artisan telescope:publish

# Disable Telescope temporarily
# In .env: TELESCOPE_ENABLED=false
```

---

## 🔍 Real-World Example Workflow

### Scenario: User Reports Slow Library Page

1. **Reproduce the Issue**
   - Login as that user
   - Visit `/api/library`
   - Note it feels slow

2. **Open Telescope**
   - Go to `http://localhost:8000/telescope`
   - Click "Requests" tab
   - Find `GET /api/library`

3. **Analyze**
   ```
   Response Time: 850ms ⚠️
   Queries: 15 ⚠️
   Memory: 45MB
   ```

4. **Click the Request → View Queries**
   - See 7 separate COUNT queries
   - Identify the issue: Multiple queries in stats calculation

5. **Fix the Code**
   - Use GROUP BY instead of multiple COUNTs
   - (This is what we did today!)

6. **Verify Fix**
   - Make the request again
   - Check Telescope
   ```
   Response Time: 180ms ✅
   Queries: 4 ✅
   Memory: 28MB ✅
   ```

7. **Deploy with Confidence!** 🚀

---

## 📊 Telescope vs Performance Middleware

You now have TWO monitoring tools:

### Telescope (Visual, Interactive)
- ✅ Best for: Development debugging
- ✅ See: Individual requests in detail
- ✅ Use when: Developing new features
- ✅ Access: Web interface

### Performance Middleware + Logs (Aggregate, Historical)
- ✅ Best for: Production monitoring
- ✅ See: Trends over time
- ✅ Use when: Analyzing patterns
- ✅ Access: Command line

**Use both together** for maximum insight!

---

## 🎓 Learning Exercise

Try this to see Telescope in action:

1. **Access Telescope**: `http://localhost:8000/telescope`

2. **Make API Requests**:
   ```bash
   # Test reading progress
   curl -H "Authorization: Bearer YOUR_TOKEN" \
        http://localhost:8000/api/reading-progress/user
   
   # Test library
   curl -H "Authorization: Bearer YOUR_TOKEN" \
        http://localhost:8000/api/library
   ```

3. **Watch Telescope Update**:
   - Requests appear in real-time
   - Click each request
   - Examine the queries
   - See our optimizations in action!

4. **Compare Endpoints**:
   - Old endpoints: Many queries
   - Optimized endpoints: Few queries
   - See the difference visually!

---

## 🎯 Next Steps

1. ✅ **Telescope is ready to use**
2. 🚀 **Access it**: `http://localhost:8000/telescope`
3. 🔍 **Make API requests** and watch them appear
4. 📊 **Analyze query counts** for each endpoint
5. 🎉 **Marvel at your optimized queries!**

---

**Pro Tip**: Keep Telescope open in a separate browser tab while developing. It's like having X-ray vision for your application! 🔭✨

---

## 📖 Official Documentation

For more advanced features:
https://laravel.com/docs/11.x/telescope

---

**Telescope is now your performance monitoring superpower!** 🦸‍♂️
