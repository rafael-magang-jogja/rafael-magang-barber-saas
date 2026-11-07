<?php

declare(strict_types=1);

namespace App\Support\Platform;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

final class PlatformConfig
{
    private const CACHE_KEY = 'platform_global_settings';
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * In-memory cache per request agar tidak query ke tabel cache DB berulang kali.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $memo = null;

    private static ?string $memoFaviconUrl = null;

    private static ?string $memoSvgFaviconUrl = null;

    private static bool $memoSvgFaviconResolved = false;

    /**
     * Ambil seluruh konfigurasi platform dalam bentuk key-value array.
     * Menggunakan cache in-memory/cache store untuk performa cepat tanpa query berulang.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        return self::$memo = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $raw = Setting::query()
                ->withoutGlobalScope('tenant')
                ->whereNull('tenant_id')
                ->pluck('value', 'key')
                ->all();

            return array_merge([
                'platform_name' => 'BarberOS Platform',
                'platform_tagline' => 'Platform Layanan & Antrean Barbershop',
                'platform_theme_color' => '#D97706',
                'logo_type' => 'icon',
                'logo_icon' => 'scissors',
                'logo_image_path' => null,
                'favicon_path' => null,
                'support_email' => 'support@barberos.id',
                'support_phone' => '081100000001',
                'default_trial_days' => 14,
                'announcement_banner' => '',
                'announcement_type' => 'info',
                'is_announcement_active' => false,
                'is_maintenance' => false,
                'promoted_products_slide_seconds' => 5,
                'promoted_products_whatsapp' => '',
                'promoted_products_json' => null,
            ], $raw);
        });
    }

    /**
     * Bersihkan in-memory memoization pada awal siklus aplikasi/request.
     */
    public static function flushMemo(): void
    {
        self::$memo = null;
        self::$memoFaviconUrl = null;
        self::$memoSvgFaviconUrl = null;
        self::$memoSvgFaviconResolved = false;
    }

    /**
     * Hapus cache setting platform saat ada perubahan konfigurasi.
     */
    public static function clearCache(): void
    {
        self::flushMemo();
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Ambil nilai spesifik setting.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return $all[$key] ?? $default;
    }

    public static function name(): string
    {
        return (string) self::get('platform_name', 'BarberOS Platform');
    }

    public static function tagline(): string
    {
        return (string) self::get('platform_tagline', 'Platform Layanan & Antrean Barbershop');
    }

    /**
     * @return array{message: string, type: string}|null
     */
    public static function announcement(): ?array
    {
        $all = self::all();
        $isActive = (bool) ($all['is_announcement_active'] ?? false);
        $msg = trim((string) ($all['announcement_banner'] ?? ''));

        if ($isActive && ! empty($msg)) {
            return [
                'message' => $msg,
                'type' => (string) ($all['announcement_type'] ?? 'info'),
            ];
        }

        return null;
    }

    public static function themeColor(): string
    {
        $color = (string) self::get('platform_theme_color', '#D97706');

        return ! empty($color) ? $color : '#D97706';
    }

    public static function logoType(): string
    {
        return (string) self::get('logo_type', 'icon');
    }

    public static function logoIcon(): string
    {
        return (string) self::get('logo_icon', 'scissors');
    }

