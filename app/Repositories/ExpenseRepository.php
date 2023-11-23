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

namespace App\Repositories;

use App\Models\Expense;
use Illuminate\Support\Carbon;
use App\Factory\ExpenseFactory;
use App\Models\ExpenseCategory;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;
use Carbon\Exceptions\InvalidFormatException;
use App\Libraries\Currency\Conversion\CurrencyApi;
use Illuminate\Database\Eloquent\Collection;

/**
 * ExpenseRepository.
 */
class ExpenseRepository extends BaseRepository
{
    use GeneratesCounter;

    private $completed = true;

    /**
     * Saves the expense and its contacts.
     *
     * @param      array                     $data     The data
     * @param      \App\Models\Expense       $expense  The expense
     *
     * @return     \App\Models\Expense
     */
    public function save(array $data, Expense $expense): Expense
    {
        if(isset($data['payment_date']) && $data['payment_date'] == $expense->payment_date) {
            //do nothing
        } elseif(isset($data['payment_date']) && strlen($data['payment_date']) > 1 && $expense->company->notify_vendor_when_paid) {
            nlog("NOT SAME");
        }

        $expense->fill($data);

        if (!$expense->id) {
            $expense = $this->processExchangeRates($data, $expense);
        }

        if (empty($expense->number)) {
            $expense = $this->findAndSaveNumber($expense);
        }
        else
            $expense->saveQuietly();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $expense);
        }

        return $expense;
    }

    /**
     * Store expenses in bulk.
     *
     * @param array $expense
     *
     * @return \App\Models\Expense|null
     */
    public function create($expense): ?Expense
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $this->save(
            $expense,
            ExpenseFactory::create($user->company()->id, $user->id)
        );
    }

    /**
     * @param mixed $data
     * @param mixed $expense
     * @return Expense
     * @throws InvalidFormatException
     */
    public function processExchangeRates($data, $expense): Expense
    {
        if (array_key_exists('exchange_rate', $data) && isset($data['exchange_rate']) && $data['exchange_rate'] != 1) {
            return $expense;
        }

        $expense_currency = $data['currency_id'];
        $company_currency = $expense->company->settings->currency_id;

        if ($company_currency != $expense_currency) {
            $exchange_rate = new CurrencyApi();

            $expense->exchange_rate = $exchange_rate->exchangeRate($expense_currency, $company_currency, Carbon::parse($expense->date));

            return $expense;
        }

        return $expense;
    }


    public function delete($expense) :Expense
    {
        
        if ($expense->transaction()->exists()) {
            
            $exp_ids = collect(explode(',', $expense->transaction->expense_id))->filter(function ($id) use ($expense) {
                return $id != $expense->hashed_id;
            })->implode(',');
                    
            $expense->transaction_id = null;
            $expense->saveQuietly();

            $expense->transaction->expense_id = $exp_ids;

            if(strlen($exp_ids) <= 2) {
                $expense->transaction->status_id = 1;
            }

            $expense->transaction->saveQuietly();

        }

        parent::delete($expense);

        return $expense;
    }


    /**
     * Handle race conditions when creating expense numbers
     *
     * @param Expense $expense
     * @return \App\Models\Expense
     */
    private function findAndSaveNumber($expense): Expense
    {
        $x = 1;

        do {
            try {
                $expense->number = $this->getNextExpenseNumber($expense);
                $expense->saveQuietly();

                $this->completed = false;
            } catch (QueryException $e) {
                $x++;

                if ($x > 50) {
                    $this->completed = false;
                }
            }
        } while ($this->completed);

        return $expense;
    }
    
    /**
     * Categorize Expenses in bulk
     *
     * @param  Collection $expenses
     * @param  int $category_id
     * @return void
     */
    public function categorize(Collection $expenses, int $category_id): void
    {
        $ec = ExpenseCategory::withTrashed()->find($category_id);

            $expenses->when($ec)
                     ->each(function ($expense) use($ec){
                                                
                        $expense->category_id = $ec->id;
                        $expense->save();

        });
    }

}
