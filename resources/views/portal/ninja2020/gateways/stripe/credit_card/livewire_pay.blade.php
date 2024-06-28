<style>
    .spinner {
        /* Simple spinner styling */
        border: 4px solid rgba(0, 0, 0, 0.1);
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border-left-color: #09f;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>

<div>



    @if($stripe_account_id)
        <meta name="stripe-account-id" content="{{ $stripe_account_id }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $company_gateway->getPublishableKey() }}">
    @endif

    <meta name="stripe-secret" content="{{ $client_secret }}">
    <meta name="only-authorization" content="">
    <meta name="client-postal-code" content="{{ $client->postal_code ?? '' }}">
    <meta name="stripe-require-postal-code" content="{{ $company_gateway->require_postal_code }}">

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card" value="{{ $token_billing_string }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        <ul class="list-none hover:list-disc">
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
            <li class="py-2 hover:text-blue hover:bg-blue-600">
                <label class="mr-4">
                    <input
                        type="radio"
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-check-input text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            </li>
            @endforeach
        @endisset

            <li class="py-2 hover:text-blue hover:bg-blue-600">
                <label>
                    <input
                        type="radio"
                        id="toggle-payment-with-credit-card"
                        class="form-check-input text-indigo-600 rounded-full cursor-pointer"
                        name="payment-type"
                        checked/>
                    <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
                </label>
            </li>    
        </ul>
        
    @endcomponent

    @include('portal.ninja2020.gateways.stripe.includes.card_widget')
    @include('portal.ninja2020.gateways.includes.pay_now')
    

    @script
    <script src="https://js.stripe.com/v3/"></script>
    @vite('resources/js/clients/payments/stripe-credit-card.js')
    @endscript
    
</div>