<?php

namespace App\Http\Controllers;

use App\Models\Memory;
use Illuminate\Contracts\View\View;

/** Public Memories pages: everyone can see them, like the welcome page. */
class MemoryController extends Controller
{
    /** Memory posts shown per page. */
    private const PAGE_SIZE = 12;

    public function index(): View
    {
        return view('memories.index', [
            'memories' => Memory::feed()->paginate(self::PAGE_SIZE),
        ]);
    }

    public function show(Memory $memory): View
    {
        return view('memories.show', ['memory' => $memory->load('photos')]);
    }
}
