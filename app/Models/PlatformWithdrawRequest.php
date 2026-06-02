<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformWithdrawRequest extends Model
{
    use HasFactory;

    protected $table = 'platform_withdraw_requests';

    protected $fillable = [
        'bank_name',
        'account_number',
        'account_name',
        'amount',
        'fee',
        'net_amount',
        'withdraw_status',
        'note',
        'reference_no',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'fee'        => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    // ─── Static Helpers ───────────────────────────────────────────────────────

    public static function totalIncome(): float
    {
        $booster = \App\Models\PaymentBooster::where('status', 'paid')->sum('amount');
        $ads     = \App\Models\AdvertisementPayment::where('status', 'paid')->sum('amount');
        return (float) ($booster + $ads);
    }

    public static function totalWithdrawn(): float
    {
        return (float) static::where('withdraw_status', 'completed')->sum('net_amount');
    }

    public static function availableBalance(): float
    {
        return max(0, static::totalIncome() - static::totalWithdrawn());
    }
}