# Genre Management — Admin API

> Endpoints for managing genres (create, read, update, delete).
> All endpoints require `Authorization: Bearer {token}` and the user must have `role = admin`.

---

## Base URL

```
/api/admin/genres
```

---

## Endpoints

### 1. `GET /api/admin/genres`

List all genres with novel counts, ordered alphabetically.

**Response (200):**
```json
{
  "message": "All genres",
  "genres": [
    {
      "id": 1,
      "name": "Fantasy",
      "slug": "fantasy",
      "description": "Stories featuring magical or supernatural elements in a fictional universe.",
      "color": "#dc2626",
      "created_at": "2026-01-01T00:00:00.000000Z",
      "updated_at": "2026-01-01T00:00:00.000000Z",
      "novels_count": 12
    }
  ]
}
```

| Field          | Type    | Description                    |
|----------------|---------|--------------------------------|
| `id`           | int     | Genre ID                       |
| `name`         | string  | Display name                   |
| `slug`         | string  | URL-friendly slug              |
| `description`  | string  | Genre description (nullable)   |
| `color`        | string  | Hex color code (e.g. `#dc2626`)|
| `novels_count` | int     | Number of novels in this genre |

---

### 2. `POST /api/admin/genres`

Create a new genre.

**Request:**
```json
{
  "name": "Cyberpunk",
  "description": "Stories set in a high-tech, dystopian future.",
  "color": "#8b5cf6"
}
```

| Field         | Type   | Required | Validation                            |
|---------------|--------|----------|---------------------------------------|
| `name`        | string | ✅       | Max 255 chars, must be unique         |
| `description` | string | ❌       | Max 1000 chars                        |
| `color`       | string | ❌       | Hex color (`#RRGGBB`), default `#dc2626` |

**Success (201):**
```json
{
  "message": "Genre created successfully",
  "genre": {
    "id": 23,
    "name": "Cyberpunk",
    "slug": "cyberpunk",
    "description": "Stories set in a high-tech, dystopian future.",
    "color": "#8b5cf6",
    "created_at": "2026-03-05T09:50:00.000000Z",
    "updated_at": "2026-03-05T09:50:00.000000Z",
    "novels_count": 0
  }
}
```

**Validation error (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name has already been taken."]
  }
}
```

---

### 3. `GET /api/admin/genres/{genre}`

Get details of a single genre.

**Response (200):**
```json
{
  "message": "Genre details",
  "genre": {
    "id": 1,
    "name": "Fantasy",
    "slug": "fantasy",
    "description": "Stories featuring magical or supernatural elements.",
    "color": "#dc2626",
    "created_at": "2026-01-01T00:00:00.000000Z",
    "updated_at": "2026-01-01T00:00:00.000000Z",
    "novels_count": 12
  }
}
```

**Not found (404):**
```json
{
  "message": "No query results for model [App\\Models\\Genre] 999"
}
```

---

### 4. `PUT /api/admin/genres/{genre}`

Update an existing genre. Only include the fields you want to change.

**Request:**
```json
{
  "name": "Dark Fantasy",
  "description": "A subgenre combining fantasy with darker themes.",
  "color": "#991b1b"
}
```

| Field         | Type   | Required | Validation                            |
|---------------|--------|----------|---------------------------------------|
| `name`        | string | ❌       | Max 255 chars, unique (excluding self)|
| `description` | string | ❌       | Max 1000 chars                        |
| `color`       | string | ❌       | Hex color (`#RRGGBB`)                 |

> [!NOTE]
> The `slug` is automatically regenerated when the `name` changes.

**Success (200):**
```json
{
  "message": "Genre updated successfully",
  "genre": {
    "id": 1,
    "name": "Dark Fantasy",
    "slug": "dark-fantasy",
    "description": "A subgenre combining fantasy with darker themes.",
    "color": "#991b1b",
    "created_at": "2026-01-01T00:00:00.000000Z",
    "updated_at": "2026-03-05T10:00:00.000000Z",
    "novels_count": 12
  }
}
```

---

### 5. `DELETE /api/admin/genres/{genre}`

Delete a genre. **Blocked if the genre has novels attached.**

**Success (200):**
```json
{
  "message": "Genre 'Cyberpunk' deleted successfully"
}
```

**Has novels (409):**
```json
{
  "message": "Cannot delete genre 'Fantasy' because it has novels attached. Remove the novels from this genre first.",
  "novels_count": 12
}
```

---

## Notes

- All genre mutations (create, update, delete) automatically invalidate the `genres_all` cache, so the public `GET /api/novels/genres` endpoint will reflect changes immediately.
- The `slug` field is auto-generated from the `name` and cannot be set manually.
- The `color` field defaults to `#dc2626` (red) if not provided on creation.
