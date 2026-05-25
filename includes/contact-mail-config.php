<?php
declare(strict_types=1);

/**
 * Contact form email settings.
 *
 * SMTP is used when smtp.enabled is true and username/password are set.
 * Override secrets in contact-mail.local.php (not committed to git).
 */
return [
    'recipient' => 'zubairhusnain58@gmail.com',
    'from_email' => 'your.email@gmail.com',
    'from_name' => 'Takeda Contact Form',

    'smtp' => [
        'enabled' => true,
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'your.email@gmail.com',
        'password' => '',
    ],

    'fallback_mail' => true,
];
