@props(['user'])

@if ($user->photoUrl())
    <img src="{{ $user->photoUrl() }}" alt="{{ $user->name }}" {{ $attributes->merge(['class' => 'rounded-full object-cover shrink-0']) }}>
@else
    <span {{ $attributes->merge(['class' => 'rounded-full bg-brand-100 text-brand-800 font-semibold flex items-center justify-center shrink-0']) }}>{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
@endif
