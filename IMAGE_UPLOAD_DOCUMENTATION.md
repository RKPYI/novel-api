# Image Upload Feature Documentation

## Overview

The image upload feature provides a secure and optimized way to handle image uploads for novel covers and user profile avatars. Images are automatically processed, resized, and optimized before storage.

## Features

- **Automatic Image Processing**: Images are automatically resized and optimized
- **Novel Cover Images**: Stored in organized directories by novel slug
- **User Avatars**: Stored with unique hashes to prevent duplicates
- **Old Image Cleanup**: Automatically deletes old images when new ones are uploaded
- **Format Standardization**: All images are converted to PNG format
- **Security**: File type and size validation
- **Memory Efficient**: Uses PHP GD library for image processing

## Storage Structure

```
storage/app/public/
├── novels/
│   └── {novel-slug}/
│       └── cover_img.png
└── profiles/
    └── {unique-hash}.png
```

## API Endpoints

### Novel Cover Upload

**Endpoint:** `POST /api/novels/{slug}/cover`
**Authentication:** Required (Author role)
**Content-Type:** `multipart/form-data`

#### Request

```bash
curl -X POST https://api.example.com/api/novels/my-novel-slug/cover \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "cover=@/path/to/image.jpg"
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| cover | File | Yes | Image file (JPEG, PNG, GIF, WebP) |

#### Response (Success - 200)

```json
{
  "message": "Cover image uploaded successfully",
  "cover_url": "/storage/novels/my-novel-slug/cover_img.png",
  "novel": {
    "id": 1,
    "title": "My Novel",
    "slug": "my-novel-slug",
    "cover_image": "/storage/novels/my-novel-slug/cover_img.png",
    ...
  }
}
```

#### Error Responses

**404 - Novel Not Found**
```json
{
  "message": "Novel not found"
}
```

**403 - Forbidden**
```json
{
  "message": "You can only upload covers for your own novels"
}
```

**422 - Validation Error**
```json
{
  "message": "The cover field must be an image.",
  "errors": {
    "cover": ["The cover field must be an image."]
  }
}
```

**500 - Upload Failed**
```json
{
  "message": "Failed to upload cover image",
  "error": "Image processing failed: ..."
}
```

---

### Novel Cover Delete

**Endpoint:** `DELETE /api/novels/{slug}/cover`
**Authentication:** Required (Author role)

#### Request

```bash
curl -X DELETE https://api.example.com/api/novels/my-novel-slug/cover \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Response (Success - 200)

```json
{
  "message": "Cover image deleted successfully",
  "novel": {
    "id": 1,
    "title": "My Novel",
    "slug": "my-novel-slug",
    "cover_image": null,
    ...
  }
}
```

---

### User Avatar Upload

**Endpoint:** `POST /api/user/avatar`
**Authentication:** Required
**Content-Type:** `multipart/form-data`

#### Request

```bash
curl -X POST https://api.example.com/api/user/avatar \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "avatar=@/path/to/image.jpg"
```

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| avatar | File | Yes | Image file (JPEG, PNG, GIF, WebP) |

#### Response (Success - 200)

```json
{
  "message": "Avatar uploaded successfully",
  "avatar_url": "/storage/profiles/a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6.png",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "avatar": "/storage/profiles/a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6.png",
    "bio": "Author bio"
  }
}
```

---

### User Avatar Delete

**Endpoint:** `DELETE /api/user/avatar`
**Authentication:** Required

#### Request

