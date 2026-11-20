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
];
