<?php

use GmiChromeManager;

class ProcessInvoiceApi
{
    public $exts;

    public function __construct()
    {
        $this->exts = new GmiChromeManager;
    }



    public function processInvoiceViaApi()
    {
        $customerId = trim(array_pop(explode(': ', $this->exts->extract('h2#kdnr', null, 'innerText'))));
        $contracts = $this->exts->execute_javascript('
        var data = [];
        try{
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "https://www.freenet-mobilfunk.de/api/ps/customers/' . $customerId . '/contracts", false);
        xhr.withCredentials = true;
        xhr.setRequestHeader("authorization", "Bearer " + document.cookie.split("accessToken=").pop().split(";")[0].trim());
        xhr.send();
        var jo = JSON.parse(xhr.responseText);
        var contracts = Object.keys(jo.contracts);
        data = contracts;
        } catch(ex){
        console.log(ex);
        }
        return data;
        ');
            $this->exts->log('contracts found: ' . json_encode($contracts));

            $invoices = [];
            foreach ($contracts as $contract) {
                $invoiceList = $this->exts->execute_javascript('
        var data = [];
        var invoice_names = [];
        try{
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "https://www.freenet-mobilfunk.de/api/ps/contracts/' . $contract . '/invoices?invoiceStartDate=&invoiceEndDate=&monthsIntervall=50", false);
        xhr.withCredentials = true;
        xhr.setRequestHeader("authorization", "Bearer " + document.cookie.split("accessToken=").pop().split(";")[0].trim());
        xhr.send();
        var jo = JSON.parse(xhr.responseText);
        var invoices = jo.data;
        for (var i = 0; i < invoices.length; i ++) {
        var inv=invoices[i];
        if(invoice_names.indexOf(inv.invoiceNumber) < 0){
        var dt=new Date(inv.invoiceDate*1000);
        data.push({
        invoiceName: inv.invoiceNumber,
        invoiceDate: dt.toISOString().split("T")[0],
        invoiceAmount: inv.amountTotal + " EUR" ,
        invoiceUrl: "https://www.freenet-mobilfunk.de/api" + inv.uri
        });
        invoice_names.push(inv.invoiceNumber);
        }
        }

        } catch(ex){
        console.log(ex);
        }
        return data; ');

            $invoices = array_merge($invoices, $invoiceList);
        }
        // Download all invoices
        $this->exts->log(' Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->isNoInvoice = false;
            $this->exts->log(' --------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                // Get pdf from api
                $this->exts->execute_javascript('
            var xhr = new XMLHttpRequest();
            xhr.open("GET", "' . $invoice['invoiceUrl'] . '", false);
            xhr.setRequestHeader("Authorization", "Bearer " + document.cookie.split("accessToken=").pop().split(";")[0].trim());
            xhr.setRequestHeader("Accept", "application/pdf");
            xhr.overrideMimeType("text/plain; charset=x-user-defined");
            xhr.send();

            var byteCharacters = xhr.responseText;
            var byteArrays = [];
            for (var offset = 0; offset < byteCharacters.length; offset +=512) {
            var slice=byteCharacters.slice(offset, offset + 512);
            var byteNumbers=new Array(slice.length);
            for (var i=0; i < slice.length; i++) {
            byteNumbers[i]=slice.charCodeAt(i);
            }
            var byteArray=new Uint8Array(byteNumbers);
            byteArrays.push(byteArray);
            }
            var blob=new Blob(byteArrays, {type: "application/pdf" });
            window.open(window.URL.createObjectURL(blob), "_blank" ); ');
                sleep(1);
                $this->exts->wait_and_check_download(' pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $pdf_content = file_get_contents($downloaded_file);
                    if (stripos($pdf_content, "%PDF") !== false) {
                        $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . ":: Not Valid PDF - " . $downloaded_file);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
}
