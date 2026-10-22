@extends('layouts.app')
@section('title', 'Register')

@section('content')
<div class="max-w-2xl mx-auto rounded-3xl bg-white p-6 md:p-10 ring-1 ring-slate-200/80 shadow-soft">
    <p class="text-sm font-semibold text-brand-700">Join {{ config('app.name') }}</p>
    <h1 class="mt-1 text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Ex-student registration</h1>
    <p class="text-sm text-slate-500 mt-2">The event payment option will be enabled once an admin verifies your registration.</p>

    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" class="mt-8 grid md:grid-cols-2 gap-5">
        @csrf
        <div class="md:col-span-2"><x-field name="name" label="Full name" required /></div>
        <x-field name="email" label="Email" type="email" required />
        <x-field name="phone" label="Mobile (bKash number preferred)" required placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="11" pattern="01[3-9][0-9]{8}" title="11-digit mobile number, like 01XXXXXXXXX" hint="11 digits, e.g. 01712345678" />
        <x-field name="ssc_batch" label="SSC batch (year)" type="number" required min="1950" max="{{ date('Y') }}" placeholder="2010" />
        <x-field name="profession" label="Profession (optional)" />
        <x-field name="company_name" label="Company / organization (optional)" />
        <x-field name="present_address" label="Present address (optional)" />
        <x-field name="linkedin_url" label="LinkedIn URL (optional)" type="url" placeholder="https://www.linkedin.com/in/..." />
        <x-field name="facebook_url" label="Facebook URL (optional)" type="url" placeholder="https://www.facebook.com/..." />
        <div class="md:col-span-2"><x-field name="photo" label="Photo (optional)" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,.heic,.heif" data-thumbnail="photo_thumbnail" hint="JPG, PNG, WebP or iPhone photo (converted to JPG). Large photos are resized automatically." /><input type="file" name="photo_thumbnail" accept="image/jpeg" hidden aria-hidden="true" tabindex="-1"></div>
        <x-student-fields :fields="$fields" />
        <x-field name="password" label="Password" type="password" required hint="At least 8 characters" />
        <x-field name="password_confirmation" label="Confirm password" type="password" required />
        <div class="md:col-span-2">
            <button class="w-full rounded-full bg-slate-900 hover:bg-brand-800 text-white font-semibold py-3 shadow-lg shadow-slate-900/10 transition">Register</button>
            <p class="text-sm text-center text-slate-500 mt-4">Already registered? <a class="font-semibold text-brand-700 hover:underline" href="{{ route('login') }}">Login</a></p>
        </div>
    </form>
</div>
@endsection
