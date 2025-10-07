# 📊 Performance Monitoring with Telescope

## Quick Start

### Access Telescope Dashboard
```
http://localhost:8000/telescope
```

### What You Can Monitor

1. **Requests** - Every API call with response time, status, and memory usage
2. **Queries** - All database queries with execution times (slow queries highlighted)
3. **Exceptions** - Errors and stack traces
4. **Logs** - Application logs
5. **Models** - Eloquent operations
6. **Cache** - Cache hits/misses
7. **Jobs** - Queue job execution

---

## 🎯 How to Use

### 1. Monitor an Endpoint

**Step 1:** Open Telescope
```
http://localhost:8000/telescope
```

**Step 2:** Make an API request
```bash
curl http://localhost:8000/api/novels
```

**Step 3:** Click the request in Telescope to see:
- ✅ Response time
- ✅ Query count
- ✅ Memory usage
- ✅ All SQL queries executed

### 2. Identify Performance Issues

**Look for:**
- 🔴 Red badges = Slow requests (>500ms)
- ⚠️ High query count (>10 queries)
- 🐌 Slow individual queries (>50ms)
- 🔁 Repeated similar queries (N+1 problem)

**Example of N+1 Problem:**
```
GET /api/reading-progress/user
├── Query 1: SELECT * FROM reading_progress WHERE user_id = ?
├── Query 2: SELECT COUNT(*) FROM chapters WHERE novel_id = 1
├── Query 3: SELECT COUNT(*) FROM chapters WHERE novel_id = 2
└── ... (repeated for each novel)
```

**After Optimization:**
```
GET /api/reading-progress/user
└── Query 1: SELECT * FROM reading_progress WITH novel.total_chapters
```

---

## 📈 Performance Targets

| Metric | Excellent | Good | Needs Work |
|--------|-----------|------|------------|
| Response Time | <200ms | 200-500ms | >500ms |
| Query Count | <10 | 10-15 | >15 |
| Memory | <32MB | 32-64MB | >64MB |

---

## 🔍 Telescope Features

### Requests Tab (Most Important)
- See all API requests in real-time
- Click any request for full details
- View request/response data
- See execution timeline

### Queries Tab (Performance Critical)
- All database queries
- Execution time for each query
- Slow queries highlighted in red (>50ms)
- Bindings and connection info

### Exceptions Tab
- All errors and exceptions
- Full stack trace
- Request context when error occurred

### Models Tab
- Eloquent model events (created, updated, deleted)
- Model hydrations
- Track data changes

---

## 🎓 Example Workflow

### Scenario: Optimize a Slow Endpoint

1. **Identify Issue**
   - Open Telescope → Requests tab
   - Find slow request (red badge)
   - Click to see details

2. **Analyze Queries**
   - Check query count (should be <10)
   - Look for duplicate queries
   - Check individual query times

3. **Fix Code**
   - Use eager loading for relationships
   - Replace multiple COUNTs with GROUP BY
   - Cache frequently accessed data

4. **Verify Fix**
   - Make request again
   - Check Telescope
   - Confirm reduced queries and time

---

## ⚙️ Configuration

### Optimizations Already Applied

✅ **Slow Query Threshold**: 50ms (queries >50ms highlighted)
✅ **Only API Routes**: Telescope only monitors `/api/*` routes
✅ **Access Control**: Open in local, admin-only in production

### Environment Variables
```env
TELESCOPE_ENABLED=true
TELESCOPE_DRIVER=database
```

---

## 🧹 Maintenance

### Clear Old Data
```bash
# Clear all Telescope data
php artisan telescope:clear

# Prune entries older than 24 hours
php artisan telescope:prune --hours=24
```

### Disable Telescope Temporarily
```env
# In .env
TELESCOPE_ENABLED=false
```

---

## 📚 See Our Optimizations

Check how we reduced queries by 70-80%:

- **PERFORMANCE_SUMMARY.md** - Complete optimization results
- **PERFORMANCE_FIXES.md** - Technical details
- **REMAINING_PERFORMANCE_ISSUES.md** - Analysis (all resolved!)

---

## 🎯 Daily Monitoring Routine

**Morning Check:**
1. Open Telescope
2. Review Requests tab for red flags
3. Check Exceptions tab for errors

**After Code Changes:**
1. Test affected endpoints
2. Check query count in Telescope
3. Verify no new slow queries

**Before Deployment:**
1. Review all endpoints
2. Ensure no >15 query endpoints
3. Check for exceptions

---

## 🚀 Pro Tips

1. **Keep Telescope Open** in a separate browser tab while developing
2. **Filter by Endpoint** - Type endpoint name in search
3. **Compare Before/After** - Track improvements over time
4. **Watch for Patterns** - Repeated slow queries indicate optimization opportunities

---

**Telescope is your X-ray vision for the API!** 🔭✨

For detailed usage guide: **TELESCOPE_GUIDE.md**
