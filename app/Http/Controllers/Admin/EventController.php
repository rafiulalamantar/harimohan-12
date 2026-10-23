<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Models\ReunionEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Admins manage events; the portal shows the active one with the latest date. */
class EventController extends Controller
{
    public function index(): View
    {
        return view('admin.events.index', [
            'events' => ReunionEvent::withCount(['payments as paid_count' => fn ($payments) => $payments->completed()])
                ->withSum(['payments as collected' => fn ($payments) => $payments->completed()], 'amount')
                ->latest('event_date')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.events.form', ['event' => new ReunionEvent(['is_active' => true])]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        ReunionEvent::create($request->eventData());

        return redirect()->route('admin.events.index')->with('success', 'Event created.');
    }

    public function edit(ReunionEvent $event): View
    {
        return view('admin.events.form', compact('event'));
    }

    public function update(EventRequest $request, ReunionEvent $event): RedirectResponse
    {
        $event->update($request->eventData());

        return redirect()->route('admin.events.index')->with('success', 'Event updated.');
    }

    /** Soft-deletes so completed payments keep their event for the records. */
    public function destroy(ReunionEvent $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('admin.events.index')->with('success', 'Event deleted.');
    }
}
