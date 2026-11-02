<?php

return [
    // Email and phone are deleted this many months after the event; names and results are kept.
    'contact_retention_months' => (int) env('CONTACT_RETENTION_MONTHS', 12),
];
