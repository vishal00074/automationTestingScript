<?php

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package uwa
 *
 * @copyright   GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/remote-chrome/utils/');

$gmi_browser_core = realpath('/var/www/remote-chrome/utils/GmiChromeManager.php');
require_once($gmi_browser_core);
class PortalScriptCDP
{

    private $exts;
    public $setupSuccess = false;
    private $chrome_manage;
    private $username;
    private $password;

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/remote-chrome/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $username, $password);
        $this->setupSuccess = true;
    }

    /*Define constants used in script*/
    public $baseUrl = 'https://app.neuronwriter.com/';
    public $loginUrl = 'https://app.neuronwriter.com/';
    public $invoicePageUrl = 'https://app.neuronwriter.com/ucp/profile';

    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = 'input[name="remember-me"]';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'form div:not(.form-group):not(.d-block):not(.float-right):not(.invalid-feedback):not(.form-check)';
    public $check_login_success_selector = 'ul#account-menu a[href="/ucp/logout"]';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        $this->exts->loadCookiesFromFile();

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->openUrl($this->invoicePageUrl);

            $this->exts->waitTillPresent('a[id="subscription-tab"]');

            if ($this->exts->exists('a[id="subscription-tab"]')) {
                $this->exts->moveToElementAndClick('a[id="subscription-tab"]');
                sleep(4);
            }

            if ($this->exts->exists('a[href="/ucp/stripe-portal"]')) {
                $this->exts->moveToElementAndClick('a[href="/ucp/stripe-portal"]');
                sleep(10);
            }


            $this->downloadInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
            if (
                stripos($error_text, "the email you've entered doesn't match any account.") !== false ||
                stripos($error_text, "the e-mail and password don't match.") !== false
            ) {

                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);

        $this->exts->waitTillPresent($this->username_selector);
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("1-login-page-filled");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(5);
            }
        }
    }
    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    public  function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function downloadInvoices($count = 0)
    {
        $this->exts->log(__FUNCTION__);

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $i = 0;
        while ($i < $restrictPages && $this->exts->exists('button[data-testid="view-more-button"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="view-more-button"]');
            sleep(4);
            $i++;
        }

        $this->exts->waitTillPresent('div.Box-root a[href*="invoice.stripe.com"]');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('div.Box-root a[href*="invoice.stripe.com"]');
        foreach ($rows as $key => $row) {
            $invoiceUrl = $row->getAttribute("href");
            array_push($invoices, array(
                'invoiceUrl' => $invoiceUrl,
            ));
            $this->isNoInvoice = false;
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(7);
            $invoiceName = time();
            $invoiceDate = '';
            $invoiceAmount = $this->exts->extract('div.InvoiceSummaryPostPayment span.CurrencyAmount');
            $this->exts->waitTillPresent('table.InvoiceDetails-table tbody tr.LabeledTableRow--wide');
            $invoiceRows = $this->exts->getElements('table.InvoiceDetails-table tbody tr.LabeledTableRow--wide');
            if(count($invoiceRows) >= 3){
                $invoiceName = $this->exts->extract('td[style*="right"] span', $invoiceRows[0]);
                $invoiceDate = $this->exts->extract('td[style*="right"] span', $invoiceRows[1]);
            }
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
            $invoiceFileName = $invoiceName . '.pdf';
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            $downloaded_file = $this->exts->click_and_download('div.InvoiceDetailsRow-Container button[data-testid="download-invoice-receipt-pdf-button"]', 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceUrl'], $invoiceDate, $invoiceAmount, $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}
