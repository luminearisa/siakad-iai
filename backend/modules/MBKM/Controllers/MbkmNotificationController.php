<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MBKM\Models\MbkmStatusHistory;

/**
 * Notification inbox for the authenticated user (Laravel database notifications)
 * plus the module-wide workflow history feed for administrators.
 */
class MbkmNotificationController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = $user->notifications()->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        return $this->successResponse([
            'notifications' => $query->limit(100)->get(),
            'unread_count' => $user->unreadNotifications()->count(),
        ], 'Notifikasi MBKM berhasil dimuat.');
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if (!$notification) {
            return $this->errorResponse('Notifikasi tidak ditemukan.', 404);
        }

        $notification->markAsRead();

        return $this->successResponse(null, 'Notifikasi ditandai sudah dibaca.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->successResponse(null, 'Semua notifikasi ditandai sudah dibaca.');
    }

    /**
     * Global workflow history feed (audit trail of MBKM status transitions).
     */
    public function history(Request $request): JsonResponse
    {
        $query = MbkmStatusHistory::query()->with('actor:id,name');

        if ($request->filled('entity_type')) {
            $query->where('entity_type', 'like', '%' . $request->query('entity_type') . '%');
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->query('entity_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->query('action') . '%');
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->query('actor_id'));
        }

        return $this->successResponse(
            $query->orderByDesc('id')->limit(200)->get(),
            'Riwayat workflow MBKM berhasil dimuat.'
        );
    }
}
