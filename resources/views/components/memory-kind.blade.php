{{-- The post's type as a small badge. Remembering posts get a quiet grey badge instead of the brand green. --}}
@props(['memory'])

<span @class([
    'rounded-full px-2 py-0.5 font-semibold ring-1',
    'bg-slate-100 text-slate-700 ring-slate-200' => $memory->isRemembering(),
    'bg-brand-50 text-brand-800 ring-brand-100' => ! $memory->isRemembering(),
])>{{ $memory->kindLabel() }}</span>
