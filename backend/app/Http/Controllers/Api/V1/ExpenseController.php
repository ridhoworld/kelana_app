<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Trip;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    private const CATEGORIES = ['transportasi', 'penginapan', 'makan', 'aktivitas', 'belanja', 'lainnya'];

    public function __construct(private TripService $tripService) {}

    public function index(Request $request, Trip $trip): JsonResponse
    {
        if ($trip->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json([
            'data' => $trip->expenses()->get(),
            'summary' => $this->tripService->buildBudgetSummary($trip),
        ]);
    }

    public function store(Request $request, Trip $trip): JsonResponse
    {
        if ($trip->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'item_id' => ['nullable', 'uuid', 'exists:itinerary_items,id'],
            'description' => ['required', 'string', 'max:200'],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'amount' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $expense = $trip->expenses()->create([
            'item_id' => $validated['item_id'] ?? null,
            'description' => $validated['description'],
            'category' => $validated['category'] ?? 'lainnya',
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency'] ?? $trip->budget_currency),
            'paid_at' => $validated['paid_at'] ?? now(),
        ]);

        return response()->json([
            'message' => 'Pengeluaran berhasil dicatat.',
            'data' => $expense,
            'summary' => $this->tripService->buildBudgetSummary($trip),
        ], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $expense->load('trip');
        if ($expense->trip->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'description' => ['sometimes', 'required', 'string', 'max:200'],
            'category' => ['sometimes', Rule::in(self::CATEGORIES)],
            'amount' => ['sometimes', 'required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'paid_at' => ['sometimes', 'date'],
        ]);

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        $expense->update($validated);

        return response()->json([
            'message' => 'Pengeluaran berhasil diperbarui.',
            'data' => $expense->fresh(),
            'summary' => $this->tripService->buildBudgetSummary($expense->trip),
        ]);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $expense->load('trip');
        if ($expense->trip->user_id !== $request->user()->id) {
            abort(403);
        }

        $trip = $expense->trip;
        $expense->delete();

        return response()->json([
            'message' => 'Pengeluaran berhasil dihapus.',
            'summary' => $this->tripService->buildBudgetSummary($trip),
        ]);
    }
}
