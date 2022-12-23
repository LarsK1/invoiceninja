<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Pdf;

use App\Services\Pdf\PdfConfiguration;
use App\Services\Pdf\PdfService;
use Beganovich\Snappdf\Snappdf;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Services\Pdf\PdfService
 */
class PdfServiceTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testInitOfClass()
    {

        $invitation = $this->invoice->invitations->first();

        $service = new PdfService($invitation);

        $this->assertInstanceOf(PdfService::class, $service);

    }

    public function testEntityResolution()
    {

        $invitation = $this->invoice->invitations->first();
    
        $service = new PdfService($invitation);

        $this->assertInstanceOf(PdfConfiguration::class, $service->config);


    }

    public function testDefaultDesign()
    {
        $invitation = $this->invoice->invitations->first();
        
        $service = new PdfService($invitation);

        $this->assertEquals(2, $service->config->design->id);

    }
}