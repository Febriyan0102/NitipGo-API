<?php

namespace App\Http\Controllers\Api;
use App\Models\Notification;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\OrderProcess; 
use App\Models\User;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerOrderController extends Controller
{

    public function store(Request $request)
    {
        $validated = $request->validate([
            'trip_id'              => 'required|exists:trips,id',
            'order_type'           => 'required|in:titip-beli,kirim',
            'name'                 => 'required|string|max:255',
            'description'          => 'required|string|max:1000',
            'weight'               => 'required|numeric|min:0.1',
            'quantity'             => 'nullable|integer|min:1',
            'item_price'           => 'nullable|numeric|min:0',
            'photo'                => 'required|image|max:5120',
            'notes'                => 'nullable|string|max:500',
            'recipient_name'       => 'nullable|required_if:order_type,kirim|string|max:255',
            'recipient_phone'      => 'nullable|required_if:order_type,kirim|string|max:20',
            'destination_address'  => 'nullable|required_if:order_type,kirim|string|max:1000',
        ], [
            'trip_id.required'             => 'Perjalanan wajib dipilih.',
            'trip_id.exists'               => 'Perjalanan tidak ditemukan.',
            'name.required'                => 'Nama barang wajib diisi.',
            'description.required'         => 'Deskripsi barang wajib diisi.',
            'weight.required'              => 'Berat barang wajib diisi.',
            'weight.min'                   => 'Berat minimal 0.1 kg.',
            'recipient_name.required_if'   => 'Nama penerima wajib diisi untuk pengiriman.',
            'recipient_phone.required_if'  => 'Nomor telepon penerima wajib diisi untuk pengiriman.',
            'destination_address.required_if' => 'Alamat penerima wajib diisi untuk pengiriman.',
        ]);

        $customer = $request->user();

        $trip = Trip::with(['traveler', 'pickups', 'collections'])
        ->where('status', 'active')
        ->whereDate('estimated_arrival_at', '>=', now()->toDateString())
        ->findOrFail($validated['trip_id']);
         
            // CEK PERJALANAN KADALUARSA
        if (\Carbon\Carbon::parse($trip->departure_at)->startOfDay()->lte(now()->startOfDay())) {
            return response()->json([
                'success' => false,
                'message' => 'Perjalanan sudah kadaluarsa dan tidak bisa dipesan.',
            ], 422);
        }
        $remaining = $trip->capacity - $trip->used_capacity;
        if ($validated['weight'] > $remaining) {
            return response()->json([
                'success' => false,
                'message' => "Kapasitas tidak cukup. Tersisa {$remaining} kg.",
            ], 422);
        }

        $weight       = $validated['weight'];
        $pricePerKg   = $trip->price;
        $shippingCost = $weight * $pricePerKg;

        $itemPrice = 0;
        $quantity  = 1;
        if ($validated['order_type'] === 'titip-beli') {
            $itemPrice = $validated['item_price'] ?? 0;
            $quantity  = $validated['quantity'] ?? 1;
        }

        $itemTotal  = $itemPrice * $quantity;
        $totalPrice = $shippingCost + $itemTotal;

        $imagePath = null;
        if ($request->hasFile('photo')) {
            $imagePath = $request->file('photo')->store('orders', 'public');
        }

        $pickupPoint     = $trip->pickups->first();
        $collectionPoint = $trip->collections->first();
        $sku             = 'ORD-' . strtoupper(Str::random(8));

        $transaction = Transaction::create([
            'traveler_id'         => $trip->traveler_id,
            'customer_id'         => $customer->id,
            'trip_id'             => $trip->id,
            'pickup_point_id'     => $pickupPoint?->id,
            'collection_point_id' => $validated['order_type'] === 'kirim' ? $collectionPoint?->id : null,
            'sku'                 => $sku,
            'order_type'          => $validated['order_type'],
            'name'                => $validated['name'],
            'description'         => $validated['description'],
            'arrival_date'        => $trip->estimated_arrival_at?->toDateString(),
            'quantity'            => $quantity,
            'item_price'          => $validated['order_type'] === 'titip-beli' ? $itemPrice : null,
            'destination_address' => $validated['destination_address'] ?? null,
            'notes'               => $validated['notes'] ?? null,
            'weight'              => $weight,
            'commission'          => 0,
            'shipping_price'      => $shippingCost,
            'price'               => $totalPrice,
            'image'               => $imagePath,
            'recipient_name'      => $validated['recipient_name'] ?? null,
            'recipient_phone'     => $validated['recipient_phone'] ?? null,
            'status'              => 'pending',
        ]);

        // Buat order process
        OrderProcess::create([
            'transaction_id' => $transaction->id,
            'step'           => 'waiting_acceptance',
        ]);

        $trip->increment('used_capacity', $weight); 
        
         // NOTIF KE TRAVELER
        Notification::create([
            'traveler_id' => $trip->traveler_id,
            'role'        => 'traveler',

            'type'  => 'order',
            'title' => 'Order Baru',

            'message' => json_encode([
                'order_id' => $transaction->id,
            ]),

            'icon' => 'info',
        ]); 

         // NOTIF KE ADMIN
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {

            Notification::create([
                'user_id' => $admin->id,

                'type'  => 'order',
                'title' => 'Order Baru Masuk',

                'message' => [
                    'order_id' => $transaction->id,
                    'status'   => 'pending',
                ],

                'is_read' => false,
            ]);

        }
        return response()->json([
            'success' => true,
            'message' => 'Order berhasil dibuat.',
            'data'    => ['id' => $transaction->id, 'sku' => $transaction->sku],
        ], 201);
    }


    public function index(Request $request)
    {
        $orders = Transaction::where('customer_id', $request->user()->id)
            ->with([
                'traveler:id,name,profile_photo',
                'trip:id,city,destination',
                'orderProcess',
                'payment:id,transaction_id,payment_status,paid_at,snap_token',
                'pickupPoint:id,name,address,pickup_time,map_url',
                'rating:id,transaction_id,rating,review',
            ])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $orders,
        ]);
    }


    public function show(Request $request, $id)
    {
        $order = Transaction::where('customer_id', $request->user()->id)
            ->with([
                'traveler:id,name,phone,email,profile_photo,city',

                'trip:id,city,destination,departure_at,estimated_arrival_at,price',
                'pickupPoint',
                'collectionPoint',
                'orderProcess',
                'payment',
                'rating',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $order,
            'price_confirmed' => (bool) $order->price_confirmed,
        ]);
    }


    public function cancel(Request $request, $id)
    {
        $order = Transaction::where('customer_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $order->update(['status' => 'cancelled']);

        $order->orderProcess?->update([
            'step'          => 'cancelled',
            'cancelled_at'  => now(),
            'cancel_reason' => 'Dibatalkan oleh customer',
        ]);

        $order->trip?->decrement('used_capacity', $order->weight);

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil dibatalkan.',
        ]);
    }
}