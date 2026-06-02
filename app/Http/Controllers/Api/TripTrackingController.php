<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripTracking;
use Illuminate\Http\Request;

class TripTrackingController extends Controller
{
    // Traveler: Start tracking
    public function start(Request $request, $tripId)
    {
        $trip = Trip::where('traveler_id', $request->user()->id)
            ->where('status', 'active')
            ->findOrFail($tripId);

        if ($trip->is_tracking) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking sudah berjalan.',
            ], 409);
        }

        $trip->update([
            'is_tracking'         => true,
            'tracking_started_at' => now(),
            'tracking_finished_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tracking dimulai.',
        ]);
    }

    // Traveler: Update location in tracking
    public function updateLocation(Request $request, $tripId)
    {
        $validated = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed'     => 'nullable|numeric|min:0',
            'heading'   => 'nullable|numeric|between:0,360',
        ]);

        $trip = Trip::where('traveler_id', $request->user()->id)
            ->where('is_tracking', true)
            ->findOrFail($tripId);

        $tracking = TripTracking::create([
            'trip_id'     => $trip->id,
            'latitude'    => $validated['latitude'],
            'longitude'   => $validated['longitude'],
            'speed'       => $validated['speed'] ?? null,
            'heading'     => $validated['heading'] ?? null,
            'recorded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $tracking,
        ]);
    }

    // Traveler: Finish tracking
    public function stop(Request $request, $tripId)
    {
        $trip = Trip::where('traveler_id', $request->user()->id)
            ->where('is_tracking', true)
            ->findOrFail($tripId);

        $trip->update([
            'is_tracking'          => false,
            'tracking_finished_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tracking selesai.',
        ]);
    }

    // Traveler: Show history tracking
    public function history(Request $request, $tripId)
    {
        $trip = Trip::where('traveler_id', $request->user()->id)
            ->findOrFail($tripId);

        $trackings = TripTracking::where('trip_id', $trip->id)
            ->orderBy('recorded_at', 'asc')
            ->get();

        return response()->json([
            'success'    => true,
            'is_tracking' => $trip->is_tracking,
            'data'       => $trackings,
        ]);
    }

    // Customer: View tracking traveler
    public function customerView(Request $request, $tripId)
    {
        $trip = Trip::with('traveler:id,name,phone,profile_photo')
            ->findOrFail($tripId);

        // Last location
        $latest = TripTracking::where('trip_id', $trip->id)
            ->latest('recorded_at')
            ->first();

        // All routes
        $route = TripTracking::where('trip_id', $trip->id)
            ->orderBy('recorded_at', 'asc')
            ->select('latitude', 'longitude', 'recorded_at')
            ->get();

        return response()->json([
            'success'     => true,
            'is_tracking' => $trip->is_tracking,
            'traveler'    => $trip->traveler,
            'trip'        => [
                'id'          => $trip->id,
                'from'        => $trip->city,
                'to'          => $trip->destination,
                'departure'   => $trip->departure_at,
                'arrival'     => $trip->estimated_arrival_at,
            ],
            'latest'      => $latest,
            'route'       => $route,
        ]);
    }
}