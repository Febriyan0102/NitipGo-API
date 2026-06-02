<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemSettingController extends Controller
{
    // PUBLIC SETTINGS (VIEW APPLICATION ALL USER)
    public function publicSettings()
    {
        $settings = SystemSetting::pluck('value', 'key');

        return response()->json([
                'appNameFirst'    => $settings['app_name_first'] ?? 'Nitip',
            'appNameLast'     => $settings['app_name_last'] ?? 'Go',
            'maintenanceMode' => filter_var($settings['maintenance_mode'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    // SHOW SETTINGS
    public function index()
    {
        $settings = SystemSetting::with('updater:id,name')
            ->get()
            ->keyBy('key');

        return response()->json([
            'data' => [
                'commissionRate'         => (int) ($settings['commission_rate']->value ?? 10),
                'minWithdrawal'          => (int) ($settings['min_withdrawal']->value ?? 50000),
                'autoVerifyTraveler'     => filter_var($settings['auto_verify_traveler']->value ?? false, FILTER_VALIDATE_BOOLEAN),
                'maintenanceMode'        => filter_var($settings['maintenance_mode']->value ?? false, FILTER_VALIDATE_BOOLEAN),
                'appNameFirst'           => $settings['app_name_first']->value ?? 'Nitip',
                'appNameLast'            => $settings['app_name_last']->value ?? 'Go',
                'financialSystemEnabled' => filter_var($settings['financial_system_enabled']->value ?? true, FILTER_VALIDATE_BOOLEAN),
                'maxPendingDays'         => (int) ($settings['max_pending_days']->value ?? 3),
            ],
            'meta' => [
                'lastUpdatedBy'  => $settings->whereNotNull('updated_by')->sortByDesc('updated_at')->first()?->updater?->name ?? null,
                'lastUpdatedAt'  => $settings->sortByDesc('updated_at')->first()?->updated_at?->toDateTimeString() ?? null,
            ]
        ]);
    }

    // UPDATE ALL SETTINGS
    public function update(Request $request)
    {
        $validated = $request->validate([
            'commissionRate'         => 'required|integer|min:0|max:50',
            'minWithdrawal'          => 'required|integer|min:10000',
            'autoVerifyTraveler'     => 'required|boolean',
            'maintenanceMode'        => 'required|boolean',
            'appNameFirst'           => 'required|string|max:50',
            'appNameLast'            => 'required|string|max:50',
            'financialSystemEnabled' => 'required|boolean',
            'maxPendingDays'         => 'required|integer|min:1|max:30',
        ]);

        $userId = $request->user()->id;

        DB::transaction(function () use ($validated, $userId) {
            $map = [
                'commission_rate'         => $validated['commissionRate'],
                'min_withdrawal'          => $validated['minWithdrawal'],
                'auto_verify_traveler'    => $validated['autoVerifyTraveler'],
                'maintenance_mode'        => $validated['maintenanceMode'],
                'app_name_first'          => $validated['appNameFirst'],
                'app_name_last'           => $validated['appNameLast'],
                'financial_system_enabled'=> $validated['financialSystemEnabled'],
                'max_pending_days'        => $validated['maxPendingDays'],
            ];

            foreach ($map as $key => $value) {
                $this->setSetting($key, $value, $userId);
            }
        });

        return response()->json([
            'message' => 'Pengaturan berhasil diperbarui.',
            'updatedBy' => $request->user()->name,
            'updatedAt' => now()->toDateTimeString(),
        ]);
    }

    // SINGLE UPDATE SETTINGS
    public function updateSingle(Request $request, string $key)
    {
        $allowed = [
            'commission_rate', 'min_withdrawal', 'auto_verify_traveler',
            'maintenance_mode', 'app_name_first', 'app_name_last',
            'financial_system_enabled', 'max_pending_days',
        ];

        if (!in_array($key, $allowed)) {
            return response()->json(['message' => 'Key tidak dikenali.'], 422);
        }

        $validated = $request->validate([
            'value' => 'required',
        ]);

        $this->setSetting($key, $validated['value'], $request->user()->id);

        return response()->json([
            'message' => "Setting '{$key}' berhasil diperbarui.",
            'key'     => $key,
            'value'   => $validated['value'],
        ]);
    }

    // HISTORIES SETTINGS ADMIN
    public function history()
    {
        $settings = SystemSetting::with('updater:id,name')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn($s) => [
                'key'         => $s->key,
                'value'       => $s->value,
                'description' => $s->description,
                'updatedBy'   => $s->updater?->name ?? 'System',
                'updatedAt'   => $s->updated_at?->toDateTimeString(),
            ]);

        return response()->json(['data' => $settings]);
    }

    // RESET SETTINGS
    public function reset(string $key)
    {
        $defaults = [
            'commission_rate'          => 10,
            'min_withdrawal'           => 50000,
            'auto_verify_traveler'     => '0',
            'maintenance_mode'         => '0',
            'app_name_first'           => 'Nitip',
            'app_name_last'            => 'Go',
            'financial_system_enabled' => '1',
            'max_pending_days'         => 3,
        ];

        if (!array_key_exists($key, $defaults)) {
            return response()->json(['message' => 'Key tidak dikenali.'], 422);
        }

        SystemSetting::where('key', $key)->update([
            'value'      => $defaults[$key],
            'updated_by' => null,
        ]);

        return response()->json([
            'message'      => "Setting '{$key}' berhasil direset ke nilai default.",
            'key'          => $key,
            'defaultValue' => $defaults[$key],
        ]);
    }

    // HELPER
    private function setSetting(string $key, mixed $value, ?int $userId = null): void
    {
        $normalized = match (true) {
            is_bool($value) => $value ? '1' : '0',
            default         => (string) $value,
        };

        SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value'      => $normalized,
                'updated_by' => $userId,
            ]
        );
    }
}