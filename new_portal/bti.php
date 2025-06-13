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
    public $baseUrl = 'https://www.bti.de/shop-de/self-service/invoice/';
    public $loginUrl = 'https://www.bti.de/shop-de/self-service/invoice/';
    public $invoicePageUrl = 'https://www.bti.de/shop-de/self-service/invoice/';

    public $username_selector = 'input[id="login_email"]';
    public $password_selector = 'input[id="login_password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"].js-login';

    public $check_login_failed_selector = 'form[name="login"] div[class="berner-validation error berner-validation-badCredentials"]';
    public $check_login_success_selector = 'form#invoiceDownloadFilterForm';

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
        sleep(10);

        $this->exts->waitTillPresent('aside#usercentrics-cmp-ui');


        $this->exts->execute_javascript('
                var shadow = document.querySelector("#usercentrics-cmp-ui");
                if(shadow){
                    shadow.shadowRoot.querySelector(\'button#accept\').click();
                }
            ');

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
            if (stripos($error_text, strtolower('Invalid username/email or password. Please try again.')) !== false) {
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
        $currentDate = $selectDate->format('d.m.y');

        if ($this->restrictPages == 0) {
            $selectDate->modify('-3 years');
            $formattedDate = $selectDate->format('d.m.y');
            $this->exts->capture('date-range-3-years');
        } else {
            $selectDate->modify('-3 months');
            $formattedDate = $selectDate->format('d.m.y');
            $this->exts->capture('date-range-3-months');
        }
        $this->exts->log('formattedDate::  ' . $formattedDate);
        $this->exts->log('currentDate::  ' . $currentDate);

        $this->exts->moveToElementAndType('input[name="orderDateFrom"]', $formattedDate);
        sleep(2);

        $this->exts->click_by_xdotool("button#searchInvoice");
        sleep(7);

        $this->downloadInvoices();
    }

    private function downloadInvoices($count = 1)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('div.SSP-flInvoiceDownload div[class="SSP-flInvoiceDownload-item"]');
        if (count($this->exts->getElements('div.SSP-flInvoiceDownload div[class="SSP-flInvoiceDownload-item"]')) == 0) {
            $this->exts->type_key_by_xdotool('Return');
            sleep(7);
            $this->exts->type_key_by_xdotool('Return');
            sleep(7);
            $this->exts->type_key_by_xdotool('Return');
            sleep(7);
            $this->exts->waitTillPresent('div.SSP-flInvoiceDownload div[class="SSP-flInvoiceDownload-item"]');
        }
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('div.SSP-flInvoiceDownload div[class="SSP-flInvoiceDownload-item"]');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('a', $row);
            if ($invoiceLink != null) {
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $invoiceName = $this->exts->extract('div.SSP-flInvoiceDownload-info-value_orderNumber', $row);
                $invoiceDate = $this->exts->extract('div.SSP-flInvoiceDownload-info-item:nth-child(1)', $row);
                $invoiceAmount = $this->exts->extract('div.SSP-flInvoiceDownload-info-item:nth-child(3)', $row);

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
}
