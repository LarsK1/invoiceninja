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

namespace App\Services\Bank;

use App\Factory\ExpenseCategoryFactory;
use App\Factory\ExpenseFactory;
use App\Libraries\MultiDB;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Services\Bank\BankService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BankMatchingService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeneratesCounter;

    private Company $company;

    private $invoices;

    public $deleteWhenMissingModels = true;

    public function __construct(private int $company_id, private string $db){}

    public function handle()
    {

        MultiDB::setDb($this->db);

        $this->company = Company::find($this->company_id);

        $this->matchTransactions();
    

    }

    private function matchTransactions()
    {
        
        BankTransaction::where('company_id', $this->company->id)
           ->where('status_id', BankTransaction::STATUS_UNMATCHED)
           ->cursor()
           ->each(function ($bt){
            
               (new BankService($bt))->processRules();

           });

    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company_id)];
    }
}
