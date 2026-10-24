<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PhotoNotSavedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MemoryRequest;
use App\Models\Memory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admins share memories (photos + a story) on the welcome page's Memories section.
 * Validation lives in MemoryRequest; saving and deleting photos lives on the Memory model.
 *
 * Saves run in a database transaction: if a photo can't be stored (PhotoNotSavedException), nothing is saved
 * and the admin is sent back to the form, with what they typed, to try again.
 */
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
        try {
            DB::transaction(function () use ($request) {
                $memory = Memory::create($request->memoryData() + ['user_id' => $request->user()->id]);
                $memory->addPhotos($request->newPhotos(), $request->newPhotoCardCopies());
            });
        } catch (PhotoNotSavedException $exception) {
            return back()->withInput()->withErrors(['photos' => $exception->getMessage()]);
        }

        return redirect()->route('admin.memories.index')->with('success', 'Memory shared on the welcome page.');
    }

    public function edit(Memory $memory): View
    {
        return view('admin.memories.form', ['memory' => $memory->load('photos')]);
    }

    public function update(MemoryRequest $request, Memory $memory): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $memory) {
                $memory->update($request->memoryData());
                // New photos first: if they fail, the photos ticked for removal are still there.
                $memory->addPhotos($request->newPhotos(), $request->newPhotoCardCopies());
                $memory->removePhotos($request->photoIdsToRemove());
            });
        } catch (PhotoNotSavedException $exception) {
            return back()->withInput()->withErrors(['photos' => $exception->getMessage()]);
        }

        return redirect()->route('admin.memories.index')->with('success', 'Memory updated.');
    }

    public function destroy(Memory $memory): RedirectResponse
    {
        $memory->deleteWithPhotos();

        return back()->with('success', 'Memory deleted.');
    }
}
