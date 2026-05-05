<?php

return [
    'rules' => [
        'vehicle.checked_in' => [
            'audience' => 'internal',
            'severity' => 'info',
            'customer_safe' => false,
        ],
        'estimate.sent' => [
            'audience' => 'customer',
            'severity' => 'info',
            'customer_safe' => true,
        ],
        'estimate.approved' => [
            'audience' => 'internal',
            'severity' => 'success',
            'customer_safe' => true,
        ],
        'estimate.partially_approved' => [
            'audience' => 'internal',
            'severity' => 'success',
            'customer_safe' => true,
        ],
        'estimate.rejected' => [
            'audience' => 'internal',
            'severity' => 'warning',
            'customer_safe' => true,
        ],
        'parts.requested' => [
            'audience' => 'internal',
            'severity' => 'info',
            'customer_safe' => false,
        ],
        'vehicle.ready_for_delivery' => [
            'audience' => 'customer',
            'severity' => 'success',
            'customer_safe' => true,
        ],
        'vehicle.delivered' => [
            'audience' => 'customer',
            'severity' => 'success',
            'customer_safe' => true,
        ],
        'complaint.created' => [
            'audience' => 'internal',
            'severity' => 'warning',
            'customer_safe' => false,
        ],
        'document.generation.started' => [
            'audience' => 'internal',
            'severity' => 'info',
            'customer_safe' => false,
        ],
        'document.generation.completed' => [
            'audience' => 'internal',
            'severity' => 'success',
            'customer_safe' => false,
        ],
        'document.generation.failed' => [
            'audience' => 'internal',
            'severity' => 'danger',
            'customer_safe' => false,
        ],
    ],

    'sensitive_payload_keys' => [
        'cost',
        'profit',
        'margin',
        'internal_note',
        'internal_notes',
        'technician_internal_notes',
        'supplier_cost',
        'purchase_price',
        'cost_price',
    ],
];
