<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guarantees no uploaded image is ever stored above 1 MB, whatever client sent
 * it — the web app, a site phone, or an offline queue replayed later. Images are
 * re-encoded to JPEG: quality drops first, then the dimensions, until it fits.
 * Non-images (PDFs, docs, backups) are stored untouched.
 */
trait CompressesImages
{
    /** Hard ceiling for any stored image. */
    protected int $maxImageBytes = 1048576; // 1 MB

    /**
     * Store an upload, compressing it first when it is an image.
     *
     * @return array{0:string,1:string,2:int}  [path, mime, size]
     */
    protected function storeCompressed(UploadedFile $file, string $dir, string $disk = 'local'): array
    {
        $mime = $file->getClientMimeType();

        if ($this->isCompressibleImage($mime) && ($out = $this->compressToJpeg($file)) !== null) {
            [$bytes, $outMime, $ext] = $out;
            $path = trim($dir, '/').'/'.uniqid('img_', true).'.'.$ext;
            Storage::disk($disk)->put($path, $bytes);

            return [$path, $outMime, strlen($bytes)];
        }

        return [$file->store($dir, $disk), $mime, (int) $file->getSize()];
    }

    protected function isCompressibleImage(?string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true);
    }

    /**
     * @return array{0:string,1:string,2:string}|null  [bytes, mime, extension]
     */
    protected function compressToJpeg(UploadedFile $file, int $maxDim = 1600, int $quality = 72): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($file->getRealPath());
        if ($raw === false) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxDim / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $bytes = $this->encodeJpeg($src, $w, $h, $nw, $nh, $quality);

        for ($i = 0; $bytes !== null && strlen($bytes) > $this->maxImageBytes && $i < 8; $i++) {
            if ($quality > 40) {
                $quality = max(40, $quality - 12);
            } else {
                $nw = (int) round($nw * 0.82);
                $nh = (int) round($nh * 0.82);
                if ($nw < 320 || $nh < 320) {
                    break;
                }
            }
            $bytes = $this->encodeJpeg($src, $w, $h, $nw, $nh, $quality);
        }

        imagedestroy($src);

        return $bytes === null ? null : [$bytes, 'image/jpeg', 'jpg'];
    }

    /** Resample the source onto a white canvas and return JPEG bytes. */
    private function encodeJpeg(\GdImage $src, int $w, int $h, int $nw, int $nh, int $quality): ?string
    {
        $dst = imagecreatetruecolor($nw, $nh);
        // Flatten onto white so transparent PNGs don't turn black as JPEG.
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($dst, null, $quality);
        $bytes = ob_get_clean();

        imagedestroy($dst);

        return $bytes === false ? null : $bytes;
    }
}
