<?php

namespace App\Services;

use Midtrans\Snap;
use Midtrans\Config;
use Midtrans\Notification;

class MidtransService
{
    protected string $frontendUrl;

    public function __construct()
    {
        Config::$serverKey    = env('MIDTRANS_SERVER_KEY');
        Config::$clientKey    = env('MIDTRANS_CLIENT_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized  = env('MIDTRANS_IS_SANITIZED', true);
        Config::$is3ds        = env('MIDTRANS_IS_3DS', true);

        
        $this->frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
    }

    /**
     * Generate Snap Token
     */
    public function createSnapToken(array $params): string
    {
        return Snap::getSnapToken($params);
    }

    /**
     * Build parameter untuk Snap
     */
    public function buildOrderPaymentParams(
        string $orderId,
        int $grossAmount,
        array $customer,
        array $items
    ): array {

        return [
            'transaction_details' => [
                'order_id'     => $orderId,
                'gross_amount' => $grossAmount,
            ],

            'customer_details' => [
                'first_name' => $customer['name'] ?? 'Customer',
                'email'      => $customer['email'] ?? '',
                'phone'      => $customer['phone'] ?? '',
            ],

            'item_details' => $items,

          
             'callbacks' => [
    'finish' => $this->frontendUrl . '/orders?status=success&order_id=' . $orderId,
    ],

    'finish_redirect_url' => $this->frontendUrl . '/orders?status=success&order_id=' . $orderId,
    ];
    }

    /**
     * Handle webhook dari Midtrans
     */
    public function handleNotification(): Notification
    {
        return new Notification();
    }

    /**
     * Ambil Client Key
     */
    public function getClientKey(): string
    {
        return env('MIDTRANS_CLIENT_KEY');
    }

    /**
     * Snap JS URL
     */
    public function getSnapUrl(): string 
    {
        return env('MIDTRANS_IS_PRODUCTION')
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }
}