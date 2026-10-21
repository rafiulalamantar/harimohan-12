@extends('layouts.app')
@section('title', 'Forgot password')

@section('content')
<div class="max-w-md mx-auto mt-4 sm:mt-10 rounded-3xl bg-white p-6 md:p-10 ring-1 ring-slate-200/80 shadow-soft">
    <span class="grid place-items-center w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-800 text-white text-lg font-extrabold">H</span>
    <h1 class="mt-5 text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Forgot password?</h1>
    <p class="mt-1 text-sm text-slate-500">Enter the email you registered with and we'll send you a link to set a new password.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <x-field name="email" label="Email" type="email" required autofocus />
        <button class="w-full rounded-full bg-slate-900 hover:bg-brand-800 text-white font-semibold py-3 shadow-lg shadow-slate-900/10 transition">Send reset link</button>
        <p class="text-sm text-center text-slate-500">Remembered it? <a class="font-semibold text-brand-700 hover:underline" href="{{ route('login') }}">Back to login</a></p>
    </form>
</div>
@endsection
