<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Room;
use App\Models\SatuSehatSyncLog;
use Illuminate\Http\Client\Response;
use Throwable;

class SatuSehatSyncService
{
    public const IPSRS_ORG_SETTING_KEY = 'satusehat.ipsrs_organization_id';

    public function __construct(
        private readonly SatuSehatClient $client,
        private readonly SettingsService $settings,
        private readonly FhirResourceMapper $mapper,
    ) {}

    /**
     * Register sub-organization "Instalasi Pemeliharaan Sarana RS (IPSRS)" once under the hospital Organization ID.
     *
     * @return array{status: string, satusehat_id: ?string, http_status: ?int, message: string}
     */
    public function syncIpsrsOrganization(): array
    {
        if (! $this->client->isEnabled()) {
            return ['status' => 'skipped', 'satusehat_id' => null, 'http_status' => null, 'message' => 'Integrasi SatuSehat tidak aktif.'];
        }

        $existingId = $this->settings->get(self::IPSRS_ORG_SETTING_KEY);
        if (is_string($existingId) && trim($existingId) !== '') {
            return [
                'status' => 'skipped',
                'satusehat_id' => $existingId,
                'http_status' => null,
                'message' => 'Sub-organisasi IPSRS sudah terdaftar.',
            ];
        }

        $parentOrgId = $this->client->organizationId();
        $hospitalName = $this->settings->hospitalName();

        $payload = [
            'resourceType' => 'Organization',
            'active' => true,
            'identifier' => [
                [
                    'use' => 'official',
                    'system' => "http://sys-ids.kemkes.go.id/organization/{$parentOrgId}",
                    'value' => 'IPSRS',
                ],
            ],
            'type' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/organization-type',
                            'code' => 'dept',
                            'display' => 'Hospital Department',
                        ],
                    ],
                ],
            ],
            'name' => 'Instalasi Pemeliharaan Sarana RS (IPSRS)',
            'partOf' => [
                'reference' => "Organization/{$parentOrgId}",
                'display' => $hospitalName,
            ],
        ];

        $phone = $this->settings->get('general.hospital_phone');
        if (filled($phone)) {
            $payload['telecom'] = [
                [
                    'system' => 'phone',
                    'value' => (string) $phone,
                    'use' => 'work',
                ],
            ];
        }

        try {
            $response = $this->client->post('Organization', $payload);
            $summary = $this->summarizeResponse($response);

            if ($response->successful() && filled($response->json('id'))) {
                $id = (string) $response->json('id');
                $this->settings->set(self::IPSRS_ORG_SETTING_KEY, $id, false);
                $this->log('Organization', null, 'create', 'success', $response->status(), "ID SatuSehat: {$id}");

                return ['status' => 'success', 'satusehat_id' => $id, 'http_status' => $response->status(), 'message' => "Sub-organisasi IPSRS terdaftar ({$id})."];
            }

            $this->log('Organization', null, 'create', 'failed', $response->status(), $summary);

            return ['status' => 'failed', 'satusehat_id' => null, 'http_status' => $response->status(), 'message' => $summary];
        } catch (Throwable $e) {
            $msg = $this->sanitizeMessage($e->getMessage());
            $this->log('Organization', null, 'create', 'failed', null, $msg);

            return ['status' => 'failed', 'satusehat_id' => null, 'http_status' => null, 'message' => $msg];
        }
    }

    /**
     * Sync a single Room to SatuSehat Location (POST if new, PUT if satusehat_id already exists).
     *
     * @return array{status: string, satusehat_id: ?string, http_status: ?int, action: string, message: string}
     */
    public function syncLocation(Room $room): array
    {
        if (! $this->client->isEnabled()) {
            return ['status' => 'skipped', 'satusehat_id' => $room->satusehat_id, 'http_status' => null, 'action' => 'skip', 'message' => 'Integrasi SatuSehat tidak aktif.'];
        }

        $parentOrgId = $this->client->organizationId();
        $hospitalName = $this->settings->hospitalName();
        $description = collect([$room->building, $room->floor])->filter()->implode(' - ');

        $payload = [
            'resourceType' => 'Location',
            'identifier' => [
                [
                    'system' => "http://sys-ids.kemkes.go.id/location/{$parentOrgId}",
                    'value' => "ROOM-{$room->id}",
                ],
            ],
            'status' => 'active',
            'name' => $room->name,
            'description' => $description !== '' ? $description : $room->name,
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
                'reference' => "Organization/{$parentOrgId}",
                'display' => $hospitalName,
            ],
        ];

        $isUpdate = filled($room->satusehat_id);
        $action = $isUpdate ? 'update' : 'create';

        if ($isUpdate) {
            $payload['id'] = $room->satusehat_id;
        }

        try {
            $response = $isUpdate
                ? $this->client->put('Location', (string) $room->satusehat_id, $payload)
                : $this->client->post('Location', $payload);

            $summary = $this->summarizeResponse($response);

            if ($response->successful()) {
                $id = (string) ($response->json('id') ?: $room->satusehat_id);

                if ($id !== '' && $room->satusehat_id !== $id) {
                    $room->update(['satusehat_id' => $id]);
                }

                $this->log('Location', $room->id, $action, 'success', $response->status(), "{$room->name} ({$id})");

                return ['status' => 'success', 'satusehat_id' => $id, 'http_status' => $response->status(), 'action' => $action, 'message' => "Location {$room->name} berhasil disinkronkan ({$id})."];
            }

            $this->log('Location', $room->id, $action, 'failed', $response->status(), $summary);

            return ['status' => 'failed', 'satusehat_id' => $room->satusehat_id, 'http_status' => $response->status(), 'action' => $action, 'message' => $summary];
        } catch (Throwable $e) {
            $msg = $this->sanitizeMessage($e->getMessage());
            $this->log('Location', $room->id, $action, 'failed', null, $msg);

            return ['status' => 'failed', 'satusehat_id' => $room->satusehat_id, 'http_status' => null, 'action' => $action, 'message' => $msg];
        }
    }

    /**
     * Sync all Rooms to SatuSehat Location.
     *
     * @return array<int, array{room_id: int, room_name: string, status: string, satusehat_id: ?string, http_status: ?int, action: string, message: string}>
     */
    public function syncAllLocations(): array
    {
        if (! $this->client->isEnabled()) {
            return [];
        }

        $results = [];
        foreach (Room::query()->orderBy('id')->get() as $room) {
            $res = $this->syncLocation($room);
            $results[] = array_merge(['room_id' => $room->id, 'room_name' => $room->name], $res);
        }

        return $results;
    }

    /**
     * Single sandbox probe for POST /Device to document SatuSehat's behavior (evidence for docs/fhir-mapping.md).
     *
     * @return array{http_status: ?int, request_payload: array<string, mixed>, response_body: string}
     */
    public function probeDevice(Equipment $equipment): array
    {
        $parentOrgId = $this->client->organizationId();
        $payload = $this->mapper->toDevice($equipment);
        unset($payload['id'], $payload['meta']);
        $payload['owner'] = ['reference' => "Organization/{$parentOrgId}"];
        if ($equipment->room?->satusehat_id) {
            $payload['location'] = ['reference' => "Location/{$equipment->room->satusehat_id}"];
        } else {
            unset($payload['location']);
        }

        try {
            $response = $this->client->post('Device', $payload);
            $body = $this->sanitizeMessage($response->body());
            $this->log(
                'Device',
                $equipment->id,
                'probe',
                $response->successful() ? 'success' : 'failed',
                $response->status(),
                mb_substr($body, 0, 500),
            );

            return [
                'http_status' => $response->status(),
                'request_payload' => $payload,
                'response_body' => $body,
            ];
        } catch (Throwable $e) {
            $msg = $this->sanitizeMessage($e->getMessage());
            $this->log('Device', $equipment->id, 'probe', 'failed', null, $msg);

            return [
                'http_status' => null,
                'request_payload' => $payload,
                'response_body' => $msg,
            ];
        }
    }

    private function summarizeResponse(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            if (($json['resourceType'] ?? null) === 'OperationOutcome') {
                $diag = $json['issue'][0]['diagnostics'] ?? $json['issue'][0]['details']['text'] ?? 'OperationOutcome error';

                return $this->sanitizeMessage("HTTP {$response->status()}: {$diag}");
            }

            if (isset($json['id'])) {
                return "HTTP {$response->status()}: ID {$json['id']}";
            }
        }

        return $this->sanitizeMessage("HTTP {$response->status()}: ".mb_substr(trim($response->body()), 0, 200));
    }

    private function sanitizeMessage(string $message): string
    {
        $secret = $this->settings->getDecrypted('satusehat.client_secret');
        if (is_string($secret) && $secret !== '') {
            $message = str_replace($secret, '[REDACTED]', $message);
        }

        // Strip any accidental bearer/access token patterns.
        return preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer [REDACTED]', $message) ?? $message;
    }

    private function log(
        string $resource,
        ?int $localId,
        string $action,
        string $status,
        ?int $httpStatus,
        string $summary,
    ): void {
        SatuSehatSyncLog::create([
            'resource' => $resource,
            'local_id' => $localId,
            'action' => $action,
            'status' => $status,
            'http_status' => $httpStatus,
            'response_summary' => mb_substr($summary, 0, 1000),
            'synced_at' => now(),
        ]);
    }
}
