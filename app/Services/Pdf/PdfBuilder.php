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

namespace App\Services\Pdf;

use App\Models\Credit;
use App\Models\Quote;
use App\Utils\Helpers;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PdfBuilder
{
    use MakesDates;

    public PdfService $service;

    public array $sections = [];

    public function __construct(PdfService $service)
    {
        $this->service = $service;
    }

    public function build()
    {

        $this->getTemplate()
             ->buildSections();

    }

    private function getTemplate() :self
    {

        $document = new DOMDocument();

        $document->validateOnParse = true;
        @$document->loadHTML(mb_convert_encoding($this->service->config->designer->template, 'HTML-ENTITIES', 'UTF-8'));

        $this->document = $document;
        $this->xpath = new DOMXPath($document);

        return $this;
    }

    private function getProductSections(): self
    {
     
        $this->genericSectionBuilder()
             ->getClientDetails()
             ->getProductAndTaskTables()
             ->getProductEntityDetails()
             ->getProductTotals();

         return $this;

    }

    private function getDeliveryNoteSections(): self
    {

        $this->genericSectionBuilder()
             ->getProductTotals();

        $this->sections[] = [            
            'client-details' => [
                'id' => 'client-details',
                'elements' => $this->clientDeliveryDetails(),
            ],
            'delivery-note-table' => [
                'id' => 'delivery-note-table',
                'elements' => $this->deliveryNoteTable(),
            ],
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->deliveryNoteDetails(),
            ],
        ];

        return $this;

    }

    private function getStatementSections(): self
    {

        $this->genericSectionBuilder();

        $this->sections[] = [
            'statement-invoice-table' => [
                'id' => 'statement-invoice-table',
                'elements' => $this->statementInvoiceTable(),
            ],
            'statement-invoice-table-totals' => [
                'id' => 'statement-invoice-table-totals',
                'elements' => $this->statementInvoiceTableTotals(),
            ],
            'statement-payment-table' => [
                'id' => 'statement-payment-table',
                'elements' => $this->statementPaymentTable(),
            ],
            'statement-payment-table-totals' => [
                'id' => 'statement-payment-table-totals',
                'elements' => $this->statementPaymentTableTotals(),
            ],
            'statement-aging-table' => [
                'id' => 'statement-aging-table',
                'elements' => $this->statementAgingTable(),
            ],
            'table-totals' => [
                'id' => 'table-totals',
                'elements' => $this->statementTableTotals(),
            ],
        ];

        return $this;

    }


    public function statementInvoiceTableTotals(): array
    {

        $outstanding = $this->service->options['invoices']->sum('balance');

        return [
            ['element' => 'p', 'content' => '$outstanding_label: ' . Number::formatMoney($outstanding, $this->service->config->client)],
        ];
    }


    /**
     * Parent method for building payments table within statement.
     *
     * @return array
     */
    public function statementPaymentTable(): array
    {
        if (is_null($this->service->option['payments'])) {
            return [];
        }

        if (\array_key_exists('show_payments_table', $this->service->options) && $this->service->options['show_payments_table'] === false) {
            return [];
        }

        $tbody = [];

        //24-03-2022 show payments per invoice
        foreach ($this->service->options['invoices'] as $invoice) {
            foreach ($invoice->payments as $payment) {

                if($payment->is_deleted)
                    continue;

                $element = ['element' => 'tr', 'elements' => []];

                $element['elements'][] = ['element' => 'td', 'content' => $invoice->number];
                $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($payment->date, $this->service->config->client->date_format(), $this->service->config->client->locale()) ?: '&nbsp;'];
                $element['elements'][] = ['element' => 'td', 'content' => $payment->type ? $payment->type->name : ctrans('texts.manual_entry')];
                $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($payment->pivot->amount, $this->service->config->client) ?: '&nbsp;'];

                $tbody[] = $element;
                
            }
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_payment')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];
    }

    public function statementPaymentTableTotals(): array
    {
        if (is_null($this->service->options['payments']) || !$this->service->options['payments']->first()) {
            return [];
        }

        if (\array_key_exists('show_payments_table', $this->service->options) && $this->service->options['show_payments_table'] === false) {
            return [];
        }
        
        $payment = $this->service->options['payments']->first();

        return [
            ['element' => 'p', 'content' => \sprintf('%s: %s', ctrans('texts.amount_paid'), Number::formatMoney($this->service->options['payments']->sum('amount'), $this->service->config->client))],
        ];
    }

    public function statementAgingTable(): array
    {

        if (\array_key_exists('show_aging_table', $this->service->options) && $this->service->options['show_aging_table'] === false) {
            return [];
        }

        $elements = [
            ['element' => 'thead', 'elements' => []],
            ['element' => 'tbody', 'elements' => [
                ['element' => 'tr', 'elements' => []],
            ]],
        ];

        foreach ($this->service->options['aging'] as $column => $value) {
            $elements[0]['elements'][] = ['element' => 'th', 'content' => $column];
            $elements[1]['elements'][] = ['element' => 'td', 'content' => $value];
        }

        return $elements;
    }


    private function getPurchaseOrderSections(): self
    {

        $this->genericSectionBuilder()
             ->getProductTotals();

        $this->sections[] = [            
            'vendor-details' => [
                'id' => 'vendor-details',
                'elements' => $this->vendorDetails(),
            ],
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->purchaseOrderDetails(),
            ],
        ];

        return $this;

    }

    private function genericSectionBuilder(): self
    {

        $this->sections[] = [
            'company-details' => [
                'id' => 'company-details',
                'elements' => $this->service->companyDetails(),
            ],
            'company-address' => [
                'id' => 'company-address',
                'elements' => $this->service->companyAddress(),
            ],
            'footer-elements' => [
                'id' => 'footer',
                'elements' => [
                    $this->sharedFooterElements(),
                ],
            ],
        ];

        return $this;
    }

    public function statementInvoiceTable(): array
    {

        $tbody = [];

        foreach ($this->service->options['invoices'] as $invoice) {
            $element = ['element' => 'tr', 'elements' => []];

            $element['elements'][] = ['element' => 'td', 'content' => $invoice->number];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($invoice->date, $this->client->date_format(), $this->client->locale()) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($invoice->due_date, $this->client->date_format(), $this->client->locale()) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($invoice->amount, $this->client) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($invoice->balance, $this->client) ?: ' '];

            $tbody[] = $element;
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_invoice')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];
    }


    /**
     * Generate the structure of table body. (<tbody/>)
     *
     * @param string $type "$product" or "$task"
     * @return array
     */
    public function buildTableBody(string $type): array
    {
        $elements = [];

        $items = $this->transformLineItems($this->entity->line_items, $type);

        $this->processNewLines($items);

        if (count($items) == 0) {
            return [];
        }

        if ($type == PdfService::DELIVERY_NOTE) {
            $product_customs = [false, false, false, false];

            foreach ($items as $row) {
                for ($i = 0; $i < count($product_customs); $i++) {
                    if (!empty($row['delivery_note.delivery_note' . ($i + 1)])) {
                        $product_customs[$i] = true;
                    }
                }
            }

            foreach ($items as $row) {
                $element = ['element' => 'tr', 'elements' => []];

                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.product_key'], 'properties' => ['data-ref' => 'delivery_note_table.product_key-td']];
                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.notes'], 'properties' => ['data-ref' => 'delivery_note_table.notes-td']];
                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.quantity'], 'properties' => ['data-ref' => 'delivery_note_table.quantity-td']];

                for ($i = 0; $i < count($product_customs); $i++) {
                    if ($product_customs[$i]) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.delivery_note' . ($i + 1)], 'properties' => ['data-ref' => 'delivery_note_table.product' . ($i + 1) . '-td']];
                    }
                }

                $elements[] = $element;
            }

            return $elements;
        }

        foreach ($items as $row) {
            $element = ['element' => 'tr', 'elements' => []];

            if (
                array_key_exists($type, $this->context) &&
                !empty($this->context[$type]) &&
                !is_null($this->context[$type])
            ) {
                $document = new DOMDocument();
                $document->loadHTML($this->context[$type], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

                $td = $document->getElementsByTagName('tr')->item(0);

                if ($td) {
                    foreach ($td->childNodes as $child) {
                        if ($child->nodeType !== 1) {
                            continue;
                        }

                        if ($child->tagName !== 'td') {
                            continue;
                        }

                        $element['elements'][] = ['element' => 'td', 'content' => strtr($child->nodeValue, $row)];
                    }
                }
            } else {
                $_type = Str::startsWith($type, '$') ? ltrim($type, '$') : $type;

                foreach ($this->context['pdf_variables']["{$_type}_columns"] as $key => $cell) {
                    // We want to keep aliases like these:
                    // $task.cost => $task.rate
                    // $task.quantity => $task.hours

                    if ($cell == '$task.rate') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.cost'], 'properties' => ['data-ref' => 'task_table-task.cost-td']];
                    } elseif ($cell == '$product.discount' && !$this->service->company->enable_product_discount) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$product.discount'], 'properties' => ['data-ref' => 'product_table-product.discount-td', 'style' => 'display: none;']];
                    } elseif ($cell == '$product.quantity' && !$this->service->company->enable_product_quantity) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$product.quantity'], 'properties' => ['data-ref' => 'product_table-product.quantity-td', 'style' => 'display: none;']];
                    } elseif ($cell == '$task.hours') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.quantity'], 'properties' => ['data-ref' => 'task_table-task.hours-td']];
                    } elseif ($cell == '$product.tax_rate1') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax1-td']];
                    } elseif ($cell == '$product.tax_rate2') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax2-td']];
                    } elseif ($cell == '$product.tax_rate3') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax3-td']];
                    } else if ($cell == '$product.unit_cost' || $cell == '$task.rate') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['style' => 'white-space: nowrap;', 'data-ref' => "{$_type}_table-" . substr($cell, 1) . '-td']];
                    } else {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => "{$_type}_table-" . substr($cell, 1) . '-td']];
                    }
                }
            }

            $elements[] = $element;
        }

        $document = null;
        
        return $elements;
    }



    /**
     * Formats the line items for display.
     *
     * @param mixed $items
     * @param string $table_type
     * @param mixed|null $custom_fields
     *
     * @return array
     */
    public function transformLineItems($items, $table_type = '$product') :array
    { 

        $data = [];

        if (! is_array($items)) {
        }

        $locale_info = localeconv();

        $this->service->config->entity_currency = $this->service->config->currency;

        foreach ($items as $key => $item) {
            if ($table_type == '$product' && $item->type_id != 1) {
                if ($item->type_id != 4 && $item->type_id != 6 && $item->type_id != 5) {
                    continue;
                }
            }

            if ($table_type == '$task' && $item->type_id != 2) {
                // if ($item->type_id != 4 && $item->type_id != 5) {
                    continue;
                // }
            }

            $helpers = new Helpers();
            $_table_type = ltrim($table_type, '$'); // From $product -> product.

            $data[$key][$table_type.'.product_key'] = is_null(optional($item)->product_key) ? $item->item : $item->product_key;
            $data[$key][$table_type.'.item'] = is_null(optional($item)->item) ? $item->product_key : $item->item;
            $data[$key][$table_type.'.service'] = is_null(optional($item)->service) ? $item->product_key : $item->service;

            $currentDateTime = null;
            if (isset($this->entity->next_send_date)) {
                $currentDateTime = Carbon::parse($this->entity->next_send_date);
            }

            $data[$key][$table_type.'.notes'] = Helpers::processReservedKeywords($item->notes, $this->service->config->currency_entity, $currentDateTime);
            $data[$key][$table_type.'.description'] = Helpers::processReservedKeywords($item->notes, $this->service->config->currency_entity, $currentDateTime);

            $data[$key][$table_type.".{$_table_type}1"] = strlen($item->custom_value1) >= 1 ? $helpers->formatCustomFieldValue($this->service->company->custom_fields, "{$_table_type}1", $item->custom_value1, $this->service->config->currency_entity) : '';
            $data[$key][$table_type.".{$_table_type}2"] = strlen($item->custom_value2) >= 1 ? $helpers->formatCustomFieldValue($this->service->company->custom_fields, "{$_table_type}2", $item->custom_value2, $this->service->config->currency_entity) : '';
            $data[$key][$table_type.".{$_table_type}3"] = strlen($item->custom_value3) >= 1 ? $helpers->formatCustomFieldValue($this->service->company->custom_fields, "{$_table_type}3", $item->custom_value3, $this->service->config->currency_entity) : '';
            $data[$key][$table_type.".{$_table_type}4"] = strlen($item->custom_value4) >= 1 ? $helpers->formatCustomFieldValue($this->service->company->custom_fields, "{$_table_type}4", $item->custom_value4, $this->service->config->currency_entity) : '';

            if ($item->quantity > 0 || $item->cost > 0) {
                $data[$key][$table_type.'.quantity'] = Number::formatValueNoTrailingZeroes($item->quantity, $this->service->config->currency_entity);

                $data[$key][$table_type.'.unit_cost'] = Number::formatMoneyNoRounding($item->cost, $this->service->config->currency_entity);

                $data[$key][$table_type.'.cost'] = Number::formatMoney($item->cost, $this->service->config->currency_entity);

                $data[$key][$table_type.'.line_total'] = Number::formatMoney($item->line_total, $this->service->config->currency_entity);
            } else {
                $data[$key][$table_type.'.quantity'] = '';

                $data[$key][$table_type.'.unit_cost'] = '';

                $data[$key][$table_type.'.cost'] = '';

                $data[$key][$table_type.'.line_total'] = '';
            }

            if (property_exists($item, 'gross_line_total')) {
                $data[$key][$table_type.'.gross_line_total'] = ($item->gross_line_total == 0) ? '' : Number::formatMoney($item->gross_line_total, $this->service->config->currency_entity);
            } else {
                $data[$key][$table_type.'.gross_line_total'] = '';
            }

            if (property_exists($item, 'tax_amount')) {
                $data[$key][$table_type.'.tax_amount'] = ($item->tax_amount == 0) ? '' : Number::formatMoney($item->tax_amount, $this->service->config->currency_entity);
            } else {
                $data[$key][$table_type.'.tax_amount'] = '';
            }

            if (isset($item->discount) && $item->discount > 0) {
                if ($item->is_amount_discount) {
                    $data[$key][$table_type.'.discount'] = Number::formatMoney($item->discount, $this->service->config->currency_entity);
                } else {
                    $data[$key][$table_type.'.discount'] = floatval($item->discount).'%';
                }
            } else {
                $data[$key][$table_type.'.discount'] = '';
            }

            // Previously we used to check for tax_rate value,
            // but that's no longer necessary.

            if (isset($item->tax_rate1)) {
                $data[$key][$table_type.'.tax_rate1'] = floatval($item->tax_rate1).'%';
                $data[$key][$table_type.'.tax1'] = &$data[$key][$table_type.'.tax_rate1'];
            }

            if (isset($item->tax_rate2)) {
                $data[$key][$table_type.'.tax_rate2'] = floatval($item->tax_rate2).'%';
                $data[$key][$table_type.'.tax2'] = &$data[$key][$table_type.'.tax_rate2'];
            }

            if (isset($item->tax_rate3)) {
                $data[$key][$table_type.'.tax_rate3'] = floatval($item->tax_rate3).'%';
                $data[$key][$table_type.'.tax3'] = &$data[$key][$table_type.'.tax_rate3'];
            }

            $data[$key]['task_id'] = property_exists($item, 'task_id') ? $item->task_id : '';
        }

        //nlog(microtime(true) - $start);
        
        return $data;
    }

     /**
     * Generate the structure of table headers. (<thead/>)
     *
     * @param string $type "product" or "task"
     * @return array
     */
    public function buildTableHeader(string $type): array
    {
        $this->processTaxColumns($type);
        // $this->processCustomColumns($type);

        $elements = [];

        // Some of column can be aliased. This is simple workaround for these.
        $aliases = [
            '$product.product_key' => '$product.item',
            '$task.product_key' => '$task.service',
            '$task.rate' => '$task.cost',
        ];

        foreach ($this->context['pdf_variables']["{$type}_columns"] as $column) {
            if (array_key_exists($column, $aliases)) {
                $elements[] = ['element' => 'th', 'content' => $aliases[$column] . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($aliases[$column], 1) . '-th', 'hidden' => $this->service->config->settings_object->getSetting('hide_empty_columns_on_pdf')]];
            } elseif ($column == '$product.discount' && !$this->service->company->enable_product_discount) {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($column, 1) . '-th', 'style' => 'display: none;']];
            } elseif ($column == '$product.quantity' && !$this->service->company->enable_product_quantity) {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($column, 1) . '-th', 'style' => 'display: none;']];
            } elseif ($column == '$product.tax_rate1') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax1-th", 'hidden' => $this->service->config->settings_object->getSetting('hide_empty_columns_on_pdf')]];
            } elseif ($column == '$product.tax_rate2') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax2-th", 'hidden' => $this->service->config->settings_object->getSetting('hide_empty_columns_on_pdf')]];
            } elseif ($column == '$product.tax_rate3') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax3-th", 'hidden' => $this->service->config->settings_object->getSetting('hide_empty_columns_on_pdf')]];
            } else {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($column, 1) . '-th', 'hidden' => $this->service->config->settings_object->getSetting('hide_empty_columns_on_pdf')]];
            }
        }

        return $elements;
    }

 /**
     * This method will help us decide either we show
     * one "tax rate" column in the table or 3 custom tax rates.
     *
     * Logic below will help us calculate that & inject the result in the
     * global state of the $context (design state).
     *
     * @param string $type "product" or "task"
     * @return void
     */
    public function processTaxColumns(string $type): void
    {
        if ($type == 'product') {
            $type_id = 1;
        }

        if ($type == 'task') {
            $type_id = 2;
        }

        // At the moment we pass "task" or "product" as type.
        // However, "pdf_variables" contains "$task.tax" or "$product.tax" <-- Notice the dollar sign.
        // This sprintf() will help us convert "task" or "product" into "$task" or "$product" without
        // evaluating the variable.

        if (in_array(sprintf('%s%s.tax', '$', $type), (array) $this->service->config->pdf_variables["{$type}_columns"])) {
            $line_items = collect($this->service->config->entity->line_items)->filter(function ($item) use ($type_id) {
                return $item->type_id = $type_id;
            });

            $tax1 = $line_items->where('tax_name1', '<>', '')->where('type_id', $type_id)->count();
            $tax2 = $line_items->where('tax_name2', '<>', '')->where('type_id', $type_id)->count();
            $tax3 = $line_items->where('tax_name3', '<>', '')->where('type_id', $type_id)->count();

            $taxes = [];

            if ($tax1 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate1', '$', $type));
            }

            if ($tax2 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate2', '$', $type));
            }

            if ($tax3 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate3', '$', $type));
            }

            $key = array_search(sprintf('%s%s.tax', '$', $type), $this->service->config->pdf_variables["{$type}_columns"], true);

            if ($key !== false) {
                array_splice($this->service->config->pdf_variables["{$type}_columns"], $key, 1, $taxes);
            }
        }
    }


    public function sharedFooterElements(): array
    {
        // We want to show headers for statements, no exceptions.
        $statements = "
            document.querySelectorAll('#statement-invoice-table > thead > tr > th, #statement-payment-table > thead > tr > th, #statement-aging-table > thead > tr > th').forEach(t => {
                t.hidden = false;
            });
        ";

        $javascript = 'document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("#product-table > tbody > tr > td, #task-table > tbody > tr > td, #delivery-note-table > tbody > tr > td").forEach(t=>{if(""!==t.innerText){let e=t.getAttribute("data-ref").slice(0,-3);document.querySelector(`th[data-ref="${e}-th"]`).removeAttribute("hidden")}}),document.querySelectorAll("#product-table > tbody > tr > td, #task-table > tbody > tr > td, #delivery-note-table > tbody > tr > td").forEach(t=>{let e=t.getAttribute("data-ref").slice(0,-3);(e=document.querySelector(`th[data-ref="${e}-th"]`)).hasAttribute("hidden")&&""==t.innerText&&t.setAttribute("hidden","true")})},!1);';

        // Previously we've been decoding the HTML on the backend and XML parsing isn't good options because it requires,
        // strict & valid HTML to even output/decode. Decoding is now done on the frontend with this piece of Javascript.

        $html_decode = 'document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(`[data-state="encoded-html"]`).forEach(e=>e.innerHTML=e.innerText)},!1);';

        return ['element' => 'div', 'elements' => [
            ['element' => 'script', 'content' => $statements],
            ['element' => 'script', 'content' => $javascript],
            ['element' => 'script', 'content' => $html_decode],
        ]];
    }

    private function getProductTotals(): self
    {

        $this->sections[] = [    
            'table-totals' => [
                'id' => 'table-totals',
                'elements' => $this->getTableTotals(),
            ],
        ];

        return $this;
    }

    private function getProductEntityDetails(): self
    {


        if($this->service->config->entity_string == 'invoice')
        {
            $this->sections[] = [
                'entity-details' => [
                    'id' => 'entity-details',
                    'elements' => $this->invoiceDetails(),
                ],
            ];
        }
        elseif($this->service->config->entity_string == 'quote')
        {

            $this->sections[] = [
                'entity-details' => [
                    'id' => 'entity-details',
                    'elements' => $this->quoteDetails(),
                ],
            ];

        }
        elseif($this->service->config->entity_string == 'credit')
        {

            $this->sections[] = [
                'entity-details' => [
                    'id' => 'entity-details',
                    'elements' => $this->creditDetails(),
                ],
            ];

        }

        return $this;
        

    }

    /* Parent entry point when building sections of the design content */
    private function buildSections() :self
    {

        return  match ($this->service->config->document_type) {
            PdfService::PRODUCT => $this->getProductSections,
            PdfService::DELIVERY_NOTE => $this->getDeliveryNoteSections(),
            PdfService::STATEMENT => $this->getStatementSections(),
            PdfService::PURCHASE_ORDER => $this->getPurchaseOrderSections(),
        };      

    }

    private function statementTableTotals(): array
    {
        return [
            ['element' => 'div', 'properties' => ['style' => 'display: flex; flex-direction: column;'], 'elements' => [
                ['element' => 'div', 'properties' => ['style' => 'margin-top: 1.5rem; display: block; align-items: flex-start; page-break-inside: avoid; visible !important;'], 'elements' => [
                    ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 2.5rem;', 'hidden' => $this->service->account->isPaid() ? 'true' : 'false', 'id' => 'invoiceninja-whitelabel-logo']],
                ]],
            ]],
        ];
    }

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

        if (is_null($this->entity->{$_variable})) {
            return true;
        }

        if (empty($this->entity->{$_variable})) {
            return true;
        }

        return false;
    }

    //First pass done, need a second pass to abstract this content completely.
    public function getTableTotals() :array
    {
        //need to see where we don't pass all these particular variables. try and refactor thisout
        $_variables = array_key_exists('variables', $this->context)
            ? $this->context['variables']
            : ['values' => ['$this->service->config->entity.public_notes' => $this->service->config->entity->public_notes, '$this->service->config->entity.terms' => $this->service->config->entity->terms, '$this->service->config->entity_footer' => $this->service->config->entity->footer], 'labels' => []];

        $variables = $this->service->config->pdf_variables['total_columns'];

        $elements = [
            ['element' => 'div', 'properties' => ['style' => 'display: flex; flex-direction: column;'], 'elements' => [
                ['element' => 'p', 'content' => strtr(str_replace(["labels","values"], ["",""], $_variables['values']['$this->service->config->entity.public_notes']), $_variables), 'properties' => ['data-ref' => 'total_table-public_notes', 'style' => 'text-align: left;']],
                ['element' => 'p', 'content' => '', 'properties' => ['style' => 'text-align: left; display: flex; flex-direction: column; page-break-inside: auto;'], 'elements' => [
                    ['element' => 'span', 'content' => '$this->service->config->entity.terms_label: ', 'properties' => ['hidden' => $this->entityVariableCheck('$this->service->config->entity.terms'), 'data-ref' => 'total_table-terms-label', 'style' => 'font-weight: bold; text-align: left; margin-top: 1rem;']],
                    ['element' => 'span', 'content' => strtr(str_replace("labels", "", $_variables['values']['$this->service->config->entity.terms']), $_variables['labels']), 'properties' => ['data-ref' => 'total_table-terms', 'style' => 'text-align: left;']],
                ]],
                ['element' => 'img', 'properties' => ['style' => 'max-width: 50%; height: auto;', 'src' => '$contact.signature', 'id' => 'contact-signature']],
                ['element' => 'div', 'properties' => ['style' => 'margin-top: 1.5rem; display: flex; align-items: flex-start; page-break-inside: auto;'], 'elements' => [
                    ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 2.5rem;', 'hidden' => $this->service->account->isPaid() ? 'true' : 'false', 'id' => 'invoiceninja-whitelabel-logo']],
                ]],
            ]],
            ['element' => 'div', 'properties' => ['class' => 'totals-table-right-side', 'dir' => '$dir'], 'elements' => []],
        ];


        if ($this->service->config->document_type == PdfService::DELIVERY_NOTE) {
            return $elements;
        }

        if ($this->service->config->entity instanceof Quote) {
            // We don't want to show Balanace due on the quotes.
            if (in_array('$outstanding', $variables)) {
                $variables = \array_diff($variables, ['$outstanding']);
            }

            if ($this->service->config->entity->partial > 0) {
                $variables[] = '$partial_due';
            }
        }

        if ($this->service->config->entity instanceof Credit) {
            // We don't want to show Balanace due on the quotes.
            if (in_array('$paid_to_date', $variables)) {
                $variables = \array_diff($variables, ['$paid_to_date']);
            }

        }

        foreach (['discount'] as $property) {
            $variable = sprintf('%s%s', '$', $property);

            if (
                !is_null($this->service->config->entity->{$property}) &&
                !empty($this->service->config->entity->{$property}) &&
                $this->service->config->entity->{$property} != 0
            ) {
                continue;
            }

            $variables = array_filter($variables, function ($m) use ($variable) {
                return $m != $variable;
            });
        }

        foreach ($variables as $variable) {
            if ($variable == '$total_taxes') {
                $taxes = $this->service->config->entity->calc()->getTotalTaxMap();

                if (!$taxes) {
                    continue;
                }

                foreach ($taxes as $i => $tax) {
                    $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                        ['element' => 'span', 'content', 'content' => $tax['name'], 'properties' => ['data-ref' => 'totals-table-total_tax_' . $i . '-label']],
                        ['element' => 'span', 'content', 'content' => Number::formatMoney($tax['total'], $this->service->config->currency_entity), 'properties' => ['data-ref' => 'totals-table-total_tax_' . $i]],
                    ]];
                }
            } elseif ($variable == '$line_taxes') {
                $taxes = $this->service->config->entity->calc()->getTaxMap();

                if (!$taxes) {
                    continue;
                }

                foreach ($taxes as $i => $tax) {
                    $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                        ['element' => 'span', 'content', 'content' => $tax['name'], 'properties' => ['data-ref' => 'totals-table-line_tax_' . $i . '-label']],
                        ['element' => 'span', 'content', 'content' => Number::formatMoney($tax['total'], $this->service->config->entity instanceof \App\Models\PurchaseOrder ? $this->service->config->vendor : $this->service->config->client), 'properties' => ['data-ref' => 'totals-table-line_tax_' . $i]],
                    ]];
                }
            } elseif (Str::startsWith($variable, '$custom_surcharge')) {
                $_variable = ltrim($variable, '$'); // $custom_surcharge1 -> custom_surcharge1

                $visible = intval($this->service->config->entity->{$_variable}) != 0;

                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            } elseif (Str::startsWith($variable, '$custom')) {
                $field = explode('_', $variable);
                $visible = is_object($this->service->company->custom_fields) && property_exists($this->service->company->custom_fields, $field[1]) && !empty($this->service->company->custom_fields->{$field[1]});

                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            } else {
                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            }
        }

        $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
            ['element' => 'span', 'content' => '',],
            ['element' => 'span', 'content' => ''],
        ]];

        return $elements;
    
    }

    /**
     * Generates the product and task tables
     * 
     * @return self
     * 
     */
    public function getProductAndTaskTables(): self
    {
        
        $this->sections[] = [
            'product-table' => [
                'id' => 'product-table',
                'elements' => $this->productTable(),
            ],
            'task-table' => [
                'id' => 'task-table',
                'elements' => $this->taskTable(),
            ],
        ];

        return $this;
    }

    /**
     * Generates the client details
     * 
     * @return self
     * 
     */
    public function getClientDetails(): self
    {
        $this->sections[] = [
            'client-details' => [
                'id' => 'client-details',
                'elements' => $this->clientDetails(),
            ],
        ];

        return $this;
    }

