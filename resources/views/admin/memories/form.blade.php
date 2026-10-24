@extends('layouts.app')
@section('title', $memory->exists ? 'Edit memory' : 'New memory')

@section('content')
<div class="max-w-3xl mx-auto bg-white rounded-2xl border p-6 md:p-8">
    <h1 class="text-2xl font-bold">{{ $memory->exists ? 'Edit memory' : 'New memory' }}</h1>
    <form method="POST" enctype="multipart/form-data" class="mt-6 space-y-4" data-submit-once
          action="{{ $memory->exists ? route('admin.memories.update', $memory) : route('admin.memories.store') }}">
        @csrf
        @if($memory->exists) @method('PUT') @endif

        <fieldset>
            <legend class="block text-sm font-semibold text-slate-700 mb-1.5">Type <span class="text-rose-600">*</span></legend>
            <div class="flex flex-wrap gap-4 text-sm">
                @foreach (\App\Models\Memory::KINDS as $kind => $label)
                    <label class="flex items-center gap-2">
                        <input type="radio" name="kind" value="{{ $kind }}" @checked(old('kind', $memory->kind) === $kind) required> {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('kind')<p class="text-sm text-rose-600 mt-1.5">{{ $message }}</p>@enderror
        </fieldset>

        <x-field name="title" label="Title" :value="$memory->title" required maxlength="150" />
        <x-field name="story" label="Story" type="textarea" :value="$memory->story"
                 hint="Optional if you add photos. Press Enter for a new line; line breaks are kept on the welcome page." />

        @if ($memory->photos->isNotEmpty())
            <div>
                <p class="text-sm font-semibold text-slate-700 mb-1.5">Current photos</p>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                    @foreach ($memory->photos as $photo)
                        <label class="block text-xs">
                            <img src="{{ $photo->url() }}" alt="" data-fallback class="w-full aspect-square object-cover rounded-lg border">
                            <span class="mt-1 flex items-center gap-1.5">
                                <input type="checkbox" name="remove_photos[]" value="{{ $photo->id }}" @checked(in_array($photo->id, old('remove_photos', [])))> Remove
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Not <x-field>: the input name is photos[] (many files), and errors come from photos and photos.*. --}}
        <div>
            <label for="photos" class="block text-sm font-semibold text-slate-700 mb-1.5">{{ $memory->photos->isNotEmpty() ? 'Add photos' : 'Photos' }}</label>
            <input id="photos" name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,.heic,.heif"
                   class="w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-slate-900 ring-1 ring-slate-200 shadow-xs file:mr-3 file:rounded-full file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-sm file:font-semibold file:text-brand-800 focus:ring-2 focus:ring-brand-500 focus:outline-hidden transition">
            <p class="text-xs text-slate-500 mt-1.5">Up to {{ \App\Models\Memory::MAX_PHOTOS }} photos per memory. Pick several at once; big photos are shrunk before upload.</p>
            @error('photos')<p class="text-sm text-rose-600 mt-1.5">{{ $message }}</p>@enderror
            @foreach ($errors->get('photos.*') as $messages)
                <p class="text-sm text-rose-600 mt-1.5">{{ $messages[0] }}</p>
            @endforeach
        </div>

        <button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg px-6 py-2.5">Save</button>
    </form>
</div>
@endsection
