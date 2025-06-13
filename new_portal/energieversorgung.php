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
    public $baseUrl = 'https://kundencenter.energieversorgung-sylt.de/';
    public $loginUrl = 'https://kundencenter.energieversorgung-sylt.de/';
    public $invoicePageUrl = '';

    public $username_selector = 'input[name="login"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button#loginButton';

    public $check_login_failed_selector = 'div#loginMessage p';
    public $check_login_success_selector = 'a[href="#tabs:logout"]';

    public $isNoInvoice = true;
    public $restrictPages = 3;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(5);

        $this->exts->waitTillPresent('a#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');

        if ($this->exts->exists('a#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
            $this->exts->click_by_xdotool('a#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            sleep(7);
        }

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->log('restrictPages:: ' .  $this->restrictPages);

        $this->exts->loadCookiesFromFile();
        sleep(1);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");


            if ($this->exts->querySelector('a[href="invoice"][id="tabs:startForm:invoice"]') != null) {
                $this->exts->click_by_xdotool('a[href="invoice"][id="tabs:startForm:invoice"]');
                sleep(10);
            }

            $this->dateRange();

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
            if (stripos($error_text, strtolower('Bitte überprüfen Sie Ihre Zugangsdaten')) !== false) {
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
            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    public function dateRange()
    {
        $selectDate = new DateTime();
        $currentDate = $selectDate->format('d.m.Y');

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        if ($restrictPages == 0) {
            $selectDate->modify('-3 years');
            $formattedDate = $selectDate->format('d.m.Y');
            $this->exts->capture('date-range-3-years');
        } else {
            $selectDate->modify('-3 months');
            $formattedDate = $selectDate->format('d.m.Y');
            $this->exts->capture('date-range-3-months');
        }
        $this->exts->log('formattedDate::  ' . $formattedDate);
        $this->exts->log('currentDate::  ' . $currentDate);

        $this->exts->moveToElementAndType('input[name="tabs:invoiceForm:invoiceDateFrom_input"]', $formattedDate);
        sleep(2);
        $this->exts->moveToElementAndType('input[name="tabs:invoiceForm:invoiceDateTo_input"]', $currentDate);
        sleep(2);
        $this->exts->click_by_xdotool('button[id="tabs:invoiceForm:filterSubmit"]');
        sleep(7);

        $this->downloadInvoices();
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    public $totalInvoices = 0;

    private function downloadInvoices($count = 1)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table tbody tr');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('table tbody tr');
        foreach ($rows as $key => $row) {
            $invoiceBtn = $this->exts->getElement('a', $row);
            if ($invoiceBtn != null) {
                if ($this->totalInvoices >= 100) {
                    return;
                }
                sleep(2);
                $invoiceUrl = '';
                $invoiceName =   $this->exts->extract('td:nth-child(3)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(7)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(6)', $row);

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' .  $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' .  $invoiceUrl);
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' .  $invoiceDate);

                $downloaded_file = $this->exts->click_and_download($invoiceBtn, 'pdf', $invoiceFileName);
                sleep(5);
                $this->waitFor('table tbody tr');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceUrl,  $invoiceDate, $invoiceAmount, $downloaded_file);
                    sleep(1);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
        $pagiantionSelector = 'span[class="ui-paginator-next ui-state-default ui-corner-all"]';

        if ($this->exts->querySelector($pagiantionSelector) != null) {
            $this->exts->click_by_xdotool($pagiantionSelector);
            sleep(7);
            $count++;
            $this->downloadInvoices($count);
        }
    }
}
