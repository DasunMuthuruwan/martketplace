<?php

return [
    'last_owner' => 'This is the only Owner. Appoint another Owner first.',
    'unverified_target' => 'Only accounts with a verified email can become staff.',
    'two_factor_required' => 'Set up two-factor authentication to use the admin area.',
    'session_expired' => 'For security, staff sessions end after a period of inactivity or a few hours. Please log in again.',

    'two_factor' => [
        'title' => 'Two-factor authentication',
        'intro' => 'Use an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Bitwarden…) to protect your account.',
        'status_on' => 'Two-factor authentication is on.',
        'status_pending' => 'Scan this QR code with your authenticator app, then enter the 6-digit code to finish.',
        'status_off' => 'Two-factor authentication is off.',
        'enable' => 'Turn on',
        'disable' => 'Turn off',
        'confirm' => 'Confirm',
        'code' => 'Authentication code',
        'setup_key' => 'Setup key',
        'recovery_title' => 'Recovery codes',
        'recovery_intro' => 'Store these codes somewhere safe. Each code works once if you lose your phone.',
        'regenerate' => 'Generate new recovery codes',
        'challenge_title' => 'Two-factor check',
        'challenge_intro' => 'Enter the 6-digit code from your authenticator app.',
        'use_recovery' => 'Or use a recovery code',
        'recovery_code' => 'Recovery code',
        'continue' => 'Continue',
    ],

    'admin' => [
        'title' => 'Admin',
        'placeholder' => 'The admin panel is built in milestone M6.',
    ],

    'reset_mail' => [
        'subject' => 'Your two-factor authentication was reset',
        'line' => 'Two-factor authentication on your account was reset by an administrator. Set it up again the next time you sign in.',
        'warning' => 'If you did not ask for this, contact the site owner immediately.',
    ],
];
