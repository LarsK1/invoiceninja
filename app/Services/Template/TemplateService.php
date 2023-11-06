<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Template;

use App\Models\Quote;
use App\Utils\Number;
use Twig\Error\Error;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Utils\HtmlEngine;
use League\Fractal\Manager;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Error\RuntimeError;
use App\Models\PurchaseOrder;
use App\Utils\VendorHtmlEngine;
use Twig\Sandbox\SecurityError;
use App\Models\RecurringInvoice;
use App\Utils\PaymentHtmlEngine;
use App\Utils\Traits\MakesDates;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\Traits\Pdf\PdfMaker;
use Twig\Extra\Intl\IntlExtension;
use App\Transformers\TaskTransformer;
use App\Transformers\QuoteTransformer;
use App\Transformers\ProjectTransformer;
use League\CommonMark\CommonMarkConverter;
use App\Transformers\PurchaseOrderTransformer;
use League\Fractal\Serializer\ArraySerializer;

class TemplateService
{
    use MakesDates;
    use PdfMaker;

    private \DomDocument $document;

    public \Twig\Environment $twig;

    private string $compiled_html = '';

    private array $data = [];

    private array $variables = [];

    public ?Company $company;

    private ?Client $client;

    private ?Vendor $vendor;

    private Invoice | Quote | Credit | PurchaseOrder | RecurringInvoice $entity;

    private Payment $payment;

    private CommonMarkConverter $commonmark;

    public function __construct(public ?Design $template = null)
    {
        $this->template = $template;
        $this->init();
    }

    /**
     * Boot Dom Document
     *
     * @return self
     */
    private function init(): self
    {

        $this->commonmark = new CommonMarkConverter([
            'allow_unsafe_links' => false,
        ]);

        $this->document = new \DOMDocument();
        $this->document->validateOnParse = true;

        $loader = new \Twig\Loader\FilesystemLoader(storage_path());
        $this->twig = new \Twig\Environment($loader, [
                'debug' => true,
        ]);
        $string_extension = new \Twig\Extension\StringLoaderExtension();
        $this->twig->addExtension($string_extension);
        $this->twig->addExtension(new IntlExtension());
        $this->twig->addExtension(new \Twig\Extension\DebugExtension());

        $function = new \Twig\TwigFunction('img', function ($string, $style = '') {
            return '<img src="' . $string . '" style="' . $style . '"></img>';
        });
        $this->twig->addFunction($function);

        $filter = new \Twig\TwigFilter('sum', function (array $array, string $column) {
            return array_sum(array_column($array, $column));
        });

        $this->twig->addFilter($filter);

        return $this;
    }

    /**
     * Iterate through all of the
     * ninja nodes, and field stacks
     *
     * @param array $data - the payload to be passed into the template
     * @return self
     */
    public function build(array $data): self
    {
        $this->compose()
             ->processData($data)
             ->parseGlobalStacks()
             ->parseNinjaBlocks()
             ->processVariables($data)
             ->parseVariables();

        return $this;
    }

    /**
     * Initialized a set of HTMLEngine variables
     *
     * @param  array | Collection $data
     * @return self
     */
    private function processVariables($data): self
    {
        $this->variables = $this->resolveHtmlEngine($data);

        return $this;
    }

    /**
     * Returns a Mock Template
     *
     * @return self
     */
    public function mock(): self
    {
        $tm = new TemplateMock($this->company);
        $tm->init();

        $this->entity = $this->company->invoices()->first();

        $this->data = $tm->engines;
        $this->variables = $tm->variables[0];

        $this->parseNinjaBlocks()
             ->parseGlobalStacks()
             ->parseVariables();

        return $this;
    }

    /**
     * Returns the HTML as string
     *
     * @return string
     */
    public function getHtml(): string
    {
        return $this->compiled_html;
    }

    /**
     * Returns the PDF string
     *
     * @return string
     */
    public function getPdf(): string
    {

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($this->compiled_html);
        } else {
            $pdf = $this->makePdf(null, null, $this->compiled_html);
        }

