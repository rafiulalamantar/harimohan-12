@extends('layouts.app')
@section('title', 'Welcome page')

@section('content')
<div class="max-w-3xl mx-auto bg-white rounded-2xl border p-6 md:p-8">
    <h1 class="text-2xl font-bold">Welcome page</h1>
    <p class="text-sm text-gray-500 mt-1">The banner at the top of the public home page. The event card below the text comes from the active event under <a href="{{ route('admin.events.index') }}" class="text-brand-700 underline">Events</a>.</p>
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
        @csrf @method('PUT')
        <x-field name="hero_title" label="Heading" :value="$settings['hero_title']" required />
        <x-field name="hero_subtitle" label="Text" type="textarea" :value="$settings['hero_subtitle']" />
        @if ($settings['hero_banner'])
            <div>
                <img src="{{ \App\Models\Setting::bannerUrl($settings['hero_banner']) }}" alt="" class="w-full max-h-64 object-cover rounded-lg border">
                <label class="flex items-center gap-2 text-sm mt-2">
                    <input type="checkbox" name="remove_banner" value="1"> Remove banner image
                </label>
            </div>
        @endif
        <x-field name="hero_banner" :label="$settings['hero_banner'] ? 'Change banner image' : 'Banner image'" type="file" accept="image/*"
                 hint="Optional. The whole image is shown full width above the text, nothing is cropped. Max 4 MB" />
        <button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg px-6 py-2.5">Save</button>
    </form>
</div>
@endsection
