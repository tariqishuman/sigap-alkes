<?php

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\TestingInstitution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class FhirResourceMapper
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * Map SIGAP EquipmentStatus to HL7 FHIR R4 Device.status + Device.statusReason.
     *
     * @return array{status: string, reason_code: string, reason_display: string}
     */
    public function mapDeviceStatus(?EquipmentStatus $status): array
    {
        return match ($status) {
            EquipmentStatus::FitForUse, EquipmentStatus::CalibrationDue => [
                'status' => 'active',
                'reason_code' => 'online',
                'reason_display' => 'Online',
            ],
            EquipmentStatus::Overdue => [
                'status' => 'active',
                'reason_code' => 'not-ready',
                'reason_display' => 'Not Ready',
            ],
            EquipmentStatus::UnderTesting => [
                'status' => 'inactive',
                'reason_code' => 'standby',
                'reason_display' => 'Standby',
            ],
            EquipmentStatus::UnderRepair => [
                'status' => 'inactive',
                'reason_code' => 'offline',
                'reason_display' => 'Offline',
            ],
            EquipmentStatus::Unfit => [
                'status' => 'inactive',
                'reason_code' => 'hw-discon',
                'reason_display' => 'Hardware Disconnected',
            ],
            default => [
                'status' => 'unknown',
                'reason_code' => 'not-ready',
                'reason_display' => 'Not Ready',
            ],
        };
    }

    /**
     * Map Equipment to HL7 FHIR R4 Device resource (strictly non-sensitive fields only).
     *
     * @return array<string, mixed>
     */
    public function toDevice(Equipment $equipment): array
    {
        $equipment->loadMissing(['room', 'equipmentType']);
        $mapped = $this->mapDeviceStatus($equipment->status);

        $identifiers = [
            [
                'system' => (string) config('sigap.fhir.inventory_code_system'),
                'value' => $equipment->inventory_code,
            ],
        ];

        if (filled($equipment->serial_number)) {
            $identifiers[] = [
                'system' => (string) config('sigap.fhir.serial_number_system'),
                'value' => $equipment->serial_number,
            ];
        }

        $resource = [
            'resourceType' => 'Device',
            'id' => (string) $equipment->id,
            'meta' => [
                'lastUpdated' => ($equipment->updated_at ?? now())->toIso8601String(),
            ],
            'identifier' => $identifiers,
            'status' => $mapped['status'],
            'statusReason' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/device-status-reason',
                            'code' => $mapped['reason_code'],
                            'display' => $mapped['reason_display'],
                        ],
                    ],
                ],
            ],
            'extension' => [
                [
                    'url' => (string) config('sigap.fhir.status_extension_url'),
                    'valueCode' => $equipment->status?->value ?? 'unknown',
                ],
            ],
            'deviceName' => [
                [
                    'name' => $equipment->name,
                    'type' => 'user-friendly-name',
                ],
            ],
            'type' => [
                'text' => $equipment->equipmentType?->name ?? 'Alat Kesehatan',
            ],
            'owner' => [
                'reference' => 'Organization/hospital',
                'display' => $this->settings->hospitalName(),
            ],
        ];

        if (filled($equipment->brand)) {
            $resource['manufacturer'] = $equipment->brand;
        }

        if (filled($equipment->model)) {
            $resource['modelNumber'] = $equipment->model;
        }

        if (filled($equipment->serial_number)) {
            $resource['serialNumber'] = $equipment->serial_number;
        }

        if ($equipment->room) {
            $resource['location'] = [
                'reference' => "Location/{$equipment->room->id}",
                'display' => $equipment->room->name,
            ];
        }

        return $resource;
    }

    /**
     * Map Equipment's calibration status to HL7 FHIR R4 DeviceMetric resource.
     *
     * @return array<string, mixed>
     */
    public function toDeviceMetric(Equipment $equipment): array
    {
        $equipment->loadMissing(['latestValidCalibration', 'equipmentType']);
        $latestValid = $equipment->latestValidCalibration;

        $isCertValid = $latestValid?->valid_until !== null
            && $latestValid->valid_until->copy()->startOfDay()->greaterThanOrEqualTo(today());

        $calibrationEntry = [
            'type' => 'two-point',
            'state' => $isCertValid ? 'calibrated' : 'unspecified',
        ];

        if ($latestValid?->tested_at) {
            $calibrationEntry['time'] = $latestValid->tested_at->toIso8601String();
        }

        $resource = [
            'resourceType' => 'DeviceMetric',
            'id' => (string) $equipment->id,
            'meta' => [
                'lastUpdated' => ($equipment->updated_at ?? now())->toIso8601String(),
            ],
            'type' => [
                'text' => 'Status Kalibrasi Alat Kesehatan',
            ],
            'source' => [
                'reference' => "Device/{$equipment->id}",
                'display' => $equipment->name,
            ],
            'operationalStatus' => in_array($equipment->status, [EquipmentStatus::UnderRepair, EquipmentStatus::Unfit], true)
                ? 'off'
                : 'on',
            'category' => 'measurement',
            'calibration' => [$calibrationEntry],
        ];

        if ($latestValid?->valid_until) {
            $resource['extension'] = [
                [
                    'url' => (string) config('sigap.fhir.valid_until_extension_url'),
                    'valueDate' => $latestValid->valid_until->format('Y-m-d'),
                ],
            ];
        }

        return $resource;
    }

    /**
     * Map Room to HL7 FHIR R4 Location resource.
     *
     * @return array<string, mixed>
     */
    public function toLocation(Room $room): array
    {
        $description = collect([$room->building, $room->floor])->filter()->implode(' - ');

        $resource = [
            'resourceType' => 'Location',
            'id' => (string) $room->id,
            'meta' => [
                'lastUpdated' => ($room->updated_at ?? now())->toIso8601String(),
            ],
            'status' => 'active',
            'name' => $room->name,
            'mode' => 'instance',
            'physicalType' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/location-physical-type',
                        'code' => 'ro',
                        'display' => 'Room',
                    ],
                ],
            ],
            'managingOrganization' => [
                'reference' => 'Organization/hospital',
                'display' => $this->settings->hospitalName(),
            ],
        ];

        if ($description !== '') {
            $resource['description'] = $description;
        }

        if (filled($room->satusehat_id)) {
            $resource['identifier'] = [
                [
                    'system' => 'https://fhir.kemkes.go.id/id/satusehat-location',
                    'value' => $room->satusehat_id,
                ],
            ];
        }

        return $resource;
    }

    /**
     * Map Hospital (from App Settings) to HL7 FHIR R4 Organization resource.
     *
     * @return array<string, mixed>
     */
    public function hospitalOrganization(): array
    {
        $org = [
            'resourceType' => 'Organization',
            'id' => 'hospital',
            'active' => true,
            'type' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/organization-type',
                            'code' => 'prov',
                            'display' => 'Healthcare Provider',
                        ],
                    ],
                ],
            ],
            'name' => $this->settings->hospitalName(),
        ];

        $satusehatOrgId = $this->settings->get('satusehat.organization_id');
        if (filled($satusehatOrgId)) {
            $org['identifier'] = [
                [
                    'use' => 'official',
                    'system' => 'https://fhir.kemkes.go.id/id/organisasi',
                    'value' => (string) $satusehatOrgId,
                ],
            ];
        }

        $phone = $this->settings->get('general.hospital_phone');
        if (filled($phone)) {
            $org['telecom'] = [
                [
                    'system' => 'phone',
                    'value' => (string) $phone,
                    'use' => 'work',
                ],
            ];
        }

        $address = $this->settings->get('general.hospital_address');
        if (filled($address)) {
            $org['address'] = [
                [
                    'use' => 'work',
                    'type' => 'physical',
                    'text' => (string) $address,
                    'country' => 'ID',
                ],
            ];
        }

        return $org;
    }

    /**
     * Map TestingInstitution to HL7 FHIR R4 Organization resource.
     *
     * @return array<string, mixed>
     */
    public function toTestingOrganization(TestingInstitution $institution): array
    {
        $org = [
            'resourceType' => 'Organization',
            'id' => "inst-{$institution->id}",
            'meta' => [
                'lastUpdated' => ($institution->updated_at ?? now())->toIso8601String(),
            ],
            'active' => true,
            'type' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/organization-type',
                            'code' => 'other',
                            'display' => $institution->kind?->getLabel() ?? 'Lembaga Penguji',
                        ],
                    ],
                ],
            ],
            'name' => $institution->name,
        ];

        if (filled($institution->accreditation_number)) {
            $org['identifier'] = [
                [
                    'use' => 'official',
                    'system' => (string) config('sigap.fhir.accreditation_system'),
                    'value' => $institution->accreditation_number,
                ],
            ];
        }

        return $org;
    }

    /**
     * Build a paginated HL7 FHIR R4 searchset Bundle.
     *
     * @param  array<int, array<string, mixed>>  $resources
     * @return array<string, mixed>
     */
    public function toSearchsetBundle(
        Request $request,
        array $resources,
        int $total,
        int $page,
        int $count,
        string $baseResourceUrl,
    ): array {
        $query = $request->query();
        $query['_count'] = $count;

        $buildLink = function (int $targetPage) use ($request, $query): string {
            $params = array_merge($query, ['page' => $targetPage]);

            return $request->url().'?'.http_build_query($params);
        };

        $links = [
            ['relation' => 'self', 'url' => $buildLink($page)],
        ];

        if (($page * $count) < $total) {
            $links[] = ['relation' => 'next', 'url' => $buildLink($page + 1)];
        }

        if ($page > 1) {
            $links[] = ['relation' => 'previous', 'url' => $buildLink($page - 1)];
        }

        $entries = array_map(fn (array $res): array => [
            'fullUrl' => rtrim($baseResourceUrl, '/').'/'.$res['id'],
            'resource' => $res,
        ], $resources);

        return [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => $total,
            'link' => $links,
            'entry' => $entries,
        ];
    }

    /**
     * Build searchset Bundle directly from a LengthAwarePaginator.
     *
     * @param  callable(mixed): array<string, mixed>  $mapper
     * @return array<string, mixed>
     */
    public function paginateToBundle(
        Request $request,
        LengthAwarePaginator $paginator,
        callable $mapper,
        string $baseResourceUrl,
    ): array {
        $mapped = array_map($mapper, $paginator->items());

        return $this->toSearchsetBundle(
            $request,
            $mapped,
            $paginator->total(),
            $paginator->currentPage(),
            $paginator->perPage(),
            $baseResourceUrl,
        );
    }

    /**
     * HL7 FHIR R4 CapabilityStatement for GET /api/fhir/r4/metadata.
     *
     * @return array<string, mixed>
     */
    public function capabilityStatement(string $baseUrl): array
    {
        return [
            'resourceType' => 'CapabilityStatement',
            'status' => 'active',
            'date' => now()->toIso8601String(),
            'publisher' => $this->settings->hospitalName(),
            'kind' => 'instance',
            'software' => [
                'name' => $this->settings->appName(),
                'version' => '1.0.0',
            ],
            'implementation' => [
                'description' => 'Read-only HL7 FHIR R4 API untuk inventaris & kalibrasi alat kesehatan (tanpa data pasien).',
                'url' => $baseUrl,
            ],
            'fhirVersion' => '4.0.1',
            'format' => ['application/fhir+json'],
            'rest' => [
                [
                    'mode' => 'server',
                    'resource' => [
                        [
                            'type' => 'Device',
                            'interaction' => [['code' => 'read'], ['code' => 'search-type']],
                            'searchParam' => [
                                ['name' => 'identifier', 'type' => 'token'],
                                ['name' => 'location', 'type' => 'reference'],
                                ['name' => 'type', 'type' => 'string'],
                                ['name' => 'status', 'type' => 'token'],
                            ],
                        ],
                        [
                            'type' => 'DeviceMetric',
                            'interaction' => [['code' => 'read'], ['code' => 'search-type']],
                            'searchParam' => [
                                ['name' => 'source', 'type' => 'reference'],
                            ],
                        ],
                        [
                            'type' => 'Location',
                            'interaction' => [['code' => 'read'], ['code' => 'search-type']],
                        ],
                        [
                            'type' => 'Organization',
                            'interaction' => [['code' => 'read'], ['code' => 'search-type']],
                        ],
                    ],
                ],
            ],
        ];
    }
}
