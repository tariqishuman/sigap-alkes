<?php

namespace Database\Seeders;

use App\Enums\CalibrationResult;
use App\Enums\EquipmentStatus;
use App\Enums\TestingInstitutionKind;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Services\CalibrationService;
use App\Services\DamageReportService;
use App\Services\EquipmentMovementService;
use App\Services\EquipmentStatusService;
use App\Services\RepairService;
use App\Services\SparePartStockService;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DummyDataSeeder extends Seeder
{
    private const RANDOM_SEED = 20260505;

    public function run(): void
    {
        $realNow = Carbon::now();

        // Bersihkan data operasional lama agar idempotent dan bebas duplikat.
        $this->call(ClearDummyDataSeeder::class);
        $this->call(RoleAndPermissionSeeder::class);

        mt_srand(self::RANDOM_SEED);
        $faker = FakerFactory::create('id_ID');
        $faker->seed(self::RANDOM_SEED);

        $teknisi = User::where('email', 'teknisi@sigap-alkes.test')->firstOrFail();
        $admin = User::where('email', 'admin@sigap-alkes.test')->firstOrFail();
        $kepalaIcu = User::where('email', 'kepalaruangan@sigap-alkes.test')->firstOrFail();

        $statusService = app(EquipmentStatusService::class);
        $calibrationService = app(CalibrationService::class);
        $damageReportService = app(DamageReportService::class);
        $repairService = app(RepairService::class);
        $movementService = app(EquipmentMovementService::class);
        $stockService = app(SparePartStockService::class);

        try {
            // 1. Ruangan (6 ruangan rumah sakit)
            $roomsData = [
                ['name' => 'Ruang ICU', 'code' => 'ICU', 'building' => 'Gedung Utama', 'floor' => 'Lantai 2'],
                ['name' => 'Ruang IGD', 'code' => 'IGD', 'building' => 'Gedung Utama', 'floor' => 'Lantai 1'],
                ['name' => 'Ruang Operasi (OK)', 'code' => 'OK', 'building' => 'Gedung Bedah Sentral', 'floor' => 'Lantai 2'],
                ['name' => 'Ruang NICU', 'code' => 'NICU', 'building' => 'Gedung Ibu & Anak', 'floor' => 'Lantai 2'],
                ['name' => 'Poliklinik Jantung', 'code' => 'POLI', 'building' => 'Gedung Rawat Jalan', 'floor' => 'Lantai 1'],
                ['name' => 'Instalasi Radiologi', 'code' => 'RAD', 'building' => 'Gedung Diagnostik', 'floor' => 'Lantai 1'],
            ];

            $rooms = [];
            foreach ($roomsData as $roomItem) {
                $rooms[$roomItem['code']] = Room::updateOrCreate(
                    ['name' => $roomItem['name']],
                    ['building' => $roomItem['building'], 'floor' => $roomItem['floor']],
                );
            }

            // 2. Jenis Alat (10 jenis alat kesehatan)
            $typesData = [
                [
                    'name' => 'Infusion Pump',
                    'requires_calibration' => true,
                    'requires_installation' => false,
                    'calibration_interval_months' => 12,
                    'useful_life_years' => 5,
                    'residual_value_percent' => 10,
                    'brands' => [['Terumo', 'Terufusion TE-LM800'], ['B. Braun', 'Infusomat Space'], ['Mindray', 'BeneFusion VP5']],
                    'cost_range' => [22000000, 32000000],
                ],
                [
                    'name' => 'Syringe Pump',
                    'requires_calibration' => true,
                    'requires_installation' => false,
                    'calibration_interval_months' => 12,
                    'useful_life_years' => 5,
                    'residual_value_percent' => 10,
                    'brands' => [['Terumo', 'Terufusion TE-SS800'], ['B. Braun', 'Perfusor Space'], ['Mindray', 'BeneFusion SP5']],
                    'cost_range' => [20000000, 30000000],
                ],
                [
                    'name' => 'Patient Monitor',
                    'requires_calibration' => true,
                    'requires_installation' => true,
                    'calibration_interval_months' => 12,
                    'useful_life_years' => 7,
                    'residual_value_percent' => 10,
                    'brands' => [['Philips', 'IntelliVue MX450'], ['Mindray', 'BeneVision N12'], ['GE Healthcare', 'CARESCAPE B450']],
                    'cost_range' => [68000000, 145000000],
                ],
                [
                    'name' => 'Ventilator',
                    'requires_calibration' => true,
                    'requires_installation' => true,
                    'calibration_interval_months' => 12,
                    'useful_life_years' => 8,
                    'residual_value_percent' => 10,
                    'brands' => [['Mindray', 'SV300'], ['Dräger', 'Savina 300 Select'], ['Hamilton', 'C3']],
                    'cost_range' => [320000000, 460000000],
                ],
                [
                    'name' => 'Defibrillator',
                    'requires_calibration' => true,
                    'requires_installation' => false,
                    'calibration_interval_months' => 12,
                    'useful_life_years' => 7,
                    'residual_value_percent' => 10,
                    'brands' => [['Philips', 'HeartStart XL+'], ['Nihon Kohden', 'Cardiolife TEC-5631'], ['Zoll', 'R Series Plus']],
                    'cost_range' => [115000000, 165000000],
                ],
                [
                    'name' => 'ECG / EKG 12 Channel',
                    'requires_calibration' => true,
                    'requires_installation' => false,
                    'calibration_interval_months' => 12,
                    'useful_life_years' => 6,
                    'residual_value_percent' => 10,
                    'brands' => [['Fukuda Denshi', 'CardiMax FX-8322'], ['Nihon Kohden', 'Cardiofax M ECG-2350'], ['GE Healthcare', 'MAC 2000']],
                    'cost_range' => [48000000, 66000000],
                ],
                [
                    'name' => 'Suction Pump',
                    'requires_calibration' => true,
                    'requires_installation' => false,
                    'calibration_interval_months' => 12,
                    'useful_life_years' => 5,
                    'residual_value_percent' => 5,
                    'brands' => [['Medela', 'Dominant Flex'], ['Yuwell', '7A-23D'], ['Medela', 'Basic 30']],
                    'cost_range' => [14000000, 42000000],
                ],
                [
                    'name' => 'Baby Incubator',
                    'requires_calibration' => true,
                    'requires_installation' => true,
                    'calibration_interval_months' => 12,
                    'useful_life_years' => 8,
                    'residual_value_percent' => 10,
                    'brands' => [['Atom Medical', 'Incu i V-2100G'], ['GE Healthcare', 'Giraffe OmniBed']],
                    'cost_range' => [210000000, 345000000],
                ],
                [
                    'name' => 'Mesin Anestesi',
                    'requires_calibration' => true,
                    'requires_installation' => true,
                    'calibration_interval_months' => 12,
                    'useful_life_years' => 10,
                    'residual_value_percent' => 10,
                    'brands' => [['GE Healthcare', 'Carestation 620'], ['Dräger', 'Fabius Plus XL']],
                    'cost_range' => [580000000, 690000000],
                ],
                [
                    'name' => 'Tensimeter Digital Non-Kalibrasi',
                    'requires_calibration' => false,
                    'requires_installation' => false,
                    'calibration_interval_months' => 12,
                    'useful_life_years' => 4,
                    'residual_value_percent' => 0,
                    'brands' => [['Omron', 'HBP-1320 Professional'], ['Omron', 'HBP-1120']],
                    'cost_range' => [3800000, 5200000],
                ],
            ];

            $types = [];
            $typeMeta = [];
            foreach ($typesData as $typeItem) {
                $meta = $typeItem;
                unset($typeItem['brands'], $typeItem['cost_range']);

                $types[$typeItem['name']] = EquipmentType::updateOrCreate(
                    ['name' => $typeItem['name']],
                    $typeItem,
                );
                $typeMeta[$typeItem['name']] = $meta;
            }

            // 3. Lembaga Penguji (3 lembaga)
            $institutions = [
                'bpafk' => TestingInstitution::updateOrCreate(
                    ['name' => 'BPAFK Surabaya'],
                    ['kind' => TestingInstitutionKind::Bpafk, 'accreditation_number' => 'LK-045-IDN'],
                ),
                'swasta' => TestingInstitution::updateOrCreate(
                    ['name' => 'PT Kalibrasi Medika Nusantara'],
                    ['kind' => TestingInstitutionKind::Swasta, 'accreditation_number' => 'LK-198-IDN'],
                ),
                'internal' => TestingInstitution::updateOrCreate(
                    ['name' => 'Unit Elektromedis Internal RS'],
                    ['kind' => TestingInstitutionKind::InternalRs, 'accreditation_number' => null],
                ),
            ];

            // 4. Suku Cadang (8 item) + stok awal 14 bulan lalu lewat SparePartStockService
            $sparePartSpecs = [
                ['name' => 'Baterai Lithium-Ion 12V Medis', 'unit' => 'pcs', 'price' => '850000.00', 'min_stock' => 6, 'initial' => 30],
                ['name' => 'Sensor SpO2 Dewasa Reusable', 'unit' => 'pcs', 'price' => '475000.00', 'min_stock' => 8, 'initial' => 24],
                ['name' => 'Kabel Trunk ECG 10 Lead', 'unit' => 'set', 'price' => '620000.00', 'min_stock' => 5, 'initial' => 25],
                ['name' => 'Selang Silikon Suction Medis', 'unit' => 'meter', 'price' => '95000.00', 'min_stock' => 10, 'initial' => 32],
                ['name' => 'Filter Bakteri / HEPA Ventilator', 'unit' => 'pcs', 'price' => '340000.00', 'min_stock' => 8, 'initial' => 28],
                ['name' => 'Manset NIBP Dewasa', 'unit' => 'pcs', 'price' => '210000.00', 'min_stock' => 6, 'initial' => 26],
                ['name' => 'Elemen Pemanas Baby Incubator', 'unit' => 'pcs', 'price' => '1450000.00', 'min_stock' => 6, 'initial' => 22],
                ['name' => 'Fuse Pengaman 2A Medis', 'unit' => 'pcs', 'price' => '45000.00', 'min_stock' => 10, 'initial' => 35],
            ];

            $stockInitTime = $realNow->copy()->subMonthsNoOverflow(14)->startOfMonth()->setTime(8, 0);
            Carbon::setTestNow($stockInitTime);

            $spareParts = [];
            foreach ($sparePartSpecs as $idx => $spItem) {
                $sp = SparePart::updateOrCreate(
                    ['name' => $spItem['name']],
                    ['unit' => $spItem['unit'], 'min_stock' => $spItem['min_stock']],
                );
                $stockService->stockIn(
                    $sp,
                    $spItem['initial'],
                    sprintf('Penerimaan stok awal gudang IPSRS (DO-2025-%03d)', $idx + 1),
                    $admin->id,
                    $stockInitTime,
                );
                $spareParts[] = ['model' => $sp->fresh(), 'price' => $spItem['price']];
            }

            // 5. Buat 60 unit alat (10 per ruangan) pada 14 bulan lalu agar seluruh 12 bulan terakhir terisi penuh.
            $roomTypeTemplates = [
                'ICU' => [
                    'Ventilator', 'Ventilator', 'Patient Monitor', 'Patient Monitor', 'Infusion Pump',
                    'Infusion Pump', 'Syringe Pump', 'Defibrillator', 'Suction Pump', 'Tensimeter Digital Non-Kalibrasi',
                ],
                'IGD' => [
                    'Defibrillator', 'Defibrillator', 'ECG / EKG 12 Channel', 'Patient Monitor', 'Patient Monitor',
                    'Infusion Pump', 'Syringe Pump', 'Suction Pump', 'Suction Pump', 'Tensimeter Digital Non-Kalibrasi',
                ],
                'OK' => [
                    'Mesin Anestesi', 'Mesin Anestesi', 'Patient Monitor', 'Patient Monitor', 'Defibrillator',
                    'Syringe Pump', 'Syringe Pump', 'Infusion Pump', 'Suction Pump', 'Ventilator',
                ],
                'NICU' => [
                    'Baby Incubator', 'Baby Incubator', 'Baby Incubator', 'Ventilator', 'Patient Monitor',
                    'Syringe Pump', 'Syringe Pump', 'Infusion Pump', 'Suction Pump', 'Tensimeter Digital Non-Kalibrasi',
                ],
                'POLI' => [
                    'ECG / EKG 12 Channel', 'ECG / EKG 12 Channel', 'ECG / EKG 12 Channel', 'Defibrillator', 'Patient Monitor',
                    'Infusion Pump', 'Syringe Pump', 'Suction Pump', 'Tensimeter Digital Non-Kalibrasi', 'Tensimeter Digital Non-Kalibrasi',
                ],
                'RAD' => [
                    'Patient Monitor', 'Patient Monitor', 'Infusion Pump', 'Infusion Pump', 'Syringe Pump',
                    'Suction Pump', 'Suction Pump', 'Defibrillator', 'ECG / EKG 12 Channel', 'Tensimeter Digital Non-Kalibrasi',
                ],
            ];

            // Skenario kondisi akhir pada indeks 0..59 (total 60 alat)
            // Indeks 0..59 tersebar di 6 ruangan (0..9 ICU, 10..19 IGD, 20..29 OK, 30..39 NICU, 40..49 POLI, 50..59 RAD)
            $replaceCandidateIndices = [4, 15, 27]; // 3 alat dengan biaya perbaikan >= 50% nilai buku -> "Pertimbangkan ganti"
            $overdueIndices = [6, 17, 28, 37, 45, 55]; // 6 alat Terlambat
            $dueSoonIndices = [3, 12, 21, 31, 41, 51, 5, 16]; // 8 alat Perlu kalibrasi (<= 30 hari)
            $underTestingIndices = [24, 35, 52]; // 3 alat Dalam uji & kalibrasi
            $underRepairIndices = [8, 18, 38, 56]; // 4 alat Dalam perbaikan
            $unfitIndices = [7, 25, 43]; // 3 alat Tidak laik pakai (gagal uji terbaru)
            $neverCalibratedIndex = 54; // 1 alat wajib kalibrasi tanpa sertifikat (Perlu kalibrasi)

            $certCounter = 1;
            $createdBase = $realNow->copy()->subMonthsNoOverflow(14)->startOfMonth()->setTime(9, 0);

            // Rencana deterministik keterlambatan kalibrasi historis pada akhir bulan 11..2 (Des..Sep),
            // sehingga tiap bulan ada 1–5 alat yang sertifikatnya lewat 6–38 hari sebelum dikalibrasi ulang.
            $historicalOverdueByIdx = [
                0 => ['offset' => 11, 'days_late' => 12, 'recal_after_end' => 8],
                1 => ['offset' => 11, 'days_late' => 24, 'recal_after_end' => 14],
                2 => ['offset' => 10, 'days_late' => 9, 'recal_after_end' => 10],
                10 => ['offset' => 10, 'days_late' => 19, 'recal_after_end' => 7],
                11 => ['offset' => 10, 'days_late' => 31, 'recal_after_end' => 12],
                13 => ['offset' => 9, 'days_late' => 15, 'recal_after_end' => 9],
                14 => ['offset' => 8, 'days_late' => 8, 'recal_after_end' => 11],
                20 => ['offset' => 8, 'days_late' => 18, 'recal_after_end' => 6],
                22 => ['offset' => 8, 'days_late' => 27, 'recal_after_end' => 15],
                23 => ['offset' => 8, 'days_late' => 11, 'recal_after_end' => 8],
                26 => ['offset' => 7, 'days_late' => 14, 'recal_after_end' => 10],
                29 => ['offset' => 7, 'days_late' => 22, 'recal_after_end' => 7],
                30 => ['offset' => 6, 'days_late' => 7, 'recal_after_end' => 9],
                32 => ['offset' => 6, 'days_late' => 16, 'recal_after_end' => 12],
                33 => ['offset' => 6, 'days_late' => 29, 'recal_after_end' => 6],
                34 => ['offset' => 6, 'days_late' => 10, 'recal_after_end' => 14],
                36 => ['offset' => 6, 'days_late' => 21, 'recal_after_end' => 8],
                40 => ['offset' => 5, 'days_late' => 13, 'recal_after_end' => 11],
                42 => ['offset' => 5, 'days_late' => 25, 'recal_after_end' => 7],
                44 => ['offset' => 5, 'days_late' => 9, 'recal_after_end' => 13],
                46 => ['offset' => 4, 'days_late' => 17, 'recal_after_end' => 10],
                47 => ['offset' => 3, 'days_late' => 11, 'recal_after_end' => 8],
                50 => ['offset' => 3, 'days_late' => 23, 'recal_after_end' => 12],
                53 => ['offset' => 3, 'days_late' => 16, 'recal_after_end' => 7],
                57 => ['offset' => 3, 'days_late' => 28, 'recal_after_end' => 9],
                58 => ['offset' => 2, 'days_late' => 12, 'recal_after_end' => 11],
                4 => ['offset' => 2, 'days_late' => 20, 'recal_after_end' => 8],
            ];

            /** @var array<int, Equipment> $allEquipment */
            $allEquipment = [];
            /** @var array<string, int> $typeCounters */
            $typeCounters = [];
            $globalIndex = 0;

            foreach ($roomTypeTemplates as $roomCode => $typeList) {
                foreach ($typeList as $slot => $typeName) {
                    $type = $types[$typeName];
                    $meta = $typeMeta[$typeName];
                    $room = $rooms[$roomCode];
                    $brandPair = $meta['brands'][$globalIndex % count($meta['brands'])];
                    $seq = str_pad((string) ($slot + 1), 3, '0', STR_PAD_LEFT);
                    $code = "ALK-{$roomCode}-{$seq}";
                    $typeCounters[$typeName] = ($typeCounters[$typeName] ?? 0) + 1;

                    // Alat kandidat "Pertimbangkan ganti" dibuat berumur ~4,5 tahun agar nilai bukunya rendah & realistis
                    if (in_array($globalIndex, $replaceCandidateIndices, true)) {
                        $acqDate = $realNow->copy()->subYears(4)->subMonths(6);
                        $cost = '24000000.00';
                        $residual = '2400000.00';
                    } else {
                        $ageMonths = 18 + (($globalIndex * 7) % 48);
                        $acqDate = $realNow->copy()->subMonthsNoOverflow($ageMonths);
                        $minCost = $meta['cost_range'][0];
                        $maxCost = $meta['cost_range'][1];
                        $steps = max(1, (int) (($maxCost - $minCost) / 1000000));
                        $costVal = $minCost + (($globalIndex * 3) % ($steps + 1)) * 1000000;
                        $cost = number_format($costVal, 2, '.', '');
                        $residual = null;
                    }

                    $entryTime = $createdBase->copy()->addHours($globalIndex * 2);
                    Carbon::setTestNow($entryTime);

                    $equipment = Equipment::create([
                        'inventory_code' => $code,
                        'name' => "{$typeName} {$brandPair[0]} #{$typeCounters[$typeName]}",
                        'equipment_type_id' => $type->id,
                        'room_id' => $room->id,
                        'brand' => $brandPair[0],
                        'model' => $brandPair[1],
                        'serial_number' => sprintf('%s-%04d', strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $brandPair[0]), 0, 3)), 2100 + $globalIndex),
                        'acquisition_date' => $acqDate->toDateString(),
                        'acquisition_cost' => $cost,
                        'residual_value' => $residual,
                        'is_active' => true,
                        'notes' => 'Data sintetis operasional SIGAP-Alkes (tanpa data pasien).',
                    ]);

                    // Kalibrasi awal: jika masuk rencana keterlambatan historis, atur valid_until jatuh sebelum akhir bulan target
                    if ($type->requires_calibration && $globalIndex !== $neverCalibratedIndex) {
                        $inst = $globalIndex % 2 === 0 ? $institutions['bpafk'] : $institutions['swasta'];

                        if (isset($historicalOverdueByIdx[$globalIndex])) {
                            $hPlan = $historicalOverdueByIdx[$globalIndex];
                            $targetMonthEnd = $realNow->copy()->startOfMonth()->subMonthsNoOverflow($hPlan['offset'])->endOfMonth();
                            $validUntil = $targetMonthEnd->copy()->subDays($hPlan['days_late']);
                            $initCalTime = $validUntil->copy()->subMonthsNoOverflow(12);
                        } else {
                            $initCalTime = $entryTime->copy()->addDays(2);
                            $validUntil = $initCalTime->copy()->addMonthsNoOverflow(12);
                        }

                        Carbon::setTestNow($initCalTime);

                        $calibrationService->record([
                            'equipment_id' => $equipment->id,
                            'testing_institution_id' => $inst->id,
                            'tested_at' => $initCalTime->toDateString(),
                            'result' => CalibrationResult::Pass->value,
                            'certificate_number' => sprintf('CERT-INIT-%04d', $certCounter++),
                            'valid_until' => $validUntil->toDateString(),
                        ], $teknisi->id);
                    }

                    $allEquipment[$globalIndex] = $equipment->fresh();
                    $globalIndex++;
                }
            }

            // 6. Simulasi kejadian kronologis 12 bulan terakhir (kerusakan, perbaikan, gagal uji, mutasi, kalibrasi ulang)
            // Target uptime per bulan bervariasi di kisaran ±91% - 97.5%.
            $complaints = [
                'Layar monitor berkedip dan alarm palsu berbunyi berulang.',
                'Baterai cadangan tidak mampu bertahan saat sumber listrik diputus.',
                'Sensor pembacaan tidak stabil dan nilai menyimpang dari standar.',
                'Pompa/motor mengeluarkan suara kasar saat beroperasi penuh.',
                'Konektor kabel pasien longgar sehingga sinyal terputus-putus.',
                'Tombol panel kontrol tidak responsif saat pengaturan dosis.',
                'Tekanan vakum / aliran udara tidak mencapai nilai target.',
            ];

            $actions = [
                'Penggantian baterai cadangan dan pengujian fungsi kelistrikan.',
                'Kalibrasi internal sensor dan pembersihan modul utama.',
                'Penggantian konektor dan kabel trunk serta uji kontinuitas.',
                'Pembongkaran motor pompa, pelumasan, dan penggantian selang internal.',
                'Penggantian modul papan sirkuit power supply dan uji beban.',
                'Pembersihan filter HEPA, penggantian fuse, dan pengujian kebocoran.',
            ];

            // Pola jumlah unit mengalami downtime per bulan (offset 11 = 11 bulan lalu .. offset 0 = bulan berjalan)
            $monthlyDowntimePlan = [
                11 => ['count' => 7, 'days' => 9],
                10 => ['count' => 10, 'days' => 11],
                9 => ['count' => 6, 'days' => 8],
                8 => ['count' => 9, 'days' => 12],
                7 => ['count' => 11, 'days' => 11],
                6 => ['count' => 5, 'days' => 9],
                5 => ['count' => 8, 'days' => 10],
                4 => ['count' => 12, 'days' => 11],
                3 => ['count' => 7, 'days' => 9],
                2 => ['count' => 10, 'days' => 10],
                1 => ['count' => 9, 'days' => 11],
                0 => ['count' => 6, 'days' => 6],
            ];

            // Kumpulan pool alat yang aman untuk siklus perbaikan historis (hindari alat overdue/dueSoon/neverCalibrated agar siklus sertifikat akhirnya tetap kronologis)
            $eventPool = array_values(array_diff(
                range(0, 59),
                array_merge($overdueIndices, $dueSoonIndices, [$neverCalibratedIndex]),
            ));

            for ($offset = 11; $offset >= 0; $offset--) {
                $monthStart = $realNow->copy()->startOfMonth()->subMonthsNoOverflow($offset);
                $monthEnd = $offset === 0
                    ? $realNow->copy()->subDays(2)
                    : $monthStart->copy()->endOfMonth()->subDays(3);

                if ($monthEnd->lessThanOrEqualTo($monthStart->copy()->addDay())) {
                    continue;
                }

                $plan = $monthlyDowntimePlan[$offset];
                $availableSpanDays = max(2, (int) $monthStart->diffInDays($monthEnd));

                for ($k = 0; $k < $plan['count']; $k++) {
                    $poolPos = (($offset * 13) + ($k * 5)) % count($eventPool);
                    $eqIdx = $eventPool[$poolPos];
                    $equipment = $allEquipment[$eqIdx]->fresh();

                    if (! in_array($equipment->status, [
                        EquipmentStatus::FitForUse,
                        EquipmentStatus::CalibrationDue,
                        EquipmentStatus::Overdue,
                        EquipmentStatus::Unfit,
                    ], true)) {
                        continue;
                    }

                    $durationDays = min($plan['days'], max(2, $availableSpanDays - 1));
                    $maxStartOffset = max(0, $availableSpanDays - $durationDays - 1);
                    $startDayOffset = ($k * 3 + $offset) % ($maxStartOffset + 1);

                    $reportAt = $monthStart->copy()->addDays($startDayOffset)->setTime(8, 30);
                    $repairStart = $reportAt->copy()->addHours(2);
                    $repairEnd = $repairStart->copy()->addDays($durationDays);

                    if ($repairEnd->greaterThanOrEqualTo($realNow->copy()->subHours(6))) {
                        $repairEnd = $realNow->copy()->subHours(12);
                        $repairStart = $repairEnd->copy()->subDays(max(1, min($durationDays, 4)));
                        $reportAt = $repairStart->copy()->subHours(2);
                    }

                    // 1) Buat laporan kerusakan
                    Carbon::setTestNow($reportAt);
                    $reporter = $equipment->room_id === $rooms['ICU']->id ? $kepalaIcu : $admin;
                    $report = $damageReportService->report([
                        'equipment_id' => $equipment->id,
                        'reported_at' => $reportAt->toDateTimeString(),
                        'complaint' => $complaints[($eqIdx + $offset) % count($complaints)],
                    ], $reporter);

                    // 2) Mulai perbaikan -> UnderRepair via EquipmentStatusService
                    Carbon::setTestNow($repairStart);
                    $spChoice = $spareParts[($eqIdx + $k) % count($spareParts)];
                    $repair = $repairService->start([
                        'damage_report_id' => $report->id,
                        'technician_id' => $teknisi->id,
                        'started_at' => $repairStart->toDateTimeString(),
                        'action' => 'Pemeriksaan awal dan pembongkaran komponen.',
                        'labor_cost' => number_format(200000 + (($k % 4) * 75000), 2, '.', ''),
                        'spare_parts' => [
                            [
                                'spare_part_id' => $spChoice['model']->id,
                                'quantity' => 1 + ($k % 2),
                                'unit_price' => $spChoice['price'],
                            ],
                        ],
                    ], $teknisi->id);

                    // 3) Selesai perbaikan -> CalibrationDue / Overdue / FitForUse
                    Carbon::setTestNow($repairEnd);
                    $repairService->finish($repair, [
                        'finished_at' => $repairEnd->toDateTimeString(),
                        'action' => $actions[($eqIdx + $offset) % count($actions)],
                    ], $teknisi->id);

                    // 4) Kalibrasi ulang pasca perbaikan (jika wajib kalibrasi) agar kembali Laik pakai
                    // Jangan menimpa jendela keterlambatan historis sebelum bulan targetnya berakhir.
                    $equipment->refresh();
                    $canRecalNow = true;
                    if (isset($historicalOverdueByIdx[$eqIdx])) {
                        $hPlan = $historicalOverdueByIdx[$eqIdx];
                        $targetEnd = $realNow->copy()->startOfMonth()->subMonthsNoOverflow($hPlan['offset'])->endOfMonth();
                        if ($repairEnd->lessThanOrEqualTo($targetEnd)) {
                            $canRecalNow = false;
                        }
                    }

                    if ($canRecalNow && $equipment->equipmentType->requires_calibration && $offset > 0) {
                        $recalAt = $repairEnd->copy()->addHours(6);
                        if ($recalAt->lessThan($realNow->copy()->subDay())) {
                            Carbon::setTestNow($recalAt);
                            $inst = $eqIdx % 2 === 0 ? $institutions['bpafk'] : $institutions['swasta'];
                            $calibrationService->record([
                                'equipment_id' => $equipment->id,
                                'testing_institution_id' => $inst->id,
                                'tested_at' => $recalAt->toDateString(),
                                'result' => CalibrationResult::Pass->value,
                                'certificate_number' => sprintf('CERT-RECAL-%04d', $certCounter++),
                                'valid_until' => $recalAt->copy()->addMonthsNoOverflow(12)->toDateString(),
                            ], $teknisi->id);
                        }
                    }

                    $allEquipment[$eqIdx] = $equipment->fresh();
                }
            }

            // 7. Pastikan minimal 3 alat memiliki total biaya perbaikan >= 50% nilai buku ("Pertimbangkan ganti")
            foreach ($replaceCandidateIndices as $idxPos => $eqIdx) {
                $equipment = $allEquipment[$eqIdx]->fresh();
                if ($equipment->status === EquipmentStatus::UnderRepair || $equipment->status === EquipmentStatus::UnderTesting) {
                    continue;
                }

                $reportAt = $realNow->copy()->subDays(25 - ($idxPos * 3))->setTime(9, 0);
                $repairStart = $reportAt->copy()->addHours(2);
                $repairEnd = $repairStart->copy()->addDays(2);

                Carbon::setTestNow($reportAt);
                $reporter = $equipment->room_id === $rooms['ICU']->id ? $kepalaIcu : $admin;
                $report = $damageReportService->report([
                    'equipment_id' => $equipment->id,
                    'reported_at' => $reportAt->toDateTimeString(),
                    'complaint' => 'Kerusakan berat pada modul kontrol utama dan power supply (perbaikan mayor).',
                ], $reporter);

                Carbon::setTestNow($repairStart);
                $repair = $repairService->start([
                    'damage_report_id' => $report->id,
                    'technician_id' => $teknisi->id,
                    'started_at' => $repairStart->toDateTimeString(),
                    'action' => 'Penggantian papan kontrol utama, power supply, dan sensor lengkap.',
                    'labor_cost' => '2500000.00',
                    'spare_parts' => [
                        ['spare_part_id' => $spareParts[0]['model']->id, 'quantity' => 2, 'unit_price' => '1500000.00'],
                        ['spare_part_id' => $spareParts[6]['model']->id, 'quantity' => 2, 'unit_price' => '1450000.00'],
                    ],
                ], $teknisi->id);

                Carbon::setTestNow($repairEnd);
                $repairService->finish($repair, [
                    'finished_at' => $repairEnd->toDateTimeString(),
                    'action' => 'Penggantian komponen mayor selesai, unit direkomendasikan evaluasi peremajaan.',
                ], $teknisi->id);

                if ($equipment->equipmentType->requires_calibration) {
                    $recalAt = $repairEnd->copy()->addHours(4);
                    Carbon::setTestNow($recalAt);
                    $calibrationService->record([
                        'equipment_id' => $equipment->id,
                        'testing_institution_id' => $institutions['bpafk']->id,
                        'tested_at' => $recalAt->toDateString(),
                        'result' => CalibrationResult::Pass->value,
                        'certificate_number' => sprintf('CERT-MAJ-%04d', $certCounter++),
                        'valid_until' => $recalAt->copy()->addMonthsNoOverflow(12)->toDateString(),
                    ], $teknisi->id);
                }

                $allEquipment[$eqIdx] = $equipment->fresh();
            }

            // 8. Simulasi mutasi alat (8 kejadian lintas ruangan, campuran biasa & reinstalasi)
            $movementSpecs = [
                ['idx' => 1, 'to' => 'OK', 'days_ago' => 210, 'reinstall' => true, 'note' => 'Pemindahan ventilator untuk dukungan ruang bedah sentral.'],
                ['idx' => 11, 'to' => 'ICU', 'days_ago' => 180, 'reinstall' => false, 'note' => 'Relokasi defibrillator cadangan ke ICU.'],
                ['idx' => 22, 'to' => 'NICU', 'days_ago' => 150, 'reinstall' => true, 'note' => 'Reinstalasi patient monitor di ruang NICU.'],
                ['idx' => 36, 'to' => 'IGD', 'days_ago' => 120, 'reinstall' => false, 'note' => 'Penambahan syringe pump untuk triase IGD.'],
                ['idx' => 42, 'to' => 'RAD', 'days_ago' => 95, 'reinstall' => false, 'note' => 'Pemindahan unit EKG ke instalasi radiologi.'],
                ['idx' => 53, 'to' => 'POLI', 'days_ago' => 70, 'reinstall' => false, 'note' => 'Relokasi infusion pump ke poliklinik jantung.'],
                ['idx' => 13, 'to' => 'OK', 'days_ago' => 45, 'reinstall' => true, 'note' => 'Reinstalasi patient monitor ruang operasi.'],
                ['idx' => 26, 'to' => 'ICU', 'days_ago' => 28, 'reinstall' => false, 'note' => 'Penempatan syringe pump tambahan di ICU.'],
            ];

            foreach ($movementSpecs as $mSpec) {
                $equipment = $allEquipment[$mSpec['idx']]->fresh();
                $targetRoom = $rooms[$mSpec['to']];
                if ($equipment->room_id === $targetRoom->id) {
                    continue;
                }

                $movedAt = $realNow->copy()->subDays($mSpec['days_ago'])->setTime(10, 0);
                Carbon::setTestNow($movedAt);

                $movementService->move([
                    'equipment_id' => $equipment->id,
                    'to_room_id' => $targetRoom->id,
                    'moved_at' => $movedAt->toDateTimeString(),
                    'is_reinstallation' => $mSpec['reinstall'],
                    'notes' => $mSpec['note'],
                ], $teknisi->id);

                // Jika mutasi memicu Perlu kalibrasi, lakukan kalibrasi ulang 2 hari kemudian agar siap pakai
                // (lewati bila jatuh sebelum akhir bulan keterlambatan historis yang direncanakan)
                $equipment->refresh();
                $canRecalAfterMove = true;
                if (isset($historicalOverdueByIdx[$mSpec['idx']])) {
                    $hPlan = $historicalOverdueByIdx[$mSpec['idx']];
                    $targetEnd = $realNow->copy()->startOfMonth()->subMonthsNoOverflow($hPlan['offset'])->endOfMonth();
                    if ($movedAt->copy()->addDays(2)->lessThanOrEqualTo($targetEnd)) {
                        $canRecalAfterMove = false;
                    }
                }

                if ($canRecalAfterMove && $equipment->status === EquipmentStatus::CalibrationDue && $equipment->equipmentType->requires_calibration) {
                    $recalAt = $movedAt->copy()->addDays(2);
                    if ($recalAt->lessThan($realNow->copy()->subDay())) {
                        Carbon::setTestNow($recalAt);
                        $calibrationService->record([
                            'equipment_id' => $equipment->id,
                            'testing_institution_id' => $institutions['bpafk']->id,
                            'tested_at' => $recalAt->toDateString(),
                            'result' => CalibrationResult::Pass->value,
                            'certificate_number' => sprintf('CERT-MOV-%04d', $certCounter++),
                            'valid_until' => $recalAt->copy()->addMonthsNoOverflow(12)->toDateString(),
                        ], $teknisi->id);
                    }
                }

                $allEquipment[$mSpec['idx']] = $equipment->fresh();
            }

            // 9. Bentuk kondisi akhir yang bervariasi dan realistis pada hari ini
            // 9a. Alat Perlu kalibrasi (jatuh tempo 5..25 hari dari sekarang)
            foreach ($dueSoonIndices as $pos => $eqIdx) {
                $equipment = $allEquipment[$eqIdx]->fresh();
                if (! $equipment->equipmentType->requires_calibration) {
                    continue;
                }

                $daysLeft = 5 + ($pos * 3);
                $validUntil = $realNow->copy()->addDays($daysLeft);
                $testedAt = $validUntil->copy()->subMonthsNoOverflow(12);

                Carbon::setTestNow($testedAt);
                $calibrationService->record([
                    'equipment_id' => $equipment->id,
                    'testing_institution_id' => $institutions['swasta']->id,
                    'tested_at' => $testedAt->toDateString(),
                    'result' => CalibrationResult::Pass->value,
                    'certificate_number' => sprintf('CERT-DUE-%04d', $certCounter++),
                    'valid_until' => $validUntil->toDateString(),
                ], $teknisi->id);

                $allEquipment[$eqIdx] = $equipment->fresh();
            }

            // 9b. Alat Terlambat (sertifikat sudah kedaluwarsa 10..40 hari lalu)
            foreach ($overdueIndices as $pos => $eqIdx) {
                $equipment = $allEquipment[$eqIdx]->fresh();
                if (! $equipment->equipmentType->requires_calibration) {
                    continue;
                }

                $daysExpired = 10 + ($pos * 6);
                $validUntil = $realNow->copy()->subDays($daysExpired);
                $testedAt = $validUntil->copy()->subMonthsNoOverflow(12);

                Carbon::setTestNow($testedAt);
                $calibrationService->record([
                    'equipment_id' => $equipment->id,
                    'testing_institution_id' => $institutions['bpafk']->id,
                    'tested_at' => $testedAt->toDateString(),
                    'result' => CalibrationResult::Pass->value,
                    'certificate_number' => sprintf('CERT-OVR-%04d', $certCounter++),
                    'valid_until' => $validUntil->toDateString(),
                ], $teknisi->id);

                $allEquipment[$eqIdx] = $equipment->fresh();
            }

            // 9c. Alat Laik pakai sehat (sertifikat berlaku 4..10 bulan ke depan)
            $specialIndices = array_merge(
                $overdueIndices,
                $dueSoonIndices,
                $underTestingIndices,
                $underRepairIndices,
                $unfitIndices,
                [$neverCalibratedIndex],
            );

            foreach ($allEquipment as $eqIdx => $equipment) {
                if (in_array($eqIdx, $specialIndices, true) || ! $equipment->equipmentType->requires_calibration) {
                    continue;
                }

                if (isset($historicalOverdueByIdx[$eqIdx])) {
                    $hPlan = $historicalOverdueByIdx[$eqIdx];
                    $targetMonthEnd = $realNow->copy()->startOfMonth()->subMonthsNoOverflow($hPlan['offset'])->endOfMonth();
                    $testedAt = $targetMonthEnd->copy()->addDays($hPlan['recal_after_end']);
                    $validUntil = $testedAt->copy()->addMonthsNoOverflow(12);
                } else {
                    $monthsRemaining = 4 + ($eqIdx % 7);
                    $validUntil = $realNow->copy()->addMonthsNoOverflow($monthsRemaining);
                    $testedAt = $validUntil->copy()->subMonthsNoOverflow(12);
                }

                if ($testedAt->lessThan($realNow->copy()->subDays(2))) {
                    Carbon::setTestNow($testedAt);
                    $inst = $eqIdx % 2 === 0 ? $institutions['bpafk'] : $institutions['swasta'];
                    $calibrationService->record([
                        'equipment_id' => $equipment->id,
                        'testing_institution_id' => $inst->id,
                        'tested_at' => $testedAt->toDateString(),
                        'result' => CalibrationResult::Pass->value,
                        'certificate_number' => sprintf('CERT-FIT-%04d', $certCounter++),
                        'valid_until' => $validUntil->toDateString(),
                    ], $teknisi->id);
                }
            }

            // 9d. Evaluasi seluruh status pada waktu sekarang (memindahkan dueSoon -> CalibrationDue, overdue -> Overdue)
            Carbon::setTestNow($realNow->copy()->subDays(4));
            foreach ($allEquipment as $equipment) {
                $statusService->evaluate($equipment->fresh());
            }

            // 9e. Alat Dalam uji & kalibrasi (dimulai 1-2 hari lalu)
            foreach ($underTestingIndices as $pos => $eqIdx) {
                $testStart = $realNow->copy()->subDays(1 + ($pos % 2))->setTime(9, 0);
                Carbon::setTestNow($testStart);
                $statusService->startTesting($allEquipment[$eqIdx]->fresh(), $teknisi->id);
            }

            // 9f. Alat Tidak laik pakai (gagal uji kalibrasi 2-3 hari lalu + laporan kerusakan terbuka)
            foreach ($unfitIndices as $pos => $eqIdx) {
                $failAt = $realNow->copy()->subDays(3 - ($pos % 2))->setTime(14, 0);
                Carbon::setTestNow($failAt);
                $equipment = $allEquipment[$eqIdx]->fresh();
                $calibrationService->record([
                    'equipment_id' => $equipment->id,
                    'testing_institution_id' => $institutions['bpafk']->id,
                    'tested_at' => $failAt->toDateString(),
                    'result' => CalibrationResult::Fail->value,
                ], $teknisi->id);

                Carbon::setTestNow($failAt->copy()->addHours(2));
                $damageReportService->report([
                    'equipment_id' => $equipment->id,
                    'reported_at' => $failAt->copy()->addHours(2)->toDateTimeString(),
                    'complaint' => 'Tidak lulus uji kalibrasi berkala — perlu investigasi dan perbaikan modul.',
                ], $teknisi);
            }

            // 9g. Alat Dalam perbaikan aktif (dimulai 1-3 hari lalu, belum selesai)
            foreach ($underRepairIndices as $pos => $eqIdx) {
                $reportAt = $realNow->copy()->subDays(3 - ($pos % 2))->setTime(8, 0);
                $repairStart = $reportAt->copy()->addHours(2);
                $equipment = $allEquipment[$eqIdx]->fresh();

                Carbon::setTestNow($reportAt);
                $reporter = $equipment->room_id === $rooms['ICU']->id ? $kepalaIcu : $admin;
                $report = $damageReportService->report([
                    'equipment_id' => $equipment->id,
                    'reported_at' => $reportAt->toDateTimeString(),
                    'complaint' => $complaints[$pos % count($complaints)],
                ], $reporter);

                Carbon::setTestNow($repairStart);
                $spChoice = $spareParts[$pos % count($spareParts)];
                $repairService->start([
                    'damage_report_id' => $report->id,
                    'technician_id' => $teknisi->id,
                    'started_at' => $repairStart->toDateTimeString(),
                    'action' => 'Sedang dilakukan pembongkaran dan pengujian komponen internal.',
                    'labor_cost' => '250000.00',
                    'spare_parts' => [
                        ['spare_part_id' => $spChoice['model']->id, 'quantity' => 1, 'unit_price' => $spChoice['price']],
                    ],
                ], $teknisi->id);
            }

            // 9h. Evaluasi final pada waktu sekarang & tambah stok opname / stok menipis realistis
            Carbon::setTestNow($realNow);
            foreach (Equipment::where('is_active', true)->get() as $equipment) {
                $statusService->evaluate($equipment);
            }

            // Pastikan minimal 2 suku cadang berada di bawah / sama dengan min_stock melalui opname realistis
            $lowTarget1 = $spareParts[1]['model']->fresh();
            if ($lowTarget1->stock > $lowTarget1->min_stock) {
                $stockService->adjust(
                    $lowTarget1,
                    max(2, $lowTarget1->min_stock - 3),
                    'Penyesuaian stock opname bulanan gudang IPSRS (unit rusak saat penyimpanan)',
                    $admin->id,
                    $realNow->copy()->subDays(2),
                );
            }
            $lowTarget2 = $spareParts[6]['model']->fresh();
            if ($lowTarget2->stock > $lowTarget2->min_stock) {
                $stockService->adjust(
                    $lowTarget2,
                    max(2, $lowTarget2->min_stock - 2),
                    'Penyesuaian stock opname bulanan gudang IPSRS',
                    $admin->id,
                    $realNow->copy()->subDay(),
                );
            }

            // Bersihkan notifikasi lama dari simulasi historis, lalu buat 2 laporan terbuka terbaru agar lonceng notifikasi relevan
            DB::table('notifications')->delete();

            $openReportTarget1 = Equipment::where('inventory_code', 'ALK-ICU-004')->first();
            if ($openReportTarget1) {
                $damageReportService->report([
                    'equipment_id' => $openReportTarget1->id,
                    'reported_at' => $realNow->copy()->subHours(3)->toDateTimeString(),
                    'complaint' => 'Layar monitor sesekali berkedip saat alarm SpO2 aktif.',
                ], $kepalaIcu);
            }

            $openReportTarget2 = Equipment::where('inventory_code', 'ALK-IGD-003')->first();
            if ($openReportTarget2) {
                $damageReportService->report([
                    'equipment_id' => $openReportTarget2->id,
                    'reported_at' => $realNow->copy()->subHours(1)->toDateTimeString(),
                    'complaint' => 'Kertas printer EKG tersendat saat mencetak 12 lead.',
                ], $admin);
            }
        } finally {
            Carbon::setTestNow();
        }
    }
}
