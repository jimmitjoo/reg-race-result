<?php

return [
    // Email and phone are deleted this many months after the event; names and results are kept.
    'contact_retention_months' => (int) env('CONTACT_RETENTION_MONTHS', 12),
    // What people send with the organizers' forms is deleted this many months after it was sent.
    'form_retention_months' => (int) env('FORM_RETENTION_MONTHS', 12),
];
