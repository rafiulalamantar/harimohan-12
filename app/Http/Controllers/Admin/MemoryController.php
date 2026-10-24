<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemoryRequest;
use App\Models\Memory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admins share memories (photos + a story) on the welcome page's Memories section.
 * Validation lives in MemoryRequest; saving and deleting photos lives on the Memory model.
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
        $memory = Memory::create($request->memoryData() + ['user_id' => $request->user()->id]);
        $memory->addPhotos($request->newPhotos());

        return redirect()->route('admin.memories.index')->with('success', 'Memory shared on the welcome page.');
    }

    public function edit(Memory $memory): View
    {
        return view('admin.memories.form', ['memory' => $memory->load('photos')]);
    }

    public function update(MemoryRequest $request, Memory $memory): RedirectResponse
    {
        $memory->update($request->memoryData());
        $memory->removePhotos($request->photoIdsToRemove());
        $memory->addPhotos($request->newPhotos());

        return redirect()->route('admin.memories.index')->with('success', 'Memory updated.');
    }

    public function destroy(Memory $memory): RedirectResponse
    {
        $memory->deleteWithPhotos();

        return back()->with('success', 'Memory deleted.');
    }
}