/**
     * Generates the product table
     *
     * @return array
     */
    public function productTable(): array
    {
        $product_items = collect($this->service->config->entity->line_items)->filter(function ($item) {
            return $item->type_id == 1 || $item->type_id == 6 || $item->type_id == 5;
        });

        if (count($product_items) == 0) {
            return [];
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('product')],
            ['element' => 'tbody', 'elements' => $this->buildTableBody('$product')],
        ];
    }

    /**
     * Generates the task table
     *
     * @return array
     */
    public function taskTable(): array
    {
        $task_items = collect($this->service->config->entity->line_items)->filter(function ($item) {
            return $item->type_id == 2;
        });

        if (count($task_items) == 0) {
            return [];
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('task')],
            ['element' => 'tbody', 'elements' => $this->buildTableBody('$task')],
        ];
    }


    /**
     * Generates the statement details
     * 
     * @return array
     * 
     */
    public function statementDetails(): array
    {

        $s_date = $this->translateDate(now(), $this->service->config->client->date_format(), $this->service->config->client->locale());
        
        return [
            ['element' => 'tr', 'properties' => ['data-ref' => 'statement-label'], 'elements' => [
                ['element' => 'th', 'properties' => [], 'content' => ""],
                ['element' => 'th', 'properties' => [], 'content' => "<h2>".ctrans('texts.statement')."</h2>"],
            ]],
            ['element' => 'tr', 'properties' => [], 'elements' => [
                ['element' => 'th', 'properties' => [], 'content' => ctrans('texts.statement_date')],
                ['element' => 'th', 'properties' => [], 'content' => $s_date ?? ''],
            ]],
            ['element' => 'tr', 'properties' => [], 'elements' => [
                ['element' => 'th', 'properties' => [], 'content' => '$balance_due_label'],
                ['element' => 'th', 'properties' => [], 'content' => Number::formatMoney($this->service->options['invoices']->sum('balance'), $this->service->config->client)],
            ]],
        ];

    }

    /**
     * Generates the invoice details
     * 
     * @return array
     * 
     */
    public function invoiceDetails(): array
    {

       $variables = $this->service->config->pdf_variables['invoice_details'];

        return $this->genericDetailsBuilder($variables);
    }

    /**
     * Generates the quote details
     * 
     * @return array
     * 
     */
    public function quoteDetails(): array
    {
        $variables = $this->service->config->pdf_variables['quote_details'];
        
        if ($this->service->config->entity->partial > 0) {
            $variables[] = '$quote.balance_due';
        }

        return $this->genericDetailsBuilder($variables);
    }


    /**
     * Generates the credit note details
     * 
     * @return array
     * 
     */
    public function creditDetails(): array
    {
    
        $variables = $this->service->config->pdf_variables['credit_details'];
    
        return $this->genericDetailsBuilder($variables);
    }

    /**
     * Generates the purchase order details
     * 
     * @return array
     */
    public function purchaseOrderDetails(): array
    {

        $variables = $this->service->config->pdf_variables['purchase_order_details'];

        return $this->genericDetailsBuilder($variables);
    
    }

    /**
     * Generates the deliveyr note details
     * 
     * @return array
     * 
     */
    public function deliveryNoteDetails(): array
    {

        $variables = $this->service->config->pdf_variables['invoice_details'];

        $variables = array_filter($variables, function ($m) {
            return !in_array($m, ['$invoice.balance_due', '$invoice.total']);
        });

        return $this->genericDetailsBuilder($variables);
    }

    /**
     * Generates the custom values for the
     * entity.
     * 
     * @param  array
     * @return array
     */
    public function genericDetailsBuilder(array $variables): array
    {

        $elements = [];


        foreach ($variables as $variable) {
            $_variable = explode('.', $variable)[1];
            $_customs = ['custom1', 'custom2', 'custom3', 'custom4'];

            $var = str_replace("custom", "custom_value", $_variable);

            if (in_array($_variable, $_customs) && !empty($this->service->config->entity->{$var})) {
                $elements[] = ['element' => 'tr', 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1)]],
                ]];
            } else {
                $elements[] = ['element' => 'tr', 'properties' => ['hidden' => $this->entityVariableCheck($variable)], 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1)]],
                ]];
            }
        }

        return $elements;
    }


    /**
     * Generates the client delivery
     * details array
     * 
     * @return array
     * 
     */
    public function clientDeliveryDetails(): array
    {
        
        $elements = [];

        if(!$this->service->config->client)
            return $elements;

        $elements = [
                ['element' => 'p', 'content' => ctrans('texts.delivery_note'), 'properties' => ['data-ref' => 'delivery_note-label', 'style' => 'font-weight: bold; text-transform: uppercase']],
                ['element' => 'p', 'content' => $this->service->config->client->name, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.name']],
                ['element' => 'p', 'content' => $this->service->config->client->shipping_address1, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.shipping_address1']],
                ['element' => 'p', 'content' => $this->service->config->client->shipping_address2, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.shipping_address2']],
                ['element' => 'p', 'show_empty' => false, 'elements' => [
                    ['element' => 'span', 'content' => "{$this->service->config->client->shipping_city} ", 'properties' => ['ref' => 'delivery_note-client.shipping_city']],
                    ['element' => 'span', 'content' => "{$this->service->config->client->shipping_state} ", 'properties' => ['ref' => 'delivery_note-client.shipping_state']],
                    ['element' => 'span', 'content' => "{$this->service->config->client->shipping_postal_code} ", 'properties' => ['ref' => 'delivery_note-client.shipping_postal_code']],
                ]],
                ['element' => 'p', 'content' => optional($this->service->config->client->shipping_country)->name, 'show_empty' => false],
            ];

            if (!is_null($this->service->config->contact)) {
                $elements[] = ['element' => 'p', 'content' => $this->service->config->contact->email, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-contact.email']];
            }

        return $elements;

    }

    /**
     * Generates the client details section
     * 
     * @return array
     */
    public function clientDetails(): array
    {
        $elements = [];

        if(!$this->service->config->client)
            return $elements;

        $variables = $this->service->config->pdf_variables['client_details'];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'client_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    /**
     * Generates the delivery note table
     * 
     * @return array
     */
    public function deliveryNoteTable(): array
    {
        /* Static array of delivery note columns*/
    
        $thead = [
            ['element' => 'th', 'content' => '$item_label', 'properties' => ['data-ref' => 'delivery_note-item_label']],
            ['element' => 'th', 'content' => '$description_label', 'properties' => ['data-ref' => 'delivery_note-description_label']],
            ['element' => 'th', 'content' => '$product.quantity_label', 'properties' => ['data-ref' => 'delivery_note-product.quantity_label']],
        ];

        $items = $this->transformLineItems($this->service->config->entity->line_items, $this->service->config->document_type);

        $this->processNewLines($items);

        $product_customs = [false, false, false, false];

        foreach ($items as $row) {
            for ($i = 0; $i < count($product_customs); $i++) {
                if (!empty($row['delivery_note.delivery_note' . ($i + 1)])) {
                    $product_customs[$i] = true;
                }
            }
        }

        for ($i = 0; $i < count($product_customs); $i++) {
            if ($product_customs[$i]) {
                array_push($thead, ['element' => 'th', 'content' => '$product.product' . ($i + 1) . '_label', 'properties' => ['data-ref' => 'delivery_note-product.product' . ($i + 1) . '_label']]);
            }
        }

        return [
            ['element' => 'thead', 'elements' => $thead],
            ['element' => 'tbody', 'elements' => $this->buildTableBody(PdfService::DELIVERY_NOTE)],
        ];
    }

    /**
     * Passes an array of items by reference
     * and performs a nl2br
     * 
     * @param  array
     * @return void
     * 
     */
    public function processNewLines(array &$items): void
    {
        foreach ($items as $key => $item) {
            foreach ($item as $variable => $value) {
                $item[$variable] = str_replace("\n", '<br>', $value);
            }

            $items[$key] = $item;
        }
    }

    /**
     * Generates an arary of the company details
     * 
     * @return array
     * 
     */
    public function companyDetails(): array
    {
        $variables = $this->service->config->pdf_variables['company_details'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    /**
     *
     * Generates an array of the company address
     * 
     * @return array
     * 
     */
    public function companyAddress(): array
    {
        $variables = $this->service->config->pdf_variables['company_address'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_address-' . substr($variable, 1)]];
        }

        return $elements;
    }

    /**
     *
     * Generates an array of vendor details
     * 
     * @return array
     * 
     */
    public function vendorDetails(): array
    {
        $elements = [];

        $variables = $this->service->config->pdf_variables['vendor_details'];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'vendor_details-' . substr($variable, 1)]];
        }

        return $elements;
    }






 //         if (isset($this->data['template']) && isset($this->data['variables'])) {
 //            $this->getEmptyElements($this->data['template'], $this->data['variables']);
 //        }

 //        if (isset($this->data['template'])) {
 //            $this->updateElementProperties($this->data['template']);
 //        }

 //        if (isset($this->data['variables'])) {
 //            $this->updateVariables($this->data['variables']);
 //        }

 //        return $this;



    
}