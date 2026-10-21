@extends('layouts.app')
@section('title', 'Reset password')

@section('content')
<div class="max-w-md mx-auto mt-4 sm:mt-10 rounded-3xl bg-white p-6 md:p-10 ring-1 ring-slate-200/80 shadow-soft">
    <span class="grid place-items-center w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-800 text-white text-lg font-extrabold">H</span>
    <h1 class="mt-5 text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Set a new password</h1>
    <p class="mt-1 text-sm text-slate-500">Choose a new password for your {{ config('app.name') }} account.</p>
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" label="Email" type="email" :value="$email" required />
        <x-field name="password" label="New password" type="password" required autofocus hint="At least 8 characters" />
        <x-field name="password_confirmation" label="Confirm new password" type="password" required />
        <button class="w-full rounded-full bg-slate-900 hover:bg-brand-800 text-white font-semibold py-3 shadow-lg shadow-slate-900/10 transition">Reset password</button>
    </form>
</div>
@endsection
