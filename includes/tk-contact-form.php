<?php
declare(strict_types=1);

require_once __DIR__ . '/tk-contact-mail.php';

function tk_contact_form_handle(array $post): array
{
    if (!tk_contact_form_enabled()) {
        return [
            'ok' => false,
            'message' => 'The contact form is temporarily unavailable. Please check back soon.',
            'values' => [],
            'disabled' => true,
        ];
    }

    $values = [
        'username' => trim((string)($post['username'] ?? '')),
        'email' => trim((string)($post['email'] ?? '')),
        'subject' => trim((string)($post['subject'] ?? '')),
        'message' => trim((string)($post['message'] ?? '')),
    ];

    if ($values['username'] === '') {
        return ['ok' => false, 'message' => 'Please enter your name.', 'values' => $values];
    }
    if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.', 'values' => $values];
    }
    if ($values['subject'] === '') {
        return ['ok' => false, 'message' => 'Please enter a subject.', 'values' => $values];
    }
    if ($values['message'] === '') {
        return ['ok' => false, 'message' => 'Please enter a message.', 'values' => $values];
    }
    if (strlen($values['message']) > 10000) {
        return ['ok' => false, 'message' => 'Message is too long (max 10,000 characters).', 'values' => $values];
    }

    // Honeypot
    if (trim((string)($post['website'] ?? '')) !== '') {
        return ['ok' => true, 'message' => 'Thank you. Your message has been sent.', 'values' => []];
    }

    $result = tk_contact_send_mail(
        $values['username'],
        $values['email'],
        $values['subject'],
        $values['message']
    );

    if ($result['ok']) {
        return ['ok' => true, 'message' => $result['message'], 'values' => []];
    }

    return ['ok' => false, 'message' => $result['message'], 'values' => $values];
}

function tk_contact_form_render(array $state): string
{
    $enabled = tk_contact_form_enabled();
    $v = $state['values'] ?? [];
    $ok = !empty($state['ok']);
    $msg = htmlspecialchars((string)($state['message'] ?? ''), ENT_QUOTES, 'UTF-8');
    $alertClass = $ok ? 'tk-contact-alert--success' : 'tk-contact-alert--error';
    $alert = $msg !== '' ? '<div class="tk-contact-alert ' . $alertClass . '" role="status">' . $msg . '</div>' : '';

    $disabledAttr = $enabled ? '' : ' disabled';
    $disabledNotice = $enabled
        ? ''
        : '<div class="tk-contact-alert tk-contact-alert--info" role="status">'
        . 'Form submissions are temporarily unavailable. Please check back soon.'
        . '</div>';

    $u = htmlspecialchars((string)($v['username'] ?? ''), ENT_QUOTES, 'UTF-8');
    $e = htmlspecialchars((string)($v['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $s = htmlspecialchars((string)($v['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
    $m = htmlspecialchars((string)($v['message'] ?? ''), ENT_QUOTES, 'UTF-8');

    $base = defined('TK_BASE_URL') ? TK_BASE_URL : '';

    return <<<HTML
<link rel="stylesheet" href="{$base}/assets/css/contact-form.css">
<main id="main-content" dir="ltr">
  <h1 class="sr-only">Contact Us</h1>
  <section class="tk-contact-hero">
    <div class="tk-contact-hero__inner">
      <h2 class="tk-contact-hero__title">Contact us</h2>
      <p class="tk-contact-hero__lead">Send us a message and we will get back to you as soon as possible.</p>
    </div>
  </section>
  <section class="tk-contact-form-section">
    <div class="tk-contact-form-wrap">
      {$disabledNotice}
      {$alert}
      <form class="tk-contact-form" method="post" action="" novalidate>
        <fieldset class="tk-contact-fieldset"{$disabledAttr}>
        <div class="tk-contact-field">
          <label for="tk-username">Name</label>
          <input type="text" id="tk-username" name="username" required autocomplete="name" maxlength="120" value="{$u}"{$disabledAttr}>
        </div>
        <div class="tk-contact-field">
          <label for="tk-email">Email</label>
          <input type="email" id="tk-email" name="email" required autocomplete="email" maxlength="254" value="{$e}"{$disabledAttr}>
        </div>
        <div class="tk-contact-field">
          <label for="tk-subject">Subject</label>
          <input type="text" id="tk-subject" name="subject" required maxlength="200" value="{$s}"{$disabledAttr}>
        </div>
        <div class="tk-contact-field">
          <label for="tk-message">Message</label>
          <textarea id="tk-message" name="message" required rows="8" maxlength="10000"{$disabledAttr}>{$m}</textarea>
        </div>
        <div class="tk-contact-hp" aria-hidden="true">
          <label for="tk-website">Website</label>
          <input type="text" id="tk-website" name="website" tabindex="-1" autocomplete="off"{$disabledAttr}>
        </div>
        <button type="submit" class="tk-contact-submit"{$disabledAttr}>Send message</button>
        </fieldset>
      </form>
    </div>
  </section>
</main>
HTML;
}
