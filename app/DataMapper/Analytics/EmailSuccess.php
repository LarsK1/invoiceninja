<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\DataMapper\Analytics;

class EmailSuccess
{

    /**
     * The type of Sample.
     *
     * Monotonically incrementing counter
     *
     *  - counter
     *
     * @var string
     */
    public $type = 'mixed_metric';

    /**
     * The name of the counter.
     * @var string
     */
    public $name = 'job.success.email';

    /**
     * The datetime of the counter measurement.
     *
     * date("Y-m-d H:i:s")
     *
     * @var DateTime
     */
    public $datetime;

    /**
     * The Class failure name
     * set to 0.
     *
     * @var string
     */
    public $string_metric5 = '';

    /**
     * The exception string
     * set to 0.
     *
     * @var string
     */
    public $string_metric6 = '';

    /**
     * The counter
     * set to 1.
     *
     * @var string
     */
    public $int_metric1 = 1;

    /**
     * Company Key
     * @var string
     */
    public $string_metric7 = '';

    public function __construct($string_metric7) {
        $this->string_metric7 = $string_metric7;
    }
}
