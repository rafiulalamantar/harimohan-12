<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Announcement;
use App\Models\ReunionEvent;
use App\Models\StudentField;
use App\Models\User;
use App\Services\PhotoThumbnail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

/** The member portal: dashboard, notices, payment history and My Profile. */
class PortalController extends Controller
{
    public function dashboard(Request $request): View
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

    public function announcements(): View
    {
        return view('portal.announcements', [
            'announcements' => Announcement::feed()->paginate(10),
        ]);
    }

    /** Drafts and scheduled notices are not visible yet. */
    public function showAnnouncement(Announcement $announcement): View
    {
        abort_if($announcement->published_at === null || $announcement->published_at->isFuture(), 404);

        return view('portal.announcement', compact('announcement'));
    }

    public function payments(Request $request): View
    {
        return view('portal.payments', [
            'payments' => $request->user()->payments()->with('event')->latest()->paginate(15),
        ]);
    }

    public function editProfile(Request $request): View
    {
        return view('portal.profile', [
            'user' => $request->user(),
            'fields' => StudentField::active()->get(),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->profileData();
        $answers = $request->fieldAnswers();

        if (! $request->hasFile('photo') && ! $this->answersChanged($user, $answers) && ! $user->fill($data)->isDirty()) {
            return back()->with('info', 'No changes to save.');
        }

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

    /**
     * @param  array<int, ?string>  $answers  keyed by student field id
     */
    private function answersChanged(User $user, array $answers): bool
    {
        $savedAnswers = $user->fieldAnswers();

        return collect($answers)->contains(fn (?string $answer, int $fieldId) => ($savedAnswers[$fieldId] ?? null) !== $answer);
    }
}
