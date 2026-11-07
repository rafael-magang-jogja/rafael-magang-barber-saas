<x-layouts.display :title="'Antrean ' . $tenant->name . ' - ' . $branch->name" :tenant="$tenant">
    <livewire:tv-queue-display :tenant-slug="$tenant->slug" :branch-code="$branch->code" />
</x-layouts.display>
