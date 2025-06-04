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
    public $baseUrl = 'https://b2b.mbps.eu/en/EUR/order-history';
    public $loginUrl = 'http://b2b.mbps.eu/en/EUR/login';
    public $invoicePageUrl = 'https://b2b.mbps.eu/en/EUR/order-history';

    public $username_selector = 'input[name="Username"]';
    public $password_selector = 'input[name="Password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"][form="localAccountForm"]';

    public $check_login_failed_selector = 'div[class="error pageLevel"] p';
    public $check_login_success_selector = 'div.header-meta__item a[href="/en/EUR/my-account"]';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
            $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            sleep(2);
        }
        $this->exts->loadCookiesFromFile();

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
            if ($this->exts->exists('button.ternary-negative')) {
                $this->exts->moveToElementAndClick('button.ternary-negative');
                sleep(2);
            }
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
                $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
                sleep(2);
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
            if (stripos($error_text, strtolower('Username/E-mail or password invalid. If you don’t remember your password, change it nows')) !== false) {
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

        if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
            $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            sleep(2);
        }

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

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function downloadInvoices($count = 0)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('datatable-body datatable-body-row');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('datatable-body datatable-body-row');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('div:nth-child(2) a', $row);
            if ($invoiceLink != null) {
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $invoiceName = $this->exts->extract('div:nth-child(2) a span', $row);
                $invoiceDate = $this->exts->extract('div.datatable-row-center datatable-body-cell:nth-child(4)', $row);
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

            $newTab = $this->exts->openNewTab($invoice['invoiceUrl']);
            sleep(7);
            $this->waitFor('button[type="button"].primary');

            $this->exts->moveToElementAndClick('button[type="button"].primary');
            sleep(5);

            $downloaded_file = $this->exts->click_and_download('div[class="section-list--element ng-star-inserted"]', 'pdf', $invoiceFileName);
            sleep(2);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            $this->exts->closeTab($newTab);
            sleep(2);
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $pagiantionSelector = 'ul.pager a[aria-label="go to next page"]';

        if ($restrictPages == 0) {
            if ($count < 50 && $this->exts->querySelector($pagiantionSelector) != null &&  $this->exts->querySelector('ul.pager li[class="disabled"] a[aria-label="go to next page"]') == null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);
                $count++;
                $this->downloadInvoices($count);
            }
        } else {
            if ($count < $restrictPages && $this->exts->querySelector($pagiantionSelector) != null &&  $this->exts->querySelector('ul.pager li[class="disabled"] a[aria-label="go to next page"]') == null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);
                $count++;
                $this->downloadInvoices($count);
            }
        }
    }
}
