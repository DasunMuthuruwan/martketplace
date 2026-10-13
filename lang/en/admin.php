<?php

return [
    'title' => 'Admin',

    'nav' => [
        'moderation' => 'Moderation',
        'changes' => 'Recent changes',
        'reports' => 'Reports',
        'site' => 'View site',
    ],

    'moderation' => [
        'empty' => 'No ads are waiting for review.',
        'flagged_only' => 'Flagged only',
        'flagged' => 'Flagged',
        'ad' => 'Ad',
        'seller' => 'Seller',
        'submitted' => 'Submitted',
        'review' => 'Review',
        'reasons' => 'Why it was flagged',
        'reason_term' => 'word ":term"',
        'reason_contact_in_text' => 'phone number or email in the text',
        'reason_duplicate' => 'same title posted recently',
        'unverified' => 'Email not verified',
        'seller_stats' => ':published published · :rejected rejected · :reports open reports',
        'template' => 'Reason',
        'note' => 'Note to the seller',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'request_changes' => 'Ask for changes',
        'approved' => 'Approved: :title',
        'rejected' => 'Rejected: :title',
        'changes_requested' => 'Changes requested: :title',
        // Reason templates (MOD-01); the moderator can add their own words.
        'templates' => [
            'photos' => 'The photos do not show the item for sale.',
            'details' => 'The description or details are missing or incorrect.',
            'category' => 'The ad is in the wrong category.',
            'prohibited' => 'This item is not allowed on the site.',
            'duplicate' => 'This is a duplicate of another ad.',
            'contact' => 'Please remove phone numbers, emails and links from the text; buyers contact you through the buttons.',
            'price' => 'The price looks wrong or misleading.',
        ],
    ],

    'changes' => [
        'empty' => 'No price changes yet.',
        'before' => 'Before',
        'after' => 'After',
        'when' => 'When',
    ],

    'reports' => [
        'empty' => 'No reports here.',
        'status' => 'Status',
        'statuses' => ['open' => 'Open', 'actioned' => 'Actioned', 'dismissed' => 'Dismissed'],
        'type' => 'Reported',
        'types' => ['listing' => 'Ad', 'user' => 'Account'],
        'reason' => 'Reason',
        'reporter' => 'Reported by',
        'deleted' => 'deleted',
        'target_gone' => 'The reported item no longer exists.',
        'dismiss' => 'Dismiss',
        'action' => 'Mark as actioned',
        'suspend_listing' => 'Take the ad down and close',
        'dismissed' => 'Report dismissed.',
        'actioned' => 'Report closed as actioned.',
        'suspended' => 'The ad was taken down and the report closed.',
    ],
];
