<?php
/**
 * Credit Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Bank;

use App\Helpers\Bank\Yodlee\Yodlee;
use App\Libraries\MultiDB;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Services\Bank\BankService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ProcessBankTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $bank_integration_account_id;

    private BankIntegration $bank_integration;

    private ?string $from_date;

    private bool $stop_loop = true;

    private int $skip = 0;

    public Company $company;

    /**
     * Create a new job instance.
     */
    public function __construct(string $bank_integration_account_id, BankIntegration $bank_integration)
    {
        $this->bank_integration_account_id = $bank_integration_account_id;
        $this->bank_integration = $bank_integration;
        $this->from_date = $bank_integration->from_date;
        $this->company = $this->bank_integration->company;

    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {

        set_time_limit(0);
        //Loop through everything until we are up to date

        $this->from_date = $this->from_date ?: '2020-01-01';

        do{

            $this->processTransactions();

        }
        while($this->stop_loop);

        BankService::dispatch($this->company->id, $this->company->db);

    }


    private function processTransactions()
    {

        $yodlee = new Yodlee($this->bank_integration_account_id);

        $data = [
            'top' => 500,
            'fromDate' => $this->from_date,
            'accountId' => $this->bank_integration->bank_account_id,
            'skip' => $this->skip,
        ];

        //Get transaction count object
        $transaction_count = $yodlee->getTransactionCount($data);

        //Get int count
        $count = $transaction_count->transaction->TOTAL->count;

        nlog("Number of transactions = {$count} - bank integration id = {$this->bank_integration->id} - bank account id = {$this->bank_integration->bank_account_id} - from {$this->from_date}");

        //get transactions array
        $transactions = $yodlee->getTransactions($data); 

        //if no transactions, update the from_date and move on
        if(count($transactions) == 0){

            $this->bank_integration->from_date = now();
            $this->bank_integration->save();
            nlog("the bank integration from_date being set to: {$this->bank_integration->from_date}");
            $this->stop_loop = false;
            return;
        }

        //Harvest the company

        MultiDB::setDb($company->db);

        /*Get the user */
        $user_id = $company->owner()->id;
        
        /* Unguard the model to perform batch inserts */
        BankTransaction::unguard();

        $now = now();
        
        foreach($transactions as $transaction)
        {

            if(BankTransaction::where('transaction_id', $transaction['transaction_id'])->where('company_id', $company->id)->withTrashed()->exists())
                continue;

            //this should be much faster to insert than using ::create()
            $bt = \DB::table('bank_transactions')->insert(
                array_merge($transaction,[
                    'company_id' => $company->id,
                    'user_id' => $user_id,
                    'bank_integration_id' => $this->bank_integration->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );

        }


        $this->skip = $this->skip + 500;

        if($count < 500){
            $this->stop_loop = false;

            $this->bank_integration->from_date = now();
            $this->bank_integration->save();

            nlog("stopping while loop");
        }

    }

}