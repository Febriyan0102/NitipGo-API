<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booster;
use App\Models\TravelerBooster; 
use App\Models\Notification;
use App\Models\User;
use App\Models\PaymentBooster;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BoosterController extends Controller
{
    // ─── PUBLIC / TRAVELER ────────────────────────────────────────────────────────

    /**
     * List all packet booster
     */
    public function plans()
    {
        $boosters = Booster::where('active', true)
            ->orderBy('priority')
            ->get()
            ->map(fn($b) => [
                'id'          => $b->id,
                'name'        => $b->name,
                'price'       => (float) $b->price,
                'price_label' => 'Rp ' . number_format($b->price, 0, ',', '.'),
                'duration'    => $b->duration,
                'slots'       => $b->slots,
                'priority'    => $b->priority,
                'color'       => $b->color,
                'description' => $b->description,
            ]);

        return response()->json(['success' => true, 'data' => $boosters]);
    }

    /**
     * Traveler payment booster via midtrans
     */
    public function buy(Request $request)
    {
        $request->validate([
            'booster_id' => 'required|exists:boosters,id',
        ]);

        $traveler = $request->user();
        $booster  = Booster::where('active', true)->findOrFail($request->booster_id);

        // Checked booster active
        $existing = TravelerBooster::where('traveler_id', $traveler->id)
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Anda masih memiliki booster aktif hingga ' .
                    $existing->end_date->format('d M Y'),
            ], 422);
        }

        // Payment
        $midtransOrderId    = 'BST-' . strtoupper(Str::random(10));
        $paymentReference   = 'REF-' . strtoupper(Str::random(12));

        $payment = PaymentBooster::create([
            'traveler_id'       => $traveler->id,
            'booster_id'        => $booster->id,
            'amount'            => $booster->price,
            'fee'               => 0,
            'total_paid'        => $booster->price,
            'midtrans_order_id' => $midtransOrderId,
            'payment_reference' => $paymentReference,
            'status'            => 'pending',
            'expired_at'        => now()->addHours(24),
        ]);

        // NOTIF KE ADMIN
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {

            Notification::create([
                'user_id' => $admin->id,

                'type' => 'Booster',
                'title' => 'Booster Dibeli',

                'message' => json_encode([
                    'traveler' => $traveler->name,
                    'booster' => $booster->name,
                    'status' => 'waiting_payment',
                ]),

                'is_read' => false,
            ]);

        }

        // Midtrans Snap
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);
        \Midtrans\Config::$isSanitized  = true;
        \Midtrans\Config::$is3ds        = true;

        $snapToken = null;
        $snapUrl   = config('midtrans.snap_url', 'https://app.sandbox.midtrans.com/snap/snap.js');
        $clientKey = config('midtrans.client_key');

        try {
            $snapToken = \Midtrans\Snap::getSnapToken([
            'transaction_details' => [
                'order_id'     => $midtransOrderId,
                'gross_amount' => (int) $booster->price,
            ],
            'customer_details' => [
                'first_name' => $traveler->name,
                'email'      => $traveler->email,
                'phone'      => $traveler->phone ?? '',
            ],
            'item_details' => [[
                'id'       => 'BST-' . $booster->id,
                'price'    => (int) $booster->price,
                'quantity' => 1,
                'name'     => $booster->name,
            ]],
            'expiry' => [
                'duration' => 24,
                'unit'     => 'hours',
            ],
            // ← TAMBAHKAN INI
            'callbacks' => [
                'finish' => config('app.frontend_url') . '/traveler/boost/payment',
            ],
        ]);

            $payment->update(['snap_token' => $snapToken]);
        } catch (\Exception $e) {
            // Jika Midtrans gagal, tetap kembalikan payment id untuk retry
        }

        return response()->json([
            'success' => true,
            'message' => 'Silakan selesaikan pembayaran.',
            'data'    => [
                'payment_id'      => $payment->id,
                'midtrans_order_id' => $midtransOrderId,
                'snap_token'      => $snapToken,
                'snap_url'        => $snapUrl,
                'client_key'      => $clientKey,
                'amount'          => (float) $booster->price,
                'booster'         => $booster->name,
                'expired_at'      => $payment->expired_at,
            ],
        ]);
    }

    /**
     * Sync status payment booster via midtrans
     */
    public function syncPayment(Request $request, $paymentId)
    {
        $payment = PaymentBooster::where('traveler_id', $request->user()->id)
            ->findOrFail($paymentId);

        if ($payment->status === 'paid') {
            return response()->json([
                'success' => true,
                'message' => 'Pembayaran sudah dikonfirmasi.',
                'data'    => ['status' => 'paid'],
            ]);
        }

        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);

        try {
            $status = \Midtrans\Transaction::status($payment->midtrans_order_id);

            if (in_array($status->transaction_status, ['settlement', 'capture'])) {
                $this->activateBooster($payment);

                return response()->json([
                    'success' => true,
                    'message' => 'Pembayaran berhasil! Booster aktif.',
                    'data'    => ['status' => 'paid'],
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => ['status' => $status->transaction_status],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengecek status pembayaran.',
            ], 500);
        }
    }

    /**
     * Sync by midtrans order ID (untuk handle redirect callback)
     */
    public function syncByOrderId(Request $request)
    {
        $request->validate([
            'midtrans_order_id' => 'required|string',
        ]);

        $payment = PaymentBooster::where('traveler_id', $request->user()->id)
            ->where('midtrans_order_id', $request->midtrans_order_id)
            ->firstOrFail();

        if ($payment->status === 'paid') {
            return response()->json([
                'success' => true,
                'data'    => ['status' => 'paid'],
            ]);
        }

        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);

        try {
            $status = \Midtrans\Transaction::status($payment->midtrans_order_id);

            if (in_array($status->transaction_status, ['settlement', 'capture'])) {
                $payment->update([
                    'paid_at' => now(),
                    'status'  => 'paid',
                ]);
                $this->activateBooster($payment);

                return response()->json([
                    'success' => true,
                    'data'    => ['status' => 'paid'],
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => ['status' => $status->transaction_status],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal sync status.',
            ], 500);
        }
    }

    /**
     * Handle Midtrans webhook notification for booster
     */
    public function handleNotification(Request $request)
    {
        \Midtrans\Config::$serverKey    = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production', false);

        try {
            $notif = new \Midtrans\Notification();

            $payment = PaymentBooster::where('midtrans_order_id', $notif->order_id)->firstOrFail();

            $transactionStatus = $notif->transaction_status;
            $fraudStatus       = $notif->fraud_status ?? null;

            if (
                $transactionStatus === 'capture' && $fraudStatus === 'accept' ||
                $transactionStatus === 'settlement'
            ) {
                $payment->update([
                    'midtrans_transaction_id' => $notif->transaction_id,
                    'payment_type'            => $notif->payment_type,
                    'payment_channel'         => $notif->payment_type,
                    'va_number'               => $notif->va_numbers[0]->va_number ?? null,
                    'paid_at'                 => now(),
                    'status'                  => 'paid',
                ]);

                $this->activateBooster($payment);

            } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
                $payment->update(['status' => $transactionStatus === 'expire' ? 'expired' : 'failed']);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Active booster traveler
     */
    public function active(Request $request)
    {
        $traveler = $request->user();

        // Auto-expire
        TravelerBooster::where('traveler_id', $traveler->id)
            ->where('status', 'active')
            ->where('end_date', '<', now())
            ->update(['status' => 'expired']);

        $booster = TravelerBooster::where('traveler_id', $traveler->id)
            ->where('status', 'active')
            ->with('booster')
            ->first();

        if (!$booster) {
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $booster->id,
                'plan'         => $booster->booster->name,
                'color'        => $booster->booster->color,
                'slots'        => $booster->booster->slots,
                'orders_gained' => $booster->orders_gained,
                'start_date'   => $booster->start_date->format('d M Y'),
                'end_date'     => $booster->end_date->format('d M Y'),
                'days_left'    => max(0, (int) now()->diffInDays($booster->end_date, false)),
                'status'       => $booster->status,
            ],
        ]);
    }

    /**
     * History payment booster traveler
     */
    public function history(Request $request)
    {
        $history = TravelerBooster::where('traveler_id', $request->user()->id)
            ->with(['booster', 'paymentBooster'])
            ->latest()
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $history]);
    }

    // ─── ADMIN ────────────────────────────────────────────────────────────────────

    /**
     * Admin: list all boosters
     */
    public function adminPlans()
    {
        $boosters = Booster::withCount('travelerBoosters')->orderBy('priority')->get();

        return response()->json(['success' => true, 'data' => $boosters]);
    }

    /**
     * Admin: create packet boosters
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'price'       => 'required|numeric|min:0',
            'duration'    => 'required|integer|min:1',
            'slots'       => 'required|integer|min:1',
            'priority'    => 'nullable|integer|min:1',
            'color'       => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
            'active'      => 'nullable|boolean',
        ]);

        $booster = Booster::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Paket booster berhasil dibuat.',
            'data'    => $booster,
        ], 201);
    }

    /**
     * Admin: update packet booster
     */
    public function update(Request $request, $id)
    {
        $booster = Booster::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100',
            'price'       => 'sometimes|numeric|min:0',
            'duration'    => 'sometimes|integer|min:1',
            'slots'       => 'sometimes|integer|min:1',
            'priority'    => 'nullable|integer|min:1',
            'color'       => 'nullable|string|max:20',
            'description' => 'nullable|string|max:500',
            'active'      => 'nullable|boolean',
        ]);

        $booster->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Paket booster berhasil diperbarui.',
            'data'    => $booster,
        ]);
    }

    /**
     * Admin: toggle active/ non active booster
     */
    public function toggleActive(Request $request, $id)
    {
        $booster = Booster::findOrFail($id);
        $booster->update(['active' => !$booster->active]);

        return response()->json([
            'success' => true,
            'message' => 'Status paket berhasil diubah.',
            'data'    => ['active' => $booster->active],
        ]);
    }

    /**
     * Admin: list all traveler
     */
    public function adminMonitoring(Request $request)
    {
        // Auto-expire semua yang sudah lewat
        TravelerBooster::where('status', 'active')
            ->where('end_date', '<', now())
            ->update(['status' => 'expired']);

        $query = TravelerBooster::with(['traveler:id,name,phone,profile_photo', 'booster', 'traveler.ratings'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('booster_id')) {
            $query->where('booster_id', $request->booster_id);
        }

        $boosters = $query->paginate(20)->through(fn($tb) => [
            'id'           => $tb->id,
            'traveler'     => [
                'id'    => $tb->traveler->id,
                'name'  => $tb->traveler->name,
                'phone' => $tb->traveler->phone,
                'photo' => $tb->traveler->profile_photo,
                'rating' => round($tb->traveler->ratings()->avg('rating') ?? 0, 1),
            ],
            'plan'         => $tb->booster->name,
            'plan_color'   => $tb->booster->color,
            'slots'        => $tb->booster->slots,
            'orders_gained' => $tb->orders_gained,
            'start_date'   => $tb->start_date->format('d M Y'),
            'end_date'     => $tb->end_date->format('d M Y'),
            'days_left'    => max(0, (int) now()->diffInDays($tb->end_date, false)),
            'status'       => $tb->status,
        ]);

        return response()->json(['success' => true, 'data' => $boosters]);
    }

    /**
     * Admin: suspend/active booster traveler
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,suspended',
        ]);

        $travelerBooster = TravelerBooster::findOrFail($id);
        $travelerBooster->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status booster berhasil diubah.',
        ]);
    }

    // ─── PRIVATE ─────────────────────────────────────────────────────────────────

    /**
     * activate booster traveler
     */
    private function activateBooster(PaymentBooster $payment): void
    {
        if ($payment->status !== 'paid') return;

        $booster = $payment->booster ?? Booster::find($payment->booster_id);
        if (!$booster) return;

        // Checked traveler booster
        if (TravelerBooster::where('payment_booster_id', $payment->id)->exists()) return;

        $startDate = now();
        $endDate   = $startDate->copy()->addDays($booster->duration);

        TravelerBooster::create([
            'traveler_id'       => $payment->traveler_id,
            'booster_id'        => $booster->id,
            'payment_booster_id' => $payment->id,
            'start_date'        => $startDate,
            'end_date'          => $endDate,
            'orders_gained'     => 0,
            'status'            => 'active',
        ]);
    }

    // Admin wallet 
    /**
 * Admin: wallet stats dari payment booster
 */
    public function adminWallet(Request $request)
    {
        // Total income booster 
        $totalIncome = PaymentBooster::where('status', 'paid')->sum('amount');

        // Income this month
        $thisMonth = PaymentBooster::where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        // Income last month
        $lastMonth = PaymentBooster::where('status', 'paid')
            ->whereMonth('paid_at', now()->subMonth()->month)
            ->whereYear('paid_at', now()->subMonth()->year)
            ->sum('amount');

        // History transaction booster
        $transactions = PaymentBooster::where('status', 'paid')
            ->with(['traveler:id,name,phone,profile_photo', 'booster:id,name,color,price'])
            ->latest('paid_at')
            ->paginate(10);

        $transactions->getCollection()->transform(fn($p) => [
            'id'         => $p->id,
            'traveler'   => [
                'id'    => $p->traveler->id,
                'name'  => $p->traveler->name,
                'photo' => $p->traveler->profile_photo,
            ],
            'plan'       => $p->booster->name,
            'plan_color' => $p->booster->color,
            'amount'     => (float) $p->amount,
            'paid_at'    => $p->paid_at?->format('d M Y'),
            'order_id'   => $p->midtrans_order_id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_income' => (float) $totalIncome,
                    'this_month'   => (float) $thisMonth,
                    'last_month'   => (float) $lastMonth,
                    'total_trx'    => PaymentBooster::where('status', 'paid')->count(),
                ],
                'transactions' => $transactions,
            ],
        ]);
    }
}