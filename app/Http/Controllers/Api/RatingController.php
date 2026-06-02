<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\Transaction;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    // Customer give rating from transaction
    public function store(Request $request, $transactionId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:500',
        ]);

        $order = Transaction::where('customer_id', $request->user()->id)
            ->where('status', 'finished')
            ->findOrFail($transactionId);

        // Cek sudah pernah rating
        if (Rating::where('transaction_id', $order->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memberikan rating untuk order ini.',
            ], 422);
        }

        $rating = Rating::create([
            'transaction_id' => $order->id,
            'traveler_id'    => $order->traveler_id,
            'customer_id'    => $request->user()->id,
            'rating'         => $request->rating,
            'review'         => $request->review,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rating berhasil dikirim. Terima kasih!',
            'data'    => $rating,
        ], 201);
    }

    public function show($transactionId)
    {
        $rating = Rating::where('transaction_id', $transactionId)->first();

        return response()->json([
            'success' => true,
            'data'    => $rating,
        ]);
    }

    // Admin: list all rating
    public function adminIndex(Request $request)
    {
        $ratings = Rating::with([
            'transaction:id,traveler_id,customer_id,order_type,trip_id',
            'transaction.traveler:id,name,city',
            'transaction.trip:id,city,destination',  
            'customer:id,name',
        ])->latest()->paginate(20);

        $ratings->getCollection()->transform(fn($r) => [
            'id'            => (string) $r->id,
            'rating'        => (int) $r->rating,
            'review'        => $r->review ?? '-',
            'customer'      => $r->customer?->name ?? 'Anonim',
            'traveler'      => $r->transaction?->traveler?->name ?? '-',
            'travelerRoute' => $r->transaction?->trip
                ? $r->transaction->trip->city . ' → ' . $r->transaction->trip->destination
                : ($r->transaction?->traveler?->city
                    ? $r->transaction->traveler->city . ' (rute tidak tersedia)'
                    : '-'),
            'orderId'       => 'TRX-' . $r->transaction_id,
            'date'          => $r->created_at->format('d M Y'),
            'category'      => match($r->transaction?->order_type) {
                'titip-beli' => 'Titip Beli',
                'kirim'      => 'Pengiriman',
                default      => '-',
            },
            'flagged' => false,
        ]);

        // Stats — pakai DB::table langsung agar lebih efisien
        $statsRaw = \DB::table('ratings')->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as neutral,
            SUM(CASE WHEN rating < 3 THEN 1 ELSE 0 END) as negative,
            ROUND(AVG(rating), 1) as avg
        ')->first();

        return response()->json([
            'success' => true,
            'data'    => $ratings,
            'stats'   => [
                'total'    => (int) $statsRaw->total,
                'positive' => (int) $statsRaw->positive,
                'neutral'  => (int) $statsRaw->neutral,
                'negative' => (int) $statsRaw->negative,
                'avg'      => (float) ($statsRaw->avg ?? 0),
            ],
        ]);
    }

    // TRAVELER
    public function travelerReviews(Request $request)
    {
        $traveler = $request->user();

        $reviews = Rating::where('traveler_id', $traveler->id)
            ->with('customer:id,name,profile_photo')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'rating'     => $r->rating,
                'review'     => $r->review,
                'customer'   => $r->customer?->name ?? 'Anonim',
                'created_at' => $r->created_at->diffForHumans(),
            ]);

        return response()->json(['success' => true, 'data' => $reviews]);
    }

    // Traveler: list all rating from customer
    public function travelerIndex(Request $request)
    {
        $traveler = $request->user();

        $ratings = Rating::where('traveler_id', $traveler->id)
            ->with([
                'customer:id,name,profile_photo',
                'transaction:id,order_type,trip_id',
                'transaction.trip:id,city,destination',
            ])
            ->latest()
            ->paginate(20);

        $ratings->getCollection()->transform(fn($r) => [
            'id'       => (string) $r->id,
            'rating'   => (int) $r->rating,
            'review'   => $r->review ?? '',
            'customer' => $r->customer?->name ?? 'Anonim',
            'orderId'  => 'TRX-' . $r->transaction_id,
            'date'     => $r->created_at->format('d M Y'),
            'category' => match($r->transaction?->order_type) {
                'titip-beli' => 'Titip Beli',
                'kirim'      => 'Pengiriman',
                default      => '-',
            },
            'route' => $r->transaction?->trip
                ? $r->transaction->trip->city . ' → ' . $r->transaction->trip->destination
                : '-',
        ]);

        // Rating stats
        $statsRaw = \DB::table('ratings')
            ->where('traveler_id', $traveler->id)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as neutral,
                SUM(CASE WHEN rating < 3 THEN 1 ELSE 0 END) as negative,
                ROUND(AVG(rating), 1) as avg
            ')->first();

        return response()->json([
            'success' => true,
            'data'    => $ratings,
            'stats'   => [
                'total'    => (int) $statsRaw->total,
                'positive' => (int) $statsRaw->positive,
                'neutral'  => (int) $statsRaw->neutral,
                'negative' => (int) $statsRaw->negative,
                'avg'      => (float) ($statsRaw->avg ?? 0),
            ],
        ]);
    }
}