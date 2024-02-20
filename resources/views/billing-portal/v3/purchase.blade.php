<div class="grid grid-cols-12 bg-gray-50">
    <div
    
    class="col-span-12 xl:col-span-6 bg-white flex flex-col items-center lg:h-screen"
    >
        <div class="w-full p-10 lg:mt-24 md:max-w-xl">
            @if($errors->any())
                @foreach($errors as $error)
                    <div class="alert alert-danger">
                        {{ $error }}
                    </div>
                @endforeach
            @endif

            <img
                class="h-8"
                src="{{ $subscription->company->present()->logo }}"
                alt="{{ $subscription->company->present()->name }}"
            />

            <div class="my-10" id="container">
                @livewire($this->component, ['context' => $context, 'subscription' => $this->subscription], key($this->componentUniqueId()))
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-6">
        <div class="sticky top-0">
            <div class="w-full p-10 lg:mt-24 md:max-w-xl">
                <div class="my-6 space-y-10 xl:ml-5">
                    @livewire('billing-portal.summary', ['subscription' => $subscription, 'context' => $context], key($this->summaryUniqueId()))
                </div>
            </div>
        </div>
    </div>

    <form 
        action="{{ route('client.payments.process', ['hash' => $hash, 'sidebar' => 'hidden']) }}"
        method="post"
        id="payment-method-form">
        @csrf

        <input type="hidden" name="action" value="payment">
        <input type="hidden" name="invoices[]" value="{{ $context['form']['invoice_hashed_id'] ?? '' }}">
        <input type="hidden" name="payable_invoices[0][amount]" value="{{ $context['form']['payable_amount'] ?? '' }}">
        <input type="hidden" name="payable_invoices[0][invoice_id]" value="{{ $context['form']['invoice_hashed_id'] ?? '' }}">
        <input type="hidden" name="company_gateway_id" value="{{ $context['form']['company_gateway_id'] ?? '' }}"/>
        <input type="hidden" name="payment_method_id" value="{{ $context['form']['payment_method_id'] ?? '' }}"/>
        <input type="hidden" name="contact_first_name" value="{{ $context['contact']['first_name'] ?? '' }}">
        <input type="hidden" name="contact_last_name" value="{{ $context['contact']['last_name'] ?? '' }}">
        <input type="hidden" name="contact_email" value="{{ $context['contact']['email'] ?? '' }}">
  </form>
</div>
