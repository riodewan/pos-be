<?php

namespace App\Modules\PosOrder\Http\Controllers;

use App\Core\Enums\QrStatus;
use App\Modules\PosOrder\Models\QrCode;
use App\Modules\PosOrder\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Manajemen QR Sekali Pakai (Disposable QR) per outlet.
 * Setiap QR terikat ke satu meja, dengan batas waktu dan/atau batas jumlah pesanan.
 * Penegakan aturan dilakukan di PublicQrController::resolve dan OrderService::createQrOrder.
 */
class DisposableQrController extends Controller
{
    public function index(): JsonResponse
    {
        $qrs = QrCode::query()
            ->where('type', QrCode::TYPE_DISPOSABLE)
            ->with('table:id,name,code')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Disposable QR codes retrieved successfully.',
            'data' => $qrs,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:150'],
            'table_id' => ['required', 'string'],
            'expires_at' => ['nullable', 'date'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        // Global scope store memastikan meja milik outlet aktif
        $table = Table::findOrFail($validated['table_id']);

        $expiresAt = null;
        if (!empty($validated['expires_at'])) {
            $expiresAt = Carbon::parse($validated['expires_at']);
            // Jika hanya tanggal (YYYY-MM-DD), berlaku sampai akhir hari tersebut
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $validated['expires_at'])) {
                $expiresAt = $expiresAt->endOfDay();
            }
            if ($expiresAt->isPast()) {
                return response()->json([
                    'message' => 'Tanggal kedaluwarsa harus di masa mendatang.',
                    'errors' => ['expires_at' => ['Tanggal kedaluwarsa harus di masa mendatang.']],
                ], 422);
            }
        }

        $qr = QrCode::create([
            'id' => (string) Str::ulid(),
            'store_id' => $table->store_id,
            'table_id' => $table->id,
            'token' => bin2hex(random_bytes(32)),
            'type' => QrCode::TYPE_DISPOSABLE,
            'label' => $validated['label'],
            'status' => QrStatus::ACTIVE,
            'expires_at' => $expiresAt,
            'max_uses' => $validated['max_uses'] ?? null,
            'used_count' => 0,
        ]);

        return response()->json([
            'message' => 'QR sekali pakai berhasil dibuat.',
            'data' => $qr->load('table:id,name,code'),
        ], 201);
    }

    public function deactivate(string $id): JsonResponse
    {
        $qr = QrCode::where('type', QrCode::TYPE_DISPOSABLE)->findOrFail($id);
        $qr->update(['status' => QrStatus::INACTIVE]);

        return response()->json([
            'message' => 'QR sekali pakai berhasil dinonaktifkan.',
            'data' => $qr->fresh('table:id,name,code'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $qr = QrCode::where('type', QrCode::TYPE_DISPOSABLE)->findOrFail($id);
        $qr->update(['status' => QrStatus::INACTIVE]);
        $qr->delete();

        return response()->json([
            'message' => 'QR sekali pakai berhasil dihapus.',
        ]);
    }
}
