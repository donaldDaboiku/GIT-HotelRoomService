<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class ImageGuard
{
    public static function extension(UploadedFile $file, string $field = 'photo'): string
    {
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $mime = (string) $file->getMimeType();
        if (! isset($allowed[$mime])) {
            throw ValidationException::withMessages([
                $field => 'Upload a JPEG, PNG, or WebP image.',
            ]);
        }

        $original = $file->getClientOriginalName();
        if (str_contains($original, "\0") || preg_match('/\.php/i', $original) === 1) {
            throw ValidationException::withMessages([
                $field => 'That file name is not allowed.',
            ]);
        }

        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            throw ValidationException::withMessages([
                $field => 'The file is not a valid image.',
            ]);
        }

        return $allowed[$mime];
    }

    public static function safeOriginalName(UploadedFile $file): string
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $base) ?: 'image';

        return substr($clean, 0, 80);
    }
}
