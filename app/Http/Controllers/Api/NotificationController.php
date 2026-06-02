<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // ─── SHARED HELPERS ──────────────────────────────────────────────────────
      private function baseQuery(Request $request)
 {
    $user = $request->user();
    $role = $user->role ?? null;

    if ($role === 'traveler') {
        // AMAN: pakai traveler_id
        $traveler = \App\Models\Traveler::where('email', $user->email)->first();

        if (!$traveler) {
            abort(403, 'Traveler tidak ditemukan');
        }

        return \App\Models\Notification::where('traveler_id', $traveler->id)->latest();
    }

    return \App\Models\Notification::where('user_id', $user->id)->latest();
 }

    // ─── GET /notifications ───────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = $this->baseQuery($request);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate(20);

        $unreadCount = (clone $this->baseQuery($request))
            ->where('is_read', false)
            ->count();

        $notifications->getCollection()->transform(fn($n) => $n->toArray());

        return response()->json([
            'success'      => true,
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    // ─── GET unread count ─────────────────────────────────────────────────────
    public function unreadCount(Request $request)
    {
        $count = $this->baseQuery($request)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    // ─── MARK READ ────────────────────────────────────────────────────────────
    public function markRead(Request $request, int $id)
    {
        $notif = $this->baseQuery($request)->findOrFail($id);

        $notif->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandai sudah dibaca.',
        ]);
    }

    public function markAllRead(Request $request)
    {
        $updated = $this->baseQuery($request)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'updated' => $updated,
        ]);
    }

    // ─── DELETE ───────────────────────────────────────────────────────────────
    public function destroy(Request $request, int $id)
    {
        $notif = $this->baseQuery($request)->findOrFail($id);
        $notif->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi dihapus.',
        ]);
    }

    public function destroyAll(Request $request)
    {
        $query = $this->baseQuery($request);

        if ($request->boolean('read_only')) {
            $query->where('is_read', true);
        }

        $deleted = $query->delete();

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }
}
