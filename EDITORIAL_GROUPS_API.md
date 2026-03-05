# Editorial Groups API Documentation

## Overview

The editorial group system allows an **Admin** to create groups of users, each containing **one Editor** and **multiple Authors**. When an author in a group publishes a chapter, it goes into a **pending review** state. The group's editor reviews and approves/rejects chapters. On approval, the group's tag is automatically applied as a genre to the novel.

> Authors **not** in any group publish chapters immediately (no review required).

---

## User Roles

| Role | Value | Description |
|------|-------|-------------|
| `USER` | `0` | Regular reader |
| `AUTHOR` | `1` | Can create novels and chapters |
| `EDITOR` | `2` | Can review chapters from authors in their group |
| `ADMIN` | `3` | Full access |

---

## Usernames

Every user has a unique, permanent `username` field. Usernames are:
- Auto-generated from the user's display name on registration (e.g. "John Doe" → `johndoe`)
- Unique — collisions are resolved by appending a number (`johndoe1`, `johndoe2`, etc.)
- Used as identifiers in editorial group member management (instead of user IDs)
- Included in all auth responses (`register`, `login`, `me`, `updateProfile`, Google OAuth)

---

## Chapter Review Statuses

| Status | Description |
|--------|-------------|
| `draft` | Not yet submitted |
| `pending` | Awaiting editor review (auto-set for grouped authors) |
| `approved` | Published and visible to readers |
| `rejected` | Rejected by editor, not visible |

> **Important:** Only `approved` chapters are visible in public chapter listings and novel pages. Authors can still see their own pending/rejected chapters.

---

## Admin Endpoints — Editorial Group Management

All endpoints require `Authorization: Bearer {token}` with an **admin** user.

Base URL: `/api/admin/editorial-groups`

---

### List All Groups

```
GET /api/admin/editorial-groups
```

**Response** `200`

```json
{
  "message": "Editorial groups",
  "groups": [
    {
      "id": 1,
      "name": "Indie Fiction",
      "slug": "indie-fiction",
      "tag": "Indie",
      "description": "Group for indie fiction authors",
      "is_active": true,
      "editor": {
        "id": 5,
        "name": "Editor Name",
        "username": "editorname",
        "avatar": "avatars/5.jpg",
        "role": "editor",
        "joined_at": "2026-03-04T00:00:00.000000Z"
      },
      "authors": [
        {
          "id": 10,
          "name": "Author Name",
          "username": "authorname",
          "avatar": null,
          "role": "author",
          "joined_at": "2026-03-04T00:00:00.000000Z"
        }
      ],
      "member_count": 2,
      "created_at": "2026-03-04T00:00:00.000000Z",
      "updated_at": "2026-03-04T00:00:00.000000Z"
    }
  ]
}
```

---

### Create Group

```
POST /api/admin/editorial-groups
```

**Request Body**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | ✅ | Max 255, must be unique |
| `tag` | string | ✅ | Applied as genre on chapter approval |
| `description` | string | ❌ | Max 1000 |

**Response** `201`

```json
{
  "message": "Editorial group created successfully",
  "group": { "..." }
}
```

---

### Show Group

```
GET /api/admin/editorial-groups/{id}
```

**Response** `200` — Same group object format as list.

---

### Update Group

```
PUT /api/admin/editorial-groups/{id}
```

**Request Body** — All fields optional:

| Field | Type | Notes |
|-------|------|-------|
| `name` | string | Slug auto-regenerates on change |
| `tag` | string | |
| `description` | string | |
| `is_active` | boolean | |

**Response** `200`

---

### Delete Group

```
DELETE /api/admin/editorial-groups/{id}
```

> Cascades: all memberships are removed. Member user roles are **not** auto-reset — handle manually if needed.

**Response** `200`

```json
{
  "message": "Editorial group 'Indie Fiction' deleted successfully"
}
```

---

### Add Member to Group

```
POST /api/admin/editorial-groups/{id}/members
```

This endpoint supports two request shapes depending on the `role`.

#### Adding an Editor (single)

**Request Body**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `username` | string | ✅ | Must match an existing user's username |
| `role` | string | ✅ | Must be `"editor"` |

**Response** `201`

```json
{
  "message": "User 'John' added to group 'Indie Fiction' as editor",
  "group": { "..." }
}
```

#### Adding Authors (bulk)

**Request Body**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `usernames` | string[] | ✅ | Array of existing usernames, min 1 |
| `role` | string | ✅ | Must be `"author"` |

**Response** `201`

```json
{
  "message": "2 author(s) added to group 'Indie Fiction': Author One, Author Two",
  "group": { "..." }
}
```

#### Business Rules

- A user can only belong to **one** group
- A group can only have **one** editor
- User's system role is auto-updated (`editor` → role 2, `author` → role 1)
- Author additions are **all-or-nothing** — if any username fails, none are added

#### Error Responses

**409 — User already in a group (editor)**

```json
{
  "message": "This user already belongs to a group: Indie Fiction"
}
```

**409 — Users already in a group (author bulk)**

```json
{
  "message": "Some users already belong to a group.",
  "conflicts": [
    "User 'authorname' already belongs to group: Indie Fiction"
  ]
}
```

**409 — Group already has an editor**

