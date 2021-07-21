<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\PayTrace;

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\PayFastPaymentDriver;
use App\PaymentDrivers\PaytracePaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CreditCard
{
    use MakesHash;

    public $paytrace_driver;

    public function __construct(PaytracePaymentDriver $paytrace_driver)
    {
        $this->paytrace_driver = $paytrace_driver;
    }

    public function authorizeView($data)
    {
        
        $data['client_key'] = $this->paytrace_driver->getAuthToken();
        $data['gateway'] = $this->paytrace_driver;

        return render('gateways.paytrace.authorize', $data);
    }

    // +"success": true
    // +"response_code": 160
    // +"status_message": "The customer profile for PLS5U60OoLUfQXzcmtJYNefPA0gTthzT/11 was successfully created."
    // +"customer_id": "PLS5U60OoLUfQXzcmtJYNefPA0gTthzT"

    //if(!$response->success)
    //handle failure
        
 	public function authorizeResponse($request)
 	{
        $data = $request->all();
        
        $response = $this->createCustomer($data);   

        return redirect()->route('client.payment_methods.index');

 	}  
    
    //  "_token" => "Vl1xHflBYQt9YFSaNCPTJKlY5x3rwcFE9kvkw71I"
    //   "company_gateway_id" => "1"
    //   "HPF_Token" => "e484a92c-90ed-4468-ac4d-da66824c75de"
    //   "enc_key" => "zqz6HMHCXALWdX5hyBqrIbSwU7TBZ0FTjjLB3Cp0FQY="
    //   "amount" => "Amount"
    //   "q" => "/client/payment_methods"
    //   "method" => "1"
    // ]

    // "customer_id":"customer789",
    // "hpf_token":"e369847e-3027-4174-9161-fa0d4e98d318",
    // "enc_key":"lI785yOBMet4Rt9o4NLXEyV84WBU3tdStExcsfoaOoo=",
    // "integrator_id":"xxxxxxxxxx",
    // "billing_address":{
    //     "name":"Mark Smith",
    //     "street_address":"8320 E. West St.",
    //     "city":"Spokane",
    //     "state":"WA",
    //     "zip":"85284"
    // }
    
    private function createCustomer($data)
    {
        $post_data = [
            'customer_id' => Str::random(32),
            'hpf_token' => $data['HPF_Token'],
            'enc_key' => $data['enc_key'],
            'integrator_id' => '959195xd1CuC',
            'billing_address' => $this->buildBillingAddress(),
        ];

        $response = $this->paytrace_driver->gatewayRequest('/v1/customer/pt_protect_create', $post_data);

        $cgt = [];
        $cgt['token'] = $response->customer_id;
        $cgt['payment_method_id'] = GatewayType::CREDIT_CARD;

        $profile = $this->getCustomerProfile($response->customer_id);

        $payment_meta = new \stdClass;
        $payment_meta->exp_month = $profile->credit_card->expiration_month;
        $payment_meta->exp_year = $profile->credit_card->expiration_year;
        $payment_meta->brand = 'CC';
        $payment_meta->last4 = $profile->credit_card->masked_number;
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $cgt['payment_meta'] = $payment_meta;

        $token = $this->paytrace_driver->storeGatewayToken($cgt, []);

        return $response;
    }

    private function getCustomerProfile($customer_id)
    {
        $profile = $this->paytrace_driver->gatewayRequest('/v1/customer/export', [
            'integrator_id' => '959195xd1CuC',
            'customer_id' => $customer_id,
            // 'include_bin' => true,
        ]);

        return $profile->customers[0];
        
    }

    private function buildBillingAddress()
    {
        return [
                'name' => $this->paytrace_driver->client->present()->name(),
                'street_address' => $this->paytrace_driver->client->address1,
                'city' => $this->paytrace_driver->client->city,
                'state' => $this->paytrace_driver->client->state,
                'zip' => $this->paytrace_driver->client->postal_code
            ];
    }

    public function paymentView($data)
    {

        $data['client_key'] = $this->paytrace_driver->getAuthToken();
        $data['gateway'] = $this->paytrace_driver;

        return render('gateways.paytrace.pay', $data);

    }

    public function paymentResponse(Request $request)
    {
        $response_array = $request->all();

        if($request->token)
            $this->processTokenPayment($request->token, $request);

        if ($request->has('store_card') && $request->input('store_card') === true) {

            $response = $this->createCustomer($request->all());
            
            $this->processTokenPayment($response->customer_id, $request);
        }

        //process a regular charge here:
        $data = [
            'hpf_token' => $data['HPF_Token'],
            'enc_key' => $data['enc_key'],
            'integrator_id' => '959195xd1CuC',
            'billing_address' => $this->buildBillingAddress(),
        ];        

        $response = $this->paytrace_driver->gatewayRequest('/v1/transactions/sale/pt_protect', $data);

        if($response->success)
            return $this->processSuccessfulPayment($response);

        return $this->processUnsuccessfulPayment($response);

    }

    public function processTokenPayment($token, $request)
    {

        $data = [
            'customer_id' => $request->token,
            'integrator_id' => '959195xd1CuC',
            'amount' => $request->input('amount_with_fee'),
        ];

        $response = $this->paytrace_driver->gatewayRequest('/v1/transactions/sale/by_customer', $data);

        if($response->success){
            $this->paytrace_driver->logSuccessfulGatewayResponse(['response' => $response, 'data' => $this->paytrace_driver->payment_hash], SystemLog::TYPE_PAYTRACE);

            return $this->processSuccessfulPayment($response);
        }

        return $this->processUnsuccessfulPayment($response);
    }

    private function processSuccessfulPayment($response)
    {
        $amount = array_sum(array_column($this->paytrace_driver->payment_hash->invoices(), 'amount')) + $this->paytrace_driver->payment_hash->fee_total;

        $response = $response_array;

        $payment_record = [];
        $payment_record['amount'] = $amount;
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        $payment_record['transaction_reference'] = $response->transaction_id;

        $payment = $this->paytrace_driver->createPayment($payment_record, Payment::STATUS_COMPLETED);

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

    }

    private function processUnsuccessfulPayment($response)
    {

        $error = $response->status_message;
        $error_code = property_exists($response, 'approval_message') ? $response->approval_message : 'Undefined code';

        $data = [
            'response' => $response,
            'error' => $error,
            'error_code' => $error_code,
        ];

        return $this->paytrace_driver->processUnsuccessfulTransaction($data);
    }

}