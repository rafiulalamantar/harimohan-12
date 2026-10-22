<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnnouncementController extends Controller
{
    public function index()
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::orderByDesc('is_pinned')->latest()->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.announcements.form', ['announcement' => new Announcement(['published_at' => now()])]);
    }

    public function store(Request $request)
    {
        Announcement::create($this->validated($request) + ['user_id' => $request->user()->id]);

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement published.');
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.form', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $this->validated($request);

        if (isset($data['banner']) || $request->boolean('remove_banner')) {
            $this->deleteBanner($announcement);
            $data['banner'] ??= null;
        }

        $announcement->update($data);

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement)
    {
        $this->deleteBanner($announcement);
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'body' => ['required', 'string', 'max:10000'],
            'published_at' => ['nullable', 'date'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);
        $data['is_pinned'] = $request->boolean('is_pinned');

        if ($request->hasFile('banner')) {
            $data['banner'] = $request->file('banner')->store('announcements', 'public');
        } else {
            unset($data['banner']);
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
