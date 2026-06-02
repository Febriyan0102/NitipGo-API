<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformWithdrawRequest;
use App\Models\PaymentBooster;
use App\Models\AdvertisementPayment;
use Illuminate\Http\Request;

class PlatformWithdrawController extends Controller
{
    // ─── GET /admin/platform-withdraw ────────────────────────────────────────

    public function index(Request $request)
    {
        $withdrawals = PlatformWithdrawRequest::latest()->paginate(15);
        $withdrawals->getCollection()->transform(fn($w) => $this->format($w));

        return response()->json([
            'success' => true,
            'data'    => $withdrawals,
            'summary' => $this->getSummary(),
        ]);
    }

    // ─── POST /admin/platform-withdraw ───────────────────────────────────────
    /**
     * Withdraw auto-completed
     */
    public function store(Request $request)
    {
        $request->validate([
            'bank_name'      => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_name'   => 'required|string|max:255',
            'amount'         => 'required|numeric|min:10000',
            'fee'            => 'nullable|numeric|min:0',
            'note'           => 'nullable|string|max:500',
        ]);

        $available = PlatformWithdrawRequest::availableBalance();
        $amount    = (float) $request->amount;
        $fee       = (float) ($request->fee ?? 0);
        $net       = $amount - $fee;

        if ($amount > $available) {
            return response()->json([
                'success' => false,
                'message' => 'Jumlah melebihi saldo tersedia (Rp ' . number_format($available, 0, ',', '.') . ').',
            ], 422);
        }

        if ($net <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Net amount harus lebih dari 0 setelah dikurangi biaya.',
            ], 422);
        }

        $withdraw = PlatformWithdrawRequest::create([
            'bank_name'       => $request->bank_name,
            'account_number'  => $request->account_number,
            'account_name'    => $request->account_name,
            'amount'          => $amount,
            'fee'             => $fee,
            'net_amount'      => $net,
            'withdraw_status' => 'completed',
            'note'            => $request->note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Penarikan saldo berhasil. Dana sebesar Rp ' . number_format($net, 0, ',', '.') . ' akan segera masuk ke rekening tujuan.',
            'data'    => $this->format($withdraw),
        ], 201);
    }

    // ─── DELETE /admin/platform-withdraw/{id} ────────────────────────────────

    public function destroy($id)
    {
        $withdraw = PlatformWithdrawRequest::findOrFail($id);
        $withdraw->delete();

        return response()->json([
            'success' => true,
            'message' => 'Riwayat penarikan dihapus.',
        ]);
    }

    // ─── GET /admin/platform-withdraw/balance ────────────────────────────────

    public function balance()
    {
        return response()->json([
            'success' => true,
            'data'    => $this->getSummary(),
        ]);
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function getSummary(): array
    {
        $boosterIncome  = (float) PaymentBooster::where('status', 'paid')->sum('amount');
        $adsIncome      = (float) AdvertisementPayment::where('status', 'paid')->sum('amount');
        $totalIncome    = $boosterIncome + $adsIncome;
        $totalWithdrawn = PlatformWithdrawRequest::totalWithdrawn();
        $available      = PlatformWithdrawRequest::availableBalance();

        return [
            'total_income'    => $totalIncome,
            'booster_income'  => $boosterIncome,
            'ads_income'      => $adsIncome,
            'total_withdrawn' => $totalWithdrawn,
            'total_pending'   => 0,
            'available'       => $available,
        ];
    }

    private function format(PlatformWithdrawRequest $w): array
    {
        return [
            'id'             => $w->id,
            'bank_name'      => $w->bank_name,
            'account_number' => $w->account_number,
            'account_name'   => $w->account_name,
            'amount'         => (float) $w->amount,
            'fee'            => (float) $w->fee,
            'net_amount'     => (float) $w->net_amount,
            'status'         => $w->withdraw_status,
            'note'           => $w->note,
            'reference_no'   => $w->reference_no,
            'created_at'     => $w->created_at->format('d M Y, H:i'),
        ];
    }
}