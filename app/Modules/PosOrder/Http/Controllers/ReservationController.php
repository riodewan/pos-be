<?php

namespace App\Modules\PosOrder\Http\Controllers;

use App\Core\Context\AppContext;
use App\Modules\PosOrder\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Reservation::with('table');

        if ($request->filled('date')) {
            $query->whereDate('reservation_date', $request->query('date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('reservation_number', 'like', "%{$search}%");
            });
        }

        $reservations = $query->orderBy('reservation_date', 'asc')
            ->orderBy('reservation_time', 'asc')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'message' => 'Reservations retrieved successfully.',
            'data' => $reservations->items(),
            'pagination' => [
                'current_page' => $reservations->currentPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
                'last_page' => $reservations->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => ['nullable', 'string'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'customer_email' => ['nullable', 'email', 'max:100'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'reservation_date' => ['required', 'date'],
            'reservation_time' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $reservationNumber = 'RSV-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        $reservation = Reservation::create([
            'tenant_id' => AppContext::tenantId(),
            'store_id' => AppContext::storeId(),
            'table_id' => $validated['table_id'] ?? null,
            'reservation_number' => $reservationNumber,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'customer_email' => $validated['customer_email'] ?? null,
            'guest_count' => $validated['guest_count'],
            'reservation_date' => $validated['reservation_date'],
            'reservation_time' => $validated['reservation_time'],
            'status' => 'Menunggu',
            'notes' => $validated['notes'] ?? null,
        ]);

        $reservation->load('table');

        // Create in-app operational notification for Reservation
        try {
            \App\Modules\System\Models\Notification::create([
                'tenant_id' => AppContext::tenantId(),
                'store_id' => AppContext::storeId(),
                'title' => "Reservasi Baru Menunggu Konfirmasi #{$reservationNumber}",
                'subtitle' => "{$validated['customer_name']} memesan meja untuk {$validated['guest_count']} tamu pada {$validated['reservation_date']} ({$validated['reservation_time']}).",
                'category' => 'Reservasi',
                'is_read' => false,
                'target_url' => '/dashboard/reservations',
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'message' => 'Reservation created successfully.',
            'data' => $reservation,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $reservation = Reservation::with('table')->findOrFail($id);

        return response()->json([
            'message' => 'Reservation retrieved successfully.',
            'data' => $reservation,
        ]);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:Menunggu,Dikonfirmasi,Selesai,Dibatalkan'],
        ]);

        $reservation->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Reservation status updated successfully.',
            'data' => $reservation,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $reservation = Reservation::findOrFail($id);
        $reservation->delete();

        return response()->json([
            'message' => 'Reservation deleted successfully.',
        ]);
    }
}
