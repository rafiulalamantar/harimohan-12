@props(['field'])

{{-- An image-only student field, e.g. a T-shirt size chart: a title, optional help text and the picture. --}}
<h3 class="text-sm font-semibold text-slate-700 break-words">{{ $field->label }}</h3>
@if ($field->help_text)<p class="text-xs text-slate-500 mt-1">{{ $field->help_text }}</p>@endif
<a href="{{ $field->imageUrl() }}" target="_blank" rel="noopener" title="Open full size" class="mt-2 block">
    <img src="{{ $field->imageUrl() }}" alt="{{ $field->label }}" class="w-full max-h-[32rem] object-contain rounded-lg border bg-white">
    <span class="block text-xs text-brand-700 underline mt-1">Open full size</span>
</a>
