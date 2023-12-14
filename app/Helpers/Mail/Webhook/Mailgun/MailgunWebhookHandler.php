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

namespace App\Helpers\Mail\Webhook\Maigun;

use App\Helpers\Mail\Webhook\BaseWebhookHandler;

interface MailgunWebhookHandler extends BaseWebhookHandler
{
    public function process()
    {

    }
}
