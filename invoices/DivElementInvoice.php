<?php

use GmiChromeManager;


class DivElementInvoice
{

    public $exts;
    public $isNoInvoice = true;
    public $invoicePageUrl = 'YOUR INVOICE PAGE URL';


    public function __construct()
    {
        $this->exts = new GmiChromeManager();
    }

    /**
     * Change the div element according to 
     * your requirments
     */
    public function processNewPortalInvoice()
    {
        $this->exts->openUrl($this->invoicePageUrl);
        $this->exts->waitTillPresent('div[class="row ng-star-inserted"]');

        if (!$this->exts->exists('div[class="row ng-star-inserted"]')) {
            $this->checkFillLogin();
            $this->exts->waitTillPresent('div[class="row ng-star-inserted"]');
        }

        $rows = $this->exts->getElements('div[class="row ng-star-inserted"]  div[class*="status-card"]');
        $this->exts->log('Billing Count ' . count($rows));
        foreach ($rows  as $key => $row) {
            $row->click();
            sleep(10);
            $exportBtn = $this->exts->getElements('div[class*="control-bar"] button[class*="trigger-export"]')[3];

            $this->exts->click_element($exportBtn);
            sleep(4);

            $pdfBtn = $this->exts->getElements('div[class="export-panel overlay"] input[value="pdf"]')[3];
            $this->exts->click_element($pdfBtn);
            sleep(2);
            $downloadBtn =  $this->exts->getElements('div[class="col-buttons border-top"] button[class="btn btn-primary btn-block"]')[3];


            $url = $this->exts->getUrl();
            preg_match('/billing\/(\d+)/', $url, $matches);
            $number = $matches[1] ?? 'Invoice_' . $key; // use custom name if getting null

            $invoiceName = $number;
            $invoiceDate = '';
            $invoiceAmount = '';
            $parsed_date = '';
            $invoiceFileName = $invoiceName . '.pdf';
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('Date parsed: ' . $parsed_date);


            $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            $this->isNoInvoice = false;
        }
    }
}
