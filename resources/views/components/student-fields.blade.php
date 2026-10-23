@props(['fields', 'answers' => []])

@foreach ($fields as $f)
    @if (! $f->takesAnswer())
        @if ($f->image)
            {{-- Image-only: just the picture, tap to open full size. --}}
            <a href="{{ $f->imageUrl() }}" target="_blank" rel="noopener" title="{{ $f->label }}" class="md:col-span-2 block w-full max-w-md">
                <img src="{{ $f->imageUrl() }}" alt="{{ $f->label }}" loading="lazy" class="w-full max-h-80 object-contain rounded-xl border bg-white">
            </a>
        @endif
        @continue
    @endif
    @php($name = "fields.{$f->id}")
    @php($value = old($name, $f->answerInput($answers[$f->id] ?? null)))
    <div class="md:col-span-2 rounded-2xl bg-slate-50 ring-1 ring-slate-200/80 p-4">
        <div class="min-w-0">
            @php($inputClass = 'w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-slate-900 ring-1 ring-slate-200 shadow-xs focus:ring-2 focus:ring-brand-500 focus:outline-hidden transition')
            @if (in_array($f->type, ['radio', 'checkboxes'], true))
                <fieldset>
                    <legend class="block text-sm font-semibold text-slate-700 mb-1.5 break-words">
                        {{ $f->label }} @if($f->is_required)<span class="text-rose-600">*</span>@endif
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($f->options ?? [] as $option)
                            @php($isChosen = $f->type === 'checkboxes' ? in_array($option, (array) $value, true) : (string) $value === (string) $option)
                            <label class="inline-flex max-w-full cursor-pointer items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm text-slate-800 ring-1 ring-slate-200 shadow-xs has-checked:ring-2 has-checked:ring-brand-500 has-checked:bg-brand-50 transition">
                                @if ($f->type === 'checkboxes')
                                    <input type="checkbox" name="fields[{{ $f->id }}][]" value="{{ $option }}" @checked($isChosen) class="shrink-0 accent-brand-600">
                                @else
                                    <input type="radio" name="fields[{{ $f->id }}]" value="{{ $option }}" @checked($isChosen) @required($f->is_required) class="shrink-0 accent-brand-600">
                                @endif
                                <span class="break-words [overflow-wrap:anywhere]">{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @else
                <label for="field-{{ $f->id }}" class="block text-sm font-semibold text-slate-700 mb-1.5 break-words">
                    {{ $f->label }} @if($f->is_required)<span class="text-rose-600">*</span>@endif
                </label>
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
                    <input id="field-{{ $f->id }}" name="fields[{{ $f->id }}]" type="{{ in_array($f->type, ['number', 'date'], true) ? $f->type : 'text' }}" @if($f->type === 'number') step="any" min="0" @endif
                           value="{{ $value }}" class="{{ $inputClass }}" @required($f->is_required)>
                @endif
            @endif
            @if ($f->help_text)<p class="text-xs text-slate-500 mt-1.5">{{ $f->help_text }}</p>@endif
            <p class="text-xs text-slate-400 mt-1">Only admins see this answer.</p>
            @error($name)<p class="text-sm text-rose-600 mt-1.5">{{ $message }}</p>@enderror
            @error("{$name}.*")<p class="text-sm text-rose-600 mt-1.5">{{ $message }}</p>@enderror
        </div>
        @if ($f->image)
            <x-student-field-image :field="$f" caption="View reference image" />
        @endif
    </div>
@endforeach
