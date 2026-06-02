<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use App\Models\Payment;
use App\Models\Notification;
use App\Models\User;
use App\Models\WithdrawRequest;
use App\Models\Traveler;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayoutAccountController extends Controller
{
    // Payout account management
    public function index(Request $request)
    {
        $accounts = PayoutAccount::where('traveler_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $accounts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'payout_type'    => ['required', Rule::in(['bank', 'e_wallet'])],
            'provider'       => ['required', Rule::in(['bca', 'bni', 'mandiri', 'ovo', 'dana', 'gopay'])],
            'account_name'   => 'required|string|max:255',
            'account_number' => 'required|string|max:50',
        ]);

        $travelerId = $request->user()->id;

        $exists = PayoutAccount::where('traveler_id', $travelerId)
            ->where('provider', $validated['provider'])
            ->where('account_number', $validated['account_number'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Rekening dengan provider dan nomor ini sudah terdaftar.',
            ], 409);
        }

        $hasDefault = PayoutAccount::where('traveler_id', $travelerId)
            ->where('is_default', true)
            ->exists();

        $account = PayoutAccount::create([
            'traveler_id'    => $travelerId,
            'payout_type'    => $validated['payout_type'],
            'provider'       => $validated['provider'],
            'account_name'   => $validated['account_name'],
            'account_number' => $validated['account_number'],
            'is_default'     => !$hasDefault,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rekening berhasil ditambahkan.',
            'data'    => $account,
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $account = PayoutAccount::where('id', $id)
            ->where('traveler_id', $request->user()->id)
            ->firstOrFail();

        $wasDefault = $account->is_default;
        $account->delete();

        if ($wasDefault) {
            $first = PayoutAccount::where('traveler_id', $request->user()->id)->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rekening berhasil dihapus.',
        ]);
    }

    public function setDefault(Request $request, $id)
    {
        $travelerId = $request->user()->id;

        $account = PayoutAccount::where('id', $id)
            ->where('traveler_id', $travelerId)
            ->firstOrFail();

        PayoutAccount::where('traveler_id', $travelerId)
            ->update(['is_default' => false]);

        $account->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Rekening utama berhasil diubah.',
        ]);
    }

   // Wallet balance management
    public function wallet(Request $request)
    {
        $traveler = $request->user();

        $totalIncome = Payment::where('traveler_id', $traveler->id)
            ->where('payment_status', 'paid')
            ->sum('amount');

        $totalWithdraw = WithdrawRequest::where('traveler_id', $traveler->id)
            ->whereIn('withdraw_status', ['approved', 'paid'])
            ->sum('amount');

        $balance = $totalIncome - $totalWithdraw;

        return response()->json([
            'success' => true,
            'data' => [
                'balance'       => (float) $balance,
                'totalIncome'   => (float) $totalIncome,
                'totalWithdraw' => (float) $totalWithdraw,
            ],
        ]);
    }

    public function recentIncome(Request $request)
    {
        $payments = Payment::where('traveler_id', $request->user()->id)
            ->where('payment_status', 'paid')
            ->with('transaction:id,sku,name')
            ->latest('paid_at')
            ->limit(10)
            ->get()
            ->map(function ($p) {
                return [
                    'id'     => $p->id,
                    'title'  => $p->transaction?->name ?? $p->transaction?->sku ?? 'Order',
                    'amount' => (float) $p->amount,
                    'date'   => $p->paid_at?->format('d M Y'),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $payments,
        ]);
    }

    // Wallet history management
    public function walletHistory(Request $request)
    {
        $travelerId = $request->user()->id;
        $filter = $request->get('filter', 'all'); // all, income, withdraw

        $items = collect();

        // Income (from payments)
        if ($filter === 'all' || $filter === 'income') {
            $incomes = Payment::where('traveler_id', $travelerId)
                ->where('payment_status', 'paid')
                ->with('transaction:id,sku,name')
                ->latest('paid_at')
                ->get()
                ->map(function ($p) {
                    return [
                        'id'     => 'inc-' . $p->id,
                        'type'   => 'income',
                        'title'  => $p->transaction?->name ?? $p->transaction?->sku ?? 'Order',
                        'amount' => (float) $p->amount,
                        'status' => 'success',
                        'method' => $p->payment_type,
                        'date'   => $p->paid_at?->toISOString(),
                        'date_formatted' => $p->paid_at?->format('d M Y • H:i'),
                    ];
                });
            $items = $items->merge($incomes);
        }

        // Withdraw
        if ($filter === 'all' || $filter === 'withdraw') {
            $withdraws = WithdrawRequest::where('traveler_id', $travelerId)
                ->with('payoutAccount:id,provider,payout_type,account_number')
                ->latest()
                ->get()
                ->map(function ($w) {
                    $statusMap = [
                        'pending'  => 'processing',
                        'approved' => 'processing',
                        'paid'     => 'success',
                        'rejected' => 'failed',
                        'failed'   => 'failed',
                    ];
                    return [
                        'id'     => 'wd-' . $w->id,
                        'type'   => 'withdraw',
                        'title'  => 'Penarikan ke ' . ($w->payoutAccount?->payout_type === 'bank' ? 'Rekening Bank' : 'E-Wallet'),
                        'amount' => (float) $w->amount,
                        'fee'    => (float) $w->fee,
                        'net'    => (float) $w->net_amount,
                        'status' => $statusMap[$w->withdraw_status] ?? 'processing',
                        'withdraw_status' => $w->withdraw_status,
                        'method' => $w->payoutAccount?->provider,
                        'account_number' => $w->payoutAccount?->account_number,
                        'note'   => $w->note,
                        'date'   => $w->created_at?->toISOString(),
                        'date_formatted' => $w->created_at?->format('d M Y • H:i'),
                    ];
                });
            $items = $items->merge($withdraws);
        }

        // Sort by date descending
        $sorted = $items->sortByDesc('date')->values();

        return response()->json([
            'success' => true,
            'data'    => $sorted,
        ]);
    }

    // Withdraw request management 
    public function createWithdraw(Request $request)
    {
        $validated = $request->validate([
            'payout_account_id' => 'required|exists:payout_accounts,id',
            'amount' => 'required|numeric|min:10000',
        ], [
            'amount.min' => 'Minimal penarikan Rp 10.000.',
        ]);

        $travelerId = $request->user()->id;
        $traveler = Traveler::findOrFail($travelerId);

        // checked payout account traveler
        $account = PayoutAccount::where('id', $validated['payout_account_id'])
            ->where('traveler_id', $travelerId)
            ->firstOrFail();

        // Checked amount and calculate fee
        $amount = $validated['amount'];
        $fee = $account->payout_type === 'bank' ? 2500 : 0;
        $netAmount = $amount - $fee;

        if ($amount > $traveler->balance) {
            return response()->json([
                'success' => false,
                'message' => 'Saldo tidak cukup. Saldo tersedia: Rp ' . number_format($traveler->balance, 0, ',', '.'),
            ], 422);
        }

        // Create withdraw — langsung status "paid" (tanpa menunggu admin)
        $withdraw = WithdrawRequest::create([
            'traveler_id'       => $travelerId,
            'payout_account_id' => $account->id,
            'amount'            => $amount,
            'fee'               => $fee,
            'net_amount'        => $netAmount,
            'withdraw_status'   => 'paid',
        ]);

        // Deduct balance
        $traveler->decrement('balance', $amount); 


        // NOTIF KE ADMIN
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {

            Notification::create([
                'user_id' => $admin->id,

                'type' => 'withdraw',
                'title' => 'Withdraw Baru',

                'message' => json_encode([
                    'traveler' => $traveler->name,
                    'amount' => $amount,
                    'status' => 'withdraw_request',
                ]),

                'is_read' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penarikan berhasil diproses. Dana akan segera masuk ke rekening tujuan.',
            'data'    => [
                'id'         => $withdraw->id,
                'amount'     => $amount,
                'fee'        => $fee,
                'net_amount' => $netAmount,
                'status'     => 'paid',
            ],
        ], 201);
    }

    public function withdrawHistory(Request $request)
    {
        $withdraws = WithdrawRequest::where('traveler_id', $request->user()->id)
            ->with('payoutAccount:id,provider,payout_type,account_name,account_number')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $withdraws,
        ]);
    }
}