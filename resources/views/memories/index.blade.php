@extends('layouts.app')
@section('title', 'Memories')

@section('content')
<div class="mb-6">
    <p class="text-sm font-semibold text-brand-700">Those School Days</p>
    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Memories</h1>
</div>

@if ($memories->isEmpty())
    <div class="rounded-2xl border border-dashed border-slate-300 py-12 text-center text-sm text-slate-500">No memories shared yet.</div>
@else
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach ($memories as $memory)
            <x-memory-card :memory="$memory" />
        @endforeach
    </div>
    <div class="mt-6">{{ $memories->links() }}</div>
@endif
@endsection
