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

namespace App\Services\PdfMaker;

use \setasign\Fpdi\Fpdi;

class PdfMerge
{

    public function __construct(private array $file_paths) {}

    public function run()
    {

        $pdf = new FPDI();

        foreach ($this->file_paths as $file) {
            $pageCount = $pdf->setSourceFile($file);
            for ($i = 0; $i < $pageCount; $i++) {
                $tpl = $pdf->importPage($i + 1, '/MediaBox');
                $pdf->addPage();
                $pdf->useTemplate($tpl);
            }
        }

        return $pdf->Output('S');

    }

}
