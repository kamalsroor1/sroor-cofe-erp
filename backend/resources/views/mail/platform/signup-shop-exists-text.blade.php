{{-- Security review: plain-text alternative of App\Mail\SignupShopExistsMail. --}}
{!! $brand['name'] !!}

{!! __('mail.signup_shop_exists.heading') !!}

{!! __('mail.signup_shop_exists.intro', ['app' => $brand['name']]) !!}
@if ($loginUrl)

{!! __('mail.signup_shop_exists.login_url') !!} {!! $loginUrl !!}
{!! __('mail.signup_shop_exists.forgot_password') !!}
@endif

{!! __('mail.signup_shop_exists.contact_support') !!}
{!! __('mail.signup_shop_exists.not_you') !!}

--
{!! __('mail.layout.support_title') !!}
@if ($brand['support_phone'] !== '')
{!! __('mail.layout.phone_label') !!} {!! $brand['support_phone'] !!}
@endif
@if ($brand['support_email'] !== '')
{!! __('mail.layout.email_label') !!} {!! $brand['support_email'] !!}
@endif
{!! __('mail.layout.automated_notice', ['app' => $brand['name']]) !!}
