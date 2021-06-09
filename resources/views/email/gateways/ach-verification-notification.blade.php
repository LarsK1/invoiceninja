@component('email.template.admin', ['logo' => $logo, 'settings' => $settings])
    <div class="center">
        <h1>{{ ctrans('texts.ach_verification_notification_label') }}</h1>
        <p>{{ ctrans('texts.ach_verification_notification') }}</p>
    </div>
@endcomponent
