# Pemetaan HL7 FHIR R4 & Integrasi SatuSehat — SIGAP-Alkes

Dokumen ini mendokumentasikan pemetaan resource **HL7 FHIR R4** read-only milik SIGAP-Alkes (`/api/fhir/r4`) dan hasil uji sinkronisasi ke **SatuSehat Kemenkes (Sandbox)**. Seluruh endpoint dan payload **tidak mengandung data pasien (non-RME)**, serta tidak pernah mengekspos biaya, harga perolehan, nilai aset, stok suku cadang, nama teknisi, atau isi keluhan kerusakan.

---

## 1. Endpoint API FHIR R4 SIGAP (Read-Only)

- **Base URL**: `/api/fhir/r4`
- **Content-Type**: `application/fhir+json`
- **Autentikasi**: `Authorization: Bearer <sanctum-token>` (kelola di **Pengaturan Aplikasi → Token API**)
- **Rate Limit**: 60 permintaan / menit per token
- **Format Error**: Semua error (`401`, `403`, `404`, `422`, `429`, `500`) mengembalikan FHIR `OperationOutcome` JSON.

| Endpoint | Metode | Parameter Pencarian | Deskripsi |
|---|---|---|---|
| `/api/fhir/r4/metadata` | `GET` | — | `CapabilityStatement` server FHIR R4 SIGAP |
| `/api/fhir/r4/Device` | `GET` | `identifier`, `location`, `type`, `status`, `_count` (default 20, maks 100), `page` | `Bundle` (`searchset`) daftar alat kesehatan aktif |
| `/api/fhir/r4/Device/{id}` | `GET` | — | Detail satu resource `Device` |
| `/api/fhir/r4/DeviceMetric` | `GET` | `source`, `_count`, `page` | `Bundle` (`searchset`) status kalibrasi per alat |
| `/api/fhir/r4/DeviceMetric/{id}` | `GET` | — | Detail satu resource `DeviceMetric` |
| `/api/fhir/r4/Location` | `GET` | `_count`, `page` | `Bundle` (`searchset`) daftar ruangan RS |
| `/api/fhir/r4/Location/{id}` | `GET` | — | Detail satu resource `Location` |
| `/api/fhir/r4/Organization` | `GET` | `_count`, `page` | `Bundle` (`searchset`) RS + lembaga penguji |
| `/api/fhir/r4/Organization/{id}` | `GET` | — | Detail `Organization/hospital` atau `Organization/inst-{id}` |

---

## 2. Pemetaan Resource

### 2.1 `Equipment` → `Device`

| Field SIGAP | Field FHIR R4 (`Device`) | Keterangan |
|---|---|---|
| `equipment.id` | `id` | String ID numerik lokal |
| `equipment.inventory_code` | `identifier[0]` | `system`: `config('sigap.fhir.inventory_code_system')` |
| `equipment.serial_number` | `identifier[1]` & `serialNumber` | `system`: `config('sigap.fhir.serial_number_system')` (bila terisi) |
| `equipment.name` | `deviceName[0].name` | `type`: `user-friendly-name` |
| `equipment.brand` | `manufacturer` | Opsional |
| `equipment.model` | `modelNumber` | Opsional |
| `equipment_types.name` | `type.text` | Jenis alat |
| `equipment.room_id` | `location.reference` | `Location/{room_id}` + `display` nama ruangan |
| Rumah Sakit (Pengaturan) | `owner.reference` | `Organization/hospital` + `display` nama RS |
| `equipment.status` | `status` + `statusReason` + `extension` | Lihat tabel pemetaan status di bawah |

#### Pemetaan 6 Status SIGAP → FHIR R4 `Device`

Kode status asli SIGAP selalu disertakan pada `extension` dengan URL `config('sigap.fhir.status_extension_url')`.

| Status SIGAP (`valueCode`) | `Device.status` | `Device.statusReason` (`device-status-reason`) |
|---|---|---|
| `fit_for_use` (Laik pakai) | `active` | `online` |
| `calibration_due` (Perlu kalibrasi) | `active` | `online` |
| `overdue` (Terlambat) | `active` | `not-ready` |
| `under_testing` (Dalam uji & kalibrasi) | `inactive` | `standby` |
| `under_repair` (Dalam perbaikan) | `inactive` | `offline` |
| `unfit` (Tidak laik pakai) | `inactive` | `hw-discon` |

### 2.2 `Calibration` (terakhir per alat) → `DeviceMetric`

| Field SIGAP | Field FHIR R4 (`DeviceMetric`) | Keterangan |
|---|---|---|
| `equipment.id` | `id` & `source.reference` | `Device/{id}` |
| `equipment.status` | `operationalStatus` | `off` bila `under_repair`/`unfit`, selain itu `on` |
| Jenis kalibrasi | `calibration[0].type` | `two-point` |
| Status sertifikat berlaku | `calibration[0].state` | `calibrated` bila `valid_until >= today()`, selain itu `unspecified` |
| `calibrations.tested_at` | `calibration[0].time` | ISO-8601 |
| `calibrations.valid_until` | `extension[0].valueDate` | URL `config('sigap.fhir.valid_until_extension_url')` |

