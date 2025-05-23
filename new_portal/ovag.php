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
    public $baseUrl = 'https://onlineservice.ovag.de/';
    public $loginUrl = 'https://onlineservice.ovag.de/sap/bc/ui5_ui5/sap/zmcf_ui/index.html?CompanyID=OVAG#/invoices';
    public $invoicePageUrl = 'https://onlineservice.ovag.de/sap/bc/ui5_ui5/sap/zmcf_ui/index.html?CompanyID=OVAG#/invoices';

    public $username_selector = 'input[id*="UsernameInput"]';
    public $password_selector = 'input[id*="PasswordInput"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[id*="button"]';

    public $check_login_failed_selector = 'div.sapMDialogError  span.sapMText';
    public $check_login_success_selector = 'div.pull-right a';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->loadCookiesFromFile();

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
        }

        $this->exts->waitTillPresent('table tbody tr:nth-child(1) a');

        if ($this->exts->exists('table tbody tr:nth-child(1) a')) {
            $this->exts->moveToElementAndClick('table tbody tr:nth-child(1) a');
            sleep(5);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('Client, name, or password is not correct; log on again')) !== false) {
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
                sleep(2);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
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

            if ($this->exts->getElementByText($this->check_login_success_selector, ['wechseln'], null, true) && !$this->exts->exists($this->password_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function processInvoices($count = 0)
    {
        $this->exts->waitTillPresent('table tbody tr:nth-child(1) a');

        $rows = $this->exts->getElements('table tbody tr');
        foreach ($rows as $key => $row) {
            $buttonSel = $key + 1;
            $this->exts->waitTillPresent('table tbody tr:nth-child(' . $buttonSel . ') a');

            if ($this->exts->exists('table tbody tr:nth-child(' . $buttonSel . ') a')) {
                $this->exts->moveToElementAndClick('table tbody tr:nth-child(' . $buttonSel . ') a');
                sleep(5);
            }
            $this->downloadInvoices();

            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->moveToElementAndClick($this->check_login_success_selector);
                sleep(5);
            }
        }
    }

    private function downloadInvoices($count = 1)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table tbody tr');
        $this->exts->capture("4-invoices-classic");

        $rows = $this->exts->getElements('table tbody tr');
        foreach ($rows as $key => $row) {
            $invoiceButton = $this->exts->getElement('td:nth-child(5) button', $row);
            if ($invoiceButton != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('td:nth-child(2)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(3)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(4)', $row);

                $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf' : '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);
                $this->isNoInvoice = false;

                $downloaded_file = $this->exts->click_and_download($invoiceButton, 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceUrl, $invoiceDate, $invoiceAmount, $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
}
