<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index()
    {
        $classrooms = Classroom::with(['church', 'teacher'])->paginate(15);
        return response()->json($classrooms);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'min_age'     => 'nullable|integer|min:0',
            'max_age'     => 'nullable|integer|gte:min_age',
            'max_members' => 'nullable|integer|min:1',
            'church_id'   => 'required|string|exists:churches,id',
            'teacher_id'  => 'nullable|string|exists:users,id',
        ]);

        $classroom = Classroom::create($validated);

        return response()->json($classroom, 201);
    }

    public function show(string $id)
    {
        $classroom = Classroom::with(['church', 'teacher', 'members', 'posts'])->findOrFail($id);
        return response()->json($classroom);
    }

    public function update(Request $request, string $id)
    {
        $classroom = Classroom::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'min_age'     => 'nullable|integer|min:0',
            'max_age'     => 'nullable|integer|gte:min_age',
            'max_members' => 'nullable|integer|min:1',
            'church_id'   => 'sometimes|required|string|exists:churches,id',
            'teacher_id'  => 'nullable|string|exists:users,id',
        ]);

        $classroom->update($validated);

        return response()->json($classroom);
    }

    public function destroy(string $id)
    {
        $classroom = Classroom::findOrFail($id);
        $classroom->delete();

        return response()->json(null, 204);
    }

    public function checkIn(Request $request, string $id)
    {
        $classroom = Classroom::findOrFail($id);
        
        $validated = $request->validate([
            'user_id' => 'required|string|exists:users,id',
        ]);

        $classroom->presences()->syncWithoutDetaching([
            $validated['user_id'] => ['check_in' => now()]
        ]);

        return response()->json(['message' => __('checkin.checkin_success')], 200);
    }

    public function checkOut(Request $request, string $id)
    {
        $classroom = Classroom::findOrFail($id);
        
        $validated = $request->validate([
            'user_id' => 'required|string|exists:users,id',
        ]);

        $classroom->presences()->syncWithoutDetaching([
            $validated['user_id'] => ['check_out' => now()]
        ]);

        return response()->json(['message' => __('checkin.checkout_success')], 200);
    }
}
