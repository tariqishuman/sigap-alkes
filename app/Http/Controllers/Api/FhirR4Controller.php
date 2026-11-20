<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\TestingInstitution;
use App\Services\FhirResourceMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FhirR4Controller extends Controller
{
    public function __construct(
        private readonly FhirResourceMapper $mapper,
    ) {}

    public function metadata(Request $request): JsonResponse
    {
        return $this->fhirResponse(
            $this->mapper->capabilityStatement(url('/api/fhir/r4')),
        );
    }

    public function searchDevices(Request $request): JsonResponse
    {
        $count = $this->resolveCount($request);

        $query = Equipment::query()
            ->where('is_active', true)
            ->with(['room', 'equipmentType']);

        if (filled($identifier = $request->query('identifier'))) {
            $term = str_contains((string) $identifier, '|')
                ? explode('|', (string) $identifier, 2)[1]
                : (string) $identifier;

            $query->where(function (Builder $q) use ($term): void {
                $q->where('inventory_code', $term)
                    ->orWhere('serial_number', $term);
            });
        }

        if (filled($location = $request->query('location'))) {
            $locId = str_starts_with((string) $location, 'Location/')
                ? substr((string) $location, strlen('Location/'))
                : (string) $location;

            $query->where('room_id', (int) $locId);
        }

        if (filled($type = $request->query('type'))) {
            $query->whereHas('equipmentType', fn (Builder $q) => $q->where('name', 'like', '%'.(string) $type.'%'));
        }

        if (filled($status = $request->query('status'))) {
            $statusParam = (string) $status;
            if ($statusParam === 'active') {
                $query->whereIn('status', ['fit_for_use', 'calibration_due', 'overdue']);
            } elseif ($statusParam === 'inactive') {
                $query->whereIn('status', ['under_testing', 'under_repair', 'unfit']);
            } else {
                $query->where('status', $statusParam);
            }
        }

        $paginator = $query->orderBy('id')->paginate($count);

        return $this->fhirResponse(
            $this->mapper->paginateToBundle(
                $request,
                $paginator,
                fn (Equipment $eq): array => $this->mapper->toDevice($eq),
                url('/api/fhir/r4/Device'),
            ),
        );
    }

    public function readDevice(int $id): JsonResponse
    {
        $equipment = Equipment::query()
            ->where('is_active', true)
            ->with(['room', 'equipmentType'])
            ->findOrFail($id);

        return $this->fhirResponse($this->mapper->toDevice($equipment));
    }

    public function searchDeviceMetrics(Request $request): JsonResponse
    {
        $count = $this->resolveCount($request);

        $query = Equipment::query()
            ->where('is_active', true)
            ->with(['latestValidCalibration', 'equipmentType']);

        if (filled($source = $request->query('source'))) {
            $deviceId = str_starts_with((string) $source, 'Device/')
                ? substr((string) $source, strlen('Device/'))
                : (string) $source;

            $query->whereKey((int) $deviceId);
        }

        $paginator = $query->orderBy('id')->paginate($count);

        return $this->fhirResponse(
            $this->mapper->paginateToBundle(
                $request,
                $paginator,
                fn (Equipment $eq): array => $this->mapper->toDeviceMetric($eq),
                url('/api/fhir/r4/DeviceMetric'),
            ),
        );
    }

    public function readDeviceMetric(int $id): JsonResponse
    {
        $equipment = Equipment::query()
            ->where('is_active', true)
            ->with(['latestValidCalibration', 'equipmentType'])
            ->findOrFail($id);

        return $this->fhirResponse($this->mapper->toDeviceMetric($equipment));
    }

    public function searchLocations(Request $request): JsonResponse
    {
        $count = $this->resolveCount($request);
        $paginator = Room::query()->orderBy('id')->paginate($count);

        return $this->fhirResponse(
            $this->mapper->paginateToBundle(
                $request,
                $paginator,
                fn (Room $room): array => $this->mapper->toLocation($room),
                url('/api/fhir/r4/Location'),
            ),
        );
    }

    public function readLocation(int $id): JsonResponse
    {
        $room = Room::query()->findOrFail($id);

        return $this->fhirResponse($this->mapper->toLocation($room));
    }

    public function searchOrganizations(Request $request): JsonResponse
    {
        $count = $this->resolveCount($request);
        $page = max(1, (int) $request->query('page', 1));

        $all = array_merge(
            [$this->mapper->hospitalOrganization()],
            TestingInstitution::query()
                ->orderBy('id')
                ->get()
                ->map(fn (TestingInstitution $inst): array => $this->mapper->toTestingOrganization($inst))
                ->all(),
        );

        $total = count($all);
        $slice = array_slice($all, ($page - 1) * $count, $count);

        return $this->fhirResponse(
            $this->mapper->toSearchsetBundle(
                $request,
                $slice,
                $total,
                $page,
                $count,
                url('/api/fhir/r4/Organization'),
            ),
        );
    }

    public function readOrganization(string $id): JsonResponse
    {
        if ($id === 'hospital') {
            return $this->fhirResponse($this->mapper->hospitalOrganization());
        }

        if (str_starts_with($id, 'inst-')) {
            $instId = (int) substr($id, 5);
            $inst = TestingInstitution::query()->findOrFail($instId);

            return $this->fhirResponse($this->mapper->toTestingOrganization($inst));
        }

        abort(404, 'Organization tidak ditemukan.');
    }

    private function resolveCount(Request $request): int
    {
        $raw = (int) $request->query('_count', 20);

        return max(1, min(100, $raw));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fhirResponse(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, [
            'Content-Type' => 'application/fhir+json',
        ]);
    }
}
