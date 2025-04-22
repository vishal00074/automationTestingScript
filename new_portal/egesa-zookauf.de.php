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
    public $baseUrl = 'https://extranet.egesa-zookauf.de/SitePages/Home.aspx';
    public $loginUrl = 'https://extranet.egesa-zookauf.de/SitePages/Home.aspx';
    public $invoicePageUrl = '';

    public $username_selector = 'input[name="username"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input[type="submit"]';

    public $check_login_failed_selector = 'div.signInError';
    public $check_login_success_selector = 'a[aria-label="Sign Out"]';

    public $isNoInvoice = true;
    public $only_notice = 0;


    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->only_notice =  isset($this->exts->config_array["only_notice"]) ? (int)@$this->exts->config_array["only_notice"] : $this->only_notice;
        $this->exts->log('only_notice ' . $this->only_notice);

        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->loadCookiesFromFile();

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('a[href="/SitePages/my egesa.aspx"]')) {
                $this->exts->moveToElementAndClick('a[href="/SitePages/my egesa.aspx"]');
                sleep(5);
            }
            $this->exts->capture("open-menu-tab");

            if ($this->only_notice) {
                // Download only notice pdf 
                $this->exts->waitTillPresent('a[href*="Avis"]');
                if ($this->exts->exists('a[href*="Avis"]')) {
                    $this->exts->moveToElementAndClick('a[href*="Avis"]');
                    sleep(5);
                }

                $this->downloadNoticeInvoice();
            } else {
                // Download both notice pdf and regular pdf
                $this->exts->waitTillPresent('a[href*="Avis"]');
                if ($this->exts->exists('a[href*="Avis"]')) {
                    $this->exts->moveToElementAndClick('a[href*="Avis"]');
                    sleep(5);
                }

                $this->downloadNoticeInvoice();

                $this->exts->waitTillPresent('a[href*="Rechnungen"]');
                if ($this->exts->exists('a[href*="Rechnungen"]')) {
                    $this->exts->moveToElementAndClick('a[href*="Rechnungen"]');
                    sleep(5);
                }

                $this->processInvoiceYear();
            }


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
            if (stripos($error_text, strtolower('Bitte prüfen Sie Benutzername und Passwort und versuchen Sie es erneut')) !== false) {
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

    private function processInvoiceYear()
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->capture("process-years");

        $this->exts->waitTillPresent('table[summary="Rechnungen"] tbody td.ms-gb');

        $rows = $this->exts->getElements('table[summary="Rechnungen"] tbody td.ms-gb');
        foreach ($rows as $yearKey =>  $row) {
            $yearLink = $this->exts->getElement('a', $row);
            if ($yearLink != null) {
                $yearLink->click();
                sleep(5);
                $this->exts->capture("open-year-tab-" . $yearKey);
                // process Month
                $this->exts->waitTillPresent('table[summary="Rechnungen"] tbody[style=""] td.ms-gb2');

                $monthRows = $this->exts->getElements('table[summary="Rechnungen"] tbody[style=""] td.ms-gb2');
                foreach ($monthRows as $monthKey => $month) {
                    $monthLink = $this->exts->getElement('a', $month);
                    if ($monthLink != null) {
                        $monthLink->click();
                        sleep(5);
                        $this->exts->capture("open-month-tab-" . $monthKey);
                        // download invoices
                        $this->downloadInvoices();

                        // close month tab
                        $monthLink->click();
                        sleep(4);
                        $this->exts->capture("close-month-tab-" . $monthKey);
                    }
                }
                // close month tab
                $yearLink->click();
                sleep(4);
                $this->exts->capture("close-year-tab-" . $yearKey);
            }
        }
    }

    private function downloadInvoices($count = 1)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table[summary="Rechnungen"] tbody[isloaded="true"] tr');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('table[summary="Rechnungen"] tbody[isloaded="true"] tr');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('a[id*="ezInvoice"]', $row);
            if ($invoiceLink != null) {
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $invoiceName = $this->exts->extract('td:nth-child(7)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(8)', $row);
                $invoiceAmount = '';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ));
                $this->isNoInvoice = false;
            }
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
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
    }

    public function downloadNoticeInvoice($count = 0)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table tbody tr');
        $this->exts->capture("4-invoices-notice");

        $invoices = [];
        $rows = $this->exts->getElements('table tbody tr');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('a[href*=".pdf"]', $row);
            if ($invoiceLink != null) {
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $invoiceName = $this->exts->extract('td:nth-child(3)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(5)', $row);
                $invoiceAmount = '';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ));
                $this->isNoInvoice = false;
            }
        }

        $this->exts->log('Notice Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('Notice invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('Notice invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('Notice invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('Notice invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
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

        if ($count < $restrictPages && $this->exts->exists('table.ms-bottompaging a[title="Next"]')) {
            $this->exts->click_by_xdotool('table.ms-bottompaging a[title="Next"]');
            sleep(7);
            $count++;
            $this->downloadNoticeInvoice($count);
        }
    }
}
