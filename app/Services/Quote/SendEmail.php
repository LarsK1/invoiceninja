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

namespace App\Services\Quote;

use App\Jobs\Entity\EmailEntity;
use App\Models\ClientContact;
use App\Services\Email\MailEntity;
use App\Services\Email\MailObject;

class SendEmail
{
    public $quote;

    protected $reminder_template;

    protected $contact;

    public function __construct($quote, $reminder_template = null, ClientContact $contact = null)
    {
        $this->quote = $quote;

        $this->reminder_template = $reminder_template;

        $this->contact = $contact;
    }

    /**
     * Builds the correct template to send.
     * @return void
     */
    public function run()
    {
        nlog($this->reminder_template);
        nlog("is there a template");

        if (! $this->reminder_template) {
            $this->reminder_template = $this->quote->calculateTemplate('quote');
        }

        $mo = new MailObject();

        $this->quote->service()->markSent()->save();

        $this->quote->invitations->each(function ($invitation) use ($mo) {
            if (! $invitation->contact->trashed() && $invitation->contact->email) {
                EmailEntity::dispatch($invitation, $invitation->company, $this->reminder_template);

                // MailEntity::dispatch($invitation, $invitation->company->db, $mo);
            }
        });
    }
}
