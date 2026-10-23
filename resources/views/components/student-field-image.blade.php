@props(['field', 'caption' => 'Tap to view full size'])

{{-- A field's picture (e.g. a T-shirt size chart) as a small thumbnail that opens full size. --}}
<a href="{{ $field->imageUrl() }}" target="_blank" rel="noopener"
   class="mt-2 flex max-w-sm items-center gap-3 rounded-xl bg-white p-2 ring-1 ring-slate-200 shadow-xs hover:ring-brand-300 transition">
    <img src="{{ $field->imageUrl() }}" alt="{{ $field->label }}" loading="lazy" class="h-16 w-16 shrink-0 rounded-lg border object-cover object-top">
    <span class="min-w-0 text-xs">
        <span class="block font-semibold text-slate-700">🔍 {{ $caption }}</span>
        <span class="block text-slate-500">Opens the image in a new tab</span>
    </span>
</a>
