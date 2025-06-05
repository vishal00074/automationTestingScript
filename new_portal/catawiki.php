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
    public $baseUrl = 'https://www.catawiki.com/de/f/seller/payments';
    public $loginUrl = 'https://www.catawiki.com/de/user/login';
    public $invoicePageUrl = 'https://www.catawiki.com/de/f/seller/payments';

    public $username_selector = 'form input[type="text"]';
    public $password_selector = 'form input[type="password"]';
    public $remember_me_selector = 'form input[type="checkbox"]';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'div.error';
    public $check_login_success_selector = 'button[data-testid="display-username"]';

    public $isNoInvoice = true;
    public $restrictPages = 3;
    public $totalInvoices = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(12);

        if ($this->exts->exists('button#cookie_bar_agree_button')) {
            $this->exts->moveToElementAndClick('button#cookie_bar_agree_button');
            sleep(2);
        }

        $this->exts->loadCookiesFromFile();

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();

            if ($this->exts->exists('div[data-react-component-assets="Authentication"] button:nth-child(1)')) {
                $this->exts->moveToElementAndClick('div[data-react-component-assets="Authentication"] button:nth-child(1)');
                sleep(2);
            }

            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('button#cookie_bar_agree_button')) {
                $this->exts->moveToElementAndClick('button#cookie_bar_agree_button');
                sleep(5);
            }

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

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('Benutzername oder Passwort ungültig')) !== false) {
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


    private function dateRange()
    {
        $this->exts->waitTillPresent('input[name="created_after"]');
        $this->exts->capture('select-date-range');

        $selectDate = new DateTime();
        $currentDate = $selectDate->format('Y-m-d\TH:i');

        if ($this->restrictPages == 0) {
            $selectDate->modify('-3 years');
            $formattedDate = $selectDate->format('Y-m-d\TH:i');
            $this->exts->capture('date-range-3-years');
        } else {
            $selectDate->modify('-3 months');
            $formattedDate = $selectDate->format('Y-m-d\TH:i');
            $this->exts->capture('date-range-3-months');
        }

        $this->exts->log('restrictPages:: ' . $this->restrictPages);
        $this->exts->log('currentDate:: ' . $currentDate);
        $this->exts->log('fromDate:: ' . $formattedDate);

        $url = 'https://www.catawiki.com/de/f/seller/payments?paid_out_at_from=' . $formattedDate . '&paid_out_at_to=' . $currentDate;
        $this->exts->openUrl($url);
        sleep(10);
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function downloadInvoices($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log('totalInvoices  ::' . $this->totalInvoices);
        // date filter
        $this->dateRange();

        $this->exts->waitTillPresent('table tbody tr');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('table tbody tr');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('td:nth-child(1) a', $row);
            if ($invoiceLink != null) {
                $invoiceDetailUrl = $invoiceLink->getAttribute("href");

                array_push($invoices, array(
                    'invoiceUrl' => $invoiceDetailUrl
                ));
            }
        }

         $this->exts->log('Total Detail Invoices Link : ' . count($invoices));
        $invoicesDetail = [];
        foreach ($invoices as $invoice) {
            $invoiceLink = $this->exts->openNewTab($invoice['invoiceUrl']);
            sleep(5);
            $this->waitFor('table[data-testid="seller-payment-details-lots"]');

            $detailRows = $this->exts->getElements('table[data-testid="seller-payment-details-lots"] tbody tr');
            foreach ($detailRows as $detailRow) {
                $invoiceDetailLink = $this->exts->getElement('a[href*="pdf"]', $detailRow);
                if ($invoiceDetailLink != null) {
                    $invoiceUrl = $invoiceDetailLink->getAttribute("href");
                    $invoiceName = '';
                    preg_match('/Catawiki_(\d+)\.pdf$/', $invoiceUrl, $matches);

                    if (isset($matches[1])) {
                        $invoiceName = $matches[1];
                    }
                    $invoiceDate = '';
                    $invoiceAmount = $this->exts->extract('td:nth-child(7)', $detailRow);

                    array_push($invoicesDetail, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl,
                    ));
                    $this->isNoInvoice = false;

                    $this->exts->log('Invoices found In Row: ' . count($invoicesDetail));
                }
            }
            $this->exts->closeTab($invoiceLink);
            sleep(4);
        }

        $this->exts->log('Total Invoices found: ' . count($invoicesDetail));
        foreach ($invoicesDetail as $invoiceDetail) {
            if ($this->totalInvoices >= 100) {
                return;
            }
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceDetail['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoiceDetail['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoiceDetail['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoiceDetail['invoiceUrl']);

            $invoiceFileName = !empty($invoiceDetail['invoiceName']) ?  $invoiceDetail['invoiceName'] . '.pdf' : '';
            $invoiceDetail['invoiceDate'] = $this->exts->parse_date($invoiceDetail['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDetail['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoiceDetail['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceDetail['invoiceName'], $invoiceDetail['invoiceDate'], $invoiceDetail['invoiceAmount'], $invoiceFileName);
                $this->totalInvoices++;
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
        // pagination 
        $pagiantionSelector = 'a[data-testid="next-page-link"]';
        if ($this->exts->querySelector($pagiantionSelector) != null) {
            $this->exts->click_by_xdotool($pagiantionSelector);
            sleep(7);
            $count++;
            $this->downloadInvoices($count);
        }
    }
}
