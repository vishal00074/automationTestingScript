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
    public $baseUrl = 'https://service.trebono.de/admin/index.php';
    public $loginUrl = 'https://service.trebono.de/admin/index.php';
    public $invoicePageUrl = 'https://service.trebono.de/admin/module.php?load=billing&Section=invoice&ActiveTab=1&PageExportInvoice=1&Page=1';

    public $username_selector = 'input[id="email"]';
    public $password_selector = 'input[id="password"]';
    public $remember_me_selector = 'input[name="RememberMe"]';
    public $submit_login_selector = 'input[type="submit"]';

    public $check_login_failed_selector = 'div[class="alert alert-error"]';
    public $check_login_success_selector = 'a[href="/admin/index.php?Logout=Y"]';

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
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->openUrl($this->invoicePageUrl);
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
            if (stripos($error_text, 'incorrect login/password!') !== false) {
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

    private function downloadInvoices($count = 1)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table tbody tr');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('table tbody tr');
        $currentUrl = $this->exts->getUrl();
        $isNextPageFound = true;
        $paginationSel = $this->exts->getElement('div#tab-1 ul.pagination li.active a');
        try {
            $paginationsUrl = $paginationSel->getAttribute("href");
            $this->exts->log(__FUNCTION__.'::currentUrl '. $currentUrl);
            $this->exts->log(__FUNCTION__.'::paginationsUrl '. $paginationsUrl);

            if ($currentUrl != $paginationsUrl) {
                $isNextPageFound = false;
            }
        } catch (\Exception $e) {
            $this->exts->log('Error in pagination handling::  ' . $e->getMessage());
        }



        if ($isNextPageFound) {
            foreach ($rows as $key => $row) {
                $invoiceLink = $this->exts->getElement('td a', $row);
                if ($invoiceLink != null) {
                    $invoiceUrl = $invoiceLink->getAttribute("href");
                    parse_str(parse_url($invoiceUrl, PHP_URL_QUERY), $params);
                    // Get the invoice_id
                    $invoiceName = $params['invoice_id'] ?? null;

                    if ($invoiceName != null) {
                        $invoiceDate = $this->exts->extract('td:nth-child(3)', $row);
                        $invoiceAmount = $this->exts->extract('td:nth-child(5)', $row);

                        array_push($invoices, array(
                            'invoiceName' => $invoiceName,
                            'invoiceDate' => $invoiceDate,
                            'invoiceAmount' => $invoiceAmount,
                            'invoiceUrl' => $invoiceUrl,
                        ));
                        $this->isNoInvoice = false;
                    }
                }
            }

            $this->exts->log('Invoices found: ' . count($invoices));
            foreach ($invoices as $invoice) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                $invoiceFileName = $invoice['invoiceName'] . '.pdf';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

            if ($count < $restrictPages && $this->exts->exists('ul.pagination li')) {
                $count++;
                $nextPageLink = 'https://service.trebono.de/admin/module.php?load=billing&Section=invoice&ActiveTab=1&PageExportInvoice=1&Page=' . $count;

                $this->exts->log(__FUNCTION__ . 'Next page Link ' . $nextPageLink);
                $this->exts->openUrl($nextPageLink);
                sleep(7);
                $this->downloadInvoices($count);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Last Invoice URL: ' . $this->exts->getUrl());
            $this->exts->log(__FUNCTION__ . 'Invoice page not found');
        }
    }
}
