<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::with('church:id,name,slug')->paginate(15);
        $settings->getCollection()->each(fn (Setting $setting) => $setting->church?->localize());

        return response()->json($settings);
    }

    public function show(Setting $setting)
    {
        $setting->load('church:id,name,slug');
        $setting->church?->localize();

        return response()->json($setting);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'church_id' => ['required', 'exists:churches,id'],
            'options' => ['nullable', 'array'],
        ]);

        $setting = Setting::updateOrCreate(['church_id' => $validated['church_id']], ['options' => $validated['options'] ?? []]);

        return response()->json($setting, 201);
    }

    public function update(Request $request, Setting $setting)
    {
        $setting->update($request->validate(['options' => ['nullable', 'array']]));

        return response()->json($setting);
    }

    public function destroy(Setting $setting)
    {
        $setting->delete();

        return response()->noContent();
    }
}
