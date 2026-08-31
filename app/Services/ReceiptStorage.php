<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReceiptStorage
{
    public const PRIVATE_PREFIX = 'private:';

    public function storeUploaded(UploadedFile $file, int $tenantId, ?string $subDirectory = null): string
    {
        $directory = 'receipts/' . $tenantId . ($subDirectory ? '/' . trim($subDirectory, '/') : '');

        return self::PRIVATE_PREFIX . $file->store($directory, 'local');
    }

    public function putPdf(string $path, string $contents): string
    {
        $path = ltrim($path, '/');
        Storage::disk('local')->put($path, $contents);

        return self::PRIVATE_PREFIX . $path;
    }

    public function delete(?string $receiptFile): void
    {
        [$disk, $path] = $this->diskAndPath($receiptFile);

        if ($path !== '' && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    public function exists(?string $receiptFile): bool
    {
        [$disk, $path] = $this->diskAndPath($receiptFile);

        return $path !== '' && Storage::disk($disk)->exists($path);
    }

    public function absolutePath(?string $receiptFile): ?string
    {
        [$disk, $path] = $this->diskAndPath($receiptFile);

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return Storage::disk($disk)->path($path);
    }

    public function diskAndPath(?string $receiptFile): array
    {
        $receiptFile = ltrim((string) $receiptFile, '/');

        if (str_starts_with($receiptFile, self::PRIVATE_PREFIX)) {
            return ['local', ltrim(substr($receiptFile, strlen(self::PRIVATE_PREFIX)), '/')];
        }

        return ['public', $receiptFile];
    }

    public function isPrivate(?string $receiptFile): bool
    {
        return str_starts_with((string) $receiptFile, self::PRIVATE_PREFIX);
    }

    public function privateTargetPath(string $baseDirectory, int $tenantId, string $sourcePath): string
    {
        $sourcePath = ltrim(str_replace('\\', '/', $sourcePath), '/');
        $tenantPrefix = trim($baseDirectory, '/') . '/' . $tenantId . '/';

        if (str_starts_with($sourcePath, $tenantPrefix)) {
            return $sourcePath;
        }

        return $tenantPrefix . 'migrated/' . basename($sourcePath);
    }

    public function uniqueLocalPath(string $path): string
    {
        $path = ltrim($path, '/');
        if (! Storage::disk('local')->exists($path)) {
            return $path;
        }

        $directory = trim(dirname($path), '.');
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $counter = 2;

        do {
            $candidate = ($directory ? $directory . '/' : '') . $filename . '-' . $counter . ($extension ? '.' . $extension : '');
            $counter++;
        } while (Storage::disk('local')->exists($candidate));

        return $candidate;
    }

    public function copyPublicToLocal(string $sourcePath, string $targetPath): void
    {
        $stream = Storage::disk('public')->readStream($sourcePath);
        if ($stream === false) {
            throw new \RuntimeException('Quelldatei kann nicht gelesen werden: ' . $sourcePath);
        }

        try {
            Storage::disk('local')->put($targetPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }
}
