<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Notification;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected MidtransService $midtrans;

    public function __construct(MidtransService $midtrans)
    {
        $this->midtrans = $midtrans;
    }

    /**
     * =========================
     * CREATE PAYMENT
     * =========================
     */
    public function createPayment(Request $request, $transactionId)
    {
        $customer = $request->user();

        $transaction = Transaction::where('customer_id', $customer->id)
            ->where('status', 'on_progress')
            ->with('trip')
            ->findOrFail($transactionId);

        $existingPayment = Payment::where('transaction_id', $transaction->id)
            ->where('payment_status', 'pending')
            ->latest()
            ->first();

        if ($existingPayment) {
            return response()->json([
                'success' => true,
                'data' => [
                    'snap_token' => $existingPayment->snap_token,
                    'payment_id' => $existingPayment->id,
                    'snap_url'   => $this->midtrans->getSnapUrl(),
                    'client_key' => $this->midtrans->getClientKey(),
                ]
            ]);
        }

        $orderId = 'NTG-' . $transaction->id . '-' . time();

        $items = [
            [
                'id'       => 'SHIPPING-' . $transaction->id,
                'price'    => (int) $transaction->shipping_price,
                'quantity' => 1,
                'name'     => 'Shipping ' . ($transaction->trip->city ?? ''),
            ]
        ];

        if ($transaction->order_type === 'titip-beli' && $transaction->item_price > 0) {
            $items[] = [
                'id'       => 'ITEM-' . $transaction->id,
                'price'    => (int) $transaction->item_price,
                'quantity' => $transaction->quantity,
                'name'     => Str::limit($transaction->name, 50),
            ];
        }

        $grossAmount = collect($items)->sum(fn($i) => $i['price'] * $i['quantity']);

        try {

            $params = $this->midtrans->buildOrderPaymentParams(
                $orderId,
                $grossAmount,
                [
                    'name'  => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
                $items
            );

            $snapToken = $this->midtrans->createSnapToken($params);

            $payment = Payment::create([
                'transaction_id'    => $transaction->id,
                'user_id'           => $customer->id,
                'traveler_id'       => $transaction->traveler_id,
                'snap_token'        => $snapToken,
                'midtrans_order_id' => $orderId,
                'payment_reference' => $orderId,
                'amount'            => $grossAmount,
                'payment_status'    => 'pending',
                'expired_at'        => now()->addHours(24),
            ]);

            Log::info('PAYMENT CREATED', $payment->toArray());

            return response()->json([
                'success' => true,
                'data' => [
                    'snap_token' => $snapToken,
                    'payment_id' => $payment->id,
                    'snap_url'   => $this->midtrans->getSnapUrl(),
                    'client_key' => $this->midtrans->getClientKey(),
                ]
            ]);

        } catch (\Exception $e) {

            Log::error('Midtrans error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pembayaran',
            ], 500);
        }
    }

    /**
     * =========================
     * MIDTRANS WEBHOOK
     * =========================
     */
    public function handleNotification(Request $request)
    {
        Log::info('MIDTRANS WEBHOOK MASUK', $request->all());

        try {

            $notification = $this->midtrans->handleNotification();

            Log::info('ORDER ID MASUK: ' . $notification->order_id);

            $payment = Payment::where('midtrans_order_id', $notification->order_id)->first();

            if (!$payment) {
                Log::error('PAYMENT TIDAK DITEMUKAN: ' . $notification->order_id);
                return response()->json(['message' => 'Payment not found'], 404);
            }

            $status = $notification->transaction_status;

            if ($status === 'settlement') {
                $payment->payment_status = 'paid';
            } elseif ($status === 'pending') {
                $payment->payment_status = 'pending';
            } elseif ($status === 'expire') {
                $payment->payment_status = 'expired';
            } else {
                $payment->payment_status = 'failed';
            }

            /**
             * =========================
             * SUCCESS ONLY ON PAID
             * =========================
             */
            if ($payment->payment_status === 'paid' && !$payment->paid_at) {
                $payment->paid_at = now();

                $this->onPaymentSuccess($payment);

                // =========================
                // NOTIFIKASI ADMIN (AMAN)
                // =========================
                $admins = User::where('role', 'admin')->get();

                foreach ($admins as $admin) {
                    Notification::create([
                        'user_id' => $admin->id,
                        'type' => 'payment',
                        'title' => 'Pembayaran Berhasil',
                        'message' => [
                            'order_id' => $payment->transaction_id,
                            'status'   => 'paid',
                            'amount'   => $payment->amount,
                        ],
                        'is_read' => false,
                    ]);
                }
            }

            $payment->save();

            return response()->json(['message' => 'OK']);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Error'], 500);
        }
    }

    /**
     * =========================
     * SUCCESS HANDLER
     * =========================
     */
    private function onPaymentSuccess(Payment $payment): void
    { 
         Log::info('ON PAYMENT SUCCESS MASUK', [
        'payment_id' => $payment->id
    ]);
    
        $transaction = $payment->transaction;
        if (!$transaction) return;

        if ($transaction->orderProcess) {
            $transaction->orderProcess->update([
                'step'    => 'paid',
                'paid_at' => now(),
            ]);
        }

        if ($transaction->traveler_id) {
            DB::table('travelers')
                ->where('id', $transaction->traveler_id)
                ->increment('balance', $payment->amount);
        }
    }

    /**
     * =========================
     * ADMIN TRANSACTIONS
     * =========================
     */
    public function adminIndex(Request $request)
    {
        $search = $request->search;
        $status = $request->status;

        $query = Payment::with([
            'transaction.trip',
            'transaction.customer',
            'transaction.traveler'
        ])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('midtrans_order_id', 'like', "%{$search}%")
                  ->orWhereHas('transaction.customer', function ($qq) use ($search) {
                      $qq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('transaction.traveler', function ($qq) use ($search) {
                      $qq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($status && $status !== 'all') {
            $query->where('payment_status', $status);
        }

        $payments = $query->get()->map(function ($payment) {
            $transaction = $payment->transaction;

            return [
                'id' => $payment->id,
                'orderId' => $payment->midtrans_order_id,
                'customer' => $transaction?->customer?->name ?? '-',
                'traveler' => $transaction?->traveler?->name ?? '-',
                'route' => ($transaction?->trip?->city ?? '-') . ' → ' . ($transaction?->trip?->destination ?? '-'),
                'amount' => (int) $payment->amount,
                'paymentStatus' => $payment->payment_status,
                'paymentChannel' => $payment->payment_method ?? 'Midtrans',
                'paymentType' => $payment->payment_type ?? '-',
                'orderType' => $transaction?->order_type ?? '-',
                'date' => $payment->created_at ? $payment->created_at->format('d M Y H:i') : '-',
                'paidAt' => $payment->paid_at ? $payment->paid_at->format('d M Y H:i') : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => ['data' => $payments],
            'stats' => [
                'total' => Payment::count(),
                'paid' => Payment::where('payment_status', 'paid')->count(),
                'pending' => Payment::where('payment_status', 'pending')->count(),
                'failed' => Payment::whereIn('payment_status', ['failed', 'expired'])->count(),
                'total_volume' => Payment::where('payment_status', 'paid')->sum('amount'),
            ]
        ]);
    }
}