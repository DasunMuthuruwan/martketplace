<?php

return [
    'wrong_password' => 'The password is incorrect.',

    'nav' => [
        'label' => 'Your account',
        'overview' => 'Overview',
        'profile' => 'Profile',
        'contact' => 'Contact',
        'security' => 'Security',
    ],

    'profile' => [
        'title' => 'Profile',
        'phone' => 'Mobile number',
        'phone_hint' => 'Leave empty to remove it. Buyers only see it if your contact settings allow.',
        'current_password' => 'Current password',
        'current_password_hint' => 'Needed only when you change your email address. You will need to verify the new address.',
        'password_for_email' => 'Enter your current password to change your email address.',
        'save' => 'Save profile',
    ],

    'contact' => [
        'title' => 'Contact settings',
        'intro' => 'Choose who can see your phone and WhatsApp numbers on your ads. Numbers are never printed on the page; they are revealed only when someone asks for them.',
        'visibility' => 'Who can see my numbers',
        'visibility_options' => [
            'hidden' => 'Nobody (hidden)',
            'registered' => 'Logged-in users only',
            'public' => 'Everyone',
        ],
        'whatsapp_country' => 'WhatsApp number country',
        'whatsapp_phone' => 'WhatsApp number (optional)',
        'whatsapp_hint' => 'Leave empty to use your mobile number for WhatsApp.',
        'number_needed' => 'Add a mobile or WhatsApp number before showing it to buyers.',
        'no_phone' => 'You have not added a mobile number yet. Add one in your profile, or enter a WhatsApp number below.',
        'save' => 'Save contact settings',
        'saved' => 'Contact settings saved.',
    ],

    'security' => [
        'title' => 'Security',
        'password_title' => 'Change password',
        'current_password' => 'Current password',
        'new_password' => 'New password',
        'other_devices' => 'You will be signed out on your other devices.',
        'save_password' => 'Change password',
        'two_factor' => 'Two-factor authentication',
        'two_factor_link' => 'Manage two-factor authentication',
    ],

    'delete' => [
        'title' => 'Delete account',
        'intro' => 'Your account will be closed, your ads hidden and you will be signed out on every device. Within :days days you can ask support to restore it; after that your personal data is removed for good.',
        'password' => 'Confirm with your password',
        'button' => 'Delete my account',
        'done' => 'Your account has been deleted.',
        'mail_subject' => 'Your account was deleted',
        'mail_line' => 'Your account has been deleted and you have been signed out on every device.',
        'mail_restore' => 'If this was a mistake, contact support within :days days to restore it.',
        'mail_warning' => 'If you did not do this, contact support immediately.',
    ],

    'status' => [
        'profile-information-updated' => 'Profile saved.',
        'password-updated' => 'Password changed.',
    ],
    'deleted_user' => 'Deleted user',

    // Retention §1: inactive accounts.
    'inactive' => [
        'mail_subject' => 'Your account will be deleted',
        'mail_line' => 'You have not signed in for a long time. To keep your account, sign in within :days days. Otherwise it will be deleted along with your ads.',
        'mail_action' => 'Sign in',
        'mail_ignore' => 'If you no longer need the account, you do not have to do anything.',
    ],

    // Account area (routes doc §3, layout doc §3.5).
    'overview' => [
        'title' => 'Hello, :name',
        'verify_title' => 'Verify your email',
        'verify_text' => 'You can post ads and contact sellers once your email address is verified.',
        'verify_action' => 'Verify now',
        'attention' => 'Some ads need your attention: :rejected rejected, :expired expired.',
        'expiring' => 'Expiring soon',
        'nothing_expiring' => 'None of your ads expire in the next few days.',
        'shortcuts' => 'Shortcuts',
    ],

    'listings' => [
        'title' => 'My ads',
        'tabs' => 'Ad status',
        'tab' => ['active' => 'Live', 'pending' => 'In review', 'drafts' => 'Drafts', 'rejected' => 'Needs changes', 'expired' => 'Expired', 'sold' => 'Sold'],
        'status' => ['active' => 'Live', 'pending' => 'In review', 'draft' => 'Draft', 'rejected' => 'Rejected', 'suspended' => 'Taken down', 'expired' => 'Expired', 'sold' => 'Sold'],
        'empty' => [
            'active' => 'You have no live ads.',
            'pending' => 'No ads are waiting for review.',
            'drafts' => 'No drafts.',
            'rejected' => 'No ads need changes.',
            'expired' => 'No expired ads.',
            'sold' => 'Nothing marked as sold yet.',
        ],
        'untitled' => 'Untitled draft',
        'expires_in' => 'Expires :when',
        'expired_on' => 'Expired on :date',
        'pending_note' => 'Our team is reviewing it. You will get an email.',
        'draft_note' => 'Last edited :when',
        'sold_on' => 'Sold on :date',
        'reason' => 'Reason: :reason',
        'renew' => 'Renew',
        'mark_sold' => 'Mark as sold',
        'edit' => 'Edit',
        'edit_resubmit' => 'Edit and resubmit',
        'delete' => 'Delete',
        'delete_title' => 'Delete this ad?',
        'delete_text' => '":title" will be removed from the site. This cannot be undone.',
        'renewed' => 'Your ad is live again.',
        'marked_sold' => 'Marked as sold. Congratulations!',
        'deleted' => 'Ad deleted.',
    ],

    'favourites' => [
        'title' => 'Saved ads',
        'empty_title' => 'No saved ads yet',
        'empty_text' => 'Tap Save on an ad to keep it here.',
        'browse' => 'Browse ads',
        'unavailable' => 'This ad is no longer available.',
    ],
];
