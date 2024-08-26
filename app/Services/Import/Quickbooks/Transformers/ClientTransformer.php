<?php

/**
 * Invoice Ninja (https://clientninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Import\Quickbooks\Transformers;

use App\DataMapper\ClientSettings;

/**
 * Class ClientTransformer.
 */
class ClientTransformer extends BaseTransformer
{

    public function qbToNinja(mixed $qb_data)
    {
        return $this->transform($qb_data);
    }

    public function ninjaToQb()
    {
    }

    public function transform(mixed $data): array
    {

        $contact = [
            'first_name' => data_get($data, 'GivenName'),
            'last_name' => data_get($data, 'FamilyName'),
            'phone' => data_get($data, 'PrimaryPhone.FreeFormNumber'),
            'email' =>  data_get($data, 'PrimaryEmailAddr.Address'),
        ];

        $client = [
            'name' => data_get($data,'CompanyName', ''),
            'address1' => data_get($data, 'BillAddr.Line1', ''),
            'address2' => data_get($data, 'BillAddr.Line2', ''),
            'city' => data_get($data, 'BillAddr.City', ''),
            'country_id' => $this->resolveCountry(data_get($data, 'BillAddr.Country', '')),
            'state' => data_get($data, 'BillAddr.CountrySubDivisionCode', ''),
            'postal_code' =>  data_get($data, 'BillAddr.PostalCode', ''),
            'shipping_address1' => data_get($data, 'ShipAddr.Line1', ''),
            'shipping_address2' => data_get($data, 'ShipAddr.Line2', ''),
            'shipping_city' => data_get($data, 'ShipAddr.City', ''),
            'shipping_country_id' => $this->resolveCountry(data_get($data, 'ShipAddr.Country', '')),
            'shipping_state' => data_get($data, 'ShipAddr.CountrySubDivisionCode', ''),
            'shipping_postal_code' =>  data_get($data, 'BillAddr.PostalCode', ''),
            'id_number' => data_get($data, 'Id.value', ''),
        ];
        
            $settings = ClientSettings::defaults();
            $settings->currency_id = (string) $this->resolveCurrency(data_get($data, 'CurrencyRef.value'));

            $new_client_merge = [
                'client_hash' => data_get($data, 'V4IDPseudonym', \Illuminate\Support\Str::random(32)),
                'settings' => $settings,
            ];

        return [$client, $contact, $new_client_merge];
    }

}
