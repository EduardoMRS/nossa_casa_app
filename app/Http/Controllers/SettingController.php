<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(Setting::with('church:id,name,slug')->paginate(15));
    }

    public function show(Setting $setting)
    {
        return response()->json($setting->load('church:id,name,slug'));
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
