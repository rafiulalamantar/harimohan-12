{{--
    One header menu link from App\Services\MainMenu ($link has url, label and isActive).
    The link of the page that is open is highlighted and marked aria-current for screen readers.

    $variant says where the link sits:
      "bar"          plain link in the wide-screen menu bar
      "dropdown"     inside a labelled dropdown of the menu bar
      "panel"        inside the ☰ menu
      "beside-login" a guest link next to Login (hidden on phones, where it is in the ☰ menu)
--}}
@props(['link', 'variant'])

@php
    $shape = match ($variant) {
        'bar' => 'whitespace-nowrap rounded-full px-3 py-1.5 transition',
        'dropdown' => 'block rounded-xl px-3 py-2',
        'panel' => 'block rounded-xl px-3 py-2 font-medium',
        'beside-login' => 'hidden sm:inline-block whitespace-nowrap rounded-full px-4 py-1.5 transition',
    };
    $idleColours = match ($variant) {
        'bar' => 'text-slate-600 hover:text-slate-900 hover:bg-slate-100',
        'dropdown', 'panel' => 'text-slate-700 hover:bg-slate-50',
        'beside-login' => 'text-slate-700 hover:bg-slate-100',
    };
@endphp

<a href="{{ $link['url'] }}"
   @if ($link['isActive']) aria-current="page" @endif
   @class([$shape, 'bg-brand-50 text-brand-800' => $link['isActive'], $idleColours => ! $link['isActive']])>{{ $link['label'] }}</a>
