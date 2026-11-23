<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->orderByDesc('created_at');

        if ($request->filled('role')) {
            $query->where('role', $request->string('role'));
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('q')) {
            $search = '%'.$request->string('q').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        $users = $query->withCount(['destinations', 'trips'])->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'is_active' => $u->is_active,
            'destinations_count' => $u->destinations_count,
            'trips_count' => $u->trips_count,
            'created_at' => $u->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => $users,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(6)],
            'role' => ['nullable', Rule::in(['admin', 'user'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'] ?? 'user',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'User berhasil ditambahkan.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->loadCount(['destinations', 'trips']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'destinations_count' => $user->destinations_count,
                'trips_count' => $user->trips_count,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', 'required', Rule::in(['admin', 'user'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($request->user()->id === $user->id) {
            if (isset($validated['is_active']) && ! $validated['is_active']) {
                return response()->json([
                    'message' => 'Admin tidak dapat menonaktifkan akunnya sendiri.',
                ], 422);
            }
            if (isset($validated['role']) && $validated['role'] !== 'admin') {
                return response()->json([
                    'message' => 'Admin tidak dapat menurunkan role akunnya sendiri.',
                ], 422);
            }
        }

        $user->update($validated);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Data user berhasil diperbarui.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'Admin tidak dapat menonaktifkan akunnya sendiri.',
            ], 422);
        }

        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => $user->is_active ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ],
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', Password::min(6)],
        ]);

        $user->update([
            'password' => $validated['password'],
        ]);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Kata sandi user berhasil direset.',
        ]);
    }
}
