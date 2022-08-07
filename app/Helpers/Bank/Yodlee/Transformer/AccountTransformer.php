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

namespace App\Helpers\Bank\Yodlee\Transformer;
 
/**
[0] => stdClass Object
(
    [CONTAINER] => bank
    [providerAccountId] => 11308693
    [accountName] => My CD - 8878
    [accountStatus] => ACTIVE
    [accountNumber] => xxxx8878
    [aggregationSource] => USER
    [isAsset] => 1
    [balance] => stdClass Object
        (
            [currency] => USD
            [amount] => 49778.07
        )

    [id] => 12331861
    [includeInNetWorth] => 1
    [providerId] => 18769
    [providerName] => Dag Site Captcha
    [isManual] => 
    [currentBalance] => stdClass Object
        (
            [currency] => USD
            [amount] => 49778.07
        )

    [accountType] => CD
    [displayedName] => LORETTA
    [createdDate] => 2022-07-28T06:55:33Z
    [lastUpdated] => 2022-07-28T06:56:09Z
    [dataset] => Array
        (
            [0] => stdClass Object
                (
                    [name] => BASIC_AGG_DATA
                    [additionalStatus] => AVAILABLE_DATA_RETRIEVED
                    [updateEligibility] => ALLOW_UPDATE
                    [lastUpdated] => 2022-07-28T06:55:50Z
                    [lastUpdateAttempt] => 2022-07-28T06:55:50Z
                )

        )

)
    )
 */

class AccountTransformer
{

    public function transform($yodlee_account){

        $data = [];

        foreach($yodlee_account->account as $account)
        {
            $data[] = $this->transformAccount($account);
        }

        return $data;
    }

    public function transformAccount($account)
    {
        nlog($account);
        return [
            'id' => $account->id,
            'account_type' => $account->CONTAINER,
            'account_name' => $account->accountName,
            'account_status' => $account->accountStatus,
            'account_number' => $account->accountNumber,
            'current_balance' => property_exists($account, 'currentBalance') ? $account->currentBalance->amount : 0,
            'account_currency' => property_exists($account, 'currency') ? $account->currentBalance->currency : '',
        ];
    }
}


