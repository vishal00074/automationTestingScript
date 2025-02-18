<?php

use GmiChromeManager;


class TableInvoice
{

    public $exts;
    public $isNoInvoice = true;
    public $invoicePageUrl = 'YOUR INVOICE PAGE URL';
    

    public function __construct()
    {
        $this->exts = new GmiChromeManager();
    }

    /**
     * This function helps you to download invoices 
     * of table format page adjust according to your requirements
     */
    public function downloadInvoice()
    {
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = count($this->exts->getElements('table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('button[onclick*="/invoices"]', $tags[4]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('button[onclick*="/invoices"]', $tags[4]);
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = $invoiceName . '.pdf';

                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    if ($this->exts->exists('a[data-qa-id*="button__download"]')) {
                        $this->exts->log("Choose download invoice");
                        $this->exts->moveToElementAndClick('a[data-qa-id*="button__download"]');
                        sleep(15);
                    }
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
                $this->exts->openUrl($this->invoicePageUrl);
            }
        }
    }
}
