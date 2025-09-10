<?php
// Server-Portal-ID: 26542 - Last modified: 21.01.2025 14:55:35 UTC - User: 1

// Script here
public $baseUrl = 'https://eshop.wuerth.de/de/DE/EUR/';
public $loginUrl = 'https://eshop.wuerth.de/de/DE/EUR/';
public $invoicePageUrl = '';

public $username_selector = 'input#LoginForm_CustomerNumber';
public $password_selector = 'input#LoginForm_Password';
public $remember_me_selector = '';
public $submit_login_selector = 'form[name="LoginForm"] button[name="LoginForm.update"]';

public $check_login_failed_selector = 'a#buttonLoginAgain';
public $check_login_success_selector = 'a#test_logout';

public $isNoInvoice = true;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');
    
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if($this->exts->getElement($this->check_login_success_selector) == null) {
        if($this->exts->exists('button.js-yes-push-btn')) {
            $this->exts->moveToElementAndClick('button.js-yes-push-btn');
            sleep(5);
        }
        $this->accept_cookies();

        $this->exts->moveToElementAndClick('a#headerUser.header-user-login');
        sleep(10);
        $this->checkFillLogin();
        sleep(5);
        $this->exts->waitTillPresent($this->check_login_success_selector, 30);
    }
    
    // then check user logged in or not
    if($this->exts->getElement($this->check_login_success_selector) != null) {
        $this->exts->log(__FUNCTION__.'::User logged in');
        $this->exts->capture("3-login-success");
        
        // Open invoices url and download invoice
        $this->invoicePageUrl = $this->exts->getElements('a#header_myAccount')[0]->getAttribute("href");
        $this->invoicePageUrl = str_replace("ViewMyAccount-Overview", "ViewInvoice-CheckAccess", $this->invoicePageUrl);
        $this->exts->openUrl($this->invoicePageUrl);
        $this->exts->waitTillAnyPresent(['input#InvoiceVerificationForm_VerificationNumber', 'table#data_table > tbody > tr a[href*="Invoice"]', 'input#allDocuments']);
        $this->accept_cookies();
        
        if($this->exts->exists('input#InvoiceVerificationForm_VerificationNumber')){
            // Sometime website ask for invoice number of last 3 months
            // You can get this number in config array 'download_invoices'
            // if this is empty then ask number as 2FA
            $this->exts->capture("invoice-verify_number-required");
            $checking_invoice_number = '';
            if(isset($this->exts->config_array['download_invoices']) && count($this->exts->config_array['download_invoices']) > 0){
                $checking_invoice_number = end($this->exts->config_array['download_invoices']);
                $this->exts->moveToElementAndType('input#InvoiceVerificationForm_VerificationNumber', $checking_invoice_number);
                sleep(1);
                $this->exts->capture("input-verify_number-inputted-1");
                $this->exts->moveToElementAndClick('.submitButton[name="invoices"]');
                sleep(7);
                $this->exts->capture("input-verify_number-submitted-1");
            }

            if(empty($checking_invoice_number) || stripos($this->exts->extract('#invoiceVerificationForm .errorMessage', null, 'innerText'), 'ltige Rechnungsnummer an') !== false){
                $this->exts->two_factor_notif_msg_en = 'For security reasons, you must verify yourself as an employee with access to the receipts. Please enter an invoice number from the last 3 months';
                $this->exts->two_factor_notif_msg_de = 'Geben Sie dazu bitte eine Rechnungsnummer der letzten 3 Monate ein';
                $this->exts->notification_uid = "";
                $checking_invoice_number = trim($this->exts->fetchTwoFactorCode());

                if(!empty($checking_invoice_number)){
                    $this->exts->moveToElementAndType('input#InvoiceVerificationForm_VerificationNumber', $checking_invoice_number);
                    $this->exts->capture("input-verify_number-inputted-2");
                    $this->exts->moveToElementAndClick('.submitButton[name="invoices"]');
                    sleep(7);
                    $this->exts->capture("input-verify_number-submitted-2");
                }
                sleep(5);
            }

            if(empty($checking_invoice_number) || stripos($this->exts->extract('#invoiceVerificationForm .errorMessage', null, 'innerText'), 'ltige Rechnungsnummer an') !== false){
                $this->exts->no_permission();
            }
        }

        if(!$this->exts->exists('input#InvoiceVerificationForm_VerificationNumber')){
            $dateTo = date('d.m.Y');
            $dateFrom = date('d.m.Y',strtotime('-1 years'));
            $this->exts->moveToElementAndClick('input#allDocuments');
            sleep(2);
            $this->exts->moveToElementAndType('input#datepickerFrom', $dateFrom);
            sleep(1);
            $this->exts->moveToElementAndType('input#datepickerTo', $dateTo);
            sleep(1);
            if($this->exts->exists('a#showAllDocuments')) {
                $this->exts->moveToElementAndClick('a#showAllDocuments');
            } else if($this->exts->exists('input#showAllDocuments')) {
                $this->exts->moveToElementAndClick('input#showAllDocuments');
            } else {
                $this->exts->moveToElementAndClick('#showAllDocuments');
            }
            
            $this->processInvoices();
        }
        // Final, check no invoice
        if($this->isNoInvoice){
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__.'::Use login failed');
        if($this->exts->exists('div.loginErrorMessageBox')){
            $meg_loginfail = $this->exts->extract('div.loginErrorMessageBox', null, 'innerText');
            $this->exts->log('message login failed: ' . $meg_loginfail);
            if((strpos($meg_loginfail, 'haben Sie sich wahrscheinlich vertippt.') !== false && strpos($meg_loginfail, 'Passwort') !== false)
                || strpos($meg_loginfail, 'oder das angegebene Passwort ist nicht korrekt') !== false) {
                $this->exts->loginFailure(1);
            }
        }
        if($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin() {
    $this->exts->moveToElementAndClick('a#headerUser');

    $this->exts->waitTillPresent($this->password_selector, 15);
    
    if($this->exts->getElement($this->password_selector) != null) {
        $this->exts->waitTillPresent($this->username_selector, 15);
        $this->exts->capture("2-login-page");
        
        $this->exts->log("Enter Username");
        $this->exts->log($this->username);
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(5);

        $this->exts->log("Enter Password");
        $this->exts->log($this->password);
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(5);
        
        $this->exts->log("Enter Partner number");
        $partner_number = isset($this->exts->config_array["PARTNERNUMBER"]) ? trim($this->exts->config_array["PARTNERNUMBER"]) : (isset($this->exts->config_array["partnernumber"]) ? trim($this->exts->config_array["partnernumber"]) : $this->exts->config_array["partnernumber"]);
        //i have hard code partnernumber, pls remove it, thanks
        //$partner_number = '1722710';
        $this->exts->moveToElementAndType('input#LoginForm_Login', $partner_number);
        sleep(5);
        
        if($this->remember_me_selector != '') {
            $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(5);
        }
        
        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        
    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function processInvoices($pageCount=1) {
    sleep(5);
    $this->exts->waitTillPresent('table#data_table > tbody > tr a[href*="Invoice"]');
    $this->exts->capture("4-invoices-page");
    $invoices = [];
    
    $rows = $this->exts->getElements('table#data_table > tbody > tr');
    if(count($rows) > 0) {
        if($this->exts->exists('table#data_table thead th[aria-label*="Datum"][aria-label*="descending"]')) {
            $this->exts->moveToElementAndClick('table#data_table thead th[aria-label*="Datum"][aria-label*="descending"]');
            sleep(2);
            $rows = $this->exts->getElements('table#data_table > tbody > tr');
        }
    }
    $this->exts->capture("4.1-invoices-page");
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        if(count($tags) >= 5 && $this->exts->getElement('a[href*="Invoice"]', $row) != null) {
            $invoiceUrl = $this->exts->getElement('a[href*="Invoice"]', $row)->getAttribute("href");
            $invoiceName = trim($tags[0]->getAttribute('innerText'));
            $invoiceDate = trim($tags[2]->getAttribute('innerText'));
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' EUR';
            
            array_push($invoices, array(
                'invoiceName'=>$invoiceName,
                'invoiceDate'=>$invoiceDate,
                'invoiceAmount'=>$invoiceAmount,
                'invoiceUrl'=>$invoiceUrl
            ));
            $this->isNoInvoice = false;
        }
    }
    
    // Download all invoices
    $this->exts->log('Invoices found: '.count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: '.$invoice['invoiceName']);
        $this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
        
        $invoiceFileName = $invoice['invoiceName'].'.pdf';
        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
        $this->exts->log('Date parsed: '.$invoice['invoiceDate']);
        
        $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
        if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
        } else {
            $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        }
    }
    
    // next page
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if($restrictPages == 0 && $pageCount < 50 && $this->exts->getElement('a.paginate_button.next:not(.disabled)') != null){
        $pageCount++;
        $this->exts->moveToElementAndClick('a.paginate_button.next:not(.disabled)');
        sleep(10);
        $this->processInvoices($pageCount);
    }
}

function accept_cookies() {
    if($this->exts->exists('div#cookieBanner button#saveCookieBanner, button#consent-buttonConfirm')) {
        $this->exts->moveToElementAndClick('div#cookieBanner button#saveCookieBanner, button#consent-buttonConfirm');
        sleep(5);
    }
}