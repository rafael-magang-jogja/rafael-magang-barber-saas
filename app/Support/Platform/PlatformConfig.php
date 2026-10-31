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
     * Ambil seluruh konfigurasi platform dalam bentuk key-value array.
     * Menggunakan cache in-memory/cache store untuk performa cepat tanpa query berulang.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $raw = Setting::query()
                ->withoutGlobalScope('tenant')
                ->whereNull('tenant_id')
                ->pluck('value', 'key')
                ->all();

            return [
                'platform_name' => $raw['platform_name'] ?? 'BarberOS Platform',
                'platform_tagline' => $raw['platform_tagline'] ?? 'Sistem Manajemen Barbershop Multi-Outlet & Payroll',
                'platform_theme_color' => $raw['platform_theme_color'] ?? '#D97706',
                'logo_type' => $raw['logo_type'] ?? 'icon',
                'logo_icon' => $raw['logo_icon'] ?? 'scissors',
                'logo_image_path' => $raw['logo_image_path'] ?? null,
                'favicon_path' => $raw['favicon_path'] ?? null,
                'support_email' => $raw['support_email'] ?? 'support@barberos.id',
                'support_phone' => $raw['support_phone'] ?? '081100000001',
                'default_trial_days' => (int) ($raw['default_trial_days'] ?? 14),
                'announcement_banner' => $raw['announcement_banner'] ?? '',
                'is_maintenance' => (bool) ($raw['is_maintenance'] ?? false),
            ];
        });
    }

    /**
     * Hapus cache setting platform saat ada perubahan konfigurasi.
     */
    public static function clearCache(): void
    {
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
        return (string) self::get('platform_tagline', 'Sistem Manajemen Barbershop Multi-Outlet & Payroll');
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

    public static function logoImageUrl(): ?string
    {
        $path = self::get('logo_image_path');
        if (empty($path)) {
            return null;
        }

        if (str_starts_with((string) $path, 'http://') || str_starts_with((string) $path, 'https://')) {
            return (string) $path;
        }

        return Storage::disk('public')->url((string) $path);
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

    public static function faviconUrl(): string
    {
        $customPath = self::get('favicon_path');
        if (! empty($customPath) && Storage::disk('public')->exists((string) $customPath)) {
            $mtime = Storage::disk('public')->lastModified((string) $customPath);

            return Storage::disk('public')->url((string) $customPath) . '?v=' . $mtime;
        }

        $faviconsIcoPath = public_path(FaviconService::FAVICON_DIR . '/favicon.ico');
        if (file_exists($faviconsIcoPath)) {
            $mtime = filemtime($faviconsIcoPath) ?: 1;

            return asset(FaviconService::FAVICON_DIR . '/favicon.ico') . '?v=' . $mtime;
        }

        // Jika berkas favicon belum ada (misal setelah refresh seeder/cache), generate otomatis sekarang
        try {
            FaviconService::generateFromIcon(self::logoIcon(), self::themeColor());
            if (file_exists($faviconsIcoPath)) {
                $mtime = filemtime($faviconsIcoPath) ?: 1;

                return asset(FaviconService::FAVICON_DIR . '/favicon.ico') . '?v=' . $mtime;
            }
        } catch (\Throwable) {}

        return asset('favicon.ico') . '?v=' . (file_exists(public_path('favicon.ico')) ? filemtime(public_path('favicon.ico')) : 1);
    }

    public static function hasSvgFavicon(): bool
    {
        $path = public_path(FaviconService::FAVICON_DIR . '/favicon.svg');
        if (file_exists($path)) {
            return true;
        }

        try {
            FaviconService::generateFromIcon(self::logoIcon(), self::themeColor());
            return file_exists($path);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function svgFaviconUrl(): string
    {
        $faviconsSvgPath = public_path(FaviconService::FAVICON_DIR . '/favicon.svg');
        if (! file_exists($faviconsSvgPath)) {
            try {
                FaviconService::generateFromIcon(self::logoIcon(), self::themeColor());
            } catch (\Throwable) {}
        }

        $mtime = file_exists($faviconsSvgPath) ? (filemtime($faviconsSvgPath) ?: 1) : 1;

        return asset(FaviconService::FAVICON_DIR . '/favicon.svg') . '?v=' . $mtime;
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
}
