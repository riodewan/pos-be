<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Core\Context\AppContext;
use App\Modules\Customer\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tier')) {
            $query->where('tier', $request->query('tier'));
        }

        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'points', 'total_spent', 'total_visits', 'name'])) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $customers = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'message' => 'Customers retrieved successfully.',
            'data' => $customers->items(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'tier' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:20'],
            'segmentation' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'points' => ['nullable', 'integer', 'min:0'],
        ]);

        $customer = Customer::create([
            'tenant_id' => AppContext::tenantId(),
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'tier' => $validated['tier'] ?? 'Bronze',
            'status' => $validated['status'] ?? 'Aktif',
            'segmentation' => $validated['segmentation'] ?? 'Reguler',
            'gender' => $validated['gender'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'address' => $validated['address'] ?? null,
            'avatar_url' => $validated['avatar_url'] ?? null,
            'points' => $validated['points'] ?? 0,
            'total_spent' => 0,
            'total_visits' => 0,
        ]);

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => $customer,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $customer = Customer::with(['orders' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(10);
        }])->findOrFail($id);

        return response()->json([
            'message' => 'Customer retrieved successfully.',
            'data' => $customer,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'tier' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:20'],
            'segmentation' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'points' => ['nullable', 'integer', 'min:0'],
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Customer updated successfully.',
            'data' => $customer,
        ]);
    }

    public function toggleStatus(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $nextStatus = ($customer->status === 'Aktif' || $customer->status === 'ACTIVE') ? 'Nonaktif' : 'Aktif';
        $customer->update(['status' => $nextStatus]);

        return response()->json([
            'message' => "Customer status changed to {$nextStatus}.",
            'data' => $customer,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully.',
        ]);
    }
}
