<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire;

use App\DataMapper\ClientSettings;
use App\Factory\ClientFactory;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Subscription\OtpCode;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laracasts\Presenter\Exceptions\PresenterException;
use Livewire\Component;

class BillingPortalPurchasev2 extends Component
{
    use MakesHash;
    /**
     * Random hash generated by backend to handle the tracking of state.
     *
     * @var string
     */
    public $hash;

    /**
     * E-mail address model for user input.
     *
     * @var string
     */
    public $email;

    /**
     * Instance of subscription.
     *
     * @var \App\Models\Subscription
     */
    public $subscription;

    /**
     * Instance of client contact.
     *
     * @var null|ClientContact
     */
    public $contact;

    /**
     * Id for CompanyGateway record.
     *
     * @var string|integer
     */
    public $company_gateway_id;

    /**
     * Id for GatewayType.
     *
     * @var string|integer
     */
    public $payment_method_id;

    /**
     * Array of front end variables for
     * the subscription
     */
    public $data = [];

    /**
     * List of payment methods fetched from client.
     *
     * @var array
     */
    public $methods = [];

    /**
     * Instance of \App\Models\Invoice
     *
     * @var Invoice
     */

    public \App\Models\Invoice $invoice;

    /**
     * Coupon model for user input
     *
     * @var string
     */
    public $coupon;

    /**
     * Quantity for seats
     *
     * @var int
     */
    public $quantity;

    public $invoice_hashed_id = '';

    public $payable_amount = 0;
    /**
     * First-hit request data (queries, locales...).
     *
     * @var array
     */
    public $request_data = [];

    /**
     * Instance of company.
     *
     * @var \App\Models\Company
     */
    public $company;


    /**
     * Instance of company.
     *
     * @var string
     */
    public string $db;

    /**
     * Campaign reference.
     *
     * @var string|null
     */
    public $campaign;

    public $bundle;
    public $recurring_products;
    public $products;
    public $optional_recurring_products;
    public $optional_products;
    public $total;
    public $non_recurring_total;
    public $recurring_total;
    public $discount;
    public $sub_total;
    public $authenticated = false;
    public $login;
    public $float_amount_total;
    public $payment_started = false;
    public $valid_coupon = false;
    public $payable_invoices = [];
    public $payment_confirmed = false;
    public $is_eligible = true;
    public $not_eligible_message = '';
    public $check_rff = false;

    public ?string $contact_first_name;
    public ?string $contact_last_name;
    public ?string $contact_email;
    public ?string $client_city;
    public ?string $client_postal_code;

    public function mount()
    {
        MultiDB::setDb($this->db);

        $this->subscription = Subscription::query()->with('company')->find($this->subscription);

        $this->company = $this->subscription->company;

        if (auth()->guard('contact')->check()) {
            $this->email = auth()->guard('contact')->user()->email;
            $this->contact = auth()->guard('contact')->user();
            $this->authenticated = true;
            $this->payment_started = true;
        } else {
            $this->bundle = collect();
        }

        $this->discount = 0;
        $this->sub_total = 0;
        $this->float_amount_total = 0;

        $this->invoice_hashed_id = '';
        $this->payable_amount = 0;

        $this->data = [];

        $this->price = $this->subscription->price; // ?

        $this->recurring_products = $this->subscription->service()->recurring_products();
        $this->products = $this->subscription->service()->products();
        $this->optional_recurring_products = $this->subscription->service()->optional_recurring_products();
        $this->optional_products = $this->subscription->service()->optional_products();

        $this->bundle = collect();

        //every thing below is redundant

        if (request()->query('coupon')) {
            $this->coupon = request()->query('coupon');
            $this->handleCoupon();
        } elseif (isset($this->subscription->promo_code) && strlen($this->subscription->promo_code) == 0 && $this->subscription->promo_discount > 0) {
            $this->price = $this->subscription->promo_price;
        }
    }

    public function loginValidation()
    {
        $this->resetErrorBag('login');
        $this->resetValidation('login');
    }

    public function handleLogin($user_code)
    {
        $this->resetErrorBag('login');
        $this->resetValidation('login');

        $code = Cache::get("subscriptions:otp:{$this->email}");

        if ($user_code != $code) {
            $errors = $this->getErrorBag();
            $errors->add('login', ctrans('texts.invalid_code'));
            return $this;
        }

        $contact = ClientContact::where('email', $this->email)
                                ->where('company_id', $this->subscription->company_id)
                                ->first();

        if ($contact) {
            Auth::guard('contact')->loginUsingId($contact->id, true);
            $this->contact = $contact;
        } else {
            $this->createClientContact();
        }

        $this->getPaymentMethods();

        $this->authenticated = true;
        $this->payment_started = true;
    }

    public function resetEmail()
    {
        $this->resetErrorBag('login');
        $this->resetValidation('login');
        $this->email = null;
    }

    public function handleEmail()
    {
        $this->validateOnly('email', ['email' => 'required|bail|email:rfc']);

        $rand = rand(100000, 999999);

        $email_hash = "subscriptions:otp:{$this->email}";

        Cache::put($email_hash, $rand, 120);

        $this->emailOtpCode($rand);
    }

    private function emailOtpCode($code)
    {
        $cc = new ClientContact();
        $cc->email = $this->email;

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new OtpCode($this->subscription->company, $this->contact, $code);
        $nmo->company = $this->subscription->company;
        $nmo->settings = $this->subscription->company->settings;
        $nmo->to_user = $cc;
        NinjaMailerJob::dispatch($nmo);
    }

    /**
     * Handle a coupon being entered into the checkout
     */
    public function handleCoupon()
    {
        $this->resetErrorBag('coupon');
        $this->resetValidation('coupon');

        if ($this->coupon == $this->subscription->promo_code) {
            $this->valid_coupon = true;
            $this->buildBundle();
        } else {
            $this->discount = 0;
            $this->valid_coupon = false;
            $this->buildBundle();
            $errors = $this->getErrorBag();
            $errors->add('coupon', ctrans('texts.invalid_coupon'));
            return $this;
        }
    }

    /**
     * Build the bundle in the checkout
     */
    public function buildBundle()
    {
        $this->bundle = collect();

        $data = $this->data;

        /* Recurring products can have a variable quantity */
        foreach ($this->recurring_products as $key => $p) {
            $qty = isset($data[$key]['recurring_qty']) ? $data[$key]['recurring_qty'] : 1;
            $total = $p->price * $qty;

            $this->bundle->push([
                'description' => $p->notes,
                'product_key' => $p->product_key,
                'unit_cost' => $p->price,
                'product' => substr(strip_tags($p->markdownNotes()), 0, 50),
                'price' => Number::formatMoney($total, $this->subscription->company).' / '. RecurringInvoice::frequencyForKey($this->subscription->frequency_id),
                'total' => $total,
                'qty' => $qty,
                'is_recurring' => true,
                'product_image' => $p->product_image,
            ]);
        }

        /* One time products can only have a single quantity */
        foreach ($this->products as $key => $p) {
            $qty = 1;
            $total = $p->price * $qty;

            $this->bundle->push([
                'description' => $p->notes,
                'product_key' => $p->product_key,
                'unit_cost' => $p->price,
                'product' => substr(strip_tags($p->markdownNotes()), 0, 50),
                'price' => Number::formatMoney($total, $this->subscription->company),
                'total' => $total,
                'qty' => $qty,
                'is_recurring' => false
            ]);
        }

        foreach ($this->data as $key => $value) {
            /* Optional recurring products can have a variable quantity */
            if (isset($this->data[$key]['optional_recurring_qty'])) {
                $p = $this->optional_recurring_products->first(function ($v, $k) use ($key) {
                    return $k == $key;
                });

                $qty = isset($this->data[$key]['optional_recurring_qty']) ? $this->data[$key]['optional_recurring_qty'] : false;
                $total = $p->price * $qty;

                if ($qty) {
                    $this->bundle->push([
                        'description' => $p->notes,
                        'product_key' => $p->product_key,
                        'unit_cost' => $p->price,
                        'product' => substr(strip_tags($p->markdownNotes()), 0, 50),
                        'price' => Number::formatMoney($total, $this->subscription->company).' / '. RecurringInvoice::frequencyForKey($this->subscription->frequency_id),
                        'total' => $total,
                        'qty' => $qty,
                        'is_recurring' => true
                    ]);
                }
            }

            /* Optional products can have a variable quantity */
            if (isset($this->data[$key]['optional_qty'])) {
                $p = $this->optional_products->first(function ($v, $k) use ($key) {
                    return $k == $key;
                });

                $qty = isset($this->data[$key]['optional_qty']) ? $this->data[$key]['optional_qty'] : false;
                $total = $p->price * $qty;

                if ($qty) {
                    $this->bundle->push([
                        'description' => $p->notes,
                        'product_key' => $p->product_key,
                        'unit_cost' => $p->price,
                        'product' => substr(strip_tags($p->markdownNotes()), 0, 50),
                        'price' => Number::formatMoney($total, $this->subscription->company),
                        'total' => $total,
                        'qty' => $qty,
                        'is_recurring' => false
                    ]);
                }
            }
        }

        $this->sub_total = Number::formatMoney($this->bundle->sum('total'), $this->subscription->company);
        $this->recurring_total = Number::formatMoney($this->bundle->where('is_recurring', true)->sum('total'), $this->subscription->company);
        $this->non_recurring_total = Number::formatMoney($this->bundle->where('is_recurring', false)->sum('total'), $this->subscription->company);
        $this->total = $this->sub_total;

        if ($this->valid_coupon) {
            if ($this->subscription->is_amount_discount) {
                $discount = $this->subscription->promo_discount;
            } else {
                $discount = round($this->bundle->sum('total') * ($this->subscription->promo_discount / 100), 2);
            }

            $this->discount = Number::formatMoney($discount, $this->subscription->company);

            $this->total = Number::formatMoney(($this->bundle->sum('total') - $discount), $this->subscription->company);

            $this->float_amount_total = ($this->bundle->sum('total') - $discount);
        } else {
            $this->float_amount_total = $this->bundle->sum('total');
            $this->total = Number::formatMoney($this->float_amount_total, $this->subscription->company);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws PresenterException
     * @throws InvalidArgumentException
     */
    private function createClientContact()
    {
        $company = $this->subscription->company;
        $user = $this->subscription->user;
        $user->setCompany($company);

        $client_repo = new ClientRepository(new ClientContactRepository());
        $data = [
            'name' => '',
            'group_settings_id' => $this->subscription->group_id,
            'contacts' => [
                ['email' => $this->email],
            ],
            'client_hash' => Str::random(40),
            'settings' => ClientSettings::defaults(),
        ];

        $client = $client_repo->save($data, ClientFactory::create($company->id, $user->id));

        $this->contact = $client->fresh()->contacts()->first();

        Auth::guard('contact')->loginUsingId($this->contact->id, true);

        return $this;
    }


    /**
     * @param mixed $propertyName
     *
     * @return BillingPortalPurchasev2
     */
    public function updated($propertyName): self
    {
        if (in_array($propertyName, ['login','email'])) {
            return $this;
        }

        $this->buildBundle();

        return $this;
    }

    /**
     * Fetching payment methods from the client.
     *
     * @return $this
     */
    protected function getPaymentMethods(): self
    {

        if ($this->float_amount_total == 0) {
            $this->methods = [];
        }

        if ($this->contact && $this->float_amount_total >= 0) {
            $this->methods = $this->contact->client->service()->getPaymentMethods($this->float_amount_total);
        }

        foreach($this->methods as $method) {

            if($method['is_paypal'] == '1' && !$this->check_rff) {
                $this->rff();
                break;
            }

        }

        return $this;
    }

    protected function rff()
    {

        $this->contact_first_name = $this->contact->first_name;
        $this->contact_last_name = $this->contact->last_name;
        $this->contact_email = $this->contact->email;
        $this->client_city = $this->contact->client->city;
        $this->client_postal_code = $this->contact->client->postal_code;

        if(
            strlen($this->contact_first_name ?? '') == 0 ||
            strlen($this->contact_last_name ?? '') == 0 ||
            strlen($this->contact_email ?? '') == 0 ||
            strlen($this->client_city ?? '') == 0 ||
            strlen($this->client_postal_code ?? '') == 0
        ) {
            $this->check_rff = true;
        }

        return $this;
    }

    public function handleRff()
    {

        $validated = $this->validate([
            'contact_first_name' => ['required'],
            'contact_last_name' => ['required'],
            'client_city' => ['required'],
            'client_postal_code' => ['required'],
            'contact_email' => ['required', 'email'],
        ]);

        $this->check_rff = false;

        $this->contact->first_name = $validated['contact_first_name'];
        $this->contact->last_name = $validated['contact_last_name'];
        $this->contact->email = $validated['contact_email'];
        $this->contact->client->postal_code = $validated['client_postal_code'];
        $this->contact->client->city = $validated['client_city'];

        $this->contact->pushQuietly();

        $this->refreshComponent();

        return $this;
    }

    protected function refreshComponent()
    {
        $this->dispatch('$refresh');
    }

    /**
     * Middle method between selecting payment method &
     * submitting the from to the backend.
     *
     * @param $company_gateway_id
     * @param $gateway_type_id
     */
    public function handleMethodSelectingEvent($company_gateway_id, $gateway_type_id)
    {
        $this->payment_confirmed = true;

        $this->company_gateway_id = $company_gateway_id;
        $this->payment_method_id = $gateway_type_id;

        $this->handleBeforePaymentEvents();

        $this->dispatch('beforePaymentEventsCompleted');

    }

    /**
     * Method to handle events before payments.
     *
     * @return self
     */
    public function handleBeforePaymentEvents(): self
    {
        $eligibility_check = $this->subscription->service()->isEligible($this->contact);

        if (is_array($eligibility_check) && $eligibility_check['message'] != 'Success') {
            $this->is_eligible = false;
            $this->not_eligible_message = $eligibility_check['message'];

            return $this;
        }

        $data = [
            'client_id' => $this->contact->client->hashed_id,
            'date' => now()->format('Y-m-d'),
            'invitations' => [[
                'key' => '',
                'client_contact_id' => $this->contact->hashed_id,
            ]],
            'user_input_promo_code' => $this->coupon,
            'coupon' => empty($this->subscription->promo_code) ? '' : $this->coupon,
        ];

        $this->invoice = $this->subscription
            ->service()
            ->createInvoiceV2($this->bundle, $this->contact->client_id, $this->valid_coupon)
            ->service()
            ->markSent()
            ->fillDefaults()
            ->adjustInventory()
            ->save();

        $this->payable_amount = $this->invoice->partial > 0 ? \App\Utils\Number::formatValue($this->invoice->partial, $this->invoice->client->currency()) : \App\Utils\Number::formatValue($this->invoice->balance, $this->invoice->client->currency());
        $this->invoice_hashed_id = $this->invoice->hashed_id;

        Cache::put($this->hash, [
            'subscription_id' => $this->subscription->hashed_id,
            'email' => $this->email ?? $this->contact->email,
            'client_id' => $this->contact->client->hashed_id,
            'invoice_id' => $this->invoice->hashed_id,
            'context' => 'purchase',
            'campaign' => $this->campaign,
            'bundle' => $this->bundle,
        ], now()->addMinutes(60));

        return $this;
    }


    /**
     * Starts the trial
     *
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function handleTrial()
    {
        return $this->subscription->service()->startTrial([
            'email' => $this->email ?? $this->contact->email,
            'quantity' => $this->quantity,
            'contact_id' => $this->contact->hashed_id,
            'client_id' => $this->contact->client->hashed_id,
            'bundle' => $this->bundle,
        ]);
    }

    /**
     * When the subscription total comes to $0 we
     * pass back a $0 Invoice.
     *
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function handlePaymentNotRequired()
    {
        $eligibility_check = $this->subscription->service()->isEligible($this->contact);

        if (is_array($eligibility_check) && $eligibility_check['message'] != 'Success') {
            $this->is_eligible = false;
            $this->not_eligible_message = $eligibility_check['message'];
            return $this;
        }

        $invoice = $this->subscription
            ->service()
            ->createInvoiceV2($this->bundle, $this->contact->client_id, $this->valid_coupon)
            ->service()
            ->fillDefaults()
            ->adjustInventory()
            ->save();

        $invoice->number = null;

        $invoice->service()
                ->markPaid()
                ->save();

        return $this->subscription
                    ->service()
                    ->handleNoPaymentFlow($invoice, $this->bundle, $this->contact);
    }












    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function rules()
    {
        $rules = [
        ];

        return $rules;
    }

    public function attributes()
    {
        $attributes = [
        ];

        return $attributes;
    }

    public function store()
    {
    }

    /**
     * Create a blank client. Used for new customers purchasing.
     *
     * @return mixed
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     */
    protected function createBlankClient()
    {
        $company = $this->subscription->company;
        $user = $this->subscription->user;
        $user->setCompany($company);

        $client_repo = new ClientRepository(new ClientContactRepository());

        $data = [
            'name' => '',
            'contacts' => [
                ['email' => $this->email],
            ],
            'client_hash' => Str::random(40),
            'settings' => ClientSettings::defaults(),
        ];

        foreach ($this->request_data as $field => $value) {
            if (in_array($field, Client::$subscriptions_fillable)) {
                $data[$field] = $value;
            }

            if (in_array($field, ClientContact::$subscription_fillable)) {
                $data['contacts'][0][$field] = $value;
            }
        }

        if (array_key_exists('currency_id', $this->request_data)) {

            /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
            $currencies = app('currencies');

            $currency = $currencies->first(function ($item) {
                return $item->id == $this->request_data['currency_id'];
            });

            if ($currency) {
                $data['settings']->currency_id = $currency->id;
            }
        } elseif ($this->subscription->group_settings && property_exists($this->subscription->group_settings->settings, 'currency_id')) {

            /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
            $currencies = app('currencies');

            $currency = $currencies->first(function ($item) {
                return $item->id == $this->subscription->group_settings->settings->currency_id;
            });

            if ($currency) {
                $data['settings']->currency_id = $currency->id;
            }
        }

        if (array_key_exists('locale', $this->request_data)) {
            $request = $this->request_data;

            /** @var \Illuminate\Support\Collection<\App\Models\Language> */
            $languages = app('languages');

            $record = $languages->first(function ($item) use ($request) {
                return $item->locale == $request['locale'];
            });

            if ($record) {
                $data['settings']['language_id'] = (string)$record->id;
            }
        }

        $client = $client_repo->save($data, ClientFactory::create($company->id, $user->id));

        return $client->fresh()->contacts->first();
    }



    /**
     * Proxy method for starting the trial.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */

    public function render()
    {
        if (array_key_exists('email', $this->request_data)) {
            $this->email = $this->request_data['email'];
        }

        if ($this->contact instanceof ClientContact) {
            $this->getPaymentMethods();
        }

        return render('components.livewire.billing-portal-purchasev2');
    }
}
