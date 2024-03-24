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

namespace App\Services\IngresEmail;

use App\Events\Expense\ExpenseWasCreated;
use App\Factory\ExpenseFactory;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Services\IngresEmail\IngresEmail;
use App\Utils\Ninja;
use App\Utils\TempFile;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\MakesHash;
use Cache;
use Illuminate\Queue\SerializesModels;
use Log;

class IngresEmailEngine
{
    use SerializesModels, MakesHash;
    use GeneratesCounter, SavesDocuments;

    private ?Company $company;
    private ?bool $isUnknownRecipent = null;
    private array $globalBlacklistDomains = [];
    private array $globalBlacklistEmails = [];
    public function __construct(private IngresEmail $email)
    {
    }
    /**
     * if there is not a company with an matching mailbox, we only do monitoring
     * reuse this method to add more mail-parsing behaviors
     */
    public function handle()
    {
        if ($this->isInvalidOrBlocked())
            return;

        $this->isUnknownRecipent = true;

        // Expense Mailbox => will create an expense
        $this->company = MultiDB::findAndSetDbByExpenseMailbox($this->email->to);
        if ($this->company) {
            $this->isUnknownRecipent = false;
            $this->createExpense();
        }

        $this->saveMeta();
    }

    // SPAM Protection
    private function isInvalidOrBlocked()
    {
        // invalid email
        if (!filter_var($this->email->from, FILTER_VALIDATE_EMAIL)) {
            Log::info('[IngressMailEngine] E-Mail blocked, because from e-mail has the wrong format: ' . $this->email->from);
            return true;
        }

        $parts = explode('@', $this->email->from);
        $domain = array_pop($parts);

        // global blacklist
        if (in_array($domain, $this->globalBlacklistDomains)) {
            Log::info('[IngressMailEngine] E-Mail blocked, because the domain was found on globalBlocklistDomains: ' . $this->email->from);
            return true;
        }
        if (in_array($this->email->from, $this->globalBlacklistEmails)) {
            Log::info('[IngressMailEngine] E-Mail blocked, because the email was found on globalBlocklistEmails: ' . $this->email->from);
            return true;
        }

        if (Cache::has('ingresEmailBlockedSender:' . $this->email->from)) { // was marked as blocked before, so we block without any console output
            return true;
        }

        // sender occured in more than 500 emails in the last 12 hours
        $senderMailCountTotal = Cache::get('ingresEmailSender:' . $this->email->from, 0);
        if ($senderMailCountTotal >= 5000) {
            Log::info('[IngressMailEngine] E-Mail blocked permanent, because the sender sended more than ' . $senderMailCountTotal . ' emails in the last 12 hours: ' . $this->email->from);
            $this->blockSender();
            return true;
        }
        if ($senderMailCountTotal >= 1000) {
            Log::info('[IngressMailEngine] E-Mail blocked, because the sender sended more than ' . $senderMailCountTotal . ' emails in the last 12 hours: ' . $this->email->from);
            $this->saveMeta();
            return true;
        }

        // sender sended more than 50 emails to the wrong mailbox in the last 6 hours
        $senderMailCountUnknownRecipent = Cache::get('ingresEmailSenderUnknownRecipent:' . $this->email->from, 0);
        if ($senderMailCountUnknownRecipent >= 50) {
            Log::info('[IngressMailEngine] E-Mail blocked, because the sender sended more than ' . $senderMailCountUnknownRecipent . ' emails to the wrong mailbox in the last 6 hours: ' . $this->email->from);
            $this->saveMeta();
            return true;
        }

        // wrong recipent occurs in more than 100 emails in the last 12 hours, so the processing is blocked
        $mailCountUnknownRecipent = Cache::get('ingresEmailUnknownRecipent:' . $this->email->to, 0); // @turbo124 maybe use many to save resources in case of spam with multiple to addresses each time
        if ($mailCountUnknownRecipent >= 100) {
            Log::info('[IngressMailEngine] E-Mail blocked, because anyone sended more than ' . $mailCountUnknownRecipent . ' emails to the wrong mailbox in the last 12 hours. Current sender was blocked as well: ' . $this->email->from);
            $this->blockSender();
            return true;
        }

        return false;
    }
    private function blockSender()
    {
        Cache::add('ingresEmailBlockedSender:' . $this->email->from, true, now()->addHours(12));
        $this->saveMeta();

        // TODO: ignore, when known sender (for heavy email-usage mostly on isHosted())
        // TODO: handle external blocking
    }
    private function saveMeta()
    {
        // save cache
        Cache::add('ingresEmailSender:' . $this->email->from, 0, now()->addHours(12));
        Cache::increment('ingresEmailSender:' . $this->email->from);

        if ($this->isUnknownRecipent) {
            Cache::add('ingresEmailSenderUnknownRecipent:' . $this->email->from, 0, now()->addHours(6));
            Cache::increment('ingresEmailSenderUnknownRecipent:' . $this->email->from); // we save the sender, to may block him

            Cache::add('ingresEmailUnknownRecipent:' . $this->email->to, 0, now()->addHours(12));
            Cache::increment('ingresEmailUnknownRecipent:' . $this->email->to); // we save the sender, to may block him
        }
    }

