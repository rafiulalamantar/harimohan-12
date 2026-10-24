<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemoryRequest;
use App\Models\Memory;
use App\Models\MemoryPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Admins share memories (photos + a story) on the welcome page's Memories section. */
class MemoryController extends Controller
{
    public function index(): View
    {
        return view('admin.memories.index', [
            'memories' => Memory::feed()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.memories.form', ['memory' => new Memory(['kind' => Memory::KIND_MEMORY])]);
    }

    public function store(MemoryRequest $request): RedirectResponse
    {
        $memory = Memory::create($request->memoryData() + ['user_id' => $request->user()->id]);
        $this->storePhotos($request, $memory);

        return redirect()->route('admin.memories.index')->with('success', 'Memory shared on the welcome page.');
    }

    public function edit(Memory $memory): View
    {
        return view('admin.memories.form', ['memory' => $memory->load('photos')]);
    }

    public function update(MemoryRequest $request, Memory $memory): RedirectResponse
    {
        $memory->update($request->memoryData());

        // whereIn on the post's own photos, so an id from another post is ignored.
        $memory->photos()->whereIn('id', $request->input('remove_photos', []))->get()
            ->each(fn (MemoryPhoto $photo) => $photo->deleteWithFile());

        $this->storePhotos($request, $memory);

        return redirect()->route('admin.memories.index')->with('success', 'Memory updated.');
    }

    public function destroy(Memory $memory): RedirectResponse
    {
        $memory->photos->each(fn (MemoryPhoto $photo) => $photo->deleteWithFile());
        $memory->delete();

        return back()->with('success', 'Memory deleted.');
    }

    /** Saves the uploaded pictures under memories/ (random names, so cached copies never go stale). */
    private function storePhotos(MemoryRequest $request, Memory $memory): void
    {
        foreach ($request->file('photos', []) as $file) {
            $memory->photos()->create(['path' => $file->store('memories', 'public')]);
        }
    }
}
