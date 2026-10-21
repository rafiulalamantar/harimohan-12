@props(['fields', 'answers' => []])

@foreach ($fields as $f)
    @php($name = "fields.{$f->id}")
    @php($value = old($name, $answers[$f->id] ?? null))
    <div class="md:col-span-2 rounded-2xl bg-slate-50 ring-1 ring-slate-200/80 p-4 {{ $f->image ? 'grid sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-4 items-start' : '' }}">
        <div>
            <label for="field-{{ $f->id }}" class="block text-sm font-semibold text-slate-700 mb-1.5">
                {{ $f->label }} @if($f->is_required)<span class="text-rose-600">*</span>@endif
            </label>
            @php($inputClass = 'w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-slate-900 ring-1 ring-slate-200 shadow-xs focus:ring-2 focus:ring-brand-500 focus:outline-hidden transition')
            @if ($f->type === 'select')
                <select id="field-{{ $f->id }}" name="fields[{{ $f->id }}]" class="{{ $inputClass }}" @required($f->is_required)>
                    <option value="">Choose…</option>
                    @foreach ($f->options ?? [] as $option)
                        <option value="{{ $option }}" @selected((string) $value === (string) $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @elseif ($f->type === 'textarea')
                <textarea id="field-{{ $f->id }}" name="fields[{{ $f->id }}]" rows="3" class="{{ $inputClass }}" @required($f->is_required)>{{ $value }}</textarea>
            @else
                <input id="field-{{ $f->id }}" name="fields[{{ $f->id }}]" type="{{ $f->type === 'number' ? 'number' : 'text' }}" @if($f->type === 'number') step="any" min="0" @endif
                       value="{{ $value }}" class="{{ $inputClass }}" @required($f->is_required)>
            @endif
            @if ($f->help_text)<p class="text-xs text-slate-500 mt-1.5">{{ $f->help_text }}</p>@endif
            @error($name)<p class="text-sm text-rose-600 mt-1.5">{{ $message }}</p>@enderror
        </div>
        @if ($f->image)
            <a href="{{ $f->imageUrl() }}" target="_blank" rel="noopener" title="Open full size">
                <img src="{{ $f->imageUrl() }}" alt="{{ $f->label }} reference" class="w-full max-h-72 object-contain rounded-lg border bg-white">
                <span class="block text-xs text-brand-700 underline mt-1">Open full size</span>
            </a>
        @endif
    </div>
@endforeach
