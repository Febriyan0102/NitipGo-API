<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

/**
 * @property Carbon $start_date
 * @property Carbon $end_date
 */
class Advertisement extends Model
{
    protected $fillable = [
        'code', 'partner_name', 'partner_contact',
        'title', 'description', 'image_path', 'link_url',
        'start_date', 'end_date', 'duration_days', 'package',
        'status', 'slot_index', 'payment_id',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
    ];

    // ── Packages ──────────────────────────────────────────────────────────────

    const PACKAGES = [
        'basic'    => ['days' => 7,  'price' => 150_000, 'label' => 'Basic'],
        'standard' => ['days' => 14, 'price' => 250_000, 'label' => 'Standard'],
        'premium'  => ['days' => 30, 'price' => 450_000, 'label' => 'Premium'],
    ];

    const MAX_LIVE_SLOTS = 3;

    // ── Relations ─────────────────────────────────────────────────────────────

    public function payment(): HasOne
    {
        return $this->hasOne(AdvertisementPayment::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeLiveNow($query)
    {
        $today = now()->toDateString();
        return $query->where('status', 'active')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->whereNotNull('slot_index')
            ->orderBy('slot_index');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getDaysRemainingAttribute(): int
    {
        return max(0, (int) now()->startOfDay()->diffInDays($this->end_date, false));
    }

    public function isExpired(): bool
    {
        return $this->end_date->isPast() || $this->status === 'expired';
    }

    public function isExpiring(): bool
    {
        return $this->days_remaining <= 3 && $this->days_remaining > 0;
    }

    /**
     * Find the next available start date given current active ads.
     * Returns today's date string if a slot is available now.
     */
    public static function nextAvailableStartDate(): string
    {
        $today = now()->toDateString();
        for ($i = 0; $i < 120; $i++) {
            $date = Carbon::parse($today)->addDays($i)->toDateString();
            $count = self::where('status', 'active')
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->count();
            if ($count < self::MAX_LIVE_SLOTS) return $date;
        }
        return Carbon::parse($today)->addDays(120)->toDateString();
    }

    /**
     * Reassign slot_index values for currently live ads (1-3).
     * Called after adding or expiring an ad.
     */
    public static function rebalanceSlots(): void
    {
        $today = now()->toDateString();

        // Clear all slot assignments first
        self::where('status', 'active')->update(['slot_index' => null]);

        // Re-assign slots ordered by start_date then id
        /** @var Advertisement[] $live */
        $live = self::where('status', 'active')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->orderBy('start_date')
            ->orderBy('id')
            ->take(self::MAX_LIVE_SLOTS)
            ->get();

        foreach ($live as $idx => $ad) {
            $ad->update(['slot_index' => $idx + 1]);
        }
    }
}