### 2.3 `Room` → `Location`

- `status`: `active`, `mode`: `instance`, `physicalType`: `ro` (`Room`)
- `name`: `rooms.name`, `description`: `"{building} - {floor}"`
- `managingOrganization`: `Organization/hospital`
- `identifier` (bila sudah tersinkron): `rooms.satusehat_id`

### 2.4 Rumah Sakit & `TestingInstitution` → `Organization`

- **Rumah Sakit** (`Organization/hospital`): `name`, `telecom` (telepon RS), `address` (alamat RS) dari Pengaturan Aplikasi; `identifier` berisi `satusehat.organization_id` bila tersedia.
- **Lembaga Penguji** (`Organization/inst-{id}`): `name` = nama lembaga, `identifier[0].value` = `accreditation_number` dengan `system` dari `config('sigap.fhir.accreditation_system')`.

---

## 3. Sinkronisasi SatuSehat (Sandbox)

Mengikuti dokumentasi resmi Playbook SatuSehat Kemenkes (`https://satusehat.kemkes.go.id/platform/docs/id/playbook/`):

- **Auth URL (Sandbox Staging)**: `https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken?grant_type=client_credentials`
- **FHIR Base URL (Sandbox Staging)**: `https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1`
- **Sub-organisasi IPSRS**: `POST /Organization` dengan `partOf.reference = Organization/{org_id}`, `identifier.system = http://sys-ids.kemkes.go.id/organization/{org_id}`, `type = dept`. ID hasil disimpan di `settings` (`satusehat.ipsrs_organization_id`).
- **Location Ruangan**: `POST /Location` (baru) atau `PUT /Location/{satusehat_id}` (perubahan) dengan `identifier.system = http://sys-ids.kemkes.go.id/location/{org_id}`, `physicalType = ro`, `managingOrganization = Organization/{org_id}`. ID hasil disimpan di `rooms.satusehat_id`.

### Bukti Percobaan `POST /Device` di Sandbox SatuSehat

Playbook resmi SatuSehat tidak mencantumkan resource `Device` untuk inventaris alat kesehatan IPSRS. Dilakukan **satu percobaan** `POST /Device` pada sandbox staging (`2026-11-20`):

- **Request (`POST /fhir-r4/v1/Device`, tanpa token)**:
```json
{
    "resourceType": "Device",
    "identifier": [
        {
            "system": "https://sigap-alkes.its.ac.id/fhir/sid/inventory-code",
            "value": "ALK-ICU-001"
        },
        {
            "system": "https://sigap-alkes.its.ac.id/fhir/sid/serial-number",
            "value": "MIN-2100"
        }
    ],
    "status": "active",
    "statusReason": [
        {
            "coding": [
                {
                    "system": "http://terminology.hl7.org/CodeSystem/device-status-reason",
                    "code": "online",
                    "display": "Online"
                }
            ]
        }
    ],
    "extension": [
        {
            "url": "https://sigap-alkes.its.ac.id/fhir/StructureDefinition/sigap-equipment-status",
            "valueCode": "calibration_due"
        }
    ],
    "deviceName": [
        {
            "name": "Ventilator Mindray #1",
            "type": "user-friendly-name"
        }
    ],
    "type": {
        "text": "Ventilator"
    },
    "owner": {
        "reference": "Organization/0b342ea0-ed93-4b10-9ca0-0eb88a1e9be2"
    },
    "manufacturer": "Mindray",
    "modelNumber": "SV300",
    "serialNumber": "MIN-2100",
    "location": {
        "reference": "Location/354eb172-e58b-42ce-8eec-92b7cbb17481"
    }
}
```

- **HTTP Status**: `400 Bad Request`
- **Respons Sandbox**:
```json
{
    "resourceType": "OperationOutcome",
    "text": { "status": "generated" },
    "issue": [
        {
            "severity": "error",
            "code": "value",
            "details": {
                "text": "Invalid identifier system: https://sigap-alkes.its.ac.id/fhir/sid/inventory-code (RuleNumber: 10995)"
            },
            "expression": ["Device.identifier[0].system"]
        },
        {
            "severity": "error",
            "code": "value",
            "details": {
                "text": "Invalid identifier system: https://sigap-alkes.its.ac.id/fhir/sid/serial-number (RuleNumber: 10995)"
            },
            "expression": ["Device.identifier[1].system"]
        },
        {
            "severity": "error",
            "code": "value",
            "details": {
                "text": "Element not found: Device.type (RuleNumber: 11006)"
            },
            "expression": ["Device.type"]
        }
    ]
}
```

**Keputusan**: Karena sandbox SatuSehat menolak `Device` non-standar klinis dan playbook tidak menyertakan profil `Device` inventaris IPSRS, sinkronisasi `Device` ke SatuSehat **tidak dibangun**. Data alat kesehatan dan status kalibrasinya disediakan secara read-only melalui API HL7 FHIR R4 SIGAP (`/api/fhir/r4/Device` dan `/api/fhir/r4/DeviceMetric`).
