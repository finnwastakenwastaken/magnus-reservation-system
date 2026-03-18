<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Validates and stores uploaded images for branding and user avatars.
 *
 * The service only accepts a small allowlist of image MIME types, generates
 * random filenames, and stores public URL paths instead of filesystem paths so
 * views never need to expose server internals.
 */
final class ImageUploadService
{
    private const ALLOWED_MIME_MAP = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private FileSystemService $files;

    public function __construct()
    {
        $this->files = new FileSystemService();
    }

    /**
     * Store an uploaded image and return its public URL path.
     */
    public function store(array $file, string $directory, int $maxBytes): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException('Invalid upload payload.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > $maxBytes) {
            throw new \RuntimeException('Uploaded image is too large.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpName) ?: '';
        $extension = self::ALLOWED_MIME_MAP[$mime] ?? null;
        if ($extension === null) {
            throw new \RuntimeException('Unsupported image format.');
        }

        if (@getimagesize($tmpName) === false) {
            throw new \RuntimeException('Invalid image file.');
        }

        $publicDirectory = '/uploads/' . trim($directory, '/');
        $filesystemDirectory = BASE_PATH . '/public' . $publicDirectory;
        $this->files->ensureDirectory($filesystemDirectory);

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $filesystemDirectory . '/' . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            throw new \RuntimeException('Unable to store uploaded image.');
        }

        return $publicDirectory . '/' . $filename;
    }

    public function deletePublicPath(?string $publicPath): void
    {
        if ($publicPath === null || $publicPath === '' || !str_starts_with($publicPath, '/uploads/')) {
            return;
        }

        $this->files->deleteFileIfExists(BASE_PATH . '/public' . $publicPath);
    }
}
