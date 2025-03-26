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
    public $baseUrl = 'https://www.tink.de/';
    public $loginUrl = 'https://www.tink.de/customer/account/login';
    public $invoicePageUrl = 'https://www.tink.de/sales/order/history/';

    public $username_selector = 'div[class*="login-form"] input[name="email"]';
    public $password_selector = 'div[class*="login-form"] input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'div[class*="login-form"] button[type="submit"]';

    public $check_login_failed_selector = 'div.InfoBox_Error span[class*="BasicIcons-Error"]';
    public $check_login_success_selector = 'ul.account_submenu a[href="/customer/account/logout/"]';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->exts->loadCookiesFromFile();

        $this->exts->waitTillPresent('div.uc-banner-content button#uc-btn-deny-banner');
        if ($this->exts->exists('div.uc-banner-content button#uc-btn-deny-banner')) {
            $this->exts->moveToElementAndClick('div.uc-banner-content button#uc-btn-deny-banner');
            sleep(5);
        }

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
            sleep(10);

            if ($this->exts->exists('button[class*="TopBrand__CloseWidgetButton"]')) {
                $this->exts->moveToElementAndClick('button[class*="TopBrand__CloseWidgetButton"]');
                sleep(5);
            }
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('button[class*="TopBrand__CloseWidgetButton"]')) {
                $this->exts->moveToElementAndClick('button[class*="TopBrand__CloseWidgetButton"]');
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
            $this->exts->loginFailure();
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
            if ($this->exts->exists('button[class*="TopBrand__CloseWidgetButton"]')) {
                $this->exts->moveToElementAndClick('button[class*="TopBrand__CloseWidgetButton"]');
                sleep(5);
            }

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("1-login-page-filled");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(3);
            }
            $isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Invalid username or password.");');
            if ($isErrorMessage) {
                $this->exts->capture("login-failed-confirmed-1");
                $this->exts->log(__FUNCTION__ . '::Use login failed');
                $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
                $this->exts->loginFailure(1);
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

    private function downloadInvoices($count = 0)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('div.my-orders ul.my-orders__list li');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('div.my-orders ul.my-orders__list li');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('a[href*="sales/order/view/order_id"]', $row);
            if ($invoiceLink != null) {
                sleep(2);
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $invoiceName = $this->exts->extract('div.my-orders__order-number', $row);
                $invoiceName =  $invoiceName ?? time();
                $invoiceDate = $this->exts->extract('div.my-orders__date', $row);
                $invoiceAmount = $this->exts->extract('div.my-orders__sum', $row);

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
            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(2);
            $this->exts->waitTillPresent('div.middle-buttons a[href*="invoicepdf/index/invoicepdf/order_id"]');

            $invoiceBlockedText = strtolower($this->exts->extract('div.page-title h1'));
            $this->exts->log('invoiceBlockedText: ' . $invoiceBlockedText);

            if (stripos($invoiceBlockedText, 'there has been an error processing your request') !== false) {
                continue;
            }

            $downloaded_file = $this->exts->click_and_download('div.middle-buttons a[href*="invoicepdf/index/invoicepdf/order_id"]', 'pdf', $invoiceFileName);
            sleep(2);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceUrl,  $invoiceDate, $invoiceAmount, $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}
