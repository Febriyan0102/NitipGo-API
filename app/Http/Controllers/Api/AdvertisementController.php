<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\AdvertisementPayment; 
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AdvertisementController extends Controller
{
    // ─── PUBLIC ──────────────────────────────────────────────────────────────

    /**
     * GET /advertisements/live
     * Return up to 3 currently live ads for the landing page.
     */
    public function live()
    {
        // Auto-expire stale ads before serving
        $this->expireStaleAds();

        $ads = Advertisement::liveNow()
            ->get()
            ->map(fn(Advertisement $a) => $this->format($a));

        return response()->json(['success' => true, 'data' => $ads]);
    }

    /**
     * GET /advertisements/packages
     * Return available packages and current queue info.
     */
    public function packages()
    {
        $this->expireStaleAds();

        $liveCount     = Advertisement::liveNow()->count();
        $nextStart     = Advertisement::nextAvailableStartDate();
        $slotsAvailable = $liveCount < Advertisement::MAX_LIVE_SLOTS;

        return response()->json([
            'success'         => true,
            'packages'        => Advertisement::PACKAGES,
            'max_slots'       => Advertisement::MAX_LIVE_SLOTS,
            'live_count'      => $liveCount,
            'slots_available' => $slotsAvailable,
            'next_start_date' => $nextStart,
        ]);
    }

    /**
     * POST /advertisements
     * Create a new ad + payment and return a Midtrans Snap token.
     */
    public function store(Request $request)
    {
        $request->validate([
            'partner_name'    => 'required|string|max:255',
            'partner_contact' => 'nullable|string|max:255',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string|max:1000',
            'image'           => 'nullable|image|max:4096',    // file upload
            'image_url'       => 'nullable|url',               // OR url
            'link_url'        => 'required|url',
            'package'         => 'required|in:basic,standard,premium',
        ]);

        $pkg = Advertisement::PACKAGES[$request->package];

        // Determine start date (auto-advance if slots full)
        $startDate = Advertisement::nextAvailableStartDate();
        $endDate   = \Carbon\Carbon::parse($startDate)->addDays($pkg['days'] - 1)->toDateString();

        // Handle image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('advertisements', 'public');
        } elseif ($request->image_url) {
            $imagePath = $request->image_url; // store URL as-is
        }

        // Create Ad (pending until paid)
        $ad = Advertisement::create([
            'code'            => 'AD-' . strtoupper(Str::random(8)),
            'partner_name'    => $request->partner_name,
            'partner_contact' => $request->partner_contact,
            'title'           => $request->title,
            'description'     => $request->description,
            'image_path'      => $imagePath,
            'link_url'        => $request->link_url,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'duration_days'   => $pkg['days'],
            'package'         => $request->package,
            'status'          => 'pending',
        ]);

        // Create Payment record
        $orderId = 'ADPAY-' . strtoupper(Str::random(8));
        $payment = AdvertisementPayment::create([
            'code'            => $orderId,
            'advertisement_id'=> $ad->id,
            'partner_name'    => $request->partner_name,
            'partner_contact' => $request->partner_contact,
            'amount'          => $pkg['price'],
            'package'         => $request->package,
            'duration_days'   => $pkg['days'],
            'status'          => 'pending',
            'order_id'        => $orderId,
        ]);

        $ad->update(['payment_id' => $payment->id]);

        // Get Midtrans Snap Token
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production');
        \Midtrans\Config::$isSanitized  = true;
        \Midtrans\Config::$is3ds        = true;

        $params = [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $pkg['price'],
            ],
            'customer_details' => [
                'first_name' => $request->partner_name,
                'email'      => $request->partner_contact ?: 'no-reply@nitipgo.com',
            ],
            'item_details' => [
                [
                    'id'       => $request->package,
                    'price'    => $pkg['price'],
                    'quantity' => 1,
                    'name'     => 'Iklan NitipGo - Paket ' . $pkg['label'],
                ],
            ],
            'callbacks' => [
                'finish' => config('app.frontend_url', 'http://localhost:5173')
                    . '/iklan/sukses?order_id=' . $orderId,
            ],
        ];

        try {
            $snapToken = \Midtrans\Snap::getSnapToken($params);
            $payment->update(['snap_token' => $snapToken]);
        } catch (\Exception $e) {
            $ad->delete();
            $payment->delete();
            return response()->json(['success' => false, 'message' => 'Gagal membuat pembayaran: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Iklan berhasil dibuat. Lanjutkan ke pembayaran.',
            'ad_id'      => $ad->id,
            'payment_id' => $payment->id,
            'snap_token' => $snapToken,
            'snap_url'   => config('midtrans.snap_url'),
            'client_key' => config('midtrans.client_key'),
            'amount'     => $pkg['price'],
            'package'    => $pkg,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ], 201);
    }

    /**
     * POST /advertisements/sync-by-order
     * Sync by order_id redirect Midtrans 
     */
    public function syncByOrder(Request $request)
    {
        $request->validate(['order_id' => 'required|string']);

        $payment = AdvertisementPayment::where('order_id', $request->order_id)->first();
        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        if ($payment->status === 'paid') {
            return response()->json(['success' => true, 'paid' => true]);
        }

        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);

        try {
            $status = \Midtrans\Transaction::status($request->order_id);

            // ← Gunakan object property, bukan array
            if (in_array($status->transaction_status, ['settlement', 'capture'])) {
                $payment->update([
                    'status'       => 'paid',
                    'payment_type' => $status->payment_type ?? null,
                    'paid_at'      => now(),
                ]);
                $payment->advertisement->update(['status' => 'active']);
                Advertisement::rebalanceSlots();

                return response()->json(['success' => true, 'paid' => true]);
            }

            return response()->json([
                'success' => true,
                'paid'    => false,
                'status'  => $status->transaction_status,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /advertisements/{id}/sync
     * Manual sync from frontend after Snap close.
     */
    public function syncPayment(Request $request, $id)
    {
        $ad = Advertisement::findOrFail($id);
        $payment = $ad->payment;
        if (!$payment) return response()->json(['success' => false]);

        if ($payment->status === 'paid') {
            return response()->json(['success' => true, 'paid' => true]);
        }

        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);

        try {
            $status = \Midtrans\Transaction::status($payment->order_id);

            // ← Gunakan object property, bukan array
            if (in_array($status->transaction_status, ['settlement', 'capture'])) {
                $payment->update([
                    'status'       => 'paid',
                    'payment_type' => $status->payment_type ?? null,
                    'paid_at'      => now(),
                ]);
                $ad->update(['status' => 'active']);
                Advertisement::rebalanceSlots();
            }
        } catch (\Exception $e) {
            // silent fail
        }

        return response()->json([
            'success' => true,
            'paid'    => $payment->fresh()->status === 'paid',
            'status'  => $ad->fresh()->status,
        ]);
    }

    /**
     * POST /advertisements/payment/notify
     * Midtrans webhook — called by Midtrans server.
     */
    public function handleNotification(Request $request)
    {
        $notif     = new \Midtrans\Notification();
        $orderId   = $notif->order_id;
        $txStatus  = $notif->transaction_status;
        $fraudStatus = $notif->fraud_status ?? null;

        $payment = AdvertisementPayment::where('order_id', $orderId)->first();
        if (!$payment) return response()->json(['ok' => true]);

        $paid = ($txStatus === 'capture' && $fraudStatus === 'accept')
             || $txStatus === 'settlement';

        if ($paid) {
            $payment->update([
                'status'            => 'paid',
                'payment_type'      => $notif->payment_type,
                'paid_at'           => now(),
                'midtrans_response' => $request->all(),
            ]); 
            
            // NOTIF KE ADMIN
    $admins = User::where('role', 'admin')->get();

    foreach ($admins as $admin) {

        Notification::create([
            'user_id' => $admin->id,

            'type'  => 'advertisement',
            'title' => 'Iklan Baru Dibayar',

            'message' => json_encode([
                'partner' => $payment->partner_name,
                'package' => $payment->package,
                'amount'  => $payment->amount,
            ]),

            'is_read' => false,
        ]);
    }


            $payment->advertisement->update(['status' => 'active']);
            Advertisement::rebalanceSlots();
        } elseif (in_array($txStatus, ['cancel', 'deny', 'expire'])) {
            $payment->update(['status' => 'failed']);
            $payment->advertisement->delete();
        }

        return response()->json(['ok' => true]);
    }

    // ─── ADMIN ───────────────────────────────────────────────────────────────

    /**
     * GET /admin/advertisements
     */
    public function adminIndex(Request $request)
    {
        $this->expireStaleAds();

        $query = Advertisement::with('payment')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $ads = $query->paginate(20);
        $ads->getCollection()->transform(fn($a) => $this->format($a, admin: true));

        $today = now()->toDateString();
        $stats = [
            'live'     => Advertisement::liveNow()->count(),
            'pending'  => Advertisement::where('status', 'pending')->count(),
            'expired'  => Advertisement::where('status', 'expired')->count(),
            'total'    => Advertisement::count(),
        ];

        return response()->json(['success' => true, 'data' => $ads, 'stats' => $stats]);
    }

    /**
     * DELETE /admin/advertisements/{id}
     */
    public function destroy($id)
    {
        $ad = Advertisement::findOrFail($id);
        if ($ad->image_path && !str_starts_with($ad->image_path, 'http')) {
            Storage::disk('public')->delete($ad->image_path);
        }
        $ad->delete();
        Advertisement::rebalanceSlots();
        return response()->json(['success' => true, 'message' => 'Iklan dihapus.']);
    }

    /**
     * PATCH /admin/advertisements/{id}/approve
     * Manually approve a pending ad (e.g. if payment is manual).
     */
    public function approve($id)
    {
        $ad = Advertisement::findOrFail($id);
        $ad->update(['status' => 'active']);
        Advertisement::rebalanceSlots();
        return response()->json(['success' => true, 'message' => 'Iklan disetujui.']);
    }

    /**
     * PATCH /admin/advertisements/{id}/reject
     */
    public function reject($id)
    {
        $ad = Advertisement::findOrFail($id);
        $ad->update(['status' => 'rejected']);
        Advertisement::rebalanceSlots();
        return response()->json(['success' => true, 'message' => 'Iklan ditolak.']);
    }
   
     public function storeManual(Request $request)
        {
            $request->validate([
                'partnerName' => 'required',
                'title'       => 'required',
                'linkUrl'     => 'required',
                'package'     => 'required',
            ]);

            $daysMap = [
                'basic' => 7,
                'standard' => 14,
                'premium' => 30,
            ];

            $days = $daysMap[$request->package] ?? 7;

            $ad = Advertisement::create([
                'code'            => 'AD-' . strtoupper(Str::random(8)),
                'partner_name'    => $request->partnerName,
                'partner_contact' => $request->partnerContact,
                'title'           => $request->title,
                'description'     => $request->description,
                'image_path'      => $request->image_url, // ← FIX
                'link_url'        => $request->linkUrl,
                'package'         => $request->package,
                'duration_days'   => $days,
                'start_date'      => now()->toDateString(),
                'end_date'        => now()->addDays($days)->toDateString(),
                'status'          => 'active',
            ]);

            Advertisement::rebalanceSlots();

            return response()->json([
                'success' => true,
                'message' => 'Iklan berhasil dibuat',
                'data' => $ad
            ]);
        }
    // ─── PRIVATE ─────────────────────────────────────────────────────────────

    private function expireStaleAds(): void
    {
        $today = now()->toDateString();
        $changed = Advertisement::where('status', 'active')
            ->where('end_date', '<', $today)
            ->update(['status' => 'expired', 'slot_index' => null]);

        if ($changed) {
            Advertisement::rebalanceSlots();
        }
    }

    private function format(Advertisement $a, bool $admin = false): array
    {
        $BASE = rtrim(config('app.url'), '/');
        $imageUrl = $a->image_path
            ? (str_starts_with($a->image_path, 'http')
                ? $a->image_path
                : "{$BASE}/storage/{$a->image_path}")
            : null;

        $data = [
            'id'           => $a->id,
            'code'         => $a->code,
            'partnerName'  => $a->partner_name,
            'title'        => $a->title,
            'description'  => $a->description,
            'imageUrl'     => $imageUrl,
            'linkUrl'      => $a->link_url,
            'startDate'    => $a->start_date->toDateString(),
            'endDate'      => $a->end_date->toDateString(),
            'durationDays' => $a->duration_days,
            'package'      => $a->package,
            'status'       => $a->status,
            'slotIndex'    => $a->slot_index,
            'daysRemaining'=> $a->days_remaining,
            'isExpiring'   => $a->isExpiring(),
        ];

        if ($admin) {
            $data['partnerContact'] = $a->partner_contact;
            $data['payment'] = $a->payment ? [
                'status'    => $a->payment->status,
                'amount'    => $a->payment->amount,
                'paidAt'    => $a->payment->paid_at?->format('d M Y, H:i'),
                'paymentType' => $a->payment->payment_type,
            ] : null;
        }

        return $data;
    }

    /**
     * GET /admin/wallet/advertisements
     * Stats dan riwayat transaksi iklan yang sudah dibayar
     */
    public function adminWallet(Request $request)
    {
        $totalIncome = AdvertisementPayment::where('status', 'paid')->sum('amount');

        $thisMonth = AdvertisementPayment::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $lastMonth = AdvertisementPayment::where('status', 'paid')
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->whereYear('paid_at', now()->subMonth()->year)
            ->sum('amount');

        $transactions = AdvertisementPayment::where('status', 'paid')
            ->with('advertisement:id,title,partner_name,package,image_path')
            ->latest('paid_at')
            ->paginate(10);

        $BASE = rtrim(config('app.url'), '/');

        $transactions->getCollection()->transform(fn($p) => [
            'id'           => $p->id,
            'partner_name' => $p->partner_name,
            'title'        => $p->advertisement?->title ?? '-',
            'package'      => $p->package,
            'package_label'=> Advertisement::PACKAGES[$p->package]['label'] ?? $p->package,
            'amount'       => (float) $p->amount,
            'paid_at'      => $p->paid_at?->format('d M Y'),
            'order_id'     => $p->order_id,
            'image_url'    => $p->advertisement?->image_path
                ? (str_starts_with($p->advertisement->image_path, 'http')
                    ? $p->advertisement->image_path
                    : "{$BASE}/storage/{$p->advertisement->image_path}")
                : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_income' => (float) $totalIncome,
                    'this_month'   => (float) $thisMonth,
                    'last_month'   => (float) $lastMonth,
                    'total_trx'    => AdvertisementPayment::where('status', 'paid')->count(),
                ],
                'transactions' => $transactions,
            ],
        ]);
    }
}