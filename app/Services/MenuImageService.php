<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * FR23, NFR08. Stores and resizes menu item photos.
 */
class MenuImageService
{
    private const MAX_WIDTH = 1200;
    private const JPEG_QUALITY = 80;

    public function store(UploadedFile $file): string
    {
        $manager = new ImageManager(new Driver());

        $encoded = $manager->decode($file)
            ->scaleDown(width: self::MAX_WIDTH)
            ->encode(new JpegEncoder(quality: self::JPEG_QUALITY));

        $path = 'menu/'.Str::uuid()->toString().'.jpg';

        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }

    /** @return array<int, string> */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'image',
            'mimes:jpeg,jpg,png,webp',
            'max:4096',
            'dimensions:min_width=400,min_height=400',
        ];
    }
}
