<?php

namespace Database\Seeders;

use App\Enums\CalibrationResult;
use App\Enums\EquipmentStatus;
use App\Enums\TestingInstitutionKind;
use App\Models\Calibration;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Room;
use App\Models\SparePart;
use App\Models\StatusHistory;
use App\Models\TestingInstitution;
use App\Models\User;
use App\Services\EquipmentStatusService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);

        $teknisi = User::where('email', 'teknisi@sigap-alkes.test')->first();
        $admin = User::where('email', 'admin@sigap-alkes.test')->first();
        $actorId = $teknisi?->id ?? $admin?->id;

        // 1. Ruangan (6 ruangan rumah sakit)
        $roomsData = [
            ['name' => 'Ruang ICU', 'building' => 'Gedung Utama', 'floor' => 'Lantai 2'],
            ['name' => 'Ruang IGD', 'building' => 'Gedung Utama', 'floor' => 'Lantai 1'],
            ['name' => 'Ruang Operasi (OK)', 'building' => 'Gedung Bedah Sentral', 'floor' => 'Lantai 2'],
            ['name' => 'Ruang NICU', 'building' => 'Gedung Ibu & Anak', 'floor' => 'Lantai 2'],
            ['name' => 'Poliklinik Jantung', 'building' => 'Gedung Rawat Jalan', 'floor' => 'Lantai 1'],
            ['name' => 'Instalasi Radiologi', 'building' => 'Gedung Diagnostik', 'floor' => 'Lantai 1'],
        ];

        $rooms = [];
        foreach ($roomsData as $roomItem) {
            $rooms[$roomItem['name']] = Room::updateOrCreate(
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
            ],
            [
                'name' => 'Syringe Pump',
                'requires_calibration' => true,
                'requires_installation' => false,
                'calibration_interval_months' => 12,
                'useful_life_years' => 5,
                'residual_value_percent' => 10,
            ],
            [
                'name' => 'Patient Monitor',
                'requires_calibration' => true,
                'requires_installation' => true,
                'calibration_interval_months' => 12,
                'useful_life_years' => 7,
                'residual_value_percent' => 10,
            ],
            [
                'name' => 'Ventilator',
                'requires_calibration' => true,
                'requires_installation' => true,
                'calibration_interval_months' => 12,
                'useful_life_years' => 8,
                'residual_value_percent' => 10,
            ],
            [
                'name' => 'Defibrillator',
                'requires_calibration' => true,
                'requires_installation' => false,
                'calibration_interval_months' => 12,
                'useful_life_years' => 7,
                'residual_value_percent' => 10,
            ],
            [
                'name' => 'ECG / EKG 12 Channel',
                'requires_calibration' => true,
                'requires_installation' => false,
                'calibration_interval_months' => 12,
                'useful_life_years' => 6,
                'residual_value_percent' => 10,
            ],
            [
                'name' => 'Suction Pump',
                'requires_calibration' => true,
                'requires_installation' => false,
                'calibration_interval_months' => 12,
                'useful_life_years' => 5,
                'residual_value_percent' => 5,
            ],
            [
                'name' => 'Baby Incubator',
                'requires_calibration' => true,
                'requires_installation' => true,
                'calibration_interval_months' => 12,
                'useful_life_years' => 8,
                'residual_value_percent' => 10,
            ],
            [
                'name' => 'Mesin Anestesi',
                'requires_calibration' => true,
                'requires_installation' => true,
                'calibration_interval_months' => 12,
                'useful_life_years' => 10,
                'residual_value_percent' => 10,
            ],
            [
                'name' => 'Tensimeter Digital Non-Kalibrasi',
                'requires_calibration' => false,
                'requires_installation' => false,
                'calibration_interval_months' => 12,
                'useful_life_years' => 4,
                'residual_value_percent' => 0,
            ],
        ];

        $types = [];
        foreach ($typesData as $typeItem) {
            $types[$typeItem['name']] = EquipmentType::updateOrCreate(
                ['name' => $typeItem['name']],
                $typeItem,
            );
        }

        // 3. Lembaga Penguji
        $institutionsData = [
            [
                'name' => 'BPAFK Surabaya',
                'kind' => TestingInstitutionKind::Bpafk,
                'accreditation_number' => 'LK-045-IDN',
            ],
            [
                'name' => 'PT Kalibrasi Medika Nusantara',
                'kind' => TestingInstitutionKind::Swasta,
                'accreditation_number' => 'LK-198-IDN',
            ],
            [
                'name' => 'Unit Elektromedis Internal RS',
                'kind' => TestingInstitutionKind::InternalRs,
                'accreditation_number' => null,
            ],
        ];

        $institutions = [];
        foreach ($institutionsData as $instItem) {
            $institutions[$instItem['name']] = TestingInstitution::updateOrCreate(
                ['name' => $instItem['name']],
                $instItem,
            );
        }

        // 4. Suku Cadang (Spare Parts)
        $sparePartsData = [
            ['name' => 'Baterai Lithium-Ion 12V Medis', 'unit' => 'pcs'],
            ['name' => 'Sensor SpO2 Dewasa Reusable', 'unit' => 'pcs'],
            ['name' => 'Kabel Trunk ECG 10 Lead', 'unit' => 'set'],
            ['name' => 'Selang Silikon Suction Medis', 'unit' => 'meter'],
            ['name' => 'Filter Bakteri / HEPA Ventilator', 'unit' => 'pcs'],
            ['name' => 'Manset NIBP Dewasa', 'unit' => 'pcs'],
            ['name' => 'Elemen Pemanas Baby Incubator', 'unit' => 'pcs'],
            ['name' => 'Fuse Pengaman 2A Medis', 'unit' => 'pcs'],
        ];

        foreach ($sparePartsData as $spItem) {
            SparePart::updateOrCreate(['name' => $spItem['name']], $spItem);
        }

        // 5. Inventaris Alat Kesehatan (30 unit dengan variasi status & jadwal kalibrasi)
        $today = Carbon::today();
        $statusService = app(EquipmentStatusService::class);

        $equipmentSpecs = [
            // --- RUANG ICU (8 unit) ---
            [
                'code' => 'ALK-ICU-001',
                'name' => 'Ventilator ICU Mindray SV300 #1',
                'type' => 'Ventilator',
                'room' => 'Ruang ICU',
                'brand' => 'Mindray',
                'model' => 'SV300',
                'serial' => 'MR-SV300-2201',
                'acq_date' => $today->copy()->subYears(3)->subMonths(2),
                'cost' => '385000000.00',
                'residual' => '38500000.00',
                'scenario' => 'fit_healthy', // Kalibrasi masih berlaku 8 bulan lagi
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-ICU-002',
                'name' => 'Ventilator ICU Dräger Savina 300',
                'type' => 'Ventilator',
                'room' => 'Ruang ICU',
                'brand' => 'Dräger',
                'model' => 'Savina 300 Select',
                'serial' => 'DRG-SV300-1904',
                'acq_date' => $today->copy()->subYears(6)->subMonths(4),
                'cost' => '450000000.00',
                'residual' => '45000000.00',
                'scenario' => 'due_7_days', // Jatuh tempo H-5 (masuk tab <= 7 hari)
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-ICU-003',
                'name' => 'Patient Monitor Multiparameter ICU #1',
                'type' => 'Patient Monitor',
                'room' => 'Ruang ICU',
                'brand' => 'Philips',
                'model' => 'IntelliVue MX450',
                'serial' => 'PH-MX450-2301',
                'acq_date' => $today->copy()->subYears(2)->subMonths(5),
                'cost' => '125000000.00',
                'residual' => '12500000.00',
                'scenario' => 'fit_healthy',
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-ICU-004',
                'name' => 'Patient Monitor Multiparameter ICU #2',
                'type' => 'Patient Monitor',
                'room' => 'Ruang ICU',
                'brand' => 'Mindray',
                'model' => 'BeneVision N12',
                'serial' => 'MR-N12-2108',
                'acq_date' => $today->copy()->subYears(4)->subMonths(1),
                'cost' => '98000000.00',
                'residual' => '9800000.00',
                'scenario' => 'due_20_days', // Jatuh tempo H-20 (masuk Perlu Kalibrasi <= 30 hari)
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-ICU-005',
                'name' => 'Syringe Pump Terumo ICU #1',
                'type' => 'Syringe Pump',
                'room' => 'Ruang ICU',
                'brand' => 'Terumo',
                'model' => 'Terufusion TE-SS800',
                'serial' => 'TRM-SS800-2211',
                'acq_date' => $today->copy()->subYears(3),
                'cost' => '24500000.00',
                'residual' => '2450000.00',
                'scenario' => 'overdue', // Sertifikat sudah lewat 15 hari -> Terlambat
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-ICU-006',
                'name' => 'Infusion Pump B. Braun Infusomat Space',
                'type' => 'Infusion Pump',
                'room' => 'Ruang ICU',
                'brand' => 'B. Braun',
                'model' => 'Infusomat Space',
                'serial' => 'BBR-ISP-2009',
                'acq_date' => $today->copy()->subYears(4)->subMonths(8),
                'cost' => '28000000.00',
                'residual' => '2800000.00',
                'scenario' => 'under_testing', // Sedang diuji oleh teknisi
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-ICU-007',
                'name' => 'Defibrillator Monitor Nihon Kohden ICU',
                'type' => 'Defibrillator',
                'room' => 'Ruang ICU',
                'brand' => 'Nihon Kohden',
                'model' => 'Cardiolife TEC-5631',
                'serial' => 'NK-TEC56-2103',
                'acq_date' => $today->copy()->subYears(4)->subMonths(3),
                'cost' => '142000000.00',
                'residual' => '14200000.00',
                'scenario' => 'failed_calibration', // Tidak lulus uji -> Tidak laik pakai
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-ICU-008',
                'name' => 'Suction Pump Mobile ICU',
                'type' => 'Suction Pump',
                'room' => 'Ruang ICU',
                'brand' => 'Medela',
                'model' => 'Dominant Flex',
                'serial' => 'MDL-DF-2012',
                'acq_date' => $today->copy()->subYears(4)->subMonths(10),
                'cost' => '42000000.00',
                'residual' => '2100000.00',
                'scenario' => 'under_repair', // Dalam perbaikan
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],

            // --- RUANG IGD (6 unit) ---
            [
                'code' => 'ALK-IGD-001',
                'name' => 'Defibrillator Biphasic IGD #1',
                'type' => 'Defibrillator',
                'room' => 'Ruang IGD',
                'brand' => 'Philips',
                'model' => 'HeartStart XL+',
                'serial' => 'PH-HSXL-2302',
                'acq_date' => $today->copy()->subYears(2)->subMonths(4),
                'cost' => '155000000.00',
                'residual' => '15500000.00',
                'scenario' => 'fit_healthy',
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-IGD-002',
                'name' => 'ECG 12 Channel Fukuda Denshi IGD',
                'type' => 'ECG / EKG 12 Channel',
                'room' => 'Ruang IGD',
                'brand' => 'Fukuda Denshi',
                'model' => 'CardiMax FX-8322',
                'serial' => 'FKD-FX83-2205',
                'acq_date' => $today->copy()->subYears(3)->subMonths(1),
                'cost' => '54000000.00',
                'residual' => '5400000.00',
                'scenario' => 'due_7_days',
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-IGD-003',
                'name' => 'Patient Monitor Triage IGD #1',
                'type' => 'Patient Monitor',
                'room' => 'Ruang IGD',
                'brand' => 'Mindray',
                'model' => 'ePM 12M',
                'serial' => 'MR-EPM12-2306',
                'acq_date' => $today->copy()->subYears(2),
                'cost' => '68000000.00',
                'residual' => '6800000.00',
                'scenario' => 'fit_healthy',
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-IGD-004',
                'name' => 'Infusion Pump Terumo IGD #1',
                'type' => 'Infusion Pump',
                'room' => 'Ruang IGD',
                'brand' => 'Terumo',
                'model' => 'Terufusion TE-LM800',
                'serial' => 'TRM-LM800-2109',
                'acq_date' => $today->copy()->subYears(3)->subMonths(9),
                'cost' => '23000000.00',
                'residual' => '2300000.00',
                'scenario' => 'overdue',
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-IGD-005',
                'name' => 'Suction Pump Emergency IGD',
                'type' => 'Suction Pump',
                'room' => 'Ruang IGD',
                'brand' => 'Yuwell',
                'model' => '7A-23D',
                'serial' => 'YWL-7A23-2208',
                'acq_date' => $today->copy()->subYears(2)->subMonths(10),
                'cost' => '14500000.00',
                'residual' => '725000.00',
                'scenario' => 'due_60_days', // Jatuh tempo H-45 (masuk tab <= 60 hari, masih Laik Pakai)
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-IGD-006',
                'name' => 'Tensimeter Digital Triage IGD',
                'type' => 'Tensimeter Digital Non-Kalibrasi',
                'room' => 'Ruang IGD',
                'brand' => 'Omron',
                'model' => 'HBP-1320 Professional',
                'serial' => 'OMR-1320-2401',
                'acq_date' => $today->copy()->subYear(),
                'cost' => '4800000.00',
                'residual' => '0.00',
                'scenario' => 'non_calibrated',
                'inst' => null,
            ],

            // --- RUANG OPERASI (OK) (5 unit) ---
            [
                'code' => 'ALK-OK-001',
                'name' => 'Mesin Anestesi GE Carestation 620 #1',
                'type' => 'Mesin Anestesi',
                'room' => 'Ruang Operasi (OK)',
                'brand' => 'GE Healthcare',
                'model' => 'Carestation 620',
                'serial' => 'GE-CS620-2102',
                'acq_date' => $today->copy()->subYears(4)->subMonths(6),
                'cost' => '680000000.00',
                'residual' => '68000000.00',
                'scenario' => 'fit_healthy',
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-OK-002',
                'name' => 'Mesin Anestesi Dräger Fabius Plus',
                'type' => 'Mesin Anestesi',
                'room' => 'Ruang Operasi (OK)',
                'brand' => 'Dräger',
                'model' => 'Fabius Plus XL',
                'serial' => 'DRG-FBP-1807',
                'acq_date' => $today->copy()->subYears(7)->subMonths(2),
                'cost' => '620000000.00',
                'residual' => '62000000.00',
                'scenario' => 'due_20_days',
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-OK-003',
                'name' => 'Patient Monitor Anestesi OK #1',
                'type' => 'Patient Monitor',
                'room' => 'Ruang Operasi (OK)',
                'brand' => 'GE Healthcare',
                'model' => 'CARESCAPE B450',
                'serial' => 'GE-B450-2204',
                'acq_date' => $today->copy()->subYears(3)->subMonths(3),
                'cost' => '135000000.00',
                'residual' => '13500000.00',
                'scenario' => 'fit_healthy',
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-OK-004',
                'name' => 'Syringe Pump Target Controlled Infusion OK',
                'type' => 'Syringe Pump',
                'room' => 'Ruang Operasi (OK)',
                'brand' => 'B. Braun',
                'model' => 'Perfusor Space',
                'serial' => 'BBR-PFS-2209',
                'acq_date' => $today->copy()->subYears(2)->subMonths(9),
                'cost' => '29500000.00',
                'residual' => '2950000.00',
                'scenario' => 'due_90_days', // Jatuh tempo H-75 (masuk tab <= 90 hari)
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-OK-005',
                'name' => 'Defibrillator Kamar Bedah OK',
                'type' => 'Defibrillator',
                'room' => 'Ruang Operasi (OK)',
                'brand' => 'Mindray',
                'model' => 'BeneHeart D6',
                'serial' => 'MR-BHD6-2111',
                'acq_date' => $today->copy()->subYears(3)->subMonths(7),
                'cost' => '118000000.00',
                'residual' => '11800000.00',
                'scenario' => 'under_testing',
                'inst' => 'BPAFK Surabaya',
            ],

            // --- RUANG NICU (4 unit) ---
            [
                'code' => 'ALK-NICU-001',
                'name' => 'Baby Incubator Atom Medical Incu i #1',
                'type' => 'Baby Incubator',
                'room' => 'Ruang NICU',
                'brand' => 'Atom Medical',
                'model' => 'Incu i V-2100G',
                'serial' => 'ATM-V2100-2203',
                'acq_date' => $today->copy()->subYears(3)->subMonths(4),
                'cost' => '215000000.00',
                'residual' => '21500000.00',
                'scenario' => 'fit_healthy',
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-NICU-002',
                'name' => 'Baby Incubator GE Giraffe OmniBed',
                'type' => 'Baby Incubator',
                'room' => 'Ruang NICU',
                'brand' => 'GE Healthcare',
                'model' => 'Giraffe OmniBed',
                'serial' => 'GE-GOB-2005',
                'acq_date' => $today->copy()->subYears(5)->subMonths(1),
                'cost' => '340000000.00',
                'residual' => '34000000.00',
                'scenario' => 'due_20_days',
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-NICU-003',
                'name' => 'Syringe Pump Neonatal NICU #1',
                'type' => 'Syringe Pump',
                'room' => 'Ruang NICU',
                'brand' => 'Terumo',
                'model' => 'Terufusion TE-SS800',
                'serial' => 'TRM-SS800-2304',
                'acq_date' => $today->copy()->subYears(2)->subMonths(2),
                'cost' => '24500000.00',
                'residual' => '2450000.00',
                'scenario' => 'fit_healthy',
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-NICU-004',
                'name' => 'Patient Monitor Neonatal NICU #1',
                'type' => 'Patient Monitor',
                'room' => 'Ruang NICU',
                'brand' => 'Philips',
                'model' => 'IntelliVue MX400',
                'serial' => 'PH-MX400-2106',
                'acq_date' => $today->copy()->subYears(4),
                'cost' => '112000000.00',
                'residual' => '11200000.00',
                'scenario' => 'overdue',
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],

            // --- POLIKLINIK JANTUNG (4 unit) ---
            [
                'code' => 'ALK-POLI-001',
                'name' => 'ECG 12 Channel Nihon Kohden Poli Jantung #1',
                'type' => 'ECG / EKG 12 Channel',
                'room' => 'Poliklinik Jantung',
                'brand' => 'Nihon Kohden',
                'model' => 'Cardiofax M ECG-2350',
                'serial' => 'NK-ECG2350-2207',
                'acq_date' => $today->copy()->subYears(2)->subMonths(11),
                'cost' => '62000000.00',
                'residual' => '6200000.00',
                'scenario' => 'fit_healthy',
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-POLI-002',
                'name' => 'ECG 12 Channel GE MAC 2000',
                'type' => 'ECG / EKG 12 Channel',
                'room' => 'Poliklinik Jantung',
                'brand' => 'GE Healthcare',
                'model' => 'MAC 2000',
                'serial' => 'GE-MAC2000-2011',
                'acq_date' => $today->copy()->subYears(4)->subMonths(7),
                'cost' => '58000000.00',
                'residual' => '5800000.00',
                'scenario' => 'due_60_days',
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-POLI-003',
                'name' => 'Defibrillator AED Plus Poli Jantung',
                'type' => 'Defibrillator',
                'room' => 'Poliklinik Jantung',
                'brand' => 'Zoll',
                'model' => 'R Series Plus',
                'serial' => 'ZOL-RSP-2112',
                'acq_date' => $today->copy()->subYears(3)->subMonths(6),
                'cost' => '138000000.00',
                'residual' => '13800000.00',
                'scenario' => 'fit_healthy',
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-POLI-004',
                'name' => 'Tensimeter Digital Nurse Station Poli Jantung',
                'type' => 'Tensimeter Digital Non-Kalibrasi',
                'room' => 'Poliklinik Jantung',
                'brand' => 'Omron',
                'model' => 'HBP-1120',
                'serial' => 'OMR-1120-2309',
                'acq_date' => $today->copy()->subYear()->subMonths(8),
                'cost' => '3900000.00',
                'residual' => '0.00',
                'scenario' => 'non_calibrated',
                'inst' => null,
            ],

            // --- INSTALASI RADIOLOGI (3 unit) ---
            [
                'code' => 'ALK-RAD-001',
                'name' => 'Patient Monitor MRI-Compatible Radiologi',
                'type' => 'Patient Monitor',
                'room' => 'Instalasi Radiologi',
                'brand' => 'Philips',
                'model' => 'Expression MR400',
                'serial' => 'PH-MR400-2104',
                'acq_date' => $today->copy()->subYears(4)->subMonths(2),
                'cost' => '295000000.00',
                'residual' => '29500000.00',
                'scenario' => 'fit_healthy',
                'inst' => 'BPAFK Surabaya',
            ],
            [
                'code' => 'ALK-RAD-002',
                'name' => 'Infusion Pump Kontras Pendamping CT-Scan',
                'type' => 'Infusion Pump',
                'room' => 'Instalasi Radiologi',
                'brand' => 'Terumo',
                'model' => 'Terufusion TE-LM800',
                'serial' => 'TRM-LM800-2210',
                'acq_date' => $today->copy()->subYears(2)->subMonths(8),
                'cost' => '23500000.00',
                'residual' => '2350000.00',
                'scenario' => 'due_90_days',
                'inst' => 'PT Kalibrasi Medika Nusantara',
            ],
            [
                'code' => 'ALK-RAD-003',
                'name' => 'Suction Pump Ruang Tindakan Radiologi',
                'type' => 'Suction Pump',
                'room' => 'Instalasi Radiologi',
                'brand' => 'Medela',
                'model' => 'Basic 30',
                'serial' => 'MDL-B30-2405',
                'acq_date' => $today->copy()->subMonths(6),
                'cost' => '34000000.00',
                'residual' => '1700000.00',
                'scenario' => 'never_calibrated', // Alat baru belum pernah dikalibrasi -> Perlu kalibrasi
                'inst' => null,
            ],
        ];

        foreach ($equipmentSpecs as $index => $spec) {
            $type = $types[$spec['type']];
            $room = $rooms[$spec['room']];
            $inst = $spec['inst'] ? $institutions[$spec['inst']] : null;
            $seq = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            $equipment = Equipment::updateOrCreate(
                ['inventory_code' => $spec['code']],
                [
                    'name' => $spec['name'],
                    'equipment_type_id' => $type->id,
                    'room_id' => $room->id,
                    'brand' => $spec['brand'],
                    'model' => $spec['model'],
                    'serial_number' => $spec['serial'],
                    'acquisition_date' => $spec['acq_date']->toDateString(),
                    'acquisition_cost' => $spec['cost'],
                    'residual_value' => $spec['residual'],
                    'is_active' => true,
                    'notes' => 'Data simulasi operasional SIGAP-Alkes (tanpa data pasien).',
                ],
            );

            // Bersihkan riwayat kalibrasi & status lama pada alat ini agar idempotent saat di-seed ulang
            $equipment->calibrations()->delete();
            $equipment->statusHistories()->delete();
            $initialStatus = $type->requires_calibration
                ? EquipmentStatus::CalibrationDue
                : EquipmentStatus::FitForUse;
            $equipment->forceFill(['status' => $initialStatus])->save();
            $statusService->initializeStatus($equipment);

            match ($spec['scenario']) {
                'fit_healthy' => $this->seedPassCalibration(
                    $equipment,
                    $inst,
                    "CERT-2026-{$seq}",
                    $today->copy()->subMonths(4),
                    $today->copy()->addMonths(8),
                    $actorId,
                    $statusService,
                ),
                'due_90_days' => $this->seedPassCalibration(
                    $equipment,
                    $inst,
                    "CERT-2026-{$seq}",
                    $today->copy()->subMonths(12)->addDays(75),
                    $today->copy()->addDays(75),
                    $actorId,
                    $statusService,
                ),
                'due_60_days' => $this->seedPassCalibration(
                    $equipment,
                    $inst,
                    "CERT-2026-{$seq}",
                    $today->copy()->subMonths(12)->addDays(45),
                    $today->copy()->addDays(45),
                    $actorId,
                    $statusService,
                ),
                'due_20_days' => $this->seedPassCalibration(
                    $equipment,
                    $inst,
                    "CERT-2026-{$seq}",
                    $today->copy()->subMonths(12)->addDays(20),
                    $today->copy()->addDays(20),
                    $actorId,
                    $statusService,
                ),
                'due_7_days' => $this->seedPassCalibration(
                    $equipment,
                    $inst,
                    "CERT-2026-{$seq}",
                    $today->copy()->subMonths(12)->addDays(5),
                    $today->copy()->addDays(5),
                    $actorId,
                    $statusService,
                ),
                'overdue' => $this->seedPassCalibration(
                    $equipment,
                    $inst,
                    "CERT-2025-{$seq}",
                    $today->copy()->subMonths(12)->subDays(15),
                    $today->copy()->subDays(15),
                    $actorId,
                    $statusService,
                ),
                'under_testing' => $statusService->startTesting($equipment, $actorId),
                'failed_calibration' => $this->seedFailedCalibration(
                    $equipment,
                    $inst,
                    $today->copy()->subDays(3),
                    $actorId,
                    $statusService,
                ),
                'under_repair' => $this->seedUnderRepair($equipment, $actorId),
                default => $statusService->evaluate($equipment),
            };
        }
    }

    private function seedPassCalibration(
        Equipment $equipment,
        TestingInstitution $institution,
        string $certificateNumber,
        Carbon $testedAt,
        Carbon $validUntil,
        ?int $actorId,
        EquipmentStatusService $statusService,
    ): void {
        // Tambahkan juga 1 riwayat kalibrasi tahun sebelumnya agar tab Riwayat Kalibrasi terlihat kaya
        Calibration::create([
            'equipment_id' => $equipment->id,
            'testing_institution_id' => $institution->id,
            'tested_at' => $testedAt->copy()->subYear()->toDateString(),
            'result' => CalibrationResult::Pass,
            'certificate_number' => $certificateNumber.'-PREV',
            'valid_until' => $testedAt->copy()->subDay()->toDateString(),
            'recorded_by' => $actorId,
        ]);

        Calibration::create([
            'equipment_id' => $equipment->id,
            'testing_institution_id' => $institution->id,
            'tested_at' => $testedAt->toDateString(),
            'result' => CalibrationResult::Pass,
            'certificate_number' => $certificateNumber,
            'valid_until' => $validUntil->toDateString(),
            'recorded_by' => $actorId,
        ]);

        $statusService->startTesting($equipment, $actorId);
        $statusService->recordTestResult($equipment, true, $actorId);
        $statusService->evaluate($equipment);
    }

    private function seedFailedCalibration(
        Equipment $equipment,
        TestingInstitution $institution,
        Carbon $testedAt,
        ?int $actorId,
        EquipmentStatusService $statusService,
    ): void {
        $statusService->startTesting($equipment, $actorId);

        Calibration::create([
            'equipment_id' => $equipment->id,
            'testing_institution_id' => $institution->id,
            'tested_at' => $testedAt->toDateString(),
            'result' => CalibrationResult::Fail,
            'certificate_number' => null,
            'valid_until' => null,
            'recorded_by' => $actorId,
        ]);

        $statusService->recordTestResult($equipment, false, $actorId);
    }

    private function seedUnderRepair(Equipment $equipment, ?int $actorId): void
    {
        $from = $equipment->status;
        $equipment->forceFill(['status' => EquipmentStatus::UnderRepair])->save();

        StatusHistory::create([
            'equipment_id' => $equipment->id,
            'from_status' => $from,
            'to_status' => EquipmentStatus::UnderRepair,
            'reason' => 'Laporan kerusakan dikonfirmasi (Data Dummy)',
            'changed_by' => $actorId,
            'changed_at' => now(),
        ]);
    }
}