        return $pdf;

    }

    /**
     * Get the parsed data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Process data variables
     *
     * @param  array | Collection $data
     * @return self
     */
    public function processData($data): self
    {

        $this->data = $this->preProcessDataBlocks($data);

        return $this;
    }

    /**
     * Parses all Ninja tags in the document
     *
     * @return self
     */
    private function parseNinjaBlocks(): self
    {
        $replacements = [];

        $contents = $this->document->getElementsByTagName('ninja');

        foreach ($contents as $content) {

            $template = $content->ownerDocument->saveHTML($content);

            try {
                $template = $this->twig->createTemplate(html_entity_decode($template));
            } catch(SyntaxError $e) {
                nlog($e->getMessage());
                throw ($e);
            } catch(Error $e) {
                nlog("error = " . $e->getMessage());
                throw ($e);
            } catch(RuntimeError $e) {
                nlog("runtime = " . $e->getMessage());
                throw ($e);
            } catch(LoaderError $e) {
                nlog("loader = " . $e->getMessage());
                throw ($e);
            } catch(SecurityError $e) {
                nlog("security = " . $e->getMessage());
                throw ($e);
            }

            $template = $template->render($this->data);

            $f = $this->document->createDocumentFragment();
            $f->appendXML(html_entity_decode($template));

            $replacements[] = $f;

        }

        foreach($contents as $key => $content) {
            $content->parentNode->replaceChild($replacements[$key], $content);
        }

        $this->save();

        return $this;

    }

    /**
     * Parses all variables in the document
     *
     * @return self
     */
    public function parseVariables(): self
    {

        $html = $this->getHtml();

        foreach($this->variables as $key => $variable) {
            if(isset($variable['labels']) && isset($variable['values'])) {
                $html = strtr($html, $variable['labels']);
                $html = strtr($html, $variable['values']);
            }
        }

        @$this->document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $this->save();

        return $this;
    }

    /**
     * Saves the document and updates the compiled string.
     *
     * @return self
     */
    private function save(): self
    {
        $this->compiled_html = str_replace('%24', '$', $this->document->saveHTML());

        return $this;
    }

    /**
     * compose
     *
     * @return self
     */
    private function compose(): self
    {
        if(!$this->template) {
            return $this;
        }

        $html = '';
        $html .= $this->template->design->includes;
        $html .= $this->template->design->header;
        $html .= $this->template->design->body;
        $html .= $this->template->design->footer;

        @$this->document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        return $this;

    }

    /**
     * Inject the template components
     * manually
     *
     * @return self
     */
    public function setTemplate(array $partials): self
    {

        $html = '';
        $html .= $partials['design']['includes'];
        $html .= $partials['design']['header'];
        $html .= $partials['design']['body'];
        $html .= $partials['design']['footer'];

        @$this->document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        return $this;

    }

    /**
     * Resolves the labels and values needed to replace the string
     * holders in the template.
     *
     * @param  array $data
     * @return array
     */
    private function resolveHtmlEngine(array $data): array
    {
        return collect($data)->map(function ($value, $key) {

            $processed = [];

            if(in_array($key, ['tasks','projects','aging']) || !$value->first()) {
                return $processed;
            }

            match ($key) {
                'variables' => $processed = $value->first() ?? [],
                'invoices' => $processed = (new HtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues() ?? [],
                'quotes' => $processed = (new HtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues() ?? [],
                'credits' => $processed = (new HtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues() ?? [],
                'payments' => $processed = (new PaymentHtmlEngine($value->first(), $value->first()->client->contacts()->first()))->generateLabelsAndValues() ?? [],
                'tasks' => $processed = [],
                'projects' => $processed = [],
                'purchase_orders' => (new VendorHtmlEngine($value->first()->invitations()->first()))->generateLabelsAndValues() ?? [],
                'aging' => $processed = [],
                default => $processed = [],
            };

            return $processed;

        })->toArray();

    }

    /**
     * Pre Processes the Data Blocks into
     * Twig consumables
     *
     * @param  array | Collection $data
     * @return array
     */
    private function preProcessDataBlocks($data): array
    {
        return collect($data)->map(function ($value, $key) {

            $processed = [];

            match ($key) {
                'invoices' => $processed = $this->processInvoices($value),
                'quotes' => $processed = $this->processQuotes($value),
                'credits' => $processed = $this->processCredits($value),
                'payments' => $processed = $this->processPayments($value),
                'tasks' => $processed = $this->processTasks($value),
                'projects' => $processed = $this->processProjects($value),
                'purchase_orders' => $processed = $this->processPurchaseOrders($value),
                'aging' => $processed = $value,
                default => $processed = [],
            };

            return $processed;

        })->toArray();
    }

    /**
     * Process Invoices into consumable form for Twig templates
     *
     * @param  array | Collection $invoices
     * @return array
     */
    public function processInvoices($invoices): array
    {
        $invoices = collect($invoices)
                ->map(function ($invoice) {

                    $payments = [];
                    $this->entity = $invoice;

                    if($invoice->payments ?? false) {
                        $payments = $invoice->payments->map(function ($payment) {
                            return $this->transformPayment($payment);
                        })->toArray();
                    }

                    return [
                        'amount' => Number::formatMoney($invoice->amount, $invoice->client),
                        'balance' => Number::formatMoney($invoice->balance, $invoice->client),
                        'balance_raw' => $invoice->balance,
                        'number' => $invoice->number ?: '',
                        'discount' => $invoice->discount,
                        'po_number' => $invoice->po_number ?: '',
                        'date' => $this->translateDate($invoice->date, $invoice->client->date_format(), $invoice->client->locale()),
                        'last_sent_date' => $this->translateDate($invoice->last_sent_date, $invoice->client->date_format(), $invoice->client->locale()),
                        'next_send_date' => $this->translateDate($invoice->next_send_date, $invoice->client->date_format(), $invoice->client->locale()),
                        'due_date' => $this->translateDate($invoice->due_date, $invoice->client->date_format(), $invoice->client->locale()),
                        'terms' => $invoice->terms ?: '',
                        'public_notes' => $invoice->public_notes ?: '',
                        'private_notes' => $invoice->private_notes ?: '',
                        'uses_inclusive_taxes' => (bool) $invoice->uses_inclusive_taxes,
                        'tax_name1' => $invoice->tax_name1 ?? '',
                        'tax_rate1' => (float) $invoice->tax_rate1,
                        'tax_name2' => $invoice->tax_name2 ?? '',
                        'tax_rate2' => (float) $invoice->tax_rate2,
                        'tax_name3' => $invoice->tax_name3 ?? '',
                        'tax_rate3' => (float) $invoice->tax_rate3,
                        'total_taxes' => Number::formatMoney($invoice->total_taxes, $invoice->client),
                        'total_taxes_raw' => $invoice->total_taxes,
                        'is_amount_discount' => (bool) $invoice->is_amount_discount ?? false,
                        'footer' => $invoice->footer ?? '',
                        'partial' => $invoice->partial ?? 0,
                        'partial_due_date' => $this->translateDate($invoice->partial_due_date, $invoice->client->date_format(), $invoice->client->locale()),
                        'custom_value1' => (string) $invoice->custom_value1 ?: '',
                        'custom_value2' => (string) $invoice->custom_value2 ?: '',
                        'custom_value3' => (string) $invoice->custom_value3 ?: '',
                        'custom_value4' => (string) $invoice->custom_value4 ?: '',
                        'custom_surcharge1' => (float) $invoice->custom_surcharge1,
                        'custom_surcharge2' => (float) $invoice->custom_surcharge2,
                        'custom_surcharge3' => (float) $invoice->custom_surcharge3,
                        'custom_surcharge4' => (float) $invoice->custom_surcharge4,
                        'exchange_rate' => (float) $invoice->exchange_rate,
                        'custom_surcharge_tax1' => (bool) $invoice->custom_surcharge_tax1,
                        'custom_surcharge_tax2' => (bool) $invoice->custom_surcharge_tax2,
                        'custom_surcharge_tax3' => (bool) $invoice->custom_surcharge_tax3,
                        'custom_surcharge_tax4' => (bool) $invoice->custom_surcharge_tax4,
                        'line_items' => $invoice->line_items ? $this->padLineItems($invoice->line_items, $invoice->client) : (array) [],
                        'reminder1_sent' => $this->translateDate($invoice->reminder1_sent, $invoice->client->date_format(), $invoice->client->locale()),
                        'reminder2_sent' => $this->translateDate($invoice->reminder2_sent, $invoice->client->date_format(), $invoice->client->locale()),
                        'reminder3_sent' => $this->translateDate($invoice->reminder3_sent, $invoice->client->date_format(), $invoice->client->locale()),
                        'reminder_last_sent' => $this->translateDate($invoice->reminder_last_sent, $invoice->client->date_format(), $invoice->client->locale()),
                        'paid_to_date' => Number::formatMoney($invoice->paid_to_date, $invoice->client),
                        'auto_bill_enabled' => (bool) $invoice->auto_bill_enabled,
                        'client' => [
                            'name' => $invoice->client->present()->name(),
                            'balance' => $invoice->client->balance,
                            'payment_balance' => $invoice->client->payment_balance,
                            'credit_balance' => $invoice->client->credit_balance,
                        ],
                        'payments' => $payments,
                        'total_tax_map' => $invoice->calc()->getTotalTaxMap(),
                        'line_tax_map' => $invoice->calc()->getTaxMap(),
                    ];

                });

        return $invoices->toArray();

    }

    /**
     * Pads Line Items with raw and formatted content
     *
     * @param  array $items
     * @param  mixed $client
     * @return array
     */
    public function padLineItems(array $items, Client $client): array
    {
        return collect($items)->map(function ($item) use ($client) {

            $item->cost_raw = $item->cost ?? 0;
            $item->discount_raw = $item->discount ?? 0;
            $item->line_total_raw = $item->line_total ?? 0;
            $item->gross_line_total_raw = $item->gross_line_total ?? 0;
            $item->tax_amount_raw = $item->tax_amount ?? 0;
            $item->product_cost_raw = $item->product_cost ?? 0;

            $item->cost = Number::formatMoney($item->cost_raw, $client);

            if($item->is_amount_discount) {
                $item->discount = Number::formatMoney($item->discount_raw, $client);
            }

            $item->line_total = Number::formatMoney($item->line_total_raw, $client);
            $item->gross_line_total = Number::formatMoney($item->gross_line_total_raw, $client);
            $item->tax_amount = Number::formatMoney($item->tax_amount_raw, $client);
            $item->product_cost = Number::formatMoney($item->product_cost_raw, $client);

            return $item;

        })->toArray();
    }

    /**
     * Transforms a Payment into consumable for twig
     *
     * @param  Payment $payment
     * @return array
     */
    private function transformPayment(Payment $payment): array
    {

        $data = [];

        $this->payment = $payment;

        $credits = $payment->credits->map(function ($credit) use ($payment) {
            return [
                'credit' => $credit->number,
                'amount_raw' => $credit->pivot->amount,
                'refunded_raw' => $credit->pivot->refunded,
                'net_raw' => $credit->pivot->amount - $credit->pivot->refunded,
                'amount' => Number::formatMoney($credit->pivot->amount, $payment->client),
                'refunded' => Number::formatMoney($credit->pivot->refunded, $payment->client),
                'net' => Number::formatMoney($credit->pivot->amount - $credit->pivot->refunded, $payment->client),
                'is_credit' => true,
                'date' => $this->translateDate($credit->date, $payment->client->date_format(), $payment->client->locale()),
                'created_at' => $this->translateDate($credit->pivot->created_at, $payment->client->date_format(), $payment->client->locale()),
                'updated_at' => $this->translateDate($credit->pivot->updated_at, $payment->client->date_format(), $payment->client->locale()),
                'timestamp' => $credit->pivot->created_at->timestamp,
            ];
        });

        $pivot = $payment->invoices->map(function ($invoice) use ($payment) {
            return [
                'invoice' => $invoice->number,
                'amount_raw' => $invoice->pivot->amount,
                'refunded_raw' => $invoice->pivot->refunded,
                'net_raw' => $invoice->pivot->amount - $invoice->pivot->refunded,
                'amount' => Number::formatMoney($invoice->pivot->amount, $payment->client),
                'refunded' => Number::formatMoney($invoice->pivot->refunded, $payment->client),
                'net' => Number::formatMoney($invoice->pivot->amount - $invoice->pivot->refunded, $payment->client),
                'is_credit' => false,
                'date' => $this->translateDate($invoice->date, $payment->client->date_format(), $payment->client->locale()),
                'created_at' => $this->translateDate($invoice->pivot->created_at, $payment->client->date_format(), $payment->client->locale()),
                'updated_at' => $this->translateDate($invoice->pivot->updated_at, $payment->client->date_format(), $payment->client->locale()),
                'timestamp' => $invoice->pivot->created_at->timestamp,
            ];
        })->merge($credits)->sortBy('timestamp')->toArray();

        return [
            'status' => $payment->stringStatus($payment->status_id),
            'badge' => $payment->badgeForStatus($payment->status_id),
            'amount' => Number::formatMoney($payment->amount, $payment->client),
            'applied' => Number::formatMoney($payment->applied, $payment->client),
            'balance' => Number::formatMoney(($payment->amount - $payment->refunded - $payment->applied), $payment->client),
            'refunded' => Number::formatMoney($payment->refunded, $payment->client),
            'amount_raw' => $payment->amount,
            'applied_raw' => $payment->applied,
            'refunded_raw' => $payment->refunded,
            'balance_raw' => ($payment->amount - $payment->refunded - $payment->applied),
            'date' => $this->translateDate($payment->date, $payment->client->date_format(), $payment->client->locale()),
            'method' => $payment->translatedType(),
            'currency' => $payment->currency->code,
            'exchange_rate' => $payment->exchange_rate,
            'transaction_reference' => $payment->transaction_reference,
            'is_manual' => $payment->is_manual,
            'number' => $payment->number,
            'custom_value1' => $payment->custom_value1 ?? '',
            'custom_value2' => $payment->custom_value2 ?? '',
            'custom_value3' => $payment->custom_value3 ?? '',
            'custom_value4' => $payment->custom_value4 ?? '',
            'created_at' => $this->translateDate($payment->created_at, $payment->client->date_format(), $payment->client->locale()),
            'updated_at' => $this->translateDate($payment->updated_at, $payment->client->date_format(), $payment->client->locale()),
            'client' => [
                'name' => $payment->client->present()->name(),
                'balance' => $payment->client->balance,
                'payment_balance' => $payment->client->payment_balance,
                'credit_balance' => $payment->client->credit_balance,
            ],
            'paymentables' => $pivot,
            'refund_activity' => $this->getPaymentRefundActivity($payment),
        ];

        return $data;

    }

    /**
     *  [
      "id" => 12,
      "date" => "2023-10-08",
      "invoices" => [
        [
          "amount" => 1,
          "invoice_id" => 23,
          "id" => null,
        ],
      ],
      "q" => "/api/v1/payments/refund",
      "email_receipt" => "true",
      "gateway_refund" => false,
      "send_email" => false,
    ],
     *
     * @param Payment $payment
     * @return array
     */
    private function getPaymentRefundActivity(Payment $payment): array
    {

        return collect($payment->refund_meta ?? [])
        ->map(function ($refund) use ($payment) {

            $date = \Carbon\Carbon::parse($refund['date'])->addSeconds($payment->client->timezone_offset());
            $date = $this->translateDate($date, $payment->client->date_format(), $payment->client->locale());
            $entity = ctrans('texts.invoice');

            $map = [];

            foreach($refund['invoices'] as $refunded_invoice) {
                $invoice = Invoice::withTrashed()->find($refunded_invoice['invoice_id']);
                $amount = Number::formatMoney($refunded_invoice['amount'], $payment->client);
                $notes = ctrans('texts.status_partially_refunded_amount', ['amount' => $amount]);

                array_push($map, "{$date} {$entity} #{$invoice->number} {$notes}\n");

            }

            return $map;

        })->flatten()->toArray();

    }

    /**
     * @todo refactor
     *
     * @param  mixed $quotes
     * @return array
     */
    public function processQuotes($quotes): array
    {
        $it = new QuoteTransformer();
        $it->setDefaultIncludes(['client']);
        $manager = new Manager();
        $manager->parseIncludes(['client']);
        $resource = new \League\Fractal\Resource\Collection($quotes, $it, null);
        $resources = $manager->createData($resource)->toArray();

        foreach($resources['data'] as $key => $resource) {

            $resources['data'][$key]['client'] = $resource['client']['data'] ?? [];
            $resources['data'][$key]['client']['contacts'] = $resource['client']['data']['contacts']['data'] ?? [];

        }

        return $resources['data'];

    }

    /**
     * Pushes credits through the appropriate transformer
     * and builds any required relationships
     *
     * @param  array | Collection $credits
     * @return array
     */
    public function processCredits($credits): array
    {
        $credits = collect($credits)
                ->map(function ($credit) {

                    $this->entity = $credit;

                    return [
                        'amount' => Number::formatMoney($credit->amount, $credit->client),
                        'balance' => Number::formatMoney($credit->balance, $credit->client),
                        'balance_raw' => $credit->balance,
                        'number' => $credit->number ?: '',
                        'discount' => $credit->discount,
                        'po_number' => $credit->po_number ?: '',
                        'date' => $this->translateDate($credit->date, $credit->client->date_format(), $credit->client->locale()),
                        'last_sent_date' => $this->translateDate($credit->last_sent_date, $credit->client->date_format(), $credit->client->locale()),
                        'next_send_date' => $this->translateDate($credit->next_send_date, $credit->client->date_format(), $credit->client->locale()),
                        'due_date' => $this->translateDate($credit->due_date, $credit->client->date_format(), $credit->client->locale()),
                        'terms' => $credit->terms ?: '',
                        'public_notes' => $credit->public_notes ?: '',
                        'private_notes' => $credit->private_notes ?: '',
                        'uses_inclusive_taxes' => (bool) $credit->uses_inclusive_taxes,
                        'tax_name1' => $credit->tax_name1 ?? '',
                        'tax_rate1' => (float) $credit->tax_rate1,
                        'tax_name2' => $credit->tax_name2 ?? '',
                        'tax_rate2' => (float) $credit->tax_rate2,
                        'tax_name3' => $credit->tax_name3 ?? '',
                        'tax_rate3' => (float) $credit->tax_rate3,
                        'total_taxes' => Number::formatMoney($credit->total_taxes, $credit->client),
                        'total_taxes_raw' => $credit->total_taxes,
                        'is_amount_discount' => (bool) $credit->is_amount_discount ?? false,
                        'footer' => $credit->footer ?? '',
                        'partial' => $credit->partial ?? 0,
                        'partial_due_date' => $this->translateDate($credit->partial_due_date, $credit->client->date_format(), $credit->client->locale()),
                        'custom_value1' => (string) $credit->custom_value1 ?: '',
                        'custom_value2' => (string) $credit->custom_value2 ?: '',
                        'custom_value3' => (string) $credit->custom_value3 ?: '',
                        'custom_value4' => (string) $credit->custom_value4 ?: '',
                        'custom_surcharge1' => (float) $credit->custom_surcharge1,
                        'custom_surcharge2' => (float) $credit->custom_surcharge2,
                        'custom_surcharge3' => (float) $credit->custom_surcharge3,
                        'custom_surcharge4' => (float) $credit->custom_surcharge4,
                        'exchange_rate' => (float) $credit->exchange_rate,
                        'custom_surcharge_tax1' => (bool) $credit->custom_surcharge_tax1,
                        'custom_surcharge_tax2' => (bool) $credit->custom_surcharge_tax2,
                        'custom_surcharge_tax3' => (bool) $credit->custom_surcharge_tax3,
                        'custom_surcharge_tax4' => (bool) $credit->custom_surcharge_tax4,
                        'line_items' => $credit->line_items ? $this->padLineItems($credit->line_items, $credit->client) : (array) [],
                        'reminder1_sent' => $this->translateDate($credit->reminder1_sent, $credit->client->date_format(), $credit->client->locale()),
                        'reminder2_sent' => $this->translateDate($credit->reminder2_sent, $credit->client->date_format(), $credit->client->locale()),
                        'reminder3_sent' => $this->translateDate($credit->reminder3_sent, $credit->client->date_format(), $credit->client->locale()),
                        'reminder_last_sent' => $this->translateDate($credit->reminder_last_sent, $credit->client->date_format(), $credit->client->locale()),
                        'paid_to_date' => Number::formatMoney($credit->paid_to_date, $credit->client),
                        'auto_bill_enabled' => (bool) $credit->auto_bill_enabled,
                        'client' => [
                            'name' => $credit->client->present()->name(),
                            'balance' => $credit->client->balance,
                            'payment_balance' => $credit->client->payment_balance,
                            'credit_balance' => $credit->client->credit_balance,
                        ],
                        'payments' => [],
                        'total_tax_map' => $credit->calc()->getTotalTaxMap(),
                        'line_tax_map' => $credit->calc()->getTaxMap(),
                    ];

                });

        return $credits->toArray();

    }

    /**
     * Pushes payments through the appropriate transformer
     *
     * @param  array | Collection $payments
     * @return array
     */
    public function processPayments($payments): array
    {

        $payments = collect($payments)->map(function ($payment) {
            return $this->transformPayment($payment);
        })->toArray();

        return $payments;

    }

    /**
     * @todo refactor
     *
     * @param  mixed $tasks
     * @return array
     */
    public function processTasks($tasks): array
    {
        $it = new TaskTransformer();
        $it->setDefaultIncludes(['client','project','invoice']);
        $manager = new Manager();
        $resource = new \League\Fractal\Resource\Collection($tasks, $it, null);
        $resources = $manager->createData($resource)->toArray();

        foreach($resources['data'] as $key => $resource) {

            $resources['data'][$key]['client'] = $resource['client']['data'] ?? [];
            $resources['data'][$key]['client']['contacts'] = $resource['client']['data']['contacts']['data'] ?? [];
            $resources['data'][$key]['project'] = $resource['project']['data'] ?? [];
            $resources['data'][$key]['invoice'] = $resource['invoice'] ?? [];

        }

        return $resources['data'];


    }

    /**
     * @todo refactor
     *
     * @param  mixed $projects
     * @return array
     */
    public function processProjects($projects): array
    {

        $it = new ProjectTransformer();
        $it->setDefaultIncludes(['client','tasks']);
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $resource = new \League\Fractal\Resource\Collection($projects, $it, Project::class);
        $i = $manager->createData($resource)->toArray();
        return $i[Project::class];

    }

    /**
     * @todo refactor
     *
     * @param  mixed $purchase_orders
     * @return array
     */
    public function processPurchaseOrders($purchase_orders): array
    {

        $it = new PurchaseOrderTransformer();
        $it->setDefaultIncludes(['vendor','expense']);
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $resource = new \League\Fractal\Resource\Collection($purchase_orders, $it, PurchaseOrder::class);
        $i = $manager->createData($resource)->toArray();
        return $i[PurchaseOrder::class];

    }

    /**
     * Set Company
     *
     * @param  mixed $company
     * @return self
     */
    public function setCompany(Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    /**
     * Get Company
     *
     * @return Company
     */
    public function getCompany(): Company
    {
        return $this->company;
    }

    /**
     * Setter that allows external variables to override the
     * resolved ones from this class
     *
     * @param  mixed $variables
     * @return self
     */
    public function overrideVariables($variables): self
    {
        $this->variables = $variables;

        return $this;
    }

    /**
     * Parses and finds any field stacks to inject into the DOM Document
     *
     * @return self
     */
    public function parseGlobalStacks(): self
    {
        $stacks = [
            'entity-details',
            'client-details',
            'vendor-details',
            'company-details',
            'company-address',
            'shipping-details',
        ];

        collect($stacks)->filter(function ($stack) {
            return $this->document->getElementById($stack) ?? false;
        })
        ->map(function ($stack){
            $node = $this->document->getElementById($stack);
                nlog(['stack' => $stack, 'labels' => $node->getAttribute('labels')]);
                return ['stack' => $stack, 'labels' => $node->getAttribute('labels')];
        })
        ->each(function ($stack) {
            $this->parseStack($stack);
        });

        return $this;

    }

    /**
     * Injects field stacks into Template
     *
     * @param  array $stack
     * @return self
     */
    private function parseStack(array $stack): self
    {

        match($stack['stack']) {
            'entity-details' => $this->entityDetails($stack['labels'] == 'true'),
            'client-details' => $this->clientDetails($stack['labels'] == 'true'),
            'vendor-details' => $this->vendorDetails($stack['labels'] == 'true'),
            'company-details' => $this->companyDetails($stack['labels'] == 'true'),
            'company-address' => $this->companyAddress($stack['labels'] == 'true'),
            'shipping-details' => $this->shippingDetails($stack['labels'] == 'true'),
        };

        $this->save();

        return $this;
    }

    /**
     * Inject the Company Details into the DOM Document
     *
     * @param  bool $include_labels
     * @return self
     */
    private function companyDetails(bool $include_labels): self
    {
        $var_set = $this->getVarSet();

        $company_details =
        collect($this->company->settings->pdf_variables->company_details)
            ->filter(function ($variable) use ($var_set) {
                return isset($var_set['values'][$variable]) && !empty($var_set['values'][$variable]);
            })
            ->when(!$include_labels, function ($collection) {
                return $collection->map(function ($variable) {
                    return ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_details-' . substr($variable, 1)]];
                });
            })->toArray();

        nlog($company_details);

        $company_details = $include_labels ? $this->labelledFieldStack($company_details, 'company_details-') : $company_details;

        nlog($company_details);

        $this->updateElementProperties('company-details', $company_details);

        return $this;
    }

    private function companyAddress(bool $include_labels = false): self
    {

        $var_set = $this->getVarSet();

        $company_address =
        collect($this->company->settings->pdf_variables->company_address)
            ->filter(function ($variable) use ($var_set) {
                return isset($var_set['values'][$variable]) && !empty($var_set['values'][$variable]);
            })
            ->when(!$include_labels, function ($collection) {
                return $collection->map(function ($variable) {
                    return ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_address-' . substr($variable, 1)]];
                });
            })->toArray();

        $company_address = $include_labels ? $this->labelledFieldStack($company_address, 'company_address-') : $company_address;

        $this->updateElementProperties('company-address', $company_address);

        return $this;
    }

    /**
     * Injects the Shipping Details into the DOM Document
     *
     * @param  bool $include_labels
     * @return self
     */
    private function shippingDetails(bool $include_labels = false): self
    {
        if(!$this->entity->client) {
            return $this;
        }

        $this->client = $this->entity->client;

        $shipping_address = [
            ['element' => 'p', 'content' => ctrans('texts.shipping_address'), 'properties' => ['data-ref' => 'shipping_address-label', 'style' => 'font-weight: bold; text-transform: uppercase']],
            ['element' => 'p', 'content' => $this->client->name, 'show_empty' => false, 'properties' => ['data-ref' => 'shipping_address-client.name']],
            ['element' => 'p', 'content' => $this->client->shipping_address1, 'show_empty' => false, 'properties' => ['data-ref' => 'shipping_address-client.shipping_address1']],
            ['element' => 'p', 'content' => $this->client->shipping_address2, 'show_empty' => false, 'properties' => ['data-ref' => 'shipping_address-client.shipping_address2']],
            ['element' => 'p', 'show_empty' => false, 'elements' => [
                ['element' => 'span', 'content' => "{$this->client->shipping_city} ", 'properties' => ['ref' => 'shipping_address-client.shipping_city']],
                ['element' => 'span', 'content' => "{$this->client->shipping_state} ", 'properties' => ['ref' => 'shipping_address-client.shipping_state']],
                ['element' => 'span', 'content' => "{$this->client->shipping_postal_code} ", 'properties' => ['ref' => 'shipping_address-client.shipping_postal_code']],
            ]],
            ['element' => 'p', 'content' => optional($this->client->shipping_country)->name, 'show_empty' => false],
        ];

        $shipping_address =
        collect($shipping_address)->filter(function ($address) {
            return isset($address['content']) && !empty($address['content']);
        })->toArray();

        $this->updateElementProperties('shipping-details', $shipping_address);

        return $this;
    }

    /**
     * Injects the Client Details into the DOM Document
     *
     * @param  bool $include_labels
     * @return self
     */
    private function clientDetails(bool $include_labels = false): self
    {
        $var_set = $this->getVarSet();

        $client_details =
        collect($this->company->settings->pdf_variables->client_details)
            ->filter(function ($variable) use ($var_set) {
                return isset($var_set['values'][$variable]) && !empty($var_set['values'][$variable]);
            })
            ->when(!$include_labels, function ($collection) {
                return $collection->map(function ($variable) {
                    return ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'client_details-' . substr($variable, 1)]];
                });
            })->toArray();

        $client_details = $include_labels ? $this->labelledFieldStack($client_details, 'client_details-') : $client_details;

        $this->updateElementProperties('client-details', $client_details);

        return $this;
    }

    /**
     * Resolves the entity.
     *
     * Only required for resolving the entity-details stack
     *
     * @return string
     */
    private function resolveEntity(): string
    {
        $entity_string = '';

        match($this->entity) {
            ($this->entity instanceof Invoice) => $entity_string = 'invoice',
            ($this->entity instanceof Quote)  => $entity_string = 'quote',
            ($this->entity instanceof Credit) => $entity_string = 'credit',
            ($this->entity instanceof RecurringInvoice) => $entity_string = 'invoice',
            ($this->entity instanceof PurchaseOrder) => $entity_string = 'purchase_order',
            default => $entity_string = 'invoice',
        };

        return $entity_string;

    }

    /**
     * Returns the variable array by first key, if it exists
     *
     * @return array
     */
    private function getVarSet(): array
    {
        return array_key_exists(array_key_first($this->variables), $this->variables) ? $this->variables[array_key_first($this->variables)] : $this->variables;
    }

    /**
     * Injects the entity details to the DOM document
     *
     * @return self
     */
    private function entityDetails(): self
    {
        $entity_string = $this->resolveEntity();
        $entity_string_prop = "{$entity_string}_details";
        $var_set = $this->getVarSet();

        $entity_details =
        collect($this->company->settings->pdf_variables->{$entity_string_prop})
            ->filter(function ($variable) use ($var_set) {
                return isset($var_set['values'][$variable]) && !empty($var_set['values'][$variable]);
            })->toArray();

        $this->updateElementProperties("entity-details", $this->labelledFieldStack($entity_details, 'entity_details-'));

        return $this;
    }

    /**
     * Generates the field stacks with labels
     *
     * @param  array $variables
     * @return array
     */
    private function labelledFieldStack(array $variables, string $data_ref): array
    {

        $elements = [];

        foreach ($variables as $variable) {
            $_variable = explode('.', $variable)[1];
            $_customs = ['custom1', 'custom2', 'custom3', 'custom4'];

            $var = str_replace("custom", "custom_value", $_variable);

            $hidden_prop = ($data_ref == 'entity_details-') ? $this->entityVariableCheck($variable) : false;
            
            if (in_array($_variable, $_customs) && !empty($this->entity->{$var})) {
                $elements[] = ['element' => 'tr', 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => $data_ref . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => $data_ref . substr($variable, 1)]],
                ]];
            } else {
                $elements[] = ['element' => 'tr', 'properties' => ['hidden' => $hidden_prop], 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => $data_ref . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => $data_ref . substr($variable, 1)]],
                ]];
            }
        }

        return $elements;

    }

    /**
     * Inject Vendor Details into DOM Document
     *
     * @param  bool $include_labels
     * @return self
     */
    private function vendorDetails(bool $include_labels = false): self
    {

        $var_set = $this->getVarSet();

        $vendor_details =
        collect($this->company->settings->pdf_variables->vendor_details)
            ->filter(function ($variable) use ($var_set) {
                return isset($var_set['values'][$variable]) && !empty($var_set['values'][$variable]);
            })->when(!$include_labels, function ($collection) {
                return $collection->map(function ($variable) {
                    return ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'vendor_details-' . substr($variable, 1)]];
                });
            })->toArray();

        $vendor_details = $include_labels ? $this->labelledFieldStack($vendor_details, 'vendor_details-') : $vendor_details;

        $this->updateElementProperties('vendor-details', $vendor_details);

        return $this;
    }


    /**
     * Performs a variable check to ensure
     * the variable exists
     *
     * @param  string $variable
     * @return bool
     *
     */
    public function entityVariableCheck(string $variable): bool
    {
        // When it comes to invoice balance, we'll always show it.
        if ($variable == '$invoice.total') {
            return false;
        }

        // Some variables don't map 1:1 to table columns. This gives us support for such cases.
        $aliases = [
            '$quote.balance_due' => 'partial',
        ];

        try {
            $_variable = explode('.', $variable)[1];
        } catch (\Exception $e) {
            throw new \Exception('Company settings seems to be broken. Missing $this->service->config->entity.variable type.');
        }

        if (\in_array($variable, \array_keys($aliases))) {
            $_variable = $aliases[$variable];
        }

        if (is_null($this->entity->{$_variable}) || empty($this->entity->{$_variable})) {
            return true;
        }

        return false;
    }

    ////////////////////////////////////////
    // Dom Traversal
    ///////////////////////////////////////

    public function updateElementProperties(string $element_id, array $elements): self
    {
        $node = $this->document->getElementById($element_id);

        $this->createElementContent($node, $elements);

        return $this;
    }

    public function updateElementProperty($element, string $attribute, ?string $value)
    {

        if ($attribute == 'hidden' && ($value == false || $value == 'false')) {
            return $element;
        }

        $element->setAttribute($attribute, $value);

        if ($element->getAttribute($attribute) === $value) {
            return $element;
        }

        return $element;

    }

    public function createElementContent($element, $children): self
    {

        foreach ($children as $child) {
            $contains_html = false;

            //06-11-2023 for some reason this parses content as HTML
            // if ($child['element'] !== 'script') {
            //     if ($this->company->markdown_enabled && array_key_exists('content', $child)) {
            //         $child['content'] = str_replace('<br>', "\r", $child['content']);
            //         $child['content'] = $this->commonmark->convert($child['content'] ?? '');
            //     }
            // }

            if (isset($child['content'])) {
                if (isset($child['is_empty']) && $child['is_empty'] === true) {
                    continue;
                }

                $contains_html = preg_match('#(?<=<)\w+(?=[^<]*?>)#', $child['content'], $m) != 0;
            }

            if ($contains_html) {
                // If the element contains the HTML, we gonna display it as is. Backend is going to
                // encode it for us, preventing any errors on the processing stage.
                // Later, we decode this using Javascript so it looks like it's normal HTML being injected.
                // To get all elements that need frontend decoding, we use 'data-state' property.

                $_child = $this->document->createElement($child['element'], '');
                $_child->setAttribute('data-state', 'encoded-html');
                $_child->nodeValue = htmlspecialchars($child['content']);
            } else {
                // .. in case string doesn't contain any HTML, we'll just return
                // raw $content.

                $_child = $this->document->createElement($child['element'], isset($child['content']) ? htmlspecialchars($child['content']) : '');
            }

            $element->appendChild($_child);

            if (isset($child['properties'])) {
                foreach ($child['properties'] as $property => $value) {
                    $this->updateElementProperty($_child, $property, $value);
                }
            }

            if (isset($child['elements'])) {
                $this->createElementContent($_child, $child['elements']);
            }

        }
        
        return $this;
    }



























}
