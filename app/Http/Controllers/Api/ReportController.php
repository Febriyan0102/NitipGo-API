<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Transaction; 
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // Customer: create help
    public function store(Request $request, $transactionId)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'priority'    => 'nullable|in:low,medium,high',
        ]);

        $transaction = Transaction::where('customer_id', $request->user()->id)
            ->findOrFail($transactionId);

        // Checked
        if (Report::where('transaction_id', $transaction->id)
            ->where('customer_id', $request->user()->id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah membuat laporan untuk order ini.',
            ], 422);
        }

        $report = Report::create([
            'code'             => 'RPT-' . strtoupper(Str::random(8)),
            'transaction_id'   => $transaction->id,
            'customer_id'      => $request->user()->id,
            'traveler_id'      => $transaction->traveler_id,
            'reporter_role'    => 'customer',
            'title'            => $request->title,
            'description'      => $request->description,
            'dispute_priority' => $request->priority ?? 'medium',
            'dispute_status'   => 'open',
        ]);
          $admins = User::where('role', 'admin')->get();

foreach ($admins as $admin) {

    Notification::create([
        'user_id' => $admin->id,
        'type' => 'Dispute',
        'title' => 'Laporan Baru',
        'message' => json_encode([
            'order_id' => $transaction->id,
            'status' => 'pending_review',
        ]),
        'is_read' => false,
    ]);

}
         

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil dikirim.',
            'data'    => $report,
        ], 201);
    } 
    

    // Customer: show report
    public function showByTransaction($transactionId)
    {
        $report = Report::where('transaction_id', $transactionId)->first();

        return response()->json([
            'success' => true,
            'data'    => $report,
        ]);
    }

    // Customer : show answer from traveler
    public function customerAnswer($transactionId)
    {
        $report = Report::where('transaction_id', $transactionId)
            ->select('id', 'code', 'title', 'description', 'dispute_status',
                    'traveler_note', 'resolved_at', 'created_at')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $report,
        ]);
    }

    // Admin: list all disputes
    public function adminIndex(Request $request)
    {
        $query = Report::with([
            'transaction:id,customer_id,traveler_id,name,order_type',
            'customer:id,name',
            'traveler:id,name',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('dispute_status', $request->status);
        }

        $reports = $query->paginate(20);

        $reports->getCollection()->transform(fn($r) => [
            'id'          => $r->id,
            'code'        => $r->code,
            'orderId'     => 'TRX-' . $r->transaction_id,
            'customer'    => $r->customer?->name ?? '-',
            'traveler'    => $r->traveler?->name ?? '-',
            'issue'       => $r->title,
            'description' => $r->description ?? '-',
            'status'      => match($r->dispute_status) {
                'open'         => 'open',
                'under_review' => 'in_review',
                'resolved'     => 'resolved',
                default        => 'open',
            },
            'priority'    => $r->dispute_priority,
            'date'        => $r->created_at->format('d M Y'),
            'amount'      => '-',
            'travelerNote' => $r->traveler_note,
            'note'        => $r->note,
            'resolvedAt'  => $r->resolved_at?->format('d M Y, H:i'),
        ]);

        $open     = Report::where('dispute_status', 'open')->count();
        $review   = Report::where('dispute_status', 'under_review')->count();
        $resolved = Report::where('dispute_status', 'resolved')->count();

        return response()->json([
            'success' => true,
            'data'    => $reports,
            'stats'   => [
                'open'     => $open,   
                'review'   => $review,  
                'resolved' => $resolved, 
            ],
        ]);
    }

    // Admin: mark report
    public function markInReview(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        $report->update(['dispute_status' => 'under_review']);

        return response()->json(['success' => true, 'message' => 'Status diperbarui.']);
    }

    // Admin: finished report
    public function resolve(Request $request, $id)
    {
        $request->validate([
            'note'     => 'required|string|max:1000',
            'decision' => 'required|in:refund,partial_refund,release_payment',
        ]);

        $report = Report::findOrFail($id);
        $report->update([
            'dispute_status' => 'resolved',
            'note'           => $request->note . ' [Keputusan: ' . $request->decision . ']',
            'resolved_at'    => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Dispute diselesaikan.']);
    }

    // Traveler: list all disputes
    public function travelerIndex(Request $request)
    {
        $traveler = $request->user();

        $query = Report::where('traveler_id', $traveler->id)
            ->with([
                'customer:id,name',
                'transaction:id,name,order_type,trip_id',
                'transaction.trip:id,city,destination',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('dispute_status', $request->status);
        }

        $reports = $query->paginate(20);

        $reports->getCollection()->transform(fn($r) => [
            'id'          => $r->id,
            'code'        => $r->code,
            'orderId'     => 'TRX-' . $r->transaction_id,
            'customer'    => $r->customer?->name ?? '-',
            'issue'       => $r->title,
            'description' => $r->description ?? '-',
            'status'      => match($r->dispute_status) {
                'open'         => 'open',
                'under_review' => 'in_review',
                'resolved'     => 'resolved',
                default        => 'open',
            },
            'priority'    => $r->dispute_priority,
            'date'        => $r->created_at->format('d M Y'),
            'amount'      => '-',
            'route'       => $r->transaction?->trip
                ? $r->transaction->trip->city . ' → ' . $r->transaction->trip->destination
                : '-',
            'orderName'   => $r->transaction?->name ?? '-',
            'note'        => $r->note,
            'travelerNote'=> $r->traveler_note ?? null, // ← field baru, lihat di bawah
            'resolvedAt'  => $r->resolved_at?->format('d M Y, H:i'),
        ]);

        $stats = [
            'open'     => Report::where('traveler_id', $traveler->id)->where('dispute_status', 'open')->count(),
            'review'   => Report::where('traveler_id', $traveler->id)->where('dispute_status', 'under_review')->count(),
            'resolved' => Report::where('traveler_id', $traveler->id)->where('dispute_status', 'resolved')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $reports,
            'stats'   => $stats,
        ]);
    }

    // traveler resolve report
    public function travelerResolve(Request $request, $id)
    {
        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $report = Report::where('traveler_id', $request->user()->id)
            ->whereIn('dispute_status', ['open', 'under_review'])
            ->findOrFail($id);

        $report->update([
            'traveler_note'  => $request->note,
            'dispute_status' => 'resolved',
            'resolved_at'    => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil diselesaikan.',
        ]);
    }

    // Traveler: reply disputes
    public function travelerReply(Request $request, $id)
    {
        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $report = Report::where('traveler_id', $request->user()->id)
            ->whereIn('dispute_status', ['open', 'under_review'])
            ->findOrFail($id);

        $report->update([
            'traveler_note' => $request->note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jawaban berhasil disimpan.',
        ]);
    }
}