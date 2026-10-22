<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['settings' => Setting::values()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'hero_title' => ['required', 'string', 'max:200'],
            'hero_subtitle' => ['nullable', 'string', 'max:1000'],
            'hero_banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $currentBanner = Setting::values()['hero_banner'];

        if ($request->hasFile('hero_banner') || $request->boolean('remove_banner')) {
            if ($currentBanner) {
                Storage::disk('public')->delete($currentBanner);
            }
            $data['hero_banner'] = $request->hasFile('hero_banner')
                ? $request->file('hero_banner')->store('settings', 'public')
                : null;
        } else {
            unset($data['hero_banner']);
        }

        Setting::put($data);

        return back()->with('success', 'Welcome page updated.');
    }
}
