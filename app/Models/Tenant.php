<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Support\Platform\FaviconService;
use App\Support\Platform\PlatformConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

final class Tenant extends Model
{
    use SoftDeletes;

    private ?Subscription $memoActiveSubscription = null;

    private bool $memoActiveSubscriptionLoaded = false;

    /**
     * @var array<int, int>|null
     */
    private ?array $memoAllowedBranchIds = null;

    private ?bool $memoCanAddBranch = null;

    private ?bool $memoCanAddStaff = null;

    private ?string $memoFaviconUrl = null;

    private ?string $memoSvgFaviconUrl = null;

    private bool $memoSvgFaviconResolved = false;

    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',
        'logo_path',
        'brand_color',
        'phone',
        'email',
        'address',
        'payout_bank_name',
        'payout_account_number',
        'payout_account_holder',
        'timezone',
        'status',
        'trial_ends_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->hasFeature('custom_logo') || empty($this->logo_path)) {
            return null;
        }

        if (str_starts_with($this->logo_path, 'http://') || str_starts_with($this->logo_path, 'https://')) {
            return $this->logo_path;
        }

        return asset('storage/'.$this->logo_path);
    }

    /**
     * URL berkas Favicon (ICO / PNG) khusus untuk tenant ini.
     * Mengikuti prioritas:
     * 1. Berkas custom favicon yang diunggah owner (khusus paket Business).
     * 2. Favicon hasil generate logo resmi platform + warna brand tenant (Starter, Pro, & Business).
     * 3. Fallback ke platform favicon global.
     */
    public function faviconUrl(): string
    {
        if ($this->memoFaviconUrl !== null) {
            return $this->memoFaviconUrl;
        }

        // 1. Jika ada custom favicon file yang diunggah (Paket Business)
        $customPath = $this->settings['favicon_path'] ?? null;
        /** @var \Illuminate\Filesystem\FilesystemAdapter $publicDisk */
        $publicDisk = Storage::disk('public');
        if (! empty($customPath) && $publicDisk->exists((string) $customPath)) {
            $mtime = $publicDisk->lastModified((string) $customPath);

            return $this->memoFaviconUrl = $publicDisk->url((string) $customPath) . '?v=' . $mtime;
        }

        // 2. Favicon otomatis untuk Starter & Pro (mengikuti icon official platform + warna background tenant)
        $iconName = PlatformConfig::logoIcon();
        $brandColor = $this->brand_color ?? PlatformConfig::themeColor();
        $cleanColor = strtoupper(ltrim($brandColor, '#'));

        $dynamicIcoName = "favicon_{$iconName}_{$cleanColor}.ico";
        $tenantIcoPath = public_path(FaviconService::TENANTS_DIR . "/{$this->id}/{$dynamicIcoName}");

        if (! file_exists($tenantIcoPath)) {
            try {
                FaviconService::generateForTenant($this);
            } catch (\Throwable) {}
        }

        if (file_exists($tenantIcoPath)) {
            $mtime = filemtime($tenantIcoPath) ?: 1;

            return $this->memoFaviconUrl = asset(FaviconService::TENANTS_DIR . "/{$this->id}/{$dynamicIcoName}") . '?v=' . $mtime;
        }

        $tenantStaticIco = public_path(FaviconService::TENANTS_DIR . "/{$this->id}/favicon.ico");
        if (file_exists($tenantStaticIco)) {
            $mtime = filemtime($tenantStaticIco) ?: 1;

            return $this->memoFaviconUrl = asset(FaviconService::TENANTS_DIR . "/{$this->id}/favicon.ico") . '?v=' . $mtime;
        }

        // 3. Fallback ke platform favicon global
        return $this->memoFaviconUrl = PlatformConfig::faviconUrl();
    }

    /**
     * URL berkas Favicon SVG khusus untuk tenant ini jika tersedia.
     */
    public function svgFaviconUrl(): ?string
    {
        if ($this->memoSvgFaviconResolved) {
            return $this->memoSvgFaviconUrl;
        }

        $this->memoSvgFaviconResolved = true;

        $customPath = $this->settings['favicon_path'] ?? null;
        if (! empty($customPath)) {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $publicDisk */
            $publicDisk = Storage::disk('public');
            if (str_ends_with(strtolower((string) $customPath), '.svg') && $publicDisk->exists((string) $customPath)) {
                $mtime = $publicDisk->lastModified((string) $customPath);

                return $this->memoSvgFaviconUrl = $publicDisk->url((string) $customPath) . '?v=' . $mtime;
            }

            // Jika kustom file berupa PNG/ICO, JANGAN emit link SVG agar browser tidak meng-override file kustom!
            return $this->memoSvgFaviconUrl = null;
        }

        $iconName = PlatformConfig::logoIcon();
        $brandColor = $this->brand_color ?? PlatformConfig::themeColor();
        $cleanColor = strtoupper(ltrim($brandColor, '#'));

        $dynamicSvgName = "favicon_{$iconName}_{$cleanColor}.svg";
        $tenantSvgPath = public_path(FaviconService::TENANTS_DIR . "/{$this->id}/{$dynamicSvgName}");

        if (! file_exists($tenantSvgPath)) {
            try {
                FaviconService::generateForTenant($this);
            } catch (\Throwable) {}
        }

        if (file_exists($tenantSvgPath)) {
            $mtime = filemtime($tenantSvgPath) ?: 1;

            return $this->memoSvgFaviconUrl = asset(FaviconService::TENANTS_DIR . "/{$this->id}/{$dynamicSvgName}") . '?v=' . $mtime;
        }

        $tenantStaticSvg = public_path(FaviconService::TENANTS_DIR . "/{$this->id}/favicon.svg");
        if (file_exists($tenantStaticSvg)) {
            $mtime = filemtime($tenantStaticSvg) ?: 1;

            return $this->memoSvgFaviconUrl = asset(FaviconService::TENANTS_DIR . "/{$this->id}/favicon.svg") . '?v=' . $mtime;
        }

        return $this->memoSvgFaviconUrl = PlatformConfig::hasSvgFavicon() ? PlatformConfig::svgFaviconUrl() : null;
    }

    /**
     * Apakah tenant memiliki favicon kustom tersendiri.
     */
    public function hasCustomFavicon(): bool
    {
        return ! empty($this->settings['favicon_path']) || file_exists(public_path(FaviconService::TENANTS_DIR . "/{$this->id}/favicon.ico"));
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function staffMemberships(): HasMany
    {
        return $this->hasMany(Membership::class)->where('role', '!=', \App\Enums\Role::Owner);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(TenantPayout::class);
    }

    public function flushMemo(): void
    {
        $this->memoActiveSubscription = null;
        $this->memoActiveSubscriptionLoaded = false;
        $this->memoAllowedBranchIds = null;
        $this->memoCanAddBranch = null;
        $this->memoCanAddStaff = null;
        $this->memoFaviconUrl = null;
        $this->memoSvgFaviconUrl = null;
        $this->memoSvgFaviconResolved = false;
    }

    public function refresh(): static
    {
        $this->flushMemo();

        return parent::refresh();
    }

    public function activeSubscription(): ?Subscription
    {
        if ($this->memoActiveSubscriptionLoaded) {
            return $this->memoActiveSubscription;
        }

        $this->memoActiveSubscriptionLoaded = true;

        $this->memoActiveSubscription = $this->subscriptions()
            ->withoutGlobalScope('tenant')
            ->with(['plan'])
            ->orderByRaw("CASE WHEN status IN ('active', 'trialing') THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();

        $this->memoActiveSubscription?->setRelation('tenant', $this);

        return $this->memoActiveSubscription;
    }

    public function isSubscriptionLocked(): bool
    {
        if ($this->status === TenantStatus::Suspended || $this->status === TenantStatus::Cancelled) {
            return true;
        }

        $isTrialActive = $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
        $sub = $this->activeSubscription();
        $isSubActive = $sub !== null
            && in_array($sub->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true)
            && ($sub->ends_at === null || $sub->ends_at->isFuture());

        return ! $isTrialActive && ! $isSubActive;
    }

    public function plan(): ?Plan
    {
        return $this->activeSubscription()?->plan;
    }

    public function planCode(): string
    {
        return $this->plan()?->code ?? 'starter';
    }

    public function maxBranches(): int
    {
        return $this->plan()?->max_branches ?? 1;
    }

    public function maxStaff(): ?int
    {
        $plan = $this->plan();
        if ($plan === null) {
            return 5;
        }

        return $plan->max_staff;
    }

    public function canAddBranch(): bool
    {
        if ($this->memoCanAddBranch !== null) {
            return $this->memoCanAddBranch;
        }

        return $this->memoCanAddBranch = $this->branches()->count() < $this->maxBranches();
    }

    /**
     * @return array<int, int>
     */
    public function allowedBranchIds(): array
    {
        if ($this->memoAllowedBranchIds !== null) {
            return $this->memoAllowedBranchIds;
        }

        $max = $this->maxBranches();

        return $this->memoAllowedBranchIds = $this->branches()
            ->orderBy('is_active', 'desc')
            ->orderBy('id', 'asc')
            ->limit($max)
            ->pluck('id')
            ->all();
    }

    public function isBranchLocked(Branch|int $branch): bool
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        $allowed = $this->allowedBranchIds();

        return ! in_array($branchId, $allowed, true);
    }

    public function canAddStaff(): bool
    {
        if ($this->memoCanAddStaff !== null) {
            return $this->memoCanAddStaff;
        }

        $limit = $this->maxStaff();
        if ($limit === null) {
            return $this->memoCanAddStaff = true;
        }

        return $this->memoCanAddStaff = $this->staffMemberships()->count() < $limit;
    }

    public function hasFeature(string $featureKey): bool
    {
        if ($this->isSubscriptionLocked()) {
            return false;
        }

        $code = $this->planCode();

        return match ($featureKey) {
            'analytics_pro' => in_array($code, ['pro', 'business'], true),
            'commission_auto' => true,
            'slip_gaji' => $code === 'business',
            'whatsapp_notification' => false,
            'whatsapp_invoice' => true,
            'brand_color' => in_array($code, ['pro', 'business'], true),
            'custom_logo' => in_array($code, ['pro', 'business'], true),
            'white_label' => $code === 'business',
            'custom_favicon' => $code === 'business',
            default => false,
        };
    }

    /**
     * URL Portal Pelanggan publik untuk tenant ini (/b/{slug}).
     *
     * @param  array<string, mixed>  $extraQuery
     */
    public function customerPortalUrl(?string $branchCode = null, array $extraQuery = []): string
    {
        $query = array_filter(array_merge(
            $branchCode ? ['branch' => $branchCode] : [],
            $extraQuery,
        ), fn ($v) => $v !== null && $v !== '');

        $appUrl = config('app.url') ?? 'http://localhost';
        $rootDomain = parse_url($appUrl, PHP_URL_HOST) ?? 'barberos.id';
        if ($rootDomain === '127.0.0.1') {
            $rootDomain = 'localhost';
        }
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'https';
        $port = parse_url($appUrl, PHP_URL_PORT);
        if (! $port && ! app()->runningInConsole() && request()->getPort()) {
            $reqPort = (int) request()->getPort();
            if (! in_array($reqPort, [80, 443], true)) {
                $port = $reqPort;
            }
        }
        $portSuffix = $port ? ":{$port}" : '';
        $queryString = ! empty($query) ? '?' . http_build_query($query) : '';

        return "{$scheme}://{$rootDomain}{$portSuffix}/b/{$this->slug}{$queryString}";
    }

    /**
     * URL halaman Login pelanggan untuk tenant ini.
     */
    public function loginUrl(): string
    {
        return rtrim($this->customerPortalUrl(), '/') . '/login';
    }

    /**
     * URL halaman Register pelanggan untuk tenant ini.
     */
    public function registerUrl(): string
    {
        return rtrim($this->customerPortalUrl(), '/') . '/register';
    }

    /**
     * URL Layanan Layar TV Antrean publik untuk tenant ini.
     * Menggunakan format subdomain / domain bila diaktifkan pada paket Business,
     * atau default route /b/{slug}/tv/{branchCode}.
     */
    public function tvDisplayUrl(string $branchCode): string
    {
        $portalUrl = rtrim($this->customerPortalUrl(), '/');

        return "{$portalUrl}/tv/{$branchCode}";
    }

    /**
     * Kembalikan / reset kustomisasi visual & domain ke default sistem jika tenant beralih ke paket lebih rendah.
     */
    public function resetPlanRestrictedFeatures(string $newPlanCode): void
    {
        $settings = $this->settings ?? [];

        if ($newPlanCode === 'starter') {
            // Hapus file logo kustom jika ada di disk
            if (! empty($this->logo_path) && ! str_starts_with($this->logo_path, 'http') && Storage::disk('public')->exists($this->logo_path)) {
                Storage::disk('public')->delete($this->logo_path);
            }

            // Kembalikan logo, warna brand/background, dan white-label ke default
            $this->logo_path = null;
            $this->brand_color = '#f59e0b'; // Default tema Starter

            FaviconService::clearTenantFavicon($this);

            $settings['white_label'] = false;
            $settings['favicon_path'] = null;
            $this->settings = $settings;

            $this->save();

            // Re-generate favicon otomatis Starter (ikon official platform + warna Starter)
            FaviconService::generateForTenant($this);
        } elseif ($newPlanCode === 'pro') {
            // Pro boleh kustom logo & warna brand, namun custom favicon file eksklusif Business
            // (Pada Pro, favicon di-generate otomatis dari logo + warna brand)
            if (! empty($settings['favicon_path'])) {
                if (Storage::disk('public')->exists($settings['favicon_path'])) {
                    Storage::disk('public')->delete($settings['favicon_path']);
                }
                $settings['favicon_path'] = null;
            }

            $settings['white_label'] = false;
            $this->settings = $settings;

            $this->save();

            // Re-generate favicon otomatis Pro
            FaviconService::generateForTenant($this);
        }
    }
}
