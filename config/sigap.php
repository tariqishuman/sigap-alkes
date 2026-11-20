<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Replacement Consideration Threshold (%)
    |--------------------------------------------------------------------------
    |
    | Equipment is flagged with "Pertimbangkan ganti" when cumulative repair
    | cost >= X% of its current book value (PRD §F6).
    |
    */
    'replace_threshold_percent' => (int) env('SIGAP_REPLACE_THRESHOLD_PERCENT', 50),

    /*
    |--------------------------------------------------------------------------
    | HL7 FHIR R4 Identifier & Extension Systems
    |--------------------------------------------------------------------------
    */
    'fhir' => [
        'inventory_code_system' => env('SIGAP_FHIR_INVENTORY_SYSTEM', 'https://sigap-alkes.its.ac.id/fhir/sid/inventory-code'),
        'serial_number_system' => env('SIGAP_FHIR_SERIAL_SYSTEM', 'https://sigap-alkes.its.ac.id/fhir/sid/serial-number'),
        'accreditation_system' => env('SIGAP_FHIR_ACCREDITATION_SYSTEM', 'https://sigap-alkes.its.ac.id/fhir/sid/kan-accreditation'),
        'status_extension_url' => env('SIGAP_FHIR_STATUS_EXT', 'https://sigap-alkes.its.ac.id/fhir/StructureDefinition/sigap-equipment-status'),
        'valid_until_extension_url' => env('SIGAP_FHIR_VALID_UNTIL_EXT', 'https://sigap-alkes.its.ac.id/fhir/StructureDefinition/calibration-valid-until'),
    ],
];
