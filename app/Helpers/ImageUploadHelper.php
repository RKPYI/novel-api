<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadHelper
{
    /**
     * Upload and process a novel cover image
     * Saves to: /novels/:novel-slug/cover_img.webp
     *
     * @param UploadedFile $file
     * @param string $novelSlug
     * @param string|null $oldCoverPath Optional path to old cover to delete
     * @return string URL path to the uploaded image
     */
    public static function uploadNovelCover(UploadedFile $file, string $novelSlug, ?string $oldCoverPath = null): string
    {
        // Validate image
        self::validateImage($file);

        // Define directory and filename
        $directory = "novels/{$novelSlug}";
        $filename = 'cover_img.webp';
        $fullPath = "{$directory}/{$filename}";

        // Delete old cover if exists (support both .png and .webp extensions)
        if ($oldCoverPath && Storage::disk('public')->exists($oldCoverPath)) {
            Storage::disk('public')->delete($oldCoverPath);
        }

        // Also delete old PNG version if it exists (for migration)
        $oldPngPath = "novels/{$novelSlug}/cover_img.png";
        if (Storage::disk('public')->exists($oldPngPath)) {
            Storage::disk('public')->delete($oldPngPath);
        }

        // Process and optimize image with auto-compression
        $processedImage = self::processImage($file, 800, 1200); // 2:3 ratio for book covers

        // Store the processed image
        Storage::disk('public')->put(
            $fullPath,
            $processedImage,
            'public'
        );

        // Return the public URL path
        return "/storage/{$fullPath}";
    }

    /**
     * Upload and process a user profile image
     * Saves to: /profiles/:unique-hash.webp
     *
     * @param UploadedFile $file
     * @param int $userId
     * @param string|null $oldAvatarPath Optional path to old avatar to delete
     * @return string URL path to the uploaded image
     */
    public static function uploadUserAvatar(UploadedFile $file, int $userId, ?string $oldAvatarPath = null): string
    {
        // Validate image
        self::validateImage($file);

        // Generate unique filename using user ID and timestamp
        $hash = hash('sha256', $userId . now()->timestamp . Str::random(16));
        $directory = 'profiles';
        $filename = "{$hash}.webp";
        $fullPath = "{$directory}/{$filename}";

        // Delete old avatar if exists and is a local file (not external URL)
        if ($oldAvatarPath && !filter_var($oldAvatarPath, FILTER_VALIDATE_URL)) {
            $oldPath = str_replace('/storage/', '', $oldAvatarPath);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Process and optimize image (square crop for avatars) with auto-compression
        $processedImage = self::processImage($file, 400, 400, true);

        // Store the processed image
        Storage::disk('public')->put(
            $fullPath,
            $processedImage,
            'public'
        );

        // Return the public URL path
        return "/storage/{$fullPath}";
    }

    /**
     * Process and optimize an image with auto-compression
     * Converts all images to WebP format for maximum compression
     *
     * @param UploadedFile $file
     * @param int $maxWidth
     * @param int $maxHeight
     * @param bool $crop Whether to crop to exact dimensions (for avatars)
     * @return string Processed image as binary string
     */
    protected static function processImage(UploadedFile $file, int $maxWidth, int $maxHeight, bool $crop = false): string
    {
        // Read the image
        $imageData = file_get_contents($file->getPathname());

        // Create image resource based on type
        $image = imagecreatefromstring($imageData);

        if ($image === false) {
            throw new \Exception("Failed to create image resource");
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        if ($crop) {
            // Crop to exact dimensions (for square avatars)
            $sourceSize = min($originalWidth, $originalHeight);
            $sourceX = ($originalWidth - $sourceSize) / 2;
            $sourceY = ($originalHeight - $sourceSize) / 2;

            $newImage = imagecreatetruecolor($maxWidth, $maxHeight);

            // Preserve transparency for WebP
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);

            imagecopyresampled(
                $newImage, $image,
                0, 0, (int)$sourceX, (int)$sourceY,
                $maxWidth, $maxHeight, (int)$sourceSize, (int)$sourceSize
            );
        } else {
            // Resize maintaining aspect ratio (for covers)
            $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            $newWidth = (int)($originalWidth * $ratio);
            $newHeight = (int)($originalHeight * $ratio);

            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for WebP
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);

            imagecopyresampled(
                $newImage, $image,
                0, 0, 0, 0,
                $newWidth, $newHeight, $originalWidth, $originalHeight
            );
        }

        // Determine quality based on original file size for auto-compression
        $originalSize = $file->getSize();
        $quality = self::calculateCompressionQuality($originalSize);

        // Output to WebP format with auto-compression
        ob_start();
        imagewebp($newImage, null, $quality);
        $processedImageData = ob_get_clean();

        // Free memory
        imagedestroy($image);
        imagedestroy($newImage);

        return $processedImageData;
    }

    /**
     * Calculate optimal compression quality based on file size
     * Larger files get more aggressive compression
     *
     * @param int $fileSize File size in bytes
     * @return int Quality value (0-100)
     */
    protected static function calculateCompressionQuality(int $fileSize): int
    {
        $sizeMB = $fileSize / (1024 * 1024);

        if ($sizeMB < 0.5) {
            return 90; // Small files: high quality
        } elseif ($sizeMB < 1) {
            return 85; // Medium files: good quality
        } elseif ($sizeMB < 3) {
            return 80; // Larger files: balanced
        } elseif ($sizeMB < 5) {
            return 75; // Big files: more compression
        } elseif ($sizeMB < 10) {
            return 70; // Very big files: aggressive compression
        } else {
            return 65; // Huge files: maximum compression
        }
    }

    /**
     * Validate uploaded image file
     * Now more lenient with file sizes since we auto-compress
     *
     * @param UploadedFile $file
     * @throws \Exception
     */
    protected static function validateImage(UploadedFile $file): void
    {
        // Validate MIME type - now supports JPEG, JPG, PNG, GIF, and WebP
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('Invalid file type. Only JPEG, JPG, PNG, GIF, and WebP images are allowed.');
        }

        // More lenient file size validation (max 20MB, since we'll compress it)
        $maxSize = 20 * 1024 * 1024; // 20MB in bytes
        if ($file->getSize() > $maxSize) {
            throw new \Exception('File size exceeds maximum allowed size of 20MB.');
        }

        // Validate image dimensions
        $imageInfo = getimagesize($file->getPathname());
        if (!$imageInfo) {
            throw new \Exception('Invalid image file.');
        }

        [$width, $height] = $imageInfo;
        if ($width < 100 || $height < 100) {
            throw new \Exception('Image dimensions too small. Minimum size is 100x100 pixels.');
        }

        if ($width > 10000 || $height > 10000) {
            throw new \Exception('Image dimensions too large. Maximum size is 10000x10000 pixels.');
        }
    }

    /**
     * Delete a novel cover image (supports both .webp and legacy .png)
     *
     * @param string $novelSlug
     * @return bool
     */
    public static function deleteNovelCover(string $novelSlug): bool
    {
        $deleted = false;

        // Delete WebP version
        $pathWebp = "novels/{$novelSlug}/cover_img.webp";
        if (Storage::disk('public')->exists($pathWebp)) {
            $deleted = Storage::disk('public')->delete($pathWebp) || $deleted;
        }

        // Delete PNG version (legacy support)
        $pathPng = "novels/{$novelSlug}/cover_img.png";
        if (Storage::disk('public')->exists($pathPng)) {
            $deleted = Storage::disk('public')->delete($pathPng) || $deleted;
        }

        return $deleted ?: true;
    }

    /**
     * Delete a user avatar image
     *
     * @param string $avatarPath The URL path like /storage/profiles/hash.webp
     * @return bool
     */
    public static function deleteUserAvatar(string $avatarPath): bool
    {
        // Don't delete external URLs
        if (filter_var($avatarPath, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Remove /storage/ prefix to get actual storage path
        $path = str_replace('/storage/', '', $avatarPath);

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return true;
    }

    /**
     * Delete an entire novel directory (when novel is deleted)
     *
     * @param string $novelSlug
     * @return bool
     */
    public static function deleteNovelDirectory(string $novelSlug): bool
    {
        $directory = "novels/{$novelSlug}";

        if (Storage::disk('public')->exists($directory)) {
            return Storage::disk('public')->deleteDirectory($directory);
        }

        return true;
    }

    /**
     * Get the public URL for a novel cover (checks both .webp and .png)
     *
     * @param string $novelSlug
     * @return string|null
     */
    public static function getNovelCoverUrl(string $novelSlug): ?string
    {
        // Check for WebP version first (preferred)
        $pathWebp = "novels/{$novelSlug}/cover_img.webp";
        if (Storage::disk('public')->exists($pathWebp)) {
            return "/storage/{$pathWebp}";
        }

        // Fallback to PNG version (legacy)
        $pathPng = "novels/{$novelSlug}/cover_img.png";
        if (Storage::disk('public')->exists($pathPng)) {
            return "/storage/{$pathPng}";
        }

        return null;
    }

    /**
     * Check if a novel has a cover image (checks both .webp and .png)
     *
     * @param string $novelSlug
     * @return bool
     */
    public static function novelHasCover(string $novelSlug): bool
    {
        $pathWebp = "novels/{$novelSlug}/cover_img.webp";
        $pathPng = "novels/{$novelSlug}/cover_img.png";
        return Storage::disk('public')->exists($pathWebp) || Storage::disk('public')->exists($pathPng);
    }
}
