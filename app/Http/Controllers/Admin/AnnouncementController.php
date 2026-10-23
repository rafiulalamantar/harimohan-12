<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Admins post notices to the welcome page and the member portal. */
class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::orderByDesc('is_pinned')->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.announcements.form', ['announcement' => new Announcement(['published_at' => now()])]);
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        Announcement::create($this->announcementData($request) + ['user_id' => $request->user()->id]);

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement published.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.announcements.form', compact('announcement'));
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->announcementData($request);

        if (isset($data['banner']) || $request->boolean('remove_banner')) {
            $this->deleteBanner($announcement);
            $data['banner'] ??= null;
        }

        $announcement->update($data);

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->deleteBanner($announcement);
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    /**
     * The notice's columns, with the uploaded banner stored when there is one.
     *
     * @return array<string, mixed>
     */
    private function announcementData(AnnouncementRequest $request): array
    {
        $data = $request->announcementData();

        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('announcements', 'public');
        }

        return $data;
    }

    private function deleteBanner(Announcement $announcement): void
    {
        if ($announcement->banner) {
            Storage::disk('public')->delete($announcement->banner);
        }
    }
}
