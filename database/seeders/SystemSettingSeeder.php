<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            [
                'key'         => 'commission_rate',
                'value'       => '10',
                'description' => 'Persentase komisi platform dari setiap transaksi (0-50%)',
            ],
            [
                'key'         => 'min_withdrawal',
                'value'       => '50000',
                'description' => 'Minimum saldo yang dapat ditarik oleh traveler (Rp)',
            ],
            [
                'key'         => 'auto_verify_traveler',
                'value'       => '0',
                'description' => 'Aktifkan notifikasi saat ada pengguna baru mendaftar',
            ],
            [
                'key'         => 'maintenance_mode',
                'value'       => '0',
                'description' => 'Nonaktifkan platform sementara untuk maintenance',
            ],
            [
                'key'         => 'app_name_first',
                'value'       => 'Nitip',
                'description' => 'Bagian pertama nama aplikasi (contoh: Nitip)',
            ],
            [
                'key'         => 'app_name_last',
                'value'       => 'Go',
                'description' => 'Bagian kedua nama aplikasi (contoh: Go)',
            ],
            [
                'key'         => 'financial_system_enabled',
                'value'       => '1',
                'description' => 'Aktifkan/matikan sistem finansial platform secara keseluruhan',
            ],
            [
                'key'         => 'max_pending_days',
                'value'       => '3',
                'description' => 'Batas hari transaksi pending sebelum otomatis dibatalkan (1-30 hari)',
            ],
        ];

        foreach ($defaults as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value'       => $setting['value'],
                    'description' => $setting['description'],
                    'updated_by'  => null,
                ]
            );
        }

        $this->command->info('SystemSetting seeded: ' . count($defaults) . ' settings.');
    }
}