```bash
curl -X DELETE https://api.example.com/api/user/avatar \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Response (Success - 200)

```json
{
  "message": "Avatar deleted successfully",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "avatar": null,
    "bio": "Author bio"
  }
}
```

---

## Image Upload Helper

### Methods

#### `uploadNovelCover(UploadedFile $file, string $novelSlug, ?string $oldCoverPath = null): string`

Upload and process a novel cover image.

**Parameters:**
- `$file`: The uploaded image file
- `$novelSlug`: The novel's slug (used for directory naming)
- `$oldCoverPath`: Optional path to old cover to delete

**Returns:** URL path to the uploaded image

**Example:**
```php
$coverUrl = ImageUploadHelper::uploadNovelCover(
    $request->file('cover'),
    'my-novel-slug',
    '/storage/novels/my-novel-slug/cover_img.png'
);
// Returns: "/storage/novels/my-novel-slug/cover_img.png"
```

---

#### `uploadUserAvatar(UploadedFile $file, int $userId, ?string $oldAvatarPath = null): string`

Upload and process a user profile image.

**Parameters:**
- `$file`: The uploaded image file
- `$userId`: The user's ID (used for unique hash generation)
- `$oldAvatarPath`: Optional path to old avatar to delete

**Returns:** URL path to the uploaded image

**Example:**
```php
$avatarUrl = ImageUploadHelper::uploadUserAvatar(
    $request->file('avatar'),
    $user->id,
    $user->avatar
);
// Returns: "/storage/profiles/a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6.png"
```

---

#### `deleteNovelCover(string $novelSlug): bool`

Delete a novel's cover image.

**Parameters:**
- `$novelSlug`: The novel's slug

**Returns:** `true` if successful

---

#### `deleteUserAvatar(string $avatarPath): bool`

Delete a user's avatar image.

**Parameters:**
- `$avatarPath`: The avatar URL path

**Returns:** `true` if successful (returns `false` for external URLs)

---

#### `deleteNovelDirectory(string $novelSlug): bool`

Delete an entire novel directory (used when novel is deleted).

**Parameters:**
- `$novelSlug`: The novel's slug

**Returns:** `true` if successful

---

#### `getNovelCoverUrl(string $novelSlug): ?string`

Get the public URL for a novel cover.

**Parameters:**
- `$novelSlug`: The novel's slug

**Returns:** URL path or `null` if not exists

---

#### `novelHasCover(string $novelSlug): bool`

Check if a novel has a cover image.

**Parameters:**
- `$novelSlug`: The novel's slug

**Returns:** `true` if cover exists

---

## Image Processing Details

### Novel Covers

- **Max Dimensions:** 800x1200 pixels (2:3 ratio)
- **Format:** PNG
- **Compression:** Level 9 (maximum)
- **Aspect Ratio:** Maintained during resize
- **Processing:** Automatic resize to fit within max dimensions

### User Avatars

- **Dimensions:** 400x400 pixels (square)
- **Format:** PNG
- **Compression:** Level 9 (maximum)
- **Aspect Ratio:** Cropped to square (centered)
- **Processing:** Automatic crop to exact dimensions

---

## Validation Rules

### File Type
- Allowed MIME types: `image/jpeg`, `image/png`, `image/jpg`, `image/gif`, `image/webp`

### File Size
- Maximum: 5MB (5,120KB)

### Image Dimensions
- Minimum: 100x100 pixels
- Maximum: 5000x5000 pixels

---

## Storage Configuration

The images are stored using Laravel's public disk. Make sure the storage is linked:

```bash
php artisan storage:link
```

This creates a symbolic link from `public/storage` to `storage/app/public`.

### Filesystem Configuration

In `config/filesystems.php`:

```php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
],
```

---

## Frontend Integration Examples

### Upload Novel Cover (JavaScript)

```javascript
async function uploadNovelCover(novelSlug, file) {
  const formData = new FormData();
  formData.append('cover', file);

  const response = await fetch(`/api/novels/${novelSlug}/cover`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
    },
    body: formData,
  });

  const data = await response.json();
  return data;
}

// Usage
const fileInput = document.getElementById('cover-input');
const file = fileInput.files[0];
const result = await uploadNovelCover('my-novel-slug', file);
console.log('Cover URL:', result.cover_url);
```

### Upload User Avatar (React)

```jsx
import { useState } from 'react';

function AvatarUpload() {
  const [avatar, setAvatar] = useState(null);
  const [uploading, setUploading] = useState(false);

  const handleUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('avatar', file);

    setUploading(true);
    try {
      const response = await fetch('/api/user/avatar', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
        },
        body: formData,
      });

      const data = await response.json();
      setAvatar(data.avatar_url);
      alert('Avatar uploaded successfully!');
    } catch (error) {
      console.error('Upload failed:', error);
      alert('Failed to upload avatar');
    } finally {
      setUploading(false);
    }
  };

  return (
    <div>
      <input
        type="file"
        accept="image/*"
        onChange={handleUpload}
        disabled={uploading}
      />
      {avatar && <img src={avatar} alt="Avatar" />}
    </div>
  );
}
```

### Upload with Preview (Vanilla JS)

```html
<input type="file" id="image-upload" accept="image/*">
<img id="preview" style="max-width: 300px; display: none;">
<button id="upload-btn" disabled>Upload</button>

<script>
const imageInput = document.getElementById('image-upload');
const preview = document.getElementById('preview');
const uploadBtn = document.getElementById('upload-btn');

