<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrayerRequest; // O Model precisará ser criado caso ainda não exista

class PrayerRequestController extends Controller
{
    public function index(Request $request)
    {
        // Retorna o histórico de orações do usuário logado
        $requests = PrayerRequest::where('user_id', $request->user()->id)->paginate(15);
        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'is_anonymous' => 'nullable|boolean',
        ]);

        $isAnonymous = (bool) ($validated['is_anonymous'] ?? false);
        unset($validated['is_anonymous']);

        // Vincula ao usuário caso ele esteja autenticado (suporta submissão pública e privada)
        if ($request->user() && ! $isAnonymous) {
            $validated['user_id'] = $request->user()->id;
        }

        $validated['church_id'] = $request->user()?->church?->id;

        $prayerRequest = PrayerRequest::create($validated);

        return response()->json($prayerRequest, 201);
    }
}
