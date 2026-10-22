@extends('layouts.app')
@section('title', $announcement->exists ? 'Edit announcement' : 'New announcement')

@section('content')
<div class="max-w-3xl mx-auto bg-white rounded-2xl border p-6 md:p-8">
    <h1 class="text-2xl font-bold">{{ $announcement->exists ? 'Edit announcement' : 'New announcement' }}</h1>
    <form method="POST" enctype="multipart/form-data" class="mt-6 space-y-4"
          action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}">
        @csrf
        @if($announcement->exists) @method('PUT') @endif
        <x-field name="title" label="Title" :value="$announcement->title" required />
        @if ($announcement->banner)
            <div>
                <img src="{{ $announcement->bannerUrl() }}" alt="" class="w-full max-h-64 object-cover rounded-lg border">
                <label class="flex items-center gap-2 text-sm mt-2">
                    <input type="checkbox" name="remove_banner" value="1"> Remove banner
                </label>
            </div>
        @endif
        <x-field name="banner" :label="$announcement->banner ? 'Change banner' : 'Banner image'" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,.heic,.heif"
                 hint="Optional. A wide image (e.g. 1200×400) looks best. Max 4 MB" />
        <x-field name="body" label="Details" type="textarea" :value="$announcement->body" required />
        <x-field name="published_at" label="Publish time" type="datetime-local"
                 :value="$announcement->published_at?->format('Y-m-d\TH:i')"
                 hint="Leave empty to keep as draft. Set a future time to show it to students from then on." />
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_pinned" value="0">
            <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned))> Pin to top
        </label>
        <button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg px-6 py-2.5">Save</button>
    </form>
</div>
@endsection
