<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ReunionEvent;
use App\Models\StudentField;
use App\Models\User;
use App\Services\PhotoThumbnail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

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
        $request->merge(['phone' => User::normalizePhone($request->input('phone'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'regex:'.User::NAME_PATTERN],
            'phone' => ['required', 'regex:/^01[3-9]\d{8}$/', Rule::unique('users')->ignore($user->id)],
            'profession' => ['nullable', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'present_address' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'photo_thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg', 'max:512'],
            ...StudentField::rulesFor($fields),
        ], [
            'phone.regex' => 'Mobile number must be 11 digits, like 01XXXXXXXXX.',
            'name.regex' => User::NAME_MESSAGE,
        ], StudentField::attributeNamesFor($fields));

        $answers = $data['fields'] ?? [];
        unset($data['fields'], $data['photo_thumbnail']);

        // Store the new photo first and only remove the old one once everything has saved,
        // so a failure never leaves the student without a photo.
        $oldPhotos = array_filter([$user->photo, $user->photo_thumb]);
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('photos', 'public');
            $data['photo_thumb'] = PhotoThumbnail::forUpload($request->file('photo_thumbnail'), $data['photo']);
        }

        try {
            DB::transaction(function () use ($user, $data, $answers) {
                $user->update($data);
                $user->saveFieldValues($answers);
            });
        } catch (Throwable $e) {
            if (isset($data['photo'])) {
                Storage::disk('public')->delete(array_filter([$data['photo'], $data['photo_thumb']]));
            }

            throw $e;
        }

        if (isset($data['photo']) && $oldPhotos) {
            Storage::disk('public')->delete($oldPhotos);
        }

        return back()->with('success', 'Profile updated.');
    }
}
