<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WithdrawRequest;
use App\Models\User;
use App\Models\Traveler;
use App\Models\Transaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // =========================
    // TRAVELER DASHBOARD
    // =========================
    public function traveler(Request $request)
    {
        $traveler = $request->user();

        // Stats
        $totalTrips = $traveler->trips()->count();

        $totalOrdersFinished = $traveler->transactions()
            ->where('status', 'finished')
            ->count();

        $totalIncome = Payment::where('traveler_id', $traveler->id)
            ->where('payment_status', 'paid')
            ->sum('amount');

        $incomeThisMonth = Payment::where('traveler_id', $traveler->id)
            ->where('payment_status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $totalWithdraw = WithdrawRequest::where('traveler_id', $traveler->id)
            ->whereIn('withdraw_status', ['approved', 'paid'])
            ->sum('amount');

        $balance = (float) $totalIncome - (float) $totalWithdraw;

        $avgRating = round(
            $traveler->ratings()->avg('rating') ?? 0,
            1
        );

        // Upcoming Trips
        $upcomingTrips = $traveler->trips()
            ->where('status', 'active')
            ->whereDate(
                'departure_at',
                '>=',
                now()->toDateString()
            )
            ->withCount('transactions')
            ->with('transactions:id,trip_id,weight,status')
            ->orderBy('departure_at', 'asc')
            ->limit(2)
            ->get()
            ->map(function ($trip) {

                $actualUsed = $trip->transactions
                    ->whereNotIn('status', ['cancelled'])
                    ->sum('weight');

                $capacityPercent = $trip->capacity > 0
                    ? round(($actualUsed / $trip->capacity) * 100)
                    : 0;

                return [
                    'id' => $trip->id,
                    'from' => $trip->city,
                    'to' => $trip->destination,
                    'date' => $trip->departure_at->format('d M Y'),
                    'departureTime' => $trip->departure_at->format('H:i'),
                    'orders' => $trip->transactions_count,
                    'capacity' => "{$actualUsed}/{$trip->capacity} kg",
                    'capacityPercent' => $capacityPercent,
                    'status' => $trip->status,
                ];
            });

        // Pending Orders
        $pendingOrders = $traveler->transactions()
            ->where('status', 'pending')
            ->with(
                'customer:id,name,phone,profile_photo',
                'trip:id,city,destination'
            )
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) {

                return [
                    'id' => $order->id,
                    'sku' => $order->sku,
                    'customer' =>
                        $order->customer?->name ?? 'Unknown',
                    'item' => $order->name,
                    'weight' => $order->weight . ' kg',
                    'price' =>
                        'Rp ' .
                        number_format(
                            $order->price,
                            0,
                            ',',
                            '.'
                        ),
                    'route' =>
                        ($order->trip?->city ?? '?') .
                        ' → ' .
                        ($order->trip?->destination ?? '?'),
                    'order_type' => $order->order_type,
                ];
            });

        return response()->json([
            'success' => true,

            'data' => [
                'stats' => [
                    'total_trips' => $totalTrips,
                    'orders_finished' =>
                        $totalOrdersFinished,
                    'balance' => $balance,
                    'rating' => $avgRating,
                    'income_this_month' =>
                        (float) $incomeThisMonth,
                ],

                'upcoming_trips' => $upcomingTrips,

                'pending_orders' => $pendingOrders,
            ],
        ]);
    }

    // =========================
    // ADMIN DASHBOARD
    // =========================
    public function admin()
    {
        // TOTAL INCOME
        $totalIncome = Payment::where(
            'payment_status',
            'paid'
        )->sum('amount');

        // TOTAL CUSTOMER
        $totalCustomers = User::where(
            'role',
            'customer'
        )->count();

        // TOTAL TRAVELER
        $totalTravelers = Traveler::count();

        // TOTAL ORDER
        $totalOrders = Transaction::count();

        // PENDING ORDER
        $pendingOrders = Transaction::where(
            'status',
            'pending'
        )
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) {

                return [
                    'id' => $order->id,

                    'order_number' =>
                        $order->sku ??
                        'ORD-' . $order->id,

                    'item_name' =>
                        $order->name ?? 'Order',

                    'total_price' =>
                        $order->price ?? 0,
                ];
            });

        // ACTIVITIES
        $activities = Transaction::latest()
            ->limit(10)
            ->get()
            ->map(function ($order) {

                return [
                    'type' => 'order',

                    'message' =>
                        'Order ' .
                        ($order->sku ?? $order->id) .
                        ' berhasil dibuat',

                    'time' =>
                        $order->created_at->diffForHumans(),

                    'detail' =>
                        'Rp ' .
                        number_format(
                            $order->price ?? 0,
                            0,
                            ',',
                            '.'
                        ),
                ];
            });

        return response()->json([
            'success' => true,

            'data' => [
                'total_income' => $totalIncome,

                'total_customers' => $totalCustomers,

                'total_travelers' => $totalTravelers,

                'total_orders' => $totalOrders,

                'activities' => $activities,

                'pending_orders' => $pendingOrders,
            ],
        ]);
    }
}