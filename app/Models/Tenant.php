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

    protected $fillable = [
        'name',
        'slug',
        'owner_user_id',
        'logo_path',
        'brand_color',
        'phone',
        'email',
        'address',
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
     * 2. Favicon hasil generate logo + warna brand tenant (paket Pro & Business).
     * 3. Fallback ke platform favicon global.
     */
    public function faviconUrl(): string
    {
        // 1. Jika ada custom favicon file yang diunggah (Paket Business)
        $customPath = $this->settings['favicon_path'] ?? null;
        if (! empty($customPath) && Storage::disk('public')->exists((string) $customPath)) {
            $mtime = Storage::disk('public')->lastModified((string) $customPath);

            return Storage::disk('public')->url((string) $customPath) . '?v=' . $mtime;
        }

        // 2. Jika ada favicon khusus tenant yang digenerate (Pro / Business)
        $tenantIcoPath = public_path(FaviconService::TENANTS_DIR . "/{$this->id}/favicon.ico");
        if (file_exists($tenantIcoPath)) {
            $mtime = filemtime($tenantIcoPath) ?: 1;

            return asset(FaviconService::TENANTS_DIR . "/{$this->id}/favicon.ico") . '?v=' . $mtime;
        }

        // 3. Fallback ke platform favicon global
        return PlatformConfig::faviconUrl();
    }

    /**
     * URL berkas Favicon SVG khusus untuk tenant ini jika tersedia.
     */
    public function svgFaviconUrl(): ?string
    {
        $customPath = $this->settings['favicon_path'] ?? null;
        if (! empty($customPath) && str_ends_with(strtolower((string) $customPath), '.svg') && Storage::disk('public')->exists((string) $customPath)) {
            $mtime = Storage::disk('public')->lastModified((string) $customPath);

            return Storage::disk('public')->url((string) $customPath) . '?v=' . $mtime;
        }

        $tenantSvgPath = public_path(FaviconService::TENANTS_DIR . "/{$this->id}/favicon.svg");
        if (file_exists($tenantSvgPath)) {
            $mtime = filemtime($tenantSvgPath) ?: 1;

            return asset(FaviconService::TENANTS_DIR . "/{$this->id}/favicon.svg") . '?v=' . $mtime;
        }

        return PlatformConfig::hasSvgFavicon() ? PlatformConfig::svgFaviconUrl() : null;
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

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->withoutGlobalScope('tenant')
            ->with(['plan.features'])
            ->orderByRaw("CASE WHEN status IN ('active', 'trialing') THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();
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
        return $this->branches()->count() < $this->maxBranches();
    }

    /**
     * @return array<int, int>
     */
    public function allowedBranchIds(): array
    {
        $max = $this->maxBranches();

        return $this->branches()
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
        $limit = $this->maxStaff();
        if ($limit === null) {
            return true;
        }

        return $this->staffMemberships()->count() < $limit;
    }

    public function hasFeature(string $featureKey): bool
    {
        $code = $this->planCode();

        return match ($featureKey) {
            'analytics_pro' => in_array($code, ['pro', 'business'], true),
            'commission_auto' => in_array($code, ['pro', 'business'], true),
            'slip_gaji' => $code === 'business',
            'whatsapp_notification' => in_array($code, ['pro', 'business'], true),
            'brand_color' => in_array($code, ['pro', 'business'], true),
            'custom_logo' => in_array($code, ['pro', 'business'], true),
            'white_label' => $code === 'business',
            'subdomain' => $code === 'business',
            'custom_favicon' => $code === 'business',
            default => false,
        };
    }

    /**
     * URL Portal Pelanggan publik untuk tenant ini.
     * Menggunakan custom subdomain / domain bila diaktifkan pada paket Business,
     * atau default path /b/{slug}.
     */
    public function customerPortalUrl(?string $branchCode = null): string
    {
        if ($this->hasFeature('subdomain')) {
            $customDomain = $this->settings['custom_domain'] ?? null;
            $customSubdomain = $this->settings['custom_subdomain'] ?? null;

            if (! empty($customDomain)) {
                $base = str_starts_with($customDomain, 'http') ? $customDomain : 'https://' . $customDomain;
                return $branchCode ? "{$base}?branch={$branchCode}" : $base;
            }

            if (! empty($customSubdomain)) {
                $appUrl = config('app.url') ?? 'http://localhost';
                $rootDomain = parse_url($appUrl, PHP_URL_HOST) ?? 'barberos.id';
                $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'https';
                $port = parse_url($appUrl, PHP_URL_PORT);
                $portSuffix = $port ? ":{$port}" : '';
                $base = "{$scheme}://{$customSubdomain}.{$rootDomain}{$portSuffix}";
                return $branchCode ? "{$base}?branch={$branchCode}" : $base;
            }
        }

        return route('customer.portal', array_filter([
            'tenant' => $this->slug,
            'branch' => $branchCode,
        ]));
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

            // Kembalikan logo, warna brand/background, white-label, dan subdomain ke default
            $this->logo_path = null;
            $this->brand_color = '#f59e0b'; // Default tema Starter

            FaviconService::clearTenantFavicon($this);

            $settings['white_label'] = false;
            $settings['custom_subdomain'] = null;
            $settings['custom_domain'] = null;
            $settings['favicon_path'] = null;
            $this->settings = $settings;

            $this->save();
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
            $settings['custom_subdomain'] = null;
            $settings['custom_domain'] = null;
            $this->settings = $settings;

            $this->save();

            // Re-generate favicon otomatis Pro
            FaviconService::generateForTenant($this);
        }
    }
}