    private static function publicDisk(): \Illuminate\Filesystem\FilesystemAdapter
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk;
    }

    public static function logoImageUrl(): ?string
    {
        $path = self::get('logo_image_path');
        if (empty($path)) {
            return null;
        }

        if (str_starts_with((string) $path, 'http://') || str_starts_with((string) $path, 'https://')) {
            return (string) $path;
        }

        return self::publicDisk()->url((string) $path);
    }

    public static function isMaintenance(): bool
    {
        return (bool) self::get('is_maintenance', false);
    }

    public static function announcementBanner(): ?string
    {
        $banner = trim((string) self::get('announcement_banner', ''));

        return $banner !== '' ? $banner : null;
    }

    public static function hasSvgFavicon(): bool
    {
        $customPath = self::get('favicon_path');
        if (! empty($customPath)) {
            return str_ends_with(strtolower((string) $customPath), '.svg')
                && self::publicDisk()->exists((string) $customPath);
        }

        return true;
    }

    public static function faviconUrl(): string
    {
        if (self::$memoFaviconUrl !== null) {
            return self::$memoFaviconUrl;
        }

        $customPath = self::get('favicon_path');
        if (! empty($customPath) && self::publicDisk()->exists((string) $customPath)) {
            $mtime = self::publicDisk()->lastModified((string) $customPath);

            return self::$memoFaviconUrl = self::publicDisk()->url((string) $customPath) . '?v=' . $mtime;
        }

        $icon = self::logoIcon();
        $color = self::themeColor();
        $cleanColor = strtoupper(ltrim($color, '#'));
        $fileName = "favicon_{$icon}_{$cleanColor}.ico";
        $filePath = public_path(FaviconService::FAVICON_DIR . '/' . $fileName);

        if (! file_exists($filePath)) {
            try {
                FaviconService::generateFromIcon($icon, $color);
            } catch (\Throwable) {}
        }

        if (file_exists($filePath)) {
            $mtime = filemtime($filePath) ?: 1;

            return self::$memoFaviconUrl = asset(FaviconService::FAVICON_DIR . '/' . $fileName) . '?v=' . $mtime;
        }

        return self::$memoFaviconUrl = asset('favicon.ico') . '?v=' . (file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : 1);
    }

    public static function svgFaviconUrl(): ?string
    {
        if (self::$memoSvgFaviconResolved) {
            return self::$memoSvgFaviconUrl;
        }

        self::$memoSvgFaviconResolved = true;

        $customPath = self::get('favicon_path');
        if (! empty($customPath)) {
            if (str_ends_with(strtolower((string) $customPath), '.svg') && self::publicDisk()->exists((string) $customPath)) {
                $mtime = self::publicDisk()->lastModified((string) $customPath);

                return self::$memoSvgFaviconUrl = self::publicDisk()->url((string) $customPath) . '?v=' . $mtime;
            }

            return self::$memoSvgFaviconUrl = null;
        }

        $icon = self::logoIcon();
        $color = self::themeColor();
        $cleanColor = strtoupper(ltrim($color, '#'));
        $fileName = "favicon_{$icon}_{$cleanColor}.svg";
        $filePath = public_path(FaviconService::FAVICON_DIR . '/' . $fileName);

        if (! file_exists($filePath)) {
            try {
                FaviconService::generateFromIcon($icon, $color);
            } catch (\Throwable) {}
        }

        if (file_exists($filePath)) {
            $mtime = filemtime($filePath) ?: 1;

            return self::$memoSvgFaviconUrl = asset(FaviconService::FAVICON_DIR . '/' . $fileName) . '?v=' . $mtime;
        }

        return self::$memoSvgFaviconUrl = null;
    }

    public static function supportEmail(): string
    {
        return (string) self::get('support_email', 'support@barberos.id');
    }

    public static function supportPhone(): string
    {
        return (string) self::get('support_phone', '081100000001');
    }

    public static function defaultTrialDays(): int
    {
        return (int) self::get('default_trial_days', 14);
    }

    /**
     * Ambil daftar produk promosi platform official.
     * Jika belum ada di setting DB, return default 4 produk resmi BarberOS.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function promotedProducts(): array
    {
        $rawJson = self::get('promoted_products_json');
        if (! empty($rawJson)) {
            $decoded = json_decode((string) $rawJson, true);
            if (is_array($decoded) && ! empty($decoded)) {
                return array_map(function ($item) {
                    if (is_array($item) && empty($item['url'])) {
                        $item['url'] = 'https://shopee.co.id';
                    }
                    return $item;
                }, $decoded);
            }
        }

        return self::defaultPromotedProducts();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function defaultPromotedProducts(): array
    {
        return [
            [
                'id' => 'matte-clay',
                'name' => 'BarberOS Matte Clay Pomade',
                'price' => 'Rp65.000',
                'original_price' => 'Rp85.000',
                'url' => 'https://shopee.co.id',
                'image' => asset('images/products/pomade.svg'),
                'images' => [
                    asset('images/products/pomade.svg'),
                    asset('images/products/pomade_open.svg'),
                ],
                'price_color' => 'text-amber-400',
                'features' => ['Water-based formula (mudah dibilas)', 'Tahan 16 jam aktivitas aktif', 'Natural matte look'],
                'desc' => 'Formula water-based premium dengan aroma maskulin elegan. Tahan 16 jam, mudah ditata ulang & tidak lengket saat dibilas.',
                'is_active' => true,
                'stock_status' => 'ready',
            ],
            [
                'id' => 'texture-powder',
                'name' => 'BarberOS Styling Texture Powder',
                'price' => 'Rp55.000',
                'original_price' => 'Rp70.000',
                'url' => 'https://shopee.co.id',
                'image' => asset('images/products/powder.svg'),
                'images' => [
                    asset('images/products/powder.svg'),
                    asset('images/products/powder_detail.svg'),
                ],
                'price_color' => 'text-sky-400',
                'features' => ['Volume instan dalam 10 detik', 'Serap minyak berlebih di kulit kepala', 'Ringan tanpa residu putih'],
                'desc' => 'Solusi rambut bervolume natural seketika tanpa minyak lepek. Mengunci tekstur gaya French crop, quiff, dan messy look seharian.',
                'is_active' => true,
                'stock_status' => 'ready',
            ],
            [
                'id' => 'beard-hair-oil',
                'name' => 'BarberOS Beard & Hair Nourishing Oil',
                'price' => 'Rp79.000',
                'original_price' => 'Rp99.000',
                'url' => 'https://shopee.co.id',
                'image' => asset('images/products/serum.svg'),
                'images' => [
                    asset('images/products/serum.svg'),
                    asset('images/products/serum_box.svg'),
                ],
                'price_color' => 'text-emerald-400',
                'features' => ['100% Cold-pressed Argan Oil', 'Melembutkan jenggot dan rambut kaku', 'Menstimulasi pertumbuhan rambut'],
                'desc' => 'Serum perawatan esensial janggut dan rambut. Menutrisi dari folikel akar, melembutkan bulu kasar, serta mencegah ketombe.',
                'is_active' => true,
                'stock_status' => 'ready',
            ],
            [
                'id' => 'clipper-pro',
                'name' => 'BarberOS Pro Cordless Clipper Set',
                'price' => 'Rp389.000',
                'original_price' => 'Rp489.000',
                'url' => 'https://shopee.co.id',
                'image' => asset('images/products/clipper.svg'),
                'images' => [
                    asset('images/products/clipper.svg'),
                    asset('images/products/clipper_blade.svg'),
                ],
                'price_color' => 'text-amber-400',
                'features' => ['Mata pisau DLC presisi ultra-tipis', 'Baterai tahan 240 menit kerja', 'Paket lengkap 6 comb guards'],
                'desc' => 'Dilengkapi DLC carbon blade tahan panas, motor 7.200 RPM bertenaga tinggi, serta baterai lithium tahan 4 jam non-stop.',
                'is_active' => true,
                'stock_status' => 'ready',
            ],
        ];
    }

    public static function promotedProductsSlideDuration(): int
    {
        $sec = (int) self::get('promoted_products_slide_seconds', 5);

        return $sec >= 2 && $sec <= 60 ? $sec : 5;
    }

    public static function promotedProductsWhatsapp(): string
    {
        $num = (string) self::get('promoted_products_whatsapp', '');

        return ! empty($num) ? $num : self::supportPhone();
    }
}
