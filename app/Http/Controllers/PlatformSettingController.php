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
        $promotedProducts = PlatformConfig::promotedProducts();
        $slideDuration = PlatformConfig::promotedProductsSlideDuration();
        $promotedWhatsapp = PlatformConfig::promotedProductsWhatsapp();

        return view('platform.settings', [
            'settings' => $settings,
            'bankAccounts' => $bankAccounts,
            'promotedProducts' => $promotedProducts,
            'slideDuration' => $slideDuration,
            'promotedWhatsapp' => $promotedWhatsapp,
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
            'announcement_type' => ['nullable', 'string', 'in:info,warning,urgent'],
            'is_announcement_active' => ['nullable', 'boolean'],
            'is_maintenance' => ['nullable', 'boolean'],
            'promoted_products_slide_seconds' => ['nullable', 'integer', 'min:2', 'max:60'],
            'promoted_products_whatsapp' => ['nullable', 'string', 'max:30'],
            'promoted_products' => ['nullable', 'array'],
            'promoted_products.*.id' => ['nullable', 'string', 'max:50'],
            'promoted_products.*.name' => ['nullable', 'string', 'max:150'],
            'promoted_products.*.price' => ['nullable', 'string', 'max:50'],
            'promoted_products.*.original_price' => ['nullable', 'string', 'max:50'],
            'promoted_products.*.price_color' => ['nullable', 'string', 'max:50'],
            'promoted_products.*.image_existing' => ['nullable', 'string', 'max:255'],
            'promoted_products.*.images_existing' => ['nullable', 'string'],
            'promoted_products.*.image_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'promoted_products.*.image_files.*' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'promoted_products.*.desc' => ['nullable', 'string', 'max:500'],
            'promoted_products.*.features' => ['nullable', 'string', 'max:500'],
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
            'announcement_type' => $validated['announcement_type'] ?? 'info',
            'is_announcement_active' => (bool) ($request->has('is_announcement_active') ? $request->boolean('is_announcement_active') : false),
            'is_maintenance' => (bool) ($request->has('is_maintenance') ? $request->boolean('is_maintenance') : false),
            'promoted_products_slide_seconds' => (int) ($validated['promoted_products_slide_seconds'] ?? 5),
            'promoted_products_whatsapp' => (string) ($validated['promoted_products_whatsapp'] ?? ''),
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

        $existingFaviconPath = PlatformConfig::get('favicon_path');

        // 2. Upload / Generate / Preserve Favicon
        if ($request->boolean('remove_favicon')) {
            $settingsMap['favicon_path'] = null;
            if (! empty($existingFaviconPath) && Storage::disk('public')->exists((string) $existingFaviconPath)) {
                Storage::disk('public')->delete((string) $existingFaviconPath);
            }
            FaviconService::generateFromIcon($logoIcon, $themeColor);
        } elseif ($request->hasFile('favicon')) {
            $faviconFile = $request->file('favicon');
            if ($faviconFile && $faviconFile->isValid()) {
                $ext = strtolower($faviconFile->getClientOriginalExtension());
                $storedFaviconPath = $faviconFile->storeAs('platform', 'favicon_' . time() . '.' . $ext, 'public');
                $settingsMap['favicon_path'] = $storedFaviconPath;

                // Konversi dan simpan ke public/favicons/favicon.ico dan public/favicon.ico
                FaviconService::generateFromImageFile($faviconFile);
            }
        } else {
            // Jika sudah ada custom favicon yang pernah diupload dan masih ada di storage, pertahankan!
            if (! empty($existingFaviconPath) && Storage::disk('public')->exists((string) $existingFaviconPath)) {
                $settingsMap['favicon_path'] = $existingFaviconPath;
            } elseif ($logoType === 'icon' && ! $request->hasFile('logo_image')) {
                // Hanya generate dari icon jika memang belum ada custom favicon
                $settingsMap['favicon_path'] = null;
                FaviconService::generateFromIcon($logoIcon, $themeColor);
            }
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

        // 4. Simpan Konfigurasi Produk Promosi Official Platform
        if ($request->has('promoted_products')) {
            $rawProducts = $request->input('promoted_products', []);
            $cleanProducts = [];
            if (is_array($rawProducts)) {
                foreach ($rawProducts as $index => $item) {
                    $name = trim((string) ($item['name'] ?? ''));
                    $price = trim((string) ($item['price'] ?? ''));
                    if ($name === '' || $price === '') {
                        continue;
                    }

                    $existingImages = [];
                    if (! empty($item['images_existing'])) {
                        $decoded = json_decode((string) $item['images_existing'], true);
                        if (is_array($decoded)) {
                            $existingImages = array_values(array_filter($decoded));
                        }
                    } elseif (! empty($item['image_existing'])) {
                        $existingImages = [trim((string) $item['image_existing'])];
                    }

                    // Cek jika ada unggahan gambar file baru (single atau multiple)
                    if ($request->hasFile("promoted_products.{$index}.image_files")) {
                        $uploadedFiles = (array) $request->file("promoted_products.{$index}.image_files");
                        $newImages = [];
                        foreach ($uploadedFiles as $fIdx => $pImg) {
                            if ($pImg && $pImg->isValid()) {
                                $ext = $pImg->getClientOriginalExtension();
                                $pFileName = 'product_' . ($index + 1) . '_' . ($fIdx + 1) . '_' . time() . '.' . $ext;
                                $stored = $pImg->storeAs('platform/products', $pFileName, 'public');
                                $newImages[] = asset('storage/' . $stored);
                            }
                        }
                        if (! empty($newImages)) {
                            $existingImages = array_values(array_unique(array_merge($newImages, $existingImages)));
                        }
                    } elseif ($request->hasFile("promoted_products.{$index}.image_file")) {
                        $pImg = $request->file("promoted_products.{$index}.image_file");
                        if ($pImg && $pImg->isValid()) {
                            $ext = $pImg->getClientOriginalExtension();
                            $pFileName = 'product_' . ($index + 1) . '_' . time() . '.' . $ext;
                            $stored = $pImg->storeAs('platform/products', $pFileName, 'public');
                            array_unshift($existingImages, asset('storage/' . $stored));
                        }
                    }

                    if (empty($existingImages)) {
                        $existingImages = [asset('images/products/pomade.svg')];
                    }

                    $mainImage = $existingImages[0];

                    $featuresRaw = $item['features'] ?? [];
                    if (is_string($featuresRaw)) {
                        $featuresList = array_values(array_filter(array_map('trim', explode("\n", $featuresRaw))));
                    } elseif (is_array($featuresRaw)) {
                        $featuresList = array_values(array_filter(array_map('trim', $featuresRaw)));
                    } else {
                        $featuresList = [];
                    }

                    $cleanProducts[] = [
                        'id' => trim((string) ($item['id'] ?? ('prod-' . ($index + 1)))),
                        'name' => $name,
                        'price' => $price,
                        'original_price' => trim((string) ($item['original_price'] ?? '')),
                        'image' => $mainImage,
                        'images' => $existingImages,
                        'price_color' => trim((string) ($item['price_color'] ?? 'text-amber-400')),
                        'desc' => trim((string) ($item['desc'] ?? '')),
                        'features' => $featuresList,
                        'is_active' => isset($item['is_active']) ? (bool) $item['is_active'] : true,
                    ];
                }
            }

            if (! empty($cleanProducts)) {
                Setting::query()->withoutGlobalScope('tenant')->updateOrCreate(
                    ['tenant_id' => null, 'key' => 'promoted_products_json'],
                    ['value' => json_encode($cleanProducts, JSON_UNESCAPED_UNICODE)]
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
