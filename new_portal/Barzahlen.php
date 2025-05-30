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
    public $baseUrl = 'http://app.viafintech.com/?locale=en';
    public $loginUrl = 'http://app.viafintech.com/?locale=en';
    public $invoicePageUrl = 'https://app.viafintech.com/controlcenter/billings?locale=en';

    public $username_selector = 'input[name="user[email]"]';
    public $password_selector = 'input[name="user[password]"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input[name="commit"]';

    public $check_login_failed_selector = 'div.alert-danger';
    public $check_login_success_selector = 'div > a[href="/users/sign_out?locale=en"]';

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
            $this->dateRange();

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

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(7);
            }
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

        // Proper JavaScript string escaping
        $this->exts->execute_javascript('document.querySelector("input[name=\'created_after\']").value = "' . $formattedDate . '";');
        sleep(2);
        $this->exts->execute_javascript('document.querySelector("input[name=\'created_before\']").value = "' . $currentDate . '";');
        sleep(2);
        $this->exts->moveToElementAndClick('input[name="commit"]');
        sleep(10);
    }


    public $total_invoices = 0;

    private function downloadInvoices($count = 0)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table tbody tr');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('table tbody tr');
        foreach ($rows as $key => $row) {

            if ($this->total_invoices >= 100) {
                return;
            }
            $this->exts->log('total_invoices ' . $this->total_invoices);

            $downloadBtn = $this->exts->getElement('form[action*="pdf"]', $row);
            if ($downloadBtn != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('td:nth-child(1)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(2)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(5)', $row);

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);
                try {
                    $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);
                    sleep(10);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        $this->total_invoices++;
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                } catch (\Exception $e) {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
}
