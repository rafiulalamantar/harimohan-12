@extends('layouts.app')
@section('title', $event->exists ? 'Edit event' : 'New event')

@section('content')
<div class="max-w-3xl mx-auto bg-white rounded-2xl border p-6 md:p-8">
    <h1 class="text-2xl font-bold">{{ $event->exists ? 'Edit event' : 'New event' }}</h1>
    <form method="POST" class="mt-6 grid md:grid-cols-2 gap-4"
          action="{{ $event->exists ? route('admin.events.update', $event) : route('admin.events.store') }}">
        @csrf
        @if($event->exists) @method('PUT') @endif
        <div class="md:col-span-2"><x-field name="title" label="Title" :value="$event->title" required /></div>
        <div class="md:col-span-2"><x-field name="description" label="Description" type="textarea" :value="$event->description" /></div>
        <x-field name="event_date" label="Event date" type="date" :value="$event->event_date?->format('Y-m-d')" />
        <x-field name="venue" label="Venue" :value="$event->venue" />
        <x-field name="fee" label="Fee (BDT)" type="number" step="0.01" min="1" :value="$event->fee" required hint="Can be changed any time. Payments already made keep the amount that was paid." />
        <x-field name="payment_deadline" label="Payment deadline" type="date" :value="$event->payment_deadline?->format('Y-m-d')" />
        <label class="flex items-center gap-2 text-sm md:col-span-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $event->is_active))> Active (shown on the portal and accepts payments)
        </label>
        <div class="md:col-span-2"><button class="bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-lg px-6 py-2.5">Save</button></div>
    </form>
</div>
@endsection
