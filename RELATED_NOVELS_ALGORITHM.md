# Related Novels Algorithm Documentation

## Endpoint
`GET /api/novels/{slug}/related`

## Purpose
Returns a list of novels that are similar to the specified novel, helping users discover content they might enjoy based on their current reading.

## Algorithm: Hybrid Similarity Scoring

The algorithm uses a **weighted scoring system** that combines multiple factors to determine novel similarity. This provides better recommendations than using a single factor alone.

### Scoring Factors

Each related novel receives a score based on the following criteria:

#### 1. Genre Matching (50 points max - Most Important)
- **Weight:** 50% of total score
- **Logic:** Calculates the percentage of matching genres
- **Formula:** `(matching_genres / total_genres_in_current_novel) × 50`
- **Example:**
  - Current novel has genres: [Fantasy, Adventure, Romance]
  - Related novel has genres: [Fantasy, Adventure]
  - Score: (2/3) × 50 = 33.33 points

#### 2. Same Author Bonus (20 points)
- **Weight:** Flat 20 points if authors match
- **Logic:** Readers often enjoy multiple works by the same author
- **Example:** Both novels by "J.K. Rowling" = +20 points

#### 3. Similar Rating (15 points max)
- **Weight:** Up to 15% of total score
- **Logic:** Quality indicator - novels with similar ratings often appeal to the same audience
- **Formula:** `max(0, 15 - (rating_difference × 3))`
- **Example:**
  - Current novel rating: 4.5
  - Related novel rating: 4.2
  - Difference: 0.3
  - Score: 15 - (0.3 × 3) = 14.1 points

#### 4. Similar Popularity (10 points max)
- **Weight:** Up to 10% of total score
- **Logic:** Uses logarithmic scale to compare view counts (prevents popular novels from dominating)
- **Formula:** `max(0, 10 - |log(views1 + 1) - log(views2 + 1)|)`
- **Example:**
  - Current novel: 10,000 views
  - Related novel: 8,000 views
  - Score: ~9.8 points

#### 5. Same Status Bonus (5 points)
- **Weight:** Flat 5 points if status matches
- **Logic:** Readers looking for completed novels prefer similar completed works
- **Statuses:** ongoing, completed, hiatus
- **Example:** Both novels "completed" = +5 points

### Total Score Range
- **Minimum:** 0 points (completely different novel)
- **Maximum:** 100 points (perfect match - same author, all genres match, identical rating, similar popularity, same status)
- **Typical Range:** 30-70 points for good recommendations

## Fallback Strategy

If the current novel has **no genres** assigned:
- Returns 6 most popular novels (by views)
- Excludes the current novel
- Response indicates `algorithm: 'popular_fallback'`

## Response Format

```json
{
  "message": "Related novels retrieved successfully",
  "data": [
    {
      "id": 123,
      "title": "Similar Novel",
      "slug": "similar-novel",
      "author": "Author Name",
      "description": "...",
      "cover_image": "...",
      "status": "ongoing",
      "rating": 4.5,
      "views": 5000,
      "similarity_score": 67.85,
      "genres": [
        {
          "id": 1,
          "name": "Fantasy",
          "slug": "fantasy"
        }
      ],
      "user": {
        "id": 1,
        "name": "Author Name"
      }
    }
    // ... up to 6 novels
  ],
  "current_novel": {
    "id": 1,
    "title": "Current Novel",
    "slug": "current-novel"
  },
  "algorithm_used": "hybrid_similarity"
}
```

## Performance Optimizations

1. **Caching:** Results cached for 30 minutes per novel
   - Cache key: `novel_related_{slug}`
   - Automatic invalidation when novels are updated

2. **Eager Loading:** Loads genres and user relationships to prevent N+1 queries

3. **Limit:** Returns top 6 related novels only

4. **Query Optimization:** Only fetches novels with at least one matching genre

## Use Cases

1. **Novel Detail Page:** Show "You might also like" section
2. **Reading Completion:** Recommend next novels when user finishes
3. **Discovery Widget:** Help users explore similar content
4. **Genre Navigation:** Better than simple genre filtering

## Example Usage

```javascript
// Frontend JavaScript/TypeScript
const response = await fetch('/api/novels/harry-potter-sorcerers-stone/related');
const data = await response.json();

// data.data contains array of related novels with similarity_score
// Higher similarity_score = more similar
```

## Future Enhancements (Optional)

1. **Collaborative Filtering:** "Users who read this also read..."
   - Use UserLibrary data
   - More personalized but more complex

2. **Content-Based Analysis:**
   - Analyze novel descriptions using NLP
   - Match similar themes and keywords

3. **User Preference Learning:**
   - Track user's reading history
   - Personalize weights based on user behavior

4. **Trending Boost:**
   - Give slight boost to currently trending novels
   - Keep recommendations fresh

5. **Diversity Factor:**
   - Ensure variety in recommendations
   - Prevent all results being from same author/genre

## Testing

Test with different novel scenarios:
- Novel with multiple genres
- Novel with single genre
- Novel with no genres
- Popular vs unpopular novels
- Completed vs ongoing novels
- Same author novels