imageInput.addEventListener('change', (e) => {
  const file = e.target.files[0];
  if (file) {
    // Show preview
    const reader = new FileReader();
    reader.onload = (e) => {
      preview.src = e.target.result;
      preview.style.display = 'block';
      uploadBtn.disabled = false;
    };
    reader.readAsDataURL(file);
  }
});

uploadBtn.addEventListener('click', async () => {
  const file = imageInput.files[0];
  const formData = new FormData();
  formData.append('avatar', file);

  try {
    const response = await fetch('/api/user/avatar', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
      body: formData,
    });

    const data = await response.json();
    if (response.ok) {
      alert('Uploaded successfully!');
      preview.src = data.avatar_url;
    } else {
      alert('Upload failed: ' + data.message);
    }
  } catch (error) {
    alert('Upload failed: ' + error.message);
  }
});
</script>
```

---

## Error Handling

### Common Errors

1. **Invalid file type**
   - Error: "Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed."
   - Solution: Ensure file is an image with correct MIME type

2. **File too large**
   - Error: "File size exceeds maximum allowed size of 5MB."
   - Solution: Compress or resize image before upload

3. **Image too small**
   - Error: "Image dimensions too small. Minimum size is 100x100 pixels."
   - Solution: Use a larger image

4. **Image processing failed**
   - Error: "Image processing failed: ..."
   - Solution: Check if image is corrupted; try a different image

---

## Security Considerations

1. **File Type Validation**: Only image MIME types are accepted
2. **Size Limits**: Maximum 5MB to prevent abuse
3. **Dimension Limits**: Prevents extremely large images
4. **Authorization**: Users can only upload for their own resources
5. **Path Traversal Prevention**: Uses slug/hash for filenames
6. **Automatic Processing**: All images are re-encoded to prevent malicious code

---

## Performance Tips

1. **Client-side Validation**: Validate file size and type on client before upload
2. **Image Compression**: Compress images on client before upload for faster uploads
3. **Progress Indicators**: Show upload progress for better UX
4. **Lazy Loading**: Use lazy loading for image display
5. **CDN**: Consider using a CDN for serving static images in production

---

## Cleanup and Maintenance

### Delete Old Avatars

When a user updates their avatar, the old one is automatically deleted. External URLs (e.g., from Google OAuth) are never deleted.

### Delete Novel Directories

When a novel is deleted, the entire directory is removed:

```php
// Automatically called in NovelController@destroy
ImageUploadHelper::deleteNovelDirectory($novel->slug);
```

### Manual Cleanup

To manually clean up orphaned images:

```php
// Get all novel slugs from database
$novelSlugs = Novel::pluck('slug')->toArray();

// Get all directories in storage/app/public/novels
$directories = Storage::disk('public')->directories('novels');

// Delete directories not in database
foreach ($directories as $dir) {
    $slug = basename($dir);
    if (!in_array($slug, $novelSlugs)) {
        Storage::disk('public')->deleteDirectory($dir);
    }
}
```

---

## Testing

### Test Upload Endpoint

```bash
# Upload novel cover
curl -X POST http://localhost:8000/api/novels/test-novel/cover \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "cover=@test-image.jpg"

# Upload user avatar
curl -X POST http://localhost:8000/api/user/avatar \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "avatar=@avatar.png"

# Delete novel cover
curl -X DELETE http://localhost:8000/api/novels/test-novel/cover \
  -H "Authorization: Bearer YOUR_TOKEN"

# Delete user avatar
curl -X DELETE http://localhost:8000/api/user/avatar \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Troubleshooting

### Images not accessible

**Problem:** Uploaded images return 404

**Solution:**
```bash
# Create storage link
php artisan storage:link

# Check permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Upload fails with "Failed to create image resource"

**Problem:** Image file is corrupted or invalid

**Solution:** Try a different image file

### Memory exhausted error

**Problem:** Image is too large for server memory

**Solution:**
- Increase PHP memory limit in `php.ini`
- Or reduce image size before upload

### Permission denied error

**Problem:** Web server cannot write to storage directory

**Solution:**
```bash
# Set correct ownership (adjust www-data to your web server user)
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

---

## Future Enhancements

Potential improvements for future versions:

1. **WebP Support**: Convert all images to WebP for better compression
2. **Multiple Sizes**: Generate multiple sizes (thumbnail, medium, large)
3. **Cloud Storage**: Support for S3, CloudFront, etc.
4. **Image Optimization**: Use third-party services like TinyPNG
5. **Batch Upload**: Allow multiple images at once
6. **Image Cropping**: Client-side crop tool before upload
7. **Format Conversion**: Allow users to choose output format
8. **Watermarking**: Add watermarks to uploaded images
