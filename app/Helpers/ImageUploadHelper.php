<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadHelper
{
    /**
     * Upload and process a novel cover image
     * Saves to: /novels/:novel-slug/cover_img.png
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
        $filename = 'cover_img.png';
        $fullPath = "{$directory}/{$filename}";

        // Delete old cover if exists and is different
        if ($oldCoverPath && Storage::disk('public')->exists($oldCoverPath)) {
            Storage::disk('public')->delete($oldCoverPath);
        }

        // Process and optimize image
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
     * Saves to: /profiles/:unique-hash.png
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
        $filename = "{$hash}.png";
        $fullPath = "{$directory}/{$filename}";

        // Delete old avatar if exists and is a local file (not external URL)
        if ($oldAvatarPath && !filter_var($oldAvatarPath, FILTER_VALIDATE_URL)) {
            $oldPath = str_replace('/storage/', '', $oldAvatarPath);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Process and optimize image (square crop for avatars)
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
     * Process and optimize an image
     *
     * @param UploadedFile $file
     * @param int $maxWidth
     * @param int $maxHeight
     * @param bool $crop Whether to crop to exact dimensions (for avatars)
     * @return string Processed image as binary string
     */
    protected static function processImage(UploadedFile $file, int $maxWidth, int $maxHeight, bool $crop = false): string
    {
        $extension = $file->getClientOriginalExtension();

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

            // Preserve transparency for PNG
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

            // Preserve transparency for PNG
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);

            imagecopyresampled(
                $newImage, $image,
                0, 0, 0, 0,
                $newWidth, $newHeight, $originalWidth, $originalHeight
            );
        }

        // Output to string
        ob_start();
        imagepng($newImage, null, 9); // Max compression
        $processedImageData = ob_get_clean();

        // Free memory
        imagedestroy($image);
        imagedestroy($newImage);

        return $processedImageData;
    }

    /**
     * Validate uploaded image file
     *
     * @param UploadedFile $file
     * @throws \Exception
     */
    protected static function validateImage(UploadedFile $file): void
    {
        // Validate MIME type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
        }

        // Validate file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if ($file->getSize() > $maxSize) {
            throw new \Exception('File size exceeds maximum allowed size of 5MB.');
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

        if ($width > 5000 || $height > 5000) {
            throw new \Exception('Image dimensions too large. Maximum size is 5000x5000 pixels.');
        }
    }

    /**
     * Delete a novel cover image
     *
     * @param string $novelSlug
     * @return bool
     */
    public static function deleteNovelCover(string $novelSlug): bool
    {
        $path = "novels/{$novelSlug}/cover_img.png";

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return true;
    }

    /**
     * Delete a user avatar image
     *
     * @param string $avatarPath The URL path like /storage/profiles/hash.png
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
     * Get the public URL for a novel cover
     *
     * @param string $novelSlug
     * @return string|null
     */
    public static function getNovelCoverUrl(string $novelSlug): ?string
    {
        $path = "novels/{$novelSlug}/cover_img.png";

        if (Storage::disk('public')->exists($path)) {
            return "/storage/{$path}";
        }

        return null;
    }

    /**
     * Check if a novel has a cover image
     *
     * @param string $novelSlug
     * @return bool
     */
    public static function novelHasCover(string $novelSlug): bool
    {
        $path = "novels/{$novelSlug}/cover_img.png";
        return Storage::disk('public')->exists($path);
    }
}
