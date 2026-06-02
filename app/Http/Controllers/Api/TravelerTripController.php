<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Pickup;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TravelerTripController extends Controller
{
    // Index New trip
    public function index(Request $request)
    {
        $traveler = $request->user();

        // Auto expire trip when estimated arrival has passed
        Trip::where('traveler_id', $traveler->id)
            ->where('status', 'active')
            ->whereNotNull('estimated_arrival_at')
            ->where('estimated_arrival_at', '<=', now()) 
            ->update(['status' => 'expired']);

        $trips = Trip::where('traveler_id', $traveler->id)
            ->withCount('transactions')
            ->with('transactions:id,trip_id,weight,status')
            ->latest('departure_at')
            ->get()
            ->map(function ($trip) {
                // Auto-sync used_capacity
                $actualUsed = $trip->transactions
                    ->whereNotIn('status', ['cancelled'])
                    ->sum('weight');
                if ((float) $trip->used_capacity !== (float) $actualUsed) {
                    $trip->update(['used_capacity' => $actualUsed]);
                    $trip->used_capacity = $actualUsed;
                }

                $capacityPercent = $trip->capacity > 0
                    ? round(($trip->used_capacity / $trip->capacity) * 100)
                    : 0;

                return [
                    'id'              => $trip->id,
                    'code'            => $trip->code,
                    'from'            => $trip->city,
                    'to'              => $trip->destination,
                    'date'            => $trip->departure_at->format('d M Y'),
                    'departureTime'   => $trip->departure_at->format('H:i'),
                    'arrivalTime'     => $trip->estimated_arrival_at?->format('H:i'),
                    'arrivalDate'     => $trip->estimated_arrival_at?->format('d M Y'),
                    'orders'          => $trip->transactions_count,
                    'capacity'        => "{$trip->used_capacity}/{$trip->capacity} kg",
                    'capacityPercent' => $capacityPercent,
                    'pricePerKg'      => $trip->price,
                    'active'          => $trip->status === 'active',
                    'status'          => $trip->status,
                    'notes'           => $trip->description,
                ];
            });

        return response()->json([
            'success' => true,
            'trips'   => $trips,
        ]);
    }

    // Detail trip
    public function show(Request $request, $id)
    {
        $trip = Trip::where('traveler_id', $request->user()->id)
            ->with(['pickups', 'collections', 'transactions'])
            ->findOrFail($id);

        // Auto-expire when estimated arrival has passed
        if ($trip->status === 'active' && $trip->departure_at->startOfDay()->lt(now()->startOfDay())) {
            $trip->update(['status' => 'expired']);
            $trip->refresh();
        }

        $actualUsed = $trip->transactions
            ->whereNotIn('status', ['cancelled'])
            ->sum('weight');

        if ((float) $trip->used_capacity !== (float) $actualUsed) {
            $trip->update(['used_capacity' => $actualUsed]);
            $trip->used_capacity = $actualUsed;
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                   => $trip->id,
                'code'                 => $trip->code,
                'city'                 => $trip->city,       
                'destination'          => $trip->destination,  
                'departure_at'         => $trip->departure_at,  
                'estimated_arrival_at' => $trip->estimated_arrival_at,
                'price'                => $trip->price,
                'capacity'             => $trip->capacity,
                'used_capacity'        => $trip->used_capacity,
                'description'          => $trip->description,
                'status'               => $trip->status,
                'is_tracking'          => $trip->is_tracking ?? false,
                'pickups'    => $trip->pickups->map(fn($p) => [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'address'     => $p->address,
                    'pickup_time' => $p->pickup_time,
                    'map_url'     => $p->map_url,
                ]),
                'collections' => $trip->collections->map(fn($c) => [
                    'id'               => $c->id,
                    'name'             => $c->name,
                    'address'          => $c->address,
                    'collections_time' => $c->collections_time,
                    'map_url'          => $c->map_url,
                ]),
            ],
        ]);
    }

    // Create trip
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from'             => 'required|string|max:255',
            'to'               => 'required|string|max:255',
            'date'             => 'required|date|after_or_equal:today',
            'departureTime'    => 'required|string',
            'arrivalTime'      => 'required|string',
            'arrivalDate'      => 'required|date|after_or_equal:date',
            'capacity'         => 'required|integer|min:1',
            'pricePerKg'       => 'required|integer|min:1000',
            'notes'            => 'nullable|string|max:500',

            // Collections points
            'collection.name'     => 'required|string|max:255',
            'collection.location' => 'required|string|max:500',
            'collection.mapUrl'   => 'nullable|string|max:500',
            'collection.time'     => 'required|string',

            // Pickup points
            'pickup.name'     => 'required|string|max:255',
            'pickup.location' => 'required|string|max:500',
            'pickup.mapUrl'   => 'nullable|string|max:500',
            'pickup.time'     => 'required|string',
        ], [
            // Alert message validate
            'date.after_or_equal'        => 'Tanggal keberangkatan tidak boleh sebelum hari ini.',
            'arrivalDate.after_or_equal' => 'Tanggal tiba tidak boleh sebelum tanggal keberangkatan.',
            'capacity.min'               => 'Kapasitas minimal 1 kg.',
            'pricePerKg.min'             => 'Harga per kg minimal Rp 1.000.',
        ]);

        $traveler = $request->user();

        // Checked payout account traveler
        if ($traveler->payoutAccounts()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tambahkan rekening pembayaran terlebih dahulu.',
            ], 422);
        }

        // create
        $departureAt = $validated['date'] . ' ' . $validated['departureTime'] . ':00';
        $arrivalAt   = $validated['arrivalDate'] . ' ' . $validated['arrivalTime'] . ':00';

        $trip = Trip::create([
            'traveler_id'        => $traveler->id,
            'code'               => 'TRP-' . strtoupper(Str::random(8)),
            'city'               => $validated['from'],
            'destination'        => $validated['to'],
            'departure_at'       => $departureAt,
            'estimated_arrival_at' => $arrivalAt,
            'price'              => $validated['pricePerKg'],
            'capacity'           => $validated['capacity'],
            'used_capacity'      => 0,
            'description'        => $validated['notes'],
            'status'             => 'active',
            'orders_count'       => 0,
            'is_tracking'        => false,
        ]);

        // Save collections
        Collection::create([
            'trip_id'          => $trip->id,
            'name'             => $validated['collection']['name'],
            'address'          => $validated['collection']['location'],
            'map_url'          => $validated['collection']['mapUrl'] ?? null,
            'collections_time' => $validated['collection']['time'],
            'order'            => 1,
        ]);

        // Save pickup
        Pickup::create([
            'trip_id'     => $trip->id,
            'name'        => $validated['pickup']['name'],
            'address'     => $validated['pickup']['location'],
            'map_url'     => $validated['pickup']['mapUrl'] ?? null,
            'pickup_time' => $validated['pickup']['time'],
            'order'       => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perjalanan berhasil dibuat.',
            'data'    => $trip->load(['pickups', 'collections']),
        ], 201);
    }

    // Update status trip
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,completed,cancelled,expired',
        ]);

        $trip = Trip::where('traveler_id', $request->user()->id)
            ->findOrFail($id);

        $trip->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Status perjalanan berhasil diubah.',
        ]);
    }

    // Delete trip
    public function destroy(Request $request, $id)
    {
        $trip = Trip::where('traveler_id', $request->user()->id)
            ->findOrFail($id);

        // Delete only non active trip
        if ($trip->transactions()->whereNotIn('status', ['cancelled', 'finished'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa menghapus perjalanan yang masih memiliki order aktif.',
            ], 422);
        }

        $trip->pickups()->delete();
        $trip->collections()->delete();
        $trip->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perjalanan berhasil dihapus.',
        ]);
    }
}