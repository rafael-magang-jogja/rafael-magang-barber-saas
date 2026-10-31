<x-layouts.display :title="'Antrean ' . $tenant->name . ' - ' . $branch->name">
    <livewire:tv-queue-display :tenant-slug="$tenant->slug" :branch-code="$branch->code" />
</x-layouts.display>
