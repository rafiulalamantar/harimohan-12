@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-semibold text-slate-700 mb-1.5">
        {{ $label }} @if($required)<span class="text-rose-600">*</span>@endif
    </label>
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="6" @required($required)
            {{ $attributes->merge(['class' => 'w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-slate-900 ring-1 ring-slate-200 shadow-sm placeholder:text-slate-400 file:mr-3 file:rounded-full file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-sm file:font-semibold file:text-brand-800 focus:ring-2 focus:ring-brand-500 focus:outline-none transition']) }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" @required($required)
            {{ $attributes->merge(['class' => 'w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-slate-900 ring-1 ring-slate-200 shadow-sm placeholder:text-slate-400 file:mr-3 file:rounded-full file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-sm file:font-semibold file:text-brand-800 focus:ring-2 focus:ring-brand-500 focus:outline-none transition']) }}>
    @endif
    @if ($hint)<p class="text-xs text-slate-500 mt-1.5">{{ $hint }}</p>@endif
    @error($name)<p class="text-sm text-rose-600 mt-1.5">{{ $message }}</p>@enderror
</div>
