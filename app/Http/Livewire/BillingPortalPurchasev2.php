<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Livewire;

use App\Factory\ClientFactory;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\ContactPasswordlessLogin;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\DataMapper\ClientSettings;
use Livewire\Component;

class BillingPortalPurchasev2 extends Component
{
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
     * Password model for user input.
     *
     * @var string
     */
    public $password;

    /**
     * Instance of subscription.
     *
     * @var Subscription
     */
    public $subscription;

    /**
     * Instance of client contact.
     *
     * @var null|ClientContact
     */
    public $contact;

    /**
     * Rules for validating the form.
     *
     * @var \string[][]
     */
    // protected $rules = [
    //     'email' => ['required', 'email'],
    //     'data' => ['required', 'array'],
    //     'data.*.recurring_qty' => ['required', 'between:100,1000'],
    //     'data.*.optional_recurring_qty' => ['required', 'between:100,1000'],
    //     'data.*.optional_qty' => ['required', 'between:100,1000'],
    // ];

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

    private $user_coupon;

    /**
     * List of steps that frontend form follows.
     *
     * @var array
     */
    public $steps = [
        'passed_email' => false,
        'existing_user' => false,
        'fetched_payment_methods' => false,
        'fetched_client' => false,
        'show_start_trial' => false,
        'passwordless_login_sent' => false,
        'started_payment' => false,
        'discount_applied' => false,
        'show_loading_bar' => false,
        'not_eligible' => null,
        'not_eligible_message' => null,
        'payment_required' => true,
    ];

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
    public $invoice;

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

    /**
     * First-hit request data (queries, locales...).
     *
     * @var array
     */
    public $request_data;

    /**
     * @var string
     */
    public $price;

    /**
     * Disabled state of passwordless login button.
     *
     * @var bool
     */
    public $passwordless_login_btn = false;

    /**
     * Instance of company.
     *
     * @var Company
     */
    public $company;

    /**
     * Campaign reference.
     *
     * @var string|null
     */
    public $campaign;

    public function mount()
    {
        MultiDB::setDb($this->company->db);

        $this->quantity = 1;

        $this->data = [];

        $this->price = $this->subscription->price;

        if (request()->query('coupon')) {
            $this->coupon = request()->query('coupon');
            $this->handleCoupon();
        }
        elseif(strlen($this->subscription->promo_code) == 0 && $this->subscription->promo_discount > 0){
            $this->price = $this->subscription->promo_price;
        }
    }

    public function updatingData()
    {
        nlog('updating');
        // nlog($this->data);
    }

    public function updatedData()
    {
        nlog('updated');
        nlog($this->data);
        $validatedData = $this->validate();
        nlog( $validatedData );
    }

    public function updated($propertyName)
    {
        nlog("validating {$propertyName}");
        $this->errors = $this->validateOnly($propertyName);

        nlog($this->errors);
        $validatedData = $this->validate();
        nlog( $validatedData );

    }

    public function rules()
    {
         $rules = [
            'email' => ['required', 'email'],
            'data' => ['required', 'array'],
            'data.*.recurring_qty' => ['required', 'between:100,1000'],
            'data.*.optional_recurring_qty' => ['required', 'between:100,1000'],
            'data.*.optional_qty' => ['required', 'between:100,1000'],
        ];

        return $rules;
    }

    /**
     * Handle user authentication
     *
     * @return $this|bool|void
     */
    public function authenticate()
    {
        $this->validate();

        $contact = ClientContact::where('email', $this->email)
            ->where('company_id', $this->subscription->company_id)
            ->first();

        if ($contact && $this->steps['existing_user'] === false) {
            return $this->steps['existing_user'] = true;
        }

        if ($contact && $this->steps['existing_user']) {
            $attempt = Auth::guard('contact')->attempt(['email' => $this->email, 'password' => $this->password, 'company_id' => $this->subscription->company_id]);

            return $attempt
                ? $this->getPaymentMethods($contact)
                : session()->flash('message', 'These credentials do not match our records.');
        }

        $this->steps['existing_user'] = false;

        $contact = $this->createBlankClient();

        if ($contact && $contact instanceof ClientContact) {
            $this->getPaymentMethods($contact);
        }
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

        if(array_key_exists('currency_id', $this->request_data)) {

            $currency = Cache::get('currencies')->filter(function ($item){
                return $item->id == $this->request_data['currency_id'];
            })->first();

            if($currency)
                $data['settings']->currency_id = $currency->id;

        }
        elseif($this->subscription->group_settings && property_exists($this->subscription->group_settings->settings, 'currency_id')) {

            $currency = Cache::get('currencies')->filter(function ($item){
                return $item->id == $this->subscription->group_settings->settings->currency_id;
            })->first();

            if($currency)
                $data['settings']->currency_id = $currency->id;

        }

        if (array_key_exists('locale', $this->request_data)) {
            $request = $this->request_data;

            $record = Cache::get('languages')->filter(function ($item) use ($request) {
                return $item->locale == $request['locale'];
            })->first();

            if ($record) {
                $data['settings']['language_id'] = (string)$record->id;
            }
        }

        $client = $client_repo->save($data, ClientFactory::create($company->id, $user->id));

        return $client->fresh()->contacts->first();
    }

    /**
     * Fetching payment methods from the client.
     *
     * @param ClientContact $contact
     * @return $this
     */
    protected function getPaymentMethods(ClientContact $contact): self
    {
        Auth::guard('contact')->loginUsingId($contact->id, true);

        $this->contact = $contact;

        if ($this->subscription->trial_enabled) {
            $this->heading_text = ctrans('texts.plan_trial');
            $this->steps['show_start_trial'] = true;

            return $this;
        }

        if ((int)$this->price == 0)
            $this->steps['payment_required'] = false;
        else
            $this->steps['fetched_payment_methods'] = true;

        $this->methods = $contact->client->service()->getPaymentMethods($this->price);

        $this->heading_text = ctrans('texts.payment_methods');

        return $this;
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
        $this->company_gateway_id = $company_gateway_id;
        $this->payment_method_id = $gateway_type_id;

        $this->handleBeforePaymentEvents();
    }

    /**
     * Method to handle events before payments.
     *
     * @return void
     */
    public function handleBeforePaymentEvents()
    {
        $this->steps['started_payment'] = true;
        $this->steps['show_loading_bar'] = true;

        $data = [
            'client_id' => $this->contact->client->id,
            'date' => now()->format('Y-m-d'),
            'invitations' => [[
                'key' => '',
                'client_contact_id' => $this->contact->hashed_id,
            ]],
            'user_input_promo_code' => $this->coupon,
            'coupon' => empty($this->subscription->promo_code) ? '' : $this->coupon,
            'quantity' => $this->quantity,
        ];

        $is_eligible = $this->subscription->service()->isEligible($this->contact);

        if (is_array($is_eligible) && $is_eligible['message'] != 'Success') {
            $this->steps['not_eligible'] = true;
            $this->steps['not_eligible_message'] = $is_eligible['message'];
            $this->steps['show_loading_bar'] = false;

            return;
        }

        $this->invoice = $this->subscription
            ->service()
            ->createInvoice($data, $this->quantity)
            ->service()
            ->markSent()
            ->fillDefaults()
            ->adjustInventory()
            ->save();

        Cache::put($this->hash, [
            'subscription_id' => $this->subscription->id,
            'email' => $this->email ?? $this->contact->email,
            'client_id' => $this->contact->client->id,
            'invoice_id' => $this->invoice->id,
            'context' => 'purchase',
            'campaign' => $this->campaign,
        ], now()->addMinutes(60));

        $this->emit('beforePaymentEventsCompleted');
    }

    /**
     * Proxy method for starting the trial.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function handleTrial()
    {
        return $this->subscription->service()->startTrial([
            'email' => $this->email ?? $this->contact->email,
            'quantity' => $this->quantity,
            'contact_id' => $this->contact->id,
            'client_id' => $this->contact->client->id,
        ]);
    }

    public function handlePaymentNotRequired()
    {

        $is_eligible = $this->subscription->service()->isEligible($this->contact);
        
        if ($is_eligible['status_code'] != 200) {
            $this->steps['not_eligible'] = true;
            $this->steps['not_eligible_message'] = $is_eligible['message'];
            $this->steps['show_loading_bar'] = false;

            return;
        }


        return $this->subscription->service()->handleNoPaymentRequired([
            'email' => $this->email ?? $this->contact->email,
            'quantity' => $this->quantity,
            'contact_id' => $this->contact->id,
            'client_id' => $this->contact->client->id,
            'coupon' => $this->coupon,
        ]);
    }

    /**
     * Update quantity property.
     *
     * @param string $option
     * @return int
     */
    public function updateQuantity(string $option): int
    {
        $this->handleCoupon();

        if ($this->quantity == 1 && $option == 'decrement') {
            $this->price = $this->price * 1;
            return $this->quantity;
        }

        if ($this->quantity > $this->subscription->max_seats_limit && $option == 'increment') {
            $this->price = $this->price * $this->subscription->max_seats_limit;
            return $this->quantity;
        }

        if ($option == 'increment') {
            $this->quantity++;
            $this->price = $this->price * $this->quantity;
            return $this->quantity;
        }

            $this->quantity--;
            $this->price = $this->price * $this->quantity;

            return $this->quantity;
    }

    public function handleCoupon()
    {

        if($this->steps['discount_applied']){
            $this->price = $this->subscription->promo_price;
            return;
        }

        if ($this->coupon == $this->subscription->promo_code) {
            $this->price = $this->subscription->promo_price;
            $this->quantity = 1;
            $this->steps['discount_applied'] = true;
        }
        else
            $this->price = $this->subscription->price;
    }

    public function passwordlessLogin()
    {
        $this->passwordless_login_btn = true;

        $contact = ClientContact::query()
            ->where('email', $this->email)
            ->where('company_id', $this->subscription->company_id)
            ->first();

        $mailer = new NinjaMailerObject();
        $mailer->mailable = new ContactPasswordlessLogin($this->email, $this->subscription->company, (string)route('client.subscription.purchase', $this->subscription->hashed_id) . '?coupon=' . $this->coupon);
        $mailer->company = $this->subscription->company;
        $mailer->settings = $this->subscription->company->settings;
        $mailer->to_user = $contact;

        NinjaMailerJob::dispatch($mailer);

        $this->steps['passwordless_login_sent'] = true;
        $this->passwordless_login_btn = false;
    }

    public function render()
    {
        if (array_key_exists('email', $this->request_data)) {
            $this->email = $this->request_data['email'];
        }

        if ($this->contact instanceof ClientContact) {
            $this->getPaymentMethods($this->contact);
        }

        return render('components.livewire.billing-portal-purchasev2');
    }

    public function changeData()
    {

        nlog($this->data);

    }
}
