<?php

return [
    'wrong_password' => 'The password is incorrect.',

    'nav' => [
        'label' => 'Account settings',
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
];
