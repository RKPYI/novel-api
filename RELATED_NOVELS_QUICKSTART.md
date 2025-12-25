# Related Novels Endpoint - Quick Reference

## ✅ Implementation Complete

### Endpoint
```
GET /api/novels/{slug}/related
```

### Example Request
```bash
# Get related novels for a specific novel
curl http://your-api-url/api/novels/harry-potter/related
```

### Response
Returns up to 6 most similar novels based on:
- ⭐ **Genre matching** (most important)
- 👤 **Same author**
- 📊 **Similar rating**
- 🔥 **Similar popularity**
- ✓ **Same status** (completed/ongoing)

Each novel includes a `similarity_score` (0-100) showing how similar it is.

### Frontend Integration Example

```javascript
// React/Next.js example
async function getRelatedNovels(slug) {
  const response = await fetch(`/api/novels/${slug}/related`);
  const { data } = await response.json();
  
  // data is an array of related novels
  // sorted by similarity_score (highest first)
  return data;
}

// Use in component
function RelatedNovelsSection({ novelSlug }) {
  const [related, setRelated] = useState([]);
  
  useEffect(() => {
    getRelatedNovels(novelSlug).then(setRelated);
  }, [novelSlug]);
  
  return (
    <div className="related-novels">
      <h2>You Might Also Like</h2>
      {related.map(novel => (
        <NovelCard 
          key={novel.id} 
          novel={novel}
          similarityScore={novel.similarity_score}
        />
      ))}
    </div>
  );
}
```

### Response Example
```json
{
  "message": "Related novels retrieved successfully",
  "data": [
    {
      "id": 2,
      "title": "Lord of the Rings",
      "slug": "lord-of-the-rings",
      "author": "J.R.R. Tolkien",
      "cover_image": "https://...",
      "status": "completed",
      "rating": 4.8,
      "views": 50000,
      "similarity_score": 72.5,
      "genres": [
        { "id": 1, "name": "Fantasy", "slug": "fantasy" },
        { "id": 3, "name": "Adventure", "slug": "adventure" }
      ]
    }
    // ... up to 5 more novels
  ],
  "current_novel": {
    "id": 1,
    "title": "Harry Potter",
    "slug": "harry-potter"
  },
  "algorithm_used": "hybrid_similarity"
}
```

### Performance
- ✅ **Cached for 30 minutes** - Fast subsequent requests
- ✅ **Optimized queries** - No N+1 query problems
- ✅ **Limited to 6 results** - Fast response times

### Where to Use
1. Novel detail page - "Similar novels" section
2. After finishing a chapter - "Read next" suggestions
3. Bottom of novel description
4. Mobile app "Discover" tab
5. Email recommendations

### Algorithm Details
See `RELATED_NOVELS_ALGORITHM.md` for complete documentation on how the similarity scoring works.
