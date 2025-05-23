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
    public $baseUrl = 'https://www.spartoo.com/securelogin.php?from=compte';
    public $loginUrl = 'https://www.spartoo.com/securelogin.php?from=compte';
    public $invoicePageUrl = '';

    public $username_selector = 'input[name="email_address"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[id="loginVal"]';

    public $check_login_failed_selector = 'div.messageStackError';
    public $check_login_success_selector = 'div[id*="Orders"]';

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
        $this->exts->loadCookiesFromFile();

        if ($this->exts->exists('a#ld_close_div')) {
            $this->exts->moveToElementAndClick('a#ld_close_div');
            sleep(2);
        }

        if ($this->exts->exists('button[class="cookies_info-pop-buttons-accept"]')) {
            $this->exts->moveToElementAndClick('button[class="cookies_info-pop-buttons-accept"]');
            sleep(2);
        }

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            // $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('a#ld_close_div')) {
                $this->exts->moveToElementAndClick('a#ld_close_div');
                sleep(2);
            }

            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->moveToElementAndClick($this->check_login_success_selector);
                sleep(2);
            }

            if ($this->exts->exists('ul[id*="Orders"] li:nth-child(2)')) {
                $this->exts->moveToElementAndClick('ul[id*="Orders"] li:nth-child(2)');
                sleep(2);
            }

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
            if (stripos($error_text, strtolower('Aucun résultat à cette adresse électronique et/ou mot de passe')) !== false) {
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
        foreach ($rows as $key => $row) {
            $invoiceBtn = $this->exts->getElement('td:nth-child(7) a', $row);
            if ($invoiceBtn != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('td:nth-child(1) a', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(3)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(6)', $row);

                try {
                    $invoiceBtn->click();
                } catch (\Exception $e) {
                    $this->exts->execute_javascript("arguments[0].click()", [$invoiceBtn]);
                }
                sleep(2);
                $this->exts->waitTillPresent('a.sp_popup_close', 7);
                if ($this->exts->exists('a.sp_popup_close')) {
                    $this->exts->moveToElementAndClick('a.sp_popup_close');
                    sleep(2);
                }
                $invoiceLink = $this->exts->getElement('div.btn_order_commande:nth-child(1) a');
                if ($invoiceLink != null) {
                    $invoiceUrl = $invoiceLink->getAttribute("href");
                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl,
                    ));
                    $this->isNoInvoice = false;
                }

                if ($this->exts->exists('button.bt_black')) {
                    $this->exts->click_by_xdotool('button.bt_black');
                    sleep(2);
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

            $newTab = $this->exts->openNewTab($invoice['invoiceUrl']);
            sleep(10);

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
            $this->exts->closeTab($newTab);
        }

        if ($this->exts->exists('ul[id*="Orders"] li:nth-child(2)')) {
            $this->exts->moveToElementAndClick('ul[id*="Orders"] li:nth-child(2)');
            sleep(2);
        }
    }
}
