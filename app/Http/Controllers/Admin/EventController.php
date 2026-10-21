<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReunionEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index()
    {
        return view('admin.events.index', [
            'events' => ReunionEvent::withCount(['payments as paid_count' => fn ($q) => $q->where('status', 'completed')])
                ->withSum(['payments as collected' => fn ($q) => $q->where('status', 'completed')], 'amount')
                ->latest('event_date')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.events.form', ['event' => new ReunionEvent(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        ReunionEvent::create($this->validated($request));

        return redirect()->route('admin.events.index')->with('success', 'Event created.');
    }

    public function edit(ReunionEvent $event)
    {
        return view('admin.events.form', compact('event'));
    }

    public function update(Request $request, ReunionEvent $event)
    {
        $event->update($this->validated($request));

        return redirect()->route('admin.events.index')->with('success', 'Event updated.');
    }

    /** Soft-deletes so completed payments keep their event for the records. */
    public function destroy(ReunionEvent $event)
    {
        $event->delete();

        return redirect()->route('admin.events.index')->with('success', 'Event deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_date' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:200'],
            'fee' => ['required', 'numeric', 'min:1', 'max:999999'],
            'payment_deadline' => ['nullable', 'date', Rule::when($request->filled('event_date'), ['before_or_equal:event_date'])],
            'is_active' => ['nullable', 'boolean'],
        ], ['payment_deadline.before_or_equal' => 'The payment deadline must be on or before the event date.']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
