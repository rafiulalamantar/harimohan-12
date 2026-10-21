<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ReunionEvent;
use App\Models\StudentField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $event = ReunionEvent::current();

        return view('portal.dashboard', [
            'user' => $user,
            'event' => $event,
            'hasPaid' => $event ? $user->hasPaidFor($event) : false,
            'announcements' => Announcement::feed()->take(5)->get(),
            'missingFields' => $user->missingRequiredFields(),
        ]);
    }

    public function announcements()
    {
        return view('portal.announcements', [
            'announcements' => Announcement::feed()->paginate(10),
        ]);
    }

    public function showAnnouncement(Announcement $announcement)
    {
        abort_if($announcement->published_at === null || $announcement->published_at->isFuture(), 404);

        return view('portal.announcement', compact('announcement'));
    }

    public function payments(Request $request)
    {
        return view('portal.payments', [
            'payments' => $request->user()->payments()->with('event')->latest()->paginate(15),
        ]);
    }

    public function editProfile(Request $request)
    {
        return view('portal.profile', [
            'user' => $request->user(),
            'fields' => StudentField::active()->get(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $fields = StudentField::active()->get();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^01[3-9]\d{8}$/', Rule::unique('users')->ignore($user->id)],
            'profession' => ['nullable', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'present_address' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
            ...StudentField::rulesFor($fields),
        ], [], StudentField::attributeNamesFor($fields));

        $user->saveFieldValues($data['fields'] ?? []);
        unset($data['fields']);

        if ($request->hasFile('photo')) {
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            $data['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }
}