```json
{
  "message": "This group already has an editor. Remove the current editor first."
}
```

**422 — Username not found (editor)**

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "username": ["No user found with username \"nonexistent\"."]
  }
}
```

**422 — Usernames not found (author bulk)**

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "usernames": ["No user found with username \"nonexistent\"."]
  }
}
```

---

### Remove Member from Group

```
DELETE /api/admin/editorial-groups/{id}/members/{username}
```

- User's system role is reset to `0` (regular user)

**Response** `200`

```json
{
  "message": "User 'John' removed from group 'Indie Fiction'",
  "group": { "..." }
}
```

---

## Editor Endpoints — Chapter Review

All endpoints require `Authorization: Bearer {token}` with an **editor** user (role 2).

Base URL: `/api/editor`

---

### Get Pending Reviews

```
GET /api/editor/reviews
```

Returns paginated chapters with `review_status: "pending"` from authors in the editor's group.

**Response** `200`

```json
{
  "message": "Chapters pending review",
  "chapters": {
    "current_page": 1,
    "data": [
      {
        "id": 42,
        "title": "Chapter 5: The Reckoning",
        "chapter_number": 5,
        "content": "...",
        "word_count": 2500,
        "review_status": "pending",
        "created_at": "2026-03-04T00:00:00.000000Z",
        "novel": {
          "id": 7,
          "title": "My Novel",
          "slug": "my-novel",
          "author": "Author Name",
          "user_id": 10,
          "user": {
            "id": 10,
            "name": "Author Name",
            "avatar": null
          }
        }
      }
    ],
    "per_page": 20,
    "total": 3,
    "last_page": 1
  }
}
```

---

### Review a Chapter (Approve / Reject)

```
POST /api/editor/reviews/{chapter_id}
```

**Request Body**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `action` | string | ✅ | `"approve"` or `"reject"` |
| `notes` | string | ❌ | Max 2000 chars, review feedback |

**Business Rules:**
- Chapter's author must be in the editor's group → `403` otherwise
- Chapter must have `review_status: "pending"` → `422` otherwise
- On **approve**: group tag is auto-attached as a genre to the novel

**Response** `200`

```json
{
  "message": "Chapter 'Chapter 5: The Reckoning' has been approved",
  "chapter": {
    "id": 42,
    "title": "Chapter 5: The Reckoning",
    "review_status": "approved",
    "reviewed_at": "2026-03-04T00:00:00.000000Z",
    "review_notes": "Great chapter!",
    "novel": {
      "id": 7,
      "title": "My Novel",
      "slug": "my-novel"
    },
    "reviewer": {
      "id": 5,
      "name": "Editor Name"
    }
  }
}
```

---

### Review History

```
GET /api/editor/reviews/history
```

Returns paginated list of chapters previously reviewed by this editor.

**Response** `200`

```json
{
  "message": "Review history",
  "chapters": {
    "current_page": 1,
    "data": [
      {
        "id": 42,
        "title": "Chapter 5",
        "review_status": "approved",
        "reviewed_at": "2026-03-04T00:00:00.000000Z",
        "review_notes": "Great chapter!",
        "novel": {
          "id": 7,
          "title": "My Novel",
          "slug": "my-novel",
          "author": "Author Name",
          "user": { "id": 10, "name": "Author Name" }
        }
      }
    ],
    "per_page": 20,
    "total": 5
  }
}
```

---

### Get Editor's Group Info

```
GET /api/editor/group
```

**Response** `200`

```json
{
  "message": "Editor group info",
  "group": {
    "id": 1,
    "name": "Indie Fiction",
    "tag": "Indie",
    "description": "Group for indie fiction authors",
    "members": [
      { "id": 5, "name": "Editor Name", "avatar": null, "role": "editor" },
      { "id": 10, "name": "Author One", "avatar": null, "role": "author" },
      { "id": 11, "name": "Author Two", "avatar": null, "role": "author" }
    ],
    "pending_reviews": 3
  }
}
```

---

## Impact on Existing Endpoints

### Auth Responses

All auth endpoints (`register`, `login`, `me`, `updateProfile`, Google OAuth callback) now include the `username` field in the user object:

```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "username": "johndoe",
    "email": "john@example.com",
    "role": 0,
    "..."
  }
}
```

### Chapter Creation (`POST /api/novels/{novel}/chapters`)

No change to the request. The `review_status` is set **automatically**:

- Author **in a group** → `review_status: "pending"` (requires editor review)
- Author **not in a group** → `review_status: "approved"` (published immediately)

### Chapter Listing (`GET /api/novels/{novel}/chapters`)

- Public listing only returns chapters with `review_status: "approved"`
- Authors can still see their own pending/rejected chapters

### Chapter Detail (`GET /api/novels/{novel}/chapters/{chapter}`)

- Non-approved chapters return `404` to public readers
- Novel owner and admins can still access them

---

## Error Reference

| Code | When |
|------|------|
| `401` | Missing or invalid auth token |
| `403` | User lacks required role (admin/editor), or editor trying to review outside their group |
| `404` | Resource not found, or editor not assigned to a group |
| `409` | User already in a group, or group already has an editor |
| `422` | Validation error (e.g. username not found), or chapter is not in `pending` status |
