<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\Platform\PlatformConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

final class PlatformPromotionController extends Controller
{
    public function index(): View
    {
        $promotedProducts = PlatformConfig::promotedProducts();
        $slideDuration = PlatformConfig::promotedProductsSlideDuration();

        $colorOptions = [
            [
                'value' => 'text-amber-400',
                'label' => 'Kuning Emas',
                'sublabel' => 'text-amber-400',
                'color' => '#fbbf24',
            ],
            [
                'value' => 'text-emerald-400',
                'label' => 'Hijau Zamrud',
                'sublabel' => 'text-emerald-400',
                'color' => '#34d399',
            ],
            [
                'value' => 'text-sky-400',
                'label' => 'Biru Langit',
                'sublabel' => 'text-sky-400',
                'color' => '#38bdf8',
            ],
            [
                'value' => 'text-rose-400',
                'label' => 'Merah Mawar',
                'sublabel' => 'text-rose-400',
                'color' => '#fb7185',
            ],
            [
                'value' => 'text-white',
                'label' => 'Putih Bersih',
                'sublabel' => 'text-white',
                'color' => '#ffffff',
            ],
        ];

        $slideDurationOptions = [
            ['value' => '3', 'label' => '3 Detik (Cepat)'],
            ['value' => '5', 'label' => '5 Detik (Standar Rekomendasi)'],
            ['value' => '7', 'label' => '7 Detik (Sedang)'],
            ['value' => '10', 'label' => '10 Detik (Santai)'],
            ['value' => '15', 'label' => '15 Detik (Lambat)'],
        ];

        $statusOptions = [
            [
                'value' => '1',
                'label' => 'Aktif (Tampil di Slide Banner)',
                'color' => '#10b981',
            ],
            [
                'value' => '0',
                'label' => 'Nonaktif (Disembunyikan)',
                'color' => '#9ca3af',
            ],
        ];

        $stockStatusOptions = [
            [
                'value' => 'ready',
                'label' => 'Tersedia (Ready Stock)',
                'color' => '#10b981',
            ],
            [
                'value' => 'habis',
                'label' => 'Stok Habis (Out of Stock)',
                'color' => '#ef4444',
            ],
        ];

        return view('platform.promotions', [
            'promotedProducts' => $promotedProducts,
            'slideDuration' => $slideDuration,
            'colorOptions' => $colorOptions,
            'slideDurationOptions' => $slideDurationOptions,
            'statusOptions' => $statusOptions,
            'stockStatusOptions' => $stockStatusOptions,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'promoted_products_slide_seconds' => ['nullable', 'integer', 'min:2', 'max:60'],
            'promoted_products' => ['nullable', 'array'],
            'promoted_products.*.id' => ['nullable', 'string', 'max:50'],
            'promoted_products.*.name' => ['nullable', 'string', 'max:150'],
            'promoted_products.*.tagline' => ['nullable', 'string', 'max:150'],
            'promoted_products.*.price' => ['nullable', 'string', 'max:50'],
            'promoted_products.*.original_price' => ['nullable', 'string', 'max:50'],
            'promoted_products.*.price_color' => ['nullable', 'string', 'max:50'],
            'promoted_products.*.url' => ['nullable', 'string', 'max:500'],
            'promoted_products.*.is_active' => ['nullable'],
            'promoted_products.*.stock_status' => ['nullable', 'string', 'in:ready,habis,in_stock,out_of_stock'],
            'promoted_products.*.image_existing' => ['nullable', 'string', 'max:255'],
            'promoted_products.*.images_existing' => ['nullable', 'string'],
            'promoted_products.*.image_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'promoted_products.*.image_files.*' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'promoted_products.*.desc' => ['nullable', 'string', 'max:500'],
            'promoted_products.*.features' => ['nullable', 'string', 'max:500'],
        ], [
            'promoted_products.*.image_file.mimes' => 'Format file foto harus berupa PNG, JPG, JPEG, WEBP, atau SVG.',
            'promoted_products.*.image_file.max' => 'Ukuran file foto melebihi batas maksimal 2MB (2048 KB). Sistem menolak berkas ini.',
            'promoted_products.*.image_files.*.mimes' => 'Format file foto harus berupa PNG, JPG, JPEG, WEBP, atau SVG.',
            'promoted_products.*.image_files.*.max' => 'Ukuran salah satu file foto melebihi batas maksimal 2MB (2048 KB). Sistem menolak berkas ini.',
            'promoted_products.*.image_files.*.file' => 'Berkas gambar yang diunggah tidak valid atau rusak.',
        ]);

        $slideSeconds = (int) ($validated['promoted_products_slide_seconds'] ?? 5);

        Setting::query()->withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => null, 'key' => 'promoted_products_slide_seconds'],
            ['value' => (string) $slideSeconds]
        );

        if (isset($validated['promoted_products']) && is_array($validated['promoted_products'])) {
            $formattedProducts = [];
            foreach ($validated['promoted_products'] as $idx => $prodData) {
                if (empty(trim((string) ($prodData['name'] ?? '')))) {
                    continue;
                }

                $imagesList = [];
                if (!empty($prodData['images_existing'])) {
                    $decoded = json_decode((string) $prodData['images_existing'], true);
                    if (is_array($decoded)) {
                        $imagesList = $decoded;
                    }
                }

                if ($request->hasFile("promoted_products.{$idx}.image_file")) {
                    $file = $request->file("promoted_products.{$idx}.image_file");
                    $path = $file->store('platform/products', 'public');
                    $newUrl = Storage::disk('public')->url($path);
                    array_unshift($imagesList, $newUrl);
                }

                if ($request->hasFile("promoted_products.{$idx}.image_files")) {
                    foreach ($request->file("promoted_products.{$idx}.image_files") as $multiFile) {
                        $path = $multiFile->store('platform/products', 'public');
                        $imagesList[] = Storage::disk('public')->url($path);
                    }
                }

                $imagesList = array_values(array_unique(array_filter($imagesList)));

                $mainImage = !empty($imagesList)
                    ? $imagesList[0]
                    : (!empty($prodData['image_existing'])
                        ? (string) $prodData['image_existing']
                        : asset('images/products/pomade.svg'));

                if (empty($imagesList)) {
                    $imagesList = [$mainImage];
                }

                $featuresRaw = (string) ($prodData['features'] ?? '');
                $featuresArr = array_values(array_filter(
                    array_map('trim', explode("\n", str_replace(["\r\n", "\r"], "\n", $featuresRaw)))
                ));

                $isActiveVal = $prodData['is_active'] ?? null;
                $isActive = ($isActiveVal === '1' || $isActiveVal === 1 || $isActiveVal === true || $isActiveVal === 'on');

                $stockStatusRaw = (string) ($prodData['stock_status'] ?? 'ready');
                $stockStatus = in_array($stockStatusRaw, ['habis', 'out_of_stock'], true) ? 'habis' : 'ready';

                $formattedProducts[] = [
                    'id' => (string) ($prodData['id'] ?? ('prod-' . ($idx + 1))),
                    'name' => trim((string) ($prodData['name'] ?? 'Produk Official')),
                    'tagline' => trim((string) ($prodData['tagline'] ?? '')),
                    'price' => trim((string) ($prodData['price'] ?? 'Rp0')),
                    'original_price' => trim((string) ($prodData['original_price'] ?? '')),
                    'price_color' => (string) ($prodData['price_color'] ?? 'text-amber-400'),
                    'url' => trim((string) ($prodData['url'] ?? '')) ?: 'https://shopee.co.id',
                    'image' => $mainImage,
                    'images' => $imagesList,
                    'is_active' => $isActive,
                    'stock_status' => $stockStatus,
                    'desc' => trim((string) ($prodData['desc'] ?? '')),
                    'features' => $featuresArr,
                ];
            }

            Setting::query()->withoutGlobalScope('tenant')->updateOrCreate(
                ['tenant_id' => null, 'key' => 'promoted_products_json'],
                ['value' => json_encode($formattedProducts, JSON_UNESCAPED_UNICODE)]
            );
        }

        PlatformConfig::clearCache();

        return redirect()
            ->route('platform.promotions')
            ->with('status', 'Katalog iklan produk official platform berhasil diperbarui.');
    }
}
