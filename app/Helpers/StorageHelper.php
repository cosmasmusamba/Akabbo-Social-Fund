<?php
namespace App\Helpers;

/**
 * AKABBO SOCIAL FUND — Storage Helper
 * 
 * Centralized, secure file upload handling to prevent code duplication
 * and ensure consistent MIME validation, naming, and storage.
 */
class StorageHelper
{
    /**
     * Handle file upload securely.
     *
     * @param array  $file          The $_FILES array element
     * @param string $directory     Subdirectory in STORAGE_PATH/uploads/ (e.g., 'avatars', 'kyc', 'receipts')
     * @param array  $allowedMimes  Allowed MIME types
     * @param int    $maxSize       Max file size in bytes
     * @param string $prefix        Filename prefix (e.g., 'avatar_', 'receipt_')
     * @return string The saved filename
     * @throws \RuntimeException on validation or move failure
     */
    public static function upload(
        array $file,
        string $directory,
        array $allowedMimes,
        int $maxSize,
        string $prefix = ''
    ): string {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed. Error code: ' . ($file['error'] ?? 'unknown'));
        }

        // 1. Server-side MIME validation (Prevents spoofing)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new \RuntimeException("Invalid file type '{$mimeType}'. Allowed: " . implode(', ', $allowedMimes));
        }

        // 2. Size validation
        if ($file['size'] > $maxSize) {
            throw new \RuntimeException('File exceeds the ' . round($maxSize / 1048576, 1) . ' MB limit.');
        }

        // 3. Secure filename generation
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        // Fallback if extension is missing but MIME is valid
        if (empty($ext)) {
            $ext = self::getExtensionFromMime($mimeType);
        }
        
        $filename = $prefix . uniqid('', true) . '.' . strtolower($ext);
        $destDir  = STORAGE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $directory;
        $destPath = $destDir . DIRECTORY_SEPARATOR . $filename;

        // 4. Ensure directory exists
        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                throw new \RuntimeException('Failed to create upload directory.');
            }
        }

        // 5. Move file
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Failed to save uploaded file to disk.');
        }

        return $filename;
    }

    /**
     * Delete a file from storage safely.
     */
    public static function delete(string $directory, string $filename): void
    {
        if (empty($filename)) return;
        
        $filePath = STORAGE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    private static function getExtensionFromMime(string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'application/pdf' => 'pdf',
            'image/x-icon' => 'ico',
        ];
        return $map[$mime] ?? 'bin';
    }
}