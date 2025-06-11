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
    public $baseUrl = 'https://uk.interparcel.com/myaccount/';
    public $loginUrl = 'https://uk.interparcel.com/myaccount/';
    public $invoicePageUrl = 'https://uk.interparcel.com/myaccount/orders/';

    public $username_selector = 'input[id="accountName"]';
    public $password_selector = 'input[id="accountPassword"]';
    public $remember_me_selector = 'input[id="accountRemember"]';
    public $submit_login_selector = 'button[id="alertButtonOk"]';

    public $check_login_failed_selector = 'div#loginErrorMessage';
    public $check_login_success_selector = 'li#catMyAccount';

    public $isNoInvoice = true;
    public $restrictPages = 3;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->log('restrictPages:: ' .  $this->restrictPages);
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
            sleep(10);

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
            if (stripos($error_text, strtolower('Invalid username or password')) !== false) {
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

        if ($this->exts->querySelector('div#dateRangePicker') != null) {
            $this->exts->moveToElementAndClick('div#dateRangePicker');
            sleep(5);
        }

        if ($this->exts->querySelector('li[data-range-key="Custom Range"]') != null) {
            $this->exts->moveToElementAndClick('li[data-range-key="Custom Range"]');
            sleep(5);
        }

        $selectDate = new DateTime();
        $currentDate = $selectDate->format('M Y');

        if ($this->restrictPages == 0) {
            $selectDate->modify('-3 years');
            $formattedDate = $selectDate->format('M Y');
            $this->exts->capture('date-range-3-years');
        } else {
            $selectDate->modify('-3 months');
            $formattedDate = $selectDate->format('M Y');
            $this->exts->capture('date-range-3-months');
        }


        $stop = 0;
        while (true) {
            $calendarMonth = $this->exts->extract('div.left div.calendar-table tr th.month');
            $this->exts->log('previous currentMonth:: ' . trim($calendarMonth));
            $this->exts->log('previous formattedDate:: ' . trim($formattedDate));

            if (trim($calendarMonth) === trim($formattedDate)) {
                sleep(4);
                break;
            }

            $this->exts->moveToElementAndClick('th.prev.available');
            sleep(1);
            $stop++;

            if ($stop > 200) {
                break;
            }
        }

        $this->exts->moveToElementAndClick('div.left table.table-condensed tr:nth-child(1) td:nth-child(2)');
        sleep(5);

        $stop2  = 0;
        while (true) {
            $calendarMonth = $this->exts->extract('div.right div.calendar-table tr th.month');
            $this->exts->log('next currentMonth:: ' . trim($calendarMonth));
            $this->exts->log('next currentDate:: ' . trim($currentDate));

            if (trim($calendarMonth) === trim($currentDate)) {
                sleep(4);
                break;
            }

            $this->exts->moveToElementAndClick('th.next.available');
            sleep(1);

            $stop2++;
            if ($stop2 > 200) {
                break;
            }
        }

        $this->exts->moveToElementAndClick('td[class="today available"]');
        sleep(5);

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

        $this->exts->waitTillPresent('table#myOrders > tr');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('table#myOrders > tr');
        foreach ($rows as $key => $row) {
            $invoiceUrl = '';
            $invoiceName = $this->exts->extract('td.id', $row);
            $invoiceDate = $this->exts->extract('td.myordersDate', $row);
            $invoiceAmount = $this->exts->extract('td.myordersTotal', $row);

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl,
            ));
            $this->isNoInvoice = false;
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            if ($this->totalInvoices > 100) {
                return;
            }
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $url = 'https://uk.interparcel.com/myaccount/orders/' . $invoice['invoiceName'];

            $this->exts->openUrl($url);
            sleep(2);
            $this->waitFor('div#orderDetailDocuments div.downloadBox:nth-child(1)');

            $invoiceBtn = $this->exts->getElement('div#orderDetailDocuments div.downloadBox:nth-child(1)');

            $downloaded_file = $this->exts->click_and_download($invoiceBtn, 'pdf', $invoiceFileName);
            sleep(4);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceUrl,  $invoiceDate, $invoiceAmount, $downloaded_file);
                sleep(1);
                $this->totalInvoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}
