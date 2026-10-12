<?php

return [
    'blocked_terms' => 'Your ad contains words that are not allowed on this site (:terms). Remove them and try again.',
    'active_limit' => 'You can have up to :limit live or pending ads. Remove or mark one as sold first.',
    'already_decided' => 'Another moderator has already handled this ad.',

    'incomplete' => [
        'details' => 'Add the title, description and type of ad.',
        'attributes' => 'Fill in the required details: :fields.',
        'price' => 'Choose the price option.',
        'photos' => 'Add at least one photo.',
        'contact' => 'Choose how buyers can contact you: add a phone number and make it visible in your contact settings, or turn on call or WhatsApp for this ad.',
    ],

    'mail' => [
        'approved_subject' => 'Your ad is live',
        'approved_line' => 'Your ad ":title" has been approved and is now visible to buyers.',
        'expires' => 'It stays online until :date. You can renew it from your account.',
        'rejected_subject' => 'Your ad was not approved',
        'rejected_line' => 'Your ad ":title" was not approved.',
        'changes_subject' => 'Changes needed for your ad',
        'changes_line' => 'Your ad ":title" needs a few changes before it can go live.',
        'reason' => 'Moderator note: :reason',
        'resubmit' => 'Edit the ad in your account and submit it again.',
        'suspended_subject' => 'Your ad was taken down',
        'suspended_line' => 'Your ad ":title" was taken down by our team. Reason: :reason',
        'expiring_subject' => 'Your ad expires soon',
        'expiring_line' => 'Your ad ":title" expires on :date. Renew it from your account to keep it online.',
        'expired_subject' => 'Your ad has expired',
        'expired_line' => 'Your ad ":title" has expired and is no longer visible. You can renew it from your account.',
    ],
];
