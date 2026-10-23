@extends('layouts.app')
@section('title', 'Profile')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-2xl border p-6 md:p-8">
    <div class="flex items-center gap-4">
        <x-avatar :user="$user" class="w-16 h-16 text-2xl" />
        <div>
            <h1 class="text-2xl font-bold">My Profile</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $user->email }} · SSC batch {{ $user->ssc_batch }} (ask an admin to change your batch)</p>
        </div>
    </div>
    <form method="POST" action="{{ route('portal.profile.update') }}" enctype="multipart/form-data" class="mt-6 grid md:grid-cols-2 gap-4">
        @csrf @method('PUT')
        <x-field name="name" label="Full name" :value="$user->name" required />
        <x-field name="phone" label="Mobile" :value="$user->phone" required placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="11" pattern="01[3-9][0-9]{8}" title="11-digit mobile number, like 01XXXXXXXXX" hint="11 digits, e.g. 01712345678" />
        <x-field name="blood_group" label="Blood group" type="select" :options="\App\Models\User::BLOOD_GROUPS" :value="$user->blood_group" />
        <x-field name="profession" label="Profession" :value="$user->profession" />
        <x-field name="company_name" label="Company / organization" :value="$user->company_name" />
        <x-field name="present_address" label="Present address" :value="$user->present_address" />
        <x-field name="linkedin_url" label="LinkedIn URL" type="url" :value="$user->linkedin_url" placeholder="https://www.linkedin.com/in/..." />
        <x-field name="facebook_url" label="Facebook URL" type="url" :value="$user->facebook_url" placeholder="https://www.facebook.com/..." />
        <div class="md:col-span-2"><x-field name="photo" label="Photo" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,.heic,.heif" data-thumbnail="photo_thumbnail" hint="Uploading a new photo replaces the old one. JPG, PNG, WebP or iPhone photo (converted to JPG). Large photos are resized automatically." /><input type="file" name="photo_thumbnail" accept="image/jpeg" hidden aria-hidden="true" tabindex="-1"></div>
        <x-student-fields :fields="$fields" :answers="$user->fieldAnswers()" />
        <div class="md:col-span-2"><button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg px-6 py-2.5">Save</button></div>
    </form>
</div>
@endsection
