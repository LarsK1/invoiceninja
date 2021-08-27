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

namespace App\PaymentDrivers\Braintree;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Http\Requests\Request;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\BraintreePaymentDriver;
use App\PaymentDrivers\Common\MethodInterface;
use App\Utils\Traits\MakesHash;

class ACH implements MethodInterface
{
    use MakesHash;

    protected BraintreePaymentDriver $braintree;

    public function __construct(BraintreePaymentDriver $braintree)
    {
        $this->braintree = $braintree;

        $this->braintree->init();
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->braintree;
        $data['client_token'] = $this->braintree->gateway->clientToken()->generate();

        return render('gateways.braintree.ach.authorize', $data);
    }

    public function authorizeResponse(Request $request)
    {
        $request->validate([
            'nonce' => ['required'],
            'gateway_type_id' => ['required'],
        ]);

        $customer = $this->braintree->findOrCreateCustomer();

        $result = $this->braintree->gateway->paymentMethod()->create([
            'customerId' => $customer->id,
            'paymentMethodNonce' => $request->nonce,
            'options' => [
                'usBankAccountVerificationMethod' => \Braintree\Result\UsBankAccountVerification::NETWORK_CHECK,
            ],
        ]);

        if ($result->success) {
            $account = $result->paymentMethod;

            try {
                $payment_meta = new \stdClass;
                $payment_meta->brand = (string)$account->bankName;
                $payment_meta->last4 = (string)$account->last4;
                $payment_meta->type = GatewayType::BANK_TRANSFER;
                $payment_meta->state = $account->verified ? 'authorized' : 'unauthorized';

                $data = [
                    'payment_meta' => $payment_meta,
                    'token' => $account->token,
                    'payment_method_id' => $request->gateway_type_id,
                ];

                $this->braintree->storeGatewayToken($data, ['gateway_customer_reference' => $customer->id]);

                return redirect()->route('client.payment_methods.index')->withMessage(ctrans('texts.payment_method_added'));
            } catch (\Exception $e) {
                return $this->braintree->processInternallyFailedPayment($this->braintree, $e);
            }
        }
    }

    public function paymentView(array $data)
    {
        $data['gateway'] = $this->braintree;
        $data['currency'] = $this->braintree->client->getCurrencyCode();
        $data['payment_method_id'] = GatewayType::BANK_TRANSFER;
        $data['amount'] = $this->braintree->payment_hash->data->amount_with_fee;

        return render('gateways.braintree.ach.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $request->validate([
            'source' => ['required'],
            'payment_hash' => ['required'],
        ]);

        $customer = $this->braintree->findOrCreateCustomer();

        $token = ClientGatewayToken::query()
            ->where('client_id', auth('contact')->user()->client->id)
            ->where('id', $this->decodePrimaryKey($request->source))
            ->firstOrFail();

        $result = $this->braintree->gateway->transaction()->sale([
            'amount' => $this->braintree->payment_hash->data->amount_with_fee,
            'paymentMethodToken' => $token->token,
            'options' => [
                'submitForSettlement' => true
            ],
        ]);

        if ($result->success) {
            $this->braintree->logSuccessfulGatewayResponse(['response' => $request->server_response, 'data' => $this->braintree->payment_hash], SystemLog::TYPE_BRAINTREE);

            return $this->processSuccessfulPayment($result);
        }

        return $this->processUnsuccessfulPayment($result);
    }

    private function processSuccessfulPayment($response)
    {
        $state = $this->braintree->payment_hash->data;

        $data = [
            'payment_type' => PaymentType::ACH,
            'amount' => $this->braintree->payment_hash->data->amount_with_fee,
            'transaction_reference' => $response->transaction->id,
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        $payment = $this->braintree->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client,
            $this->braintree->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->braintree->encodePrimaryKey($payment->id)]);
    }

    private function processUnsuccessfulPayment($response)
    {
        PaymentFailureMailer::dispatch($this->braintree->client, $response->transaction->additionalProcessorResponse, $this->braintree->client->company, $this->braintree->payment_hash->data->amount_with_fee);

        PaymentFailureMailer::dispatch(
            $this->braintree->client,
            $response,
            $this->braintree->client->company,
            $this->braintree->payment_hash->data->amount_with_fee,
        );

        $message = [
            'server_response' => $response,
            'data' => $this->braintree->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client,
            $this->braintree->client->company,
        );

        throw new PaymentFailed($response->transaction->additionalProcessorResponse, $response->transaction->processorResponseCode);
    }
}
