<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SubscriptionInvoice;
use App\Support\Platform\FaviconService;
use App\Support\Platform\PlatformConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

final class PlatformSettingController extends Controller
{
    public function index(): View
    {
        $settings = PlatformConfig::all();
        $bankAccounts = SubscriptionInvoice::platformBankAccounts();

        return view('platform.settings', [
            'settings' => $settings,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform_name' => ['required', 'string', 'max:100'],
            'platform_tagline' => ['nullable', 'string', 'max:255'],
            'platform_theme_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{3}){1,2}$/'],
            'logo_type' => ['nullable', 'string', 'in:icon,image'],
            'logo_icon' => ['nullable', 'string', 'max:50'],
            'logo_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,svg', 'max:2048'],
            'support_email' => ['nullable', 'email', 'max:150'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'default_trial_days' => ['required', 'integer', 'min:1', 'max:90'],
            'announcement_banner' => ['nullable', 'string', 'max:500'],
            'is_maintenance' => ['nullable', 'boolean'],
            'bank_accounts' => ['nullable', 'array'],
            'bank_accounts.*.bank' => ['required_with:bank_accounts', 'string', 'max:50'],
            'bank_accounts.*.account_number' => ['required_with:bank_accounts', 'string', 'max:50'],
            'bank_accounts.*.account_name' => ['required_with:bank_accounts', 'string', 'max:100'],
            'bank_accounts.*.logo_color' => ['nullable', 'string', 'max:50'],
        ], [
            'logo_image.file' => 'Berkas logo yang diunggah harus berupa file yang valid.',
            'logo_image.mimes' => 'Format file logo tidak didukung! Sistem hanya menerima file PNG, JPG, JPEG, WEBP, atau SVG.',
            'logo_image.max' => 'Ukuran berkas logo melebihi batas maksimal 2MB (2048 KB). Sistem menolak berkas ini.',
            'favicon.file' => 'Berkas favicon yang diunggah harus berupa file yang valid.',
            'favicon.mimes' => 'Format file favicon tidak didukung! Sistem hanya menerima berkas .ICO, .PNG, atau .SVG.',
            'favicon.max' => 'Ukuran berkas favicon melebihi batas maksimal 2MB (2048 KB). Sistem menolak berkas ini.',
        ]);

        $themeColor = strtoupper((string) ($validated['platform_theme_color'] ?? '#D97706'));
        $logoType = (string) ($validated['logo_type'] ?? 'icon');
        $logoIcon = (string) ($validated['logo_icon'] ?? 'scissors');

        $settingsMap = [
            'platform_name' => $validated['platform_name'],
            'platform_tagline' => $validated['platform_tagline'] ?? '',
            'platform_theme_color' => $themeColor,
            'logo_type' => $logoType,
            'logo_icon' => $logoIcon,
            'support_email' => $validated['support_email'] ?? '',
            'support_phone' => $validated['support_phone'] ?? '',
            'default_trial_days' => (int) $validated['default_trial_days'],
            'announcement_banner' => $validated['announcement_banner'] ?? '',
            'is_maintenance' => (bool) ($request->has('is_maintenance') ? $request->boolean('is_maintenance') : false),
        ];

        // 1. Upload Logo Gambar (jika ada file baru diunggah)
        if ($request->hasFile('logo_image')) {
            $logoFile = $request->file('logo_image');
            if ($logoFile && $logoFile->isValid()) {
                $ext = $logoFile->getClientOriginalExtension();
                $logoFileName = 'platform_logo_' . time() . '.' . $ext;
                $path = $logoFile->storeAs('platform', $logoFileName, 'public');
                $settingsMap['logo_image_path'] = $path;

                // Jika user tidak mengunggah file favicon spesifik, generate favicon otomatis dari file logo ini
                if (! $request->hasFile('favicon')) {
                    FaviconService::generateFromImageFile($logoFile);
                }
            }
        }

        // 2. Upload / Generate Favicon
        if ($request->hasFile('favicon')) {
            $faviconFile = $request->file('favicon');
            if ($faviconFile && $faviconFile->isValid()) {
                $ext = strtolower($faviconFile->getClientOriginalExtension());
                $storedFaviconPath = $faviconFile->storeAs('platform', 'favicon_' . time() . '.' . $ext, 'public');
                $settingsMap['favicon_path'] = $storedFaviconPath;

                // Konversi dan simpan ke public/favicons/favicon.ico dan public/favicon.ico
                FaviconService::generateFromImageFile($faviconFile);
            }
        } elseif ($logoType === 'icon' && ! $request->hasFile('logo_image')) {
            // Bersihkan custom favicon_path lama agar selalu menggunakan generated icon
            $settingsMap['favicon_path'] = null;

            // Generate favicon SVG dan ICO yang sinkron dengan icon dan warna tema
            FaviconService::generateFromIcon($logoIcon, $themeColor);
        }

        // Simpan setiap item setting ke tabel settings
        foreach ($settingsMap as $key => $value) {
            Setting::query()->withoutGlobalScope('tenant')->updateOrCreate(
                ['tenant_id' => null, 'key' => $key],
                ['value' => $value]
            );
        }

        // 3. Simpan Konfigurasi Rekening Bank Platform
        if ($request->has('bank_accounts')) {
            $rawBanks = $request->input('bank_accounts', []);
            $cleanBanks = [];
            if (is_array($rawBanks)) {
                foreach ($rawBanks as $item) {
                    $bName = trim((string) ($item['bank'] ?? ''));
                    $accNum = trim((string) ($item['account_number'] ?? ''));
                    $accName = trim((string) ($item['account_name'] ?? ''));
                    $color = trim((string) ($item['logo_color'] ?? 'bg-zinc-800'));

                    if ($bName !== '' && $accNum !== '') {
                        $cleanBanks[] = [
                            'bank' => $bName,
                            'account_number' => $accNum,
                            'account_name' => $accName !== '' ? $accName : $validated['platform_name'],
                            'logo_color' => $color !== '' ? $color : 'bg-zinc-800',
                        ];
                    }
                }
            }

            if (! empty($cleanBanks)) {
                Setting::query()->withoutGlobalScope('tenant')->updateOrCreate(
                    ['tenant_id' => null, 'key' => 'platform_bank_accounts'],
                    ['value' => json_encode($cleanBanks, JSON_UNESCAPED_UNICODE)]
                );
            }
        }

        // Bersihkan cache setting agar perubahan langsung aktif seketika
        PlatformConfig::clearCache();

        // Regenerasi favicon untuk tenant yang belum mengunggah custom logo agar otomatis mengikuti ikon baru dari platform
        \App\Models\Tenant::query()
            ->whereNull('logo_path')
            ->each(function (\App\Models\Tenant $tenant): void {
                if (empty($tenant->settings['favicon_path']) && $tenant->hasFeature('brand_color')) {
                    FaviconService::generateForTenant($tenant);
                }
            });

        return back()->with('status', 'Konfigurasi global platform, tema warna, logo & favicon berhasil diperbarui.');
    }
}
