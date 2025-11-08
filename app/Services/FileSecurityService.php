<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FileSecurityService
{
    /**
     * Allowed MIME types for uploads
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Maximum file size in bytes (10MB)
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Allowed file extensions
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'
    ];

    /**
     * Validate uploaded file
     * 
     * @param UploadedFile $file
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateUpload(UploadedFile $file): array
    {
        // Check if file was uploaded successfully
        if (!$file->isValid()) {
            return ['valid' => false, 'error' => 'File upload failed'];
        }

        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return ['valid' => false, 'error' => 'File size exceeds 10MB limit'];
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return ['valid' => false, 'error' => 'File type not allowed. Only images and PDFs are permitted.'];
        }

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return ['valid' => false, 'error' => 'File extension not allowed'];
        }

        // Check for double extensions (security risk)
        $originalName = $file->getClientOriginalName();
        if (substr_count($originalName, '.') > 1) {
            return ['valid' => false, 'error' => 'Files with multiple extensions are not allowed'];
        }

        // Validate image dimensions if it's an image
        if (Str::startsWith($mimeType, 'image/')) {
            $imageInfo = getimagesize($file->getRealPath());
            if (!$imageInfo) {
                return ['valid' => false, 'error' => 'Invalid image file'];
            }

            // Max dimensions: 4000x4000 pixels
            if ($imageInfo[0] > 4000 || $imageInfo[1] > 4000) {
                return ['valid' => false, 'error' => 'Image dimensions exceed 4000x4000 pixels'];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Scan file for malware (placeholder - integrate with ClamAV or VirusTotal)
     * 
     * @param UploadedFile $file
     * @return bool
     */
    public function scanForViruses(UploadedFile $file): bool
    {
        // In production, integrate with ClamAV or VirusTotal API
        // Example ClamAV command: clamscan --no-summary /path/to/file
        
        if (config('services.clamav.enabled', false)) {
            try {
                $filePath = $file->getRealPath();
                $output = shell_exec("clamscan --no-summary {$filePath}");
                
                // If output contains "Infected", file is malicious
                if (Str::contains($output, 'Infected')) {
                    Log::warning('Malware detected in uploaded file', [
                        'file' => $file->getClientOriginalName(),
                        'scan_result' => $output
                    ]);
                    return false;
                }
            } catch (\Exception $e) {
                Log::error('Virus scan failed: ' . $e->getMessage());
                // Fail-safe: if scanning fails, reject file
                return false;
            }
        }

        return true; // Pass if scanning disabled
    }

    /**
     * Store file securely in private storage
     * 
     * @param UploadedFile $file
     * @param string $type (e.g., 'kyc', 'project', 'document')
     * @param int $userId
     * @return string Encrypted storage path
     */
    public function storeSecurely(UploadedFile $file, string $type, int $userId): string
    {
        // Generate random filename to prevent enumeration
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;

        // Create directory structure: private/{type}/{user_id}/{year}/{month}
        $directory = sprintf(
            'private/%s/%d/%s/%s',
            $type,
            $userId,
            now()->format('Y'),
            now()->format('m')
        );

        // Store file in private disk (not public)
        $path = $file->storeAs($directory, $filename, 'local');

        // Encrypt the path before storing in database
        $encryptedPath = encrypt($path);

        Log::info('File stored securely', [
            'type' => $type,
            'user_id' => $userId,
            'original_name' => $file->getClientOriginalName(),
            'stored_as' => $filename,
        ]);

        return $encryptedPath;
    }

    /**
     * Generate signed URL for temporary file access
     * 
     * @param string $encryptedPath
     * @param int $minutes (default: 10 minutes)
     * @return string
     */
    public function getSignedUrl(string $encryptedPath, int $minutes = 10): string
    {
        try {
            $path = decrypt($encryptedPath);
            
            // Generate temporary URL (valid for specified minutes)
            return Storage::temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\Exception $e) {
            Log::error('Failed to generate signed URL: ' . $e->getMessage());
            throw new \Exception('Unable to generate file access URL');
        }
    }

    /**
     * Delete file securely
     * 
     * @param string $encryptedPath
     * @return bool
     */
    public function deleteSecurely(string $encryptedPath): bool
    {
        try {
            $path = decrypt($encryptedPath);
            
            if (Storage::exists($path)) {
                Storage::delete($path);
                Log::info('File deleted securely', ['path' => $path]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to delete file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sanitize filename
     * 
     * @param string $filename
     * @return string
     */
    public function sanitizeFilename(string $filename): string
    {
        // Remove special characters and spaces
        $filename = preg_replace('/[^A-Za-z0-9\-_\.]/', '_', $filename);
        
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Trim underscores from start and end
        return trim($filename, '_');
    }

    /**
     * Get file metadata
     * 
     * @param string $encryptedPath
     * @return array|null
     */
    public function getFileMetadata(string $encryptedPath): ?array
    {
        try {
            $path = decrypt($encryptedPath);
            
            if (!Storage::exists($path)) {
                return null;
            }

            return [
                'size' => Storage::size($path),
                'last_modified' => Storage::lastModified($path),
                'mime_type' => Storage::mimeType($path),
                'exists' => true,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get file metadata: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if file exists
     * 
     * @param string $encryptedPath
     * @return bool
     */
    public function exists(string $encryptedPath): bool
    {
        try {
            $path = decrypt($encryptedPath);
            return Storage::exists($path);
        } catch (\Exception $e) {
            return false;
        }
    }
}