    // MAIL-PARSING
    private function processHtmlBodyToDocument()
    {

        if ($this->email->body !== null)
            $this->email->body_document = TempFile::UploadedFileFromRaw($this->email->body, "E-Mail.html", "text/html");

    }

    // MAIN-PROCESSORS
    protected function createExpense()
    {
        // Skipping executions: will not result in not saving Metadata to prevent usage of these conditions, to spam
        if (!$this->validateExpenseShouldProcess()) {
            Log::info('email parsing not active for this company: ' . $this->company->id . ' from: ' . $this->email->from);
            return;
        }
        if (!$this->validateExpenseSender()) {
            Log::info('invalid sender of an ingest email to company: ' . $this->company->id . ' from: ' . $this->email->from);
            return;
        }
        if (sizeOf($this->email->documents) == 0) {
            Log::info('email does not contain any attachments and is likly not an expense. company: ' . $this->company->id . ' from: ' . $this->email->from);
            return;
        }

        // create expense
        $expense = ExpenseFactory::create($this->company->id, $this->company->owner()->id);

        $expense->public_notes = $this->email->subject;
        $expense->private_notes = $this->email->text_body;
        $expense->date = $this->email->date;

        // handle vendor assignment
        $expense_vendor = $this->getExpenseVendor();
        if ($expense_vendor)
            $expense->vendor_id = $expense_vendor->id;

        // handle documents
        $this->processHtmlBodyToDocument();
        $documents = [];
        array_push($documents, ...$this->email->documents);
        if ($this->email->body_document !== null)
            array_push($documents, $this->email->body_document);

        $expense->saveQuietly();

        $this->saveDocuments($documents, $expense);

        event(new ExpenseWasCreated($expense, $expense->company, Ninja::eventVars(null))); // @turbo124 please check, I copied from API-Controller
        event('eloquent.created: App\Models\Expense', $expense); // @turbo124 please check, I copied from API-Controller
    }

    // HELPERS
    private function validateExpenseShouldProcess()
    {
        return $this->company?->expense_mailbox_active ?: false;
    }
    private function validateExpenseSender()
    {
        $parts = explode('@', $this->email->from);
        $domain = array_pop($parts);

        // whitelists
        $email_whitelist = explode(",", $this->company->expense_mailbox_whitelist_emails);
        if (in_array($this->email->from, $email_whitelist))
            return true;
        $domain_whitelist = explode(",", $this->company->expense_mailbox_whitelist_domains);
        if (in_array($domain, $domain_whitelist))
            return true;
        $email_blacklist = explode(",", $this->company->expense_mailbox_blacklist_emails);
        if (in_array($this->email->from, $email_blacklist))
            return false;
        $domain_blacklist = explode(",", $this->company->expense_mailbox_blacklist_domains);
        if (in_array($domain, $domain_blacklist))
            return false;

        // allow unknown
        if ($this->company->expense_mailbox_allow_unknown && sizeOf($email_whitelist) == 0 && sizeOf($domain_whitelist) == 0) // from unknown only, when no whitelists are defined
            return true;

        // own users
        if ($this->company->expense_mailbox_allow_company_users && $this->company->users()->where("email", $this->email->from)->exists())
            return true;

        // from clients/vendors (if active)
        if ($this->company->expense_mailbox_allow_vendors && $this->company->vendors()->where("invoicing_email", $this->email->from)->orWhere("invoicing_domain", $domain)->exists())
            return true;
        if ($this->company->expense_mailbox_allow_vendors && $this->company->vendors()->contacts()->where("email", $this->email->from)->exists())
            return true;

        // denie
        return false;
    }
    private function getExpenseVendor()
    {
        $parts = explode('@', $this->email->from);
        $domain = array_pop($parts);

        $vendor = Vendor::where("company_id", $this->company->id)->where('invoicing_email', $this->email->from)->first();
        if ($vendor == null)
            $vendor = Vendor::where("company_id", $this->company->id)->where("invoicing_domain", $domain)->first();
        if ($vendor == null) {
            $vendorContact = VendorContact::where("company_id", $this->company->id)->where("email", $this->email->from)->first();
            $vendor = $vendorContact->vendor();
        }

        return $vendor;
    }
}
