<?php

declare(strict_types=1);

namespace App\Support\Platform;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

final class FaviconService
{
    public const FAVICON_DIR = 'favicons';

    /**
     * Pastikan direktori khusus favicons ada di public.
     */
    public static function ensureFaviconDirectory(): string
    {
        $dir = public_path(self::FAVICON_DIR);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        return $dir;
    }

    /**
     * Generate & simpan favicon.ico dan favicon.svg berdasarkan pilihan icon & tema warna.
     */
    public static function generateFromIcon(string $iconName, string $hexColor): void
    {
        self::ensureFaviconDirectory();

        $cleanColor = strtoupper(ltrim($hexColor, '#'));
        $svgName = "favicon_{$iconName}_{$cleanColor}.svg";
        $icoName = "favicon_{$iconName}_{$cleanColor}.ico";

        // 1. Simpan favicon.svg beresolusi tinggi (vector)
        $svgContent = self::createSvgForIcon($iconName, $hexColor);
        File::put(public_path(self::FAVICON_DIR . '/' . $svgName), $svgContent);
        File::put(public_path(self::FAVICON_DIR . '/favicon.svg'), $svgContent);

        // 2. Buat favicon.ico (32x32) menggunakan GD & bungkus dalam format ICO
        $icoData = self::createIcoFromIconBadge($iconName, $hexColor);
        File::put(public_path(self::FAVICON_DIR . '/' . $icoName), $icoData);
        File::put(public_path(self::FAVICON_DIR . '/favicon.ico'), $icoData);

        // Hapus varian favicon lama yang sudah tidak terpakai
        foreach (File::glob(public_path(self::FAVICON_DIR . '/favicon_*.*')) as $oldFile) {
            $base = basename($oldFile);
            if ($base !== $svgName && $base !== $icoName) {
                @unlink($oldFile);
            }
        }

        // Salin juga ke public/favicon.ico sebagai fallback standar browser
        File::put(public_path('favicon.ico'), $icoData);
    }

    /**
     * Generate & simpan favicon dari file logo atau file gambar yang diunggah.
     */
    public static function generateFromImageFile(UploadedFile|string $file): void
    {
        self::ensureFaviconDirectory();

        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if (! file_exists($path)) {
            return;
        }

        $extension = strtolower($file instanceof UploadedFile ? $file->getClientOriginalExtension() : pathinfo($path, PATHINFO_EXTENSION));

        // Jika file asli sudah .ico, simpan langsung
        if ($extension === 'ico') {
            $icoData = file_get_contents($path);
            if ($icoData !== false) {
                File::put(public_path(self::FAVICON_DIR . '/favicon.ico'), $icoData);
                File::put(public_path('favicon.ico'), $icoData);
            }
            return;
        }

        // Jika file svg
        if ($extension === 'svg') {
            $svgData = file_get_contents($path);
            if ($svgData !== false) {
                File::put(public_path(self::FAVICON_DIR . '/favicon.svg'), $svgData);
            }
        }

        // Konversi gambar raster (PNG, JPG, WEBP) ke 32x32 ICO
        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }

        if (extension_loaded('gd')) {
            $source = @imagecreatefromstring($content);
            if ($source !== false) {
                $origW = imagesx($source);
                $origH = imagesy($source);

                $resized = imagecreatetruecolor(32, 32);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $trans = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $trans);

                // Pertahankan aspect ratio jika tidak 1:1
                $dstX = 0;
                $dstY = 0;
                $dstW = 32;
                $dstH = 32;
                if ($origW > $origH) {
                    $dstH = (int) round(32 * ($origH / $origW));
                    $dstY = (int) floor((32 - $dstH) / 2);
                } elseif ($origH > $origW) {
                    $dstW = (int) round(32 * ($origW / $origH));
                    $dstX = (int) floor((32 - $dstW) / 2);
                }

                imagecopyresampled($resized, $source, $dstX, $dstY, 0, 0, $dstW, $dstH, $origW, $origH);

                ob_start();
                imagepng($resized);
                $pngData = (string) ob_get_clean();

                $icoData = self::wrapPngInIco($pngData);
                File::put(public_path(self::FAVICON_DIR . '/favicon.ico'), $icoData);
                File::put(public_path('favicon.ico'), $icoData);
            }
        }
    }

    /**
     * Membungkus byte data PNG menjadi file format Windows .ico yang sah.
     */
    public static function wrapPngInIco(string $pngData): string
    {
        // Header ICONDIR (6 bytes): Reserved=0, Type=1 (ICO), Count=1 image
        $header = pack('v3', 0, 1, 1);

        // ICONDIRENTRY (16 bytes)
        // Width=32, Height=32, ColorCount=0, Reserved=0, Planes=1, BitCount=32, Size, Offset=22
        $entry = pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($pngData), 22);

        return $header . $entry . $pngData;
    }

    /**
     * Menghasilkan representasi ICO 32x32 berkualitas tinggi menggunakan
     * Super-Sampled Anti-Aliasing (SSAA) 128x128 -> 32x32 bicubic downsampling.
     */
    private static function createIcoFromIconBadge(string $iconName, string $hexColor): string
    {
        if (! extension_loaded('gd')) {
            return '';
        }

        $rgb = self::hexToRgb($hexColor);

        // 1. Buat kanvas supersampled 128x128 untuk anti-aliasing sempurna
        $hi = imagecreatetruecolor(128, 128);
        imagealphablending($hi, false);
        imagesavealpha($hi, true);

        $transHi = imagecolorallocatealpha($hi, 0, 0, 0, 127);
        imagefill($hi, 0, 0, $transHi);

        imagealphablending($hi, true);
        $bg = imagecolorallocate($hi, $rgb['r'], $rgb['g'], $rgb['b']);

        // Gambar squircle / rounded rectangle halus beresolusi 128x128
        self::drawRoundedBadge($hi, 128, 128, 36, $bg);

        // Hitung warna kontras ikon
        $luminance = (0.299 * $rgb['r'] + 0.587 * $rgb['g'] + 0.114 * $rgb['b']) / 255;
        $fgColor = $luminance > 0.65
            ? imagecolorallocate($hi, 15, 23, 42)
            : imagecolorallocate($hi, 255, 255, 255);

        // Gambar ikon barbershop berkualitas tinggi di kanvas 128x128
        self::drawHighResGlyph($hi, $iconName, $fgColor, $bg);

        // 2. Downsample ke 32x32 menggunakan bicubic interpolation (smooth anti-aliasing)
        $dst = imagecreatetruecolor(32, 32);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transDst = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transDst);

        imagecopyresampled($dst, $hi, 0, 0, 0, 0, 32, 32, 128, 128);

        ob_start();
        imagepng($dst);
        $pngData = (string) ob_get_clean();

        return self::wrapPngInIco($pngData);
    }

    /**
     * Gambar badge rounded squircle pada kanvas resolusi tinggi.
     */
    private static function drawRoundedBadge($im, int $w, int $h, int $r, int $color): void
    {
        imagefilledellipse($im, $r, $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $w - $r, $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $r, $h - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $w - $r, $h - $r, $r * 2, $r * 2, $color);

        imagefilledrectangle($im, $r, 0, $w - $r, $h, $color);
        imagefilledrectangle($im, 0, $r, $w, $h - $r, $color);
    }

    /**
     * Gambar glyph vektor tebal beresolusi tinggi (128x128) untuk gunting, pisau cukur, dan kumis.
     */
    private static function drawHighResGlyph($im, string $iconName, int $color, int $bgColor): void
    {
        switch ($iconName) {
            case 'mustache':
                // Kumis Handlebar Vektor Tebal (Sesuai Referensi Gambar 1)
                $pts = [
                    64, 58,
                    74, 48, 92, 46, 108, 52, 118, 54, 122, 50,
                    116, 62, 106, 76, 92, 82, 78, 80, 68, 70,
                    64, 66,
                    60, 70, 50, 80, 36, 82, 22, 76, 12, 62,
                    6, 50, 10, 54, 20, 52, 36, 46, 54, 48,
                ];
                imagefilledpolygon($im, $pts, $color);
                break;

            case 'razor':
            case 'straight-razor':
                // Pisau Cukur Lipat Terbuka (Sesuai Referensi Gambar 2)
                // 1. Bilah Pisau (Blade) miring ke kiri atas
                $bladePts = [
                    26, 26,
                    36, 20,
                    88, 70,
                    78, 78,
                ];
                imagefilledpolygon($im, $bladePts, $color);
                imagefilledellipse($im, 26, 26, 10, 10, $color);

                // 2. Gagang Melengkung (Handle) di bagian bawah
                $handlePts = [
                    16, 90,
                    20, 84,
                    54, 88,
                    94, 84,
                    102, 90,
                    94, 98,
                    54, 102,
                    18, 98,
                ];
                imagefilledpolygon($im, $handlePts, $color);
                imagefilledellipse($im, 16, 94, 12, 12, $color);

                // 3. Engsel Pivot Pin & Ekor Tang
                imagefilledellipse($im, 98, 88, 16, 16, $color);
                $tangPts = [
                    94, 84,
                    114, 98,
                    110, 106,
                    90, 92,
                ];
                imagefilledpolygon($im, $tangPts, $color);
                break;

            case 'scissors':
            case 'scissors-comb':
            default:
                // Gunting Barber Vektor Tebal
                // Bilah silang
                $blade1 = [
                    38, 20, 48, 20,
                    94, 90, 84, 96,
                ];
                $blade2 = [
                    80, 20, 90, 20,
                    44, 96, 34, 90,
                ];
                imagefilledpolygon($im, $blade1, $color);
                imagefilledpolygon($im, $blade2, $color);

                // Lubang jari gunting berlubang halus
                imagefilledellipse($im, 38, 96, 28, 28, $color);
                imagefilledellipse($im, 38, 96, 14, 14, $bgColor);
                imagefilledellipse($im, 90, 96, 28, 28, $color);
                imagefilledellipse($im, 90, 96, 14, 14, $bgColor);
                // Baut poros tengah
                imagefilledellipse($im, 64, 58, 12, 12, $color);
                break;
        }
    }

    /**
     * Buat SVG vector untuk favicon.svg yang tajam pada semua skala browser & device.
     */
    public static function createSvgForIcon(string $iconName, string $hexColor): string
    {
        $rgb = self::hexToRgb($hexColor);
        $luminance = (0.299 * $rgb['r'] + 0.587 * $rgb['g'] + 0.114 * $rgb['b']) / 255;
        $textColor = $luminance > 0.65 ? '#09090b' : '#ffffff';

        $iconPaths = match ($iconName) {
            'barber-pole', 'pole' => '<path d="M20 6h8M20 42h8" stroke="' . $textColor . '" stroke-width="2.5"/><circle cx="24" cy="5" r="2" fill="' . $textColor . '"/><circle cx="24" cy="43" r="2" fill="' . $textColor . '"/><rect x="16" y="8" width="16" height="32" rx="4" fill="none" stroke="' . $textColor . '" stroke-width="3"/><line x1="16" y1="16" x2="32" y2="24" stroke="' . $textColor . '" stroke-width="2.5"/><line x1="16" y1="24" x2="32" y2="32" stroke="' . $textColor . '" stroke-width="2.5"/><line x1="16" y1="32" x2="26" y2="37" stroke="' . $textColor . '" stroke-width="2.5"/><line x1="22" y1="11" x2="32" y2="16" stroke="' . $textColor . '" stroke-width="2.5"/><path d="M10 12h6M10 36h6M10 8v32" stroke="' . $textColor . '" stroke-width="2.5"/>',
            'clipper', 'hair-clipper' => '<path d="M16 4h16M18 4v6M24 4v6M30 4v6" stroke="' . $textColor . '" stroke-width="3" stroke-linecap="round"/><path d="M14 10h20l-2 22a6 6 0 0 1-6 6h-4a6 6 0 0 1-6-6L14 10z" fill="none" stroke="' . $textColor . '" stroke-width="3"/><line x1="24" y1="18" x2="24" y2="26" stroke="' . $textColor . '" stroke-width="3" stroke-linecap="round"/><path d="M24 38v6" stroke="' . $textColor . '" stroke-width="3" stroke-linecap="round"/>',
            'razor', 'straight-razor' => '<path d="M9.6 8.4c-1.6 1-2.2 2.8-1.4 4.4l16.8 15.2 5-3.6L13.6 8c-1-.8-2.6-.6-4 .4z" fill="' . $textColor . '"/><path d="M38 29.6c-1 0-2 .4-2.8 1.2L5 31c-2.4 0-3 1.6-3 3.4 0 1.8 1 3.6 3.6 3.6 10.8.6 22-.8 32.4-3.6l7.2 5.2c1 .8 2.6.4 3.4-.6.8-1.2.4-2.6-.6-3.4l-4.8-3.4c.6-.8 1-1.6 1-2.6 0-2.2-1.8-4-4-4z" fill="' . $textColor . '"/>',
            'comb', 'barber-comb' => '<path d="M4 14h40a2 2 0 0 1 2 2v6H2v-6a2 2 0 0 1 2-2z" fill="' . $textColor . '" fill-opacity="0.2" stroke="' . $textColor . '" stroke-width="3"/><path d="M8 22v16M14 22v16M20 22v16M26 22v16M30 22v16M34 22v16M38 22v16M42 22v16" stroke="' . $textColor . '" stroke-width="3" stroke-linecap="round"/>',
            'spray', 'spray-bottle' => '<path d="M8 20a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v16a4 4 0 0 1-4 4h-8a4 4 0 0 1-4-4z" fill="none" stroke="' . $textColor . '" stroke-width="3"/><path d="M12 16v-6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v6" stroke="' . $textColor . '" stroke-width="3"/><path d="M20 12h4" stroke="' . $textColor . '" stroke-width="3"/><circle cx="31" cy="12" r="1.5" fill="' . $textColor . '"/><circle cx="37" cy="17" r="1.5" fill="' . $textColor . '"/><circle cx="37" cy="7" r="1.5" fill="' . $textColor . '"/><circle cx="42" cy="12" r="1.5" fill="' . $textColor . '"/>',
            'mustache' => '<path d="M24 22c4-5 12-6 16-2.4 3 2.4 5 4 7.6-.6-.6 4-4.6 11-12.6 11.4-5.4.2-9-2.8-11-5.4-2 2.6-5.6 5.6-11 5.4-8-.4-12-7.4-12.6-11.4 2.6 4.6 4.6 3 7.6.6 4-3.6 12-2.6 16 2.4z" fill="' . $textColor . '"/>',
            'chair', 'barber-chair' => '<rect x="18" y="6" width="12" height="5" rx="2" fill="' . $textColor . '" fill-opacity="0.25" stroke="' . $textColor . '" stroke-width="2.5"/><path d="M24 11v4M14 16h20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H14a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2z" fill="none" stroke="' . $textColor . '" stroke-width="3"/><path d="M8 28h32a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2z" fill="' . $textColor . '" fill-opacity="0.2" stroke="' . $textColor . '" stroke-width="3"/><path d="M10 22v6M38 22v6" stroke="' . $textColor . '" stroke-width="3"/><path d="M24 34v8M16 42h16" stroke="' . $textColor . '" stroke-width="3"/><path d="M16 34l-6 4h10" stroke="' . $textColor . '" stroke-width="2.5"/>',
            default => '<g fill="none" stroke="' . $textColor . '" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="36" r="6"/><circle cx="36" cy="36" r="6"/><path d="M16 32L24 24M32 32L24 24"/><circle cx="24" cy="24" r="2.5" fill="' . $textColor . '"/><path d="M24 24L14 6M24 24L34 6"/></g>',
        };

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48">
    <rect width="48" height="48" rx="14" fill="{$hexColor}"/>
    {$iconPaths}
</svg>
SVG;
    }

    public const TENANTS_DIR = 'favicons/tenants';

    /**
     * Pastikan direktori khusus favicons tenant ada di public.
     */
    public static function ensureTenantFaviconDirectory(int $tenantId): string
    {
        $dir = public_path(self::TENANTS_DIR . '/' . $tenantId);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        return $dir;
    }

    /**
     * Generate favicon untuk tenant (Starter, Pro, dsb.) secara otomatis:
     * Mengikuti icon resmi platform (PlatformConfig::logoIcon()) dengan
     * latar belakang warna brand milik tenant ($tenant->brand_color).
     */
    public static function generateForTenant(Tenant $tenant): void
    {
        $dir = self::ensureTenantFaviconDirectory($tenant->id);
        $brandColor = $tenant->brand_color ?? PlatformConfig::themeColor();
        $iconName = PlatformConfig::logoIcon();
        $cleanColor = strtoupper(ltrim($brandColor, '#'));

        $svgName = "favicon_{$iconName}_{$cleanColor}.svg";
        $icoName = "favicon_{$iconName}_{$cleanColor}.ico";

        // 1. Simpan favicon.svg (vector) beresolusi tinggi
        $svgContent = self::createSvgForIcon($iconName, $brandColor);
        self::safeWriteFile($dir . '/' . $svgName, $svgContent);
        self::safeWriteFile($dir . '/favicon.svg', $svgContent);

        // 2. Buat favicon.ico (32x32) menggunakan GD & bungkus dalam format ICO
        $icoData = self::createIcoFromIconBadge($iconName, $brandColor);
        self::safeWriteFile($dir . '/' . $icoName, $icoData);
        self::safeWriteFile($dir . '/favicon.ico', $icoData);

        // Hapus varian favicon lama milik tenant yang sudah tidak terpakai
        foreach (File::glob($dir . '/favicon_*.*') as $oldFile) {
            $base = basename($oldFile);
            if ($base !== $svgName && $base !== $icoName) {
                @unlink($oldFile);
            }
        }
    }

    private static function safeWriteFile(string $filePath, string $contents): void
    {
        try {
            if (file_exists($filePath)) {
                @chmod($filePath, 0666);
                @unlink($filePath);
            }
            File::put($filePath, $contents);
        } catch (\Throwable) {
            @file_put_contents($filePath, $contents);
        }
    }

    /**
     * Simpan berkas favicon kustom yang diunggah oleh owner paket Business.
     */
    public static function storeCustomFaviconForTenant(Tenant $tenant, UploadedFile $file): string
    {
        $dir = self::ensureTenantFaviconDirectory($tenant->id);
        $ext = strtolower($file->getClientOriginalExtension());
        $storedPath = $file->storeAs("tenants/{$tenant->id}", 'favicon_' . time() . '.' . $ext, 'public');

        if ($ext === 'ico') {
            $icoData = file_get_contents($file->getRealPath());
            if ($icoData !== false) {
                File::put($dir . '/favicon.ico', $icoData);
            }
        } elseif ($ext === 'svg') {
            $svgData = file_get_contents($file->getRealPath());
            if ($svgData !== false) {
                File::put($dir . '/favicon.svg', $svgData);
            }
            // Juga konversi ke ICO 32x32 jika memungkinkan
            self::generateFromImageFile($file);
        } else {
            // Gambar raster (PNG, JPG, WEBP)
            $sourceContent = file_get_contents($file->getRealPath());
            if ($sourceContent && extension_loaded('gd')) {
                $src = @imagecreatefromstring($sourceContent);
                if ($src) {
                    $resized = imagecreatetruecolor(32, 32);
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    $trans = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                    imagefill($resized, 0, 0, $trans);

                    $origW = imagesx($src);
                    $origH = imagesy($src);
                    imagecopyresampled($resized, $src, 0, 0, 0, 0, 32, 32, $origW, $origH);

                    ob_start();
                    imagepng($resized);
                    $pngData = (string) ob_get_clean();
                    $icoData = self::wrapPngInIco($pngData);
                    File::put($dir . '/favicon.ico', $icoData);
                }
            }
        }

        return $storedPath;
    }

    /**
     * Hapus berkas favicon khusus tenant.
     */
    public static function clearTenantFavicon(Tenant $tenant): void
    {
        $dir = public_path(self::TENANTS_DIR . '/' . $tenant->id);
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    /**
     * Konversi string hex menjadi RGB array.
     */
    public static function hexToRgb(string $hex): array
    {
        $clean = ltrim($hex, '#');
        if (strlen($clean) === 3) {
            $clean = $clean[0].$clean[0].$clean[1].$clean[1].$clean[2].$clean[2];
        }

        if (strlen($clean) === 6 && ctype_xdigit($clean)) {
            return [
                'r' => hexdec(substr($clean, 0, 2)),
                'g' => hexdec(substr($clean, 2, 2)),
                'b' => hexdec(substr($clean, 4, 2)),
            ];
        }

        return ['r' => 217, 'g' => 119, 'b' => 6];
    }
}
