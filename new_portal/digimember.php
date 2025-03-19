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
    public $baseUrl = 'https://digimember.de/';
    public $loginUrl = 'https://digimember.de/wp-login.php';
    public $invoicePageUrl = '';

    public $username_selector = 'input[id="user_login"]';
    public $password_selector = 'input[id="user_pass"]';
    public $remember_me_selector = 'input[id="rememberme"]';
    public $submit_login_selector = 'input[id="wp-submit"]';

    public $check_login_failed_selector = 'div[class="ncore_msg ncore_msg_error"]';
    public $check_login_success_selector = 'div[class*="treemenuitem"] a[href*="logout"]';

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
        $this->exts->waitTillPresent('div[id="cookiescript_accept"]', 7);

        if ($this->exts->exists('div[id="cookiescript_accept"]')) {
            $this->exts->moveToElementAndClick('div[id="cookiescript_accept"]');
            sleep(7);
        }
        $this->exts->loadCookiesFromFile();
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            sleep(5);
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('div[id="cookiescript_accept"]')) {
                $this->exts->moveToElementAndClick('div[id="cookiescript_accept"]');
                sleep(7);
            }
            $this->exts->openUrl('https://digimember.de/mitgliederbereich-account/');

            $this->exts->waitTillPresent('a[href*="receipt"]');

            $goToInvoice = $this->exts->getElement('a[href*="receipt"]');
            if ($goToInvoice != null) {
                $invoiceUrl = $goToInvoice->getAttribute("href");
                $this->exts->openUrl($invoiceUrl);
                $this->exts->waitTillPresent('div.receipt_page div[class="tglr_container"] button[class="tglr_label click-button small invoice toggler_open"]');

                if ($this->exts->exists('div.receipt_page div[class="tglr_container"] button[class="tglr_label click-button small invoice toggler_open"]')) {
                    $this->exts->moveToElementAndClick('div.receipt_page div[class="tglr_container"] button[class="tglr_label click-button small invoice toggler_open"]');
                    sleep(2);
                }
            }

            $this->downloadInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            if (stripos($this->exts->extract($this->check_login_failed_selector), 'Das Kennwort für') !== false) {
                $this->exts->log("Wrong credential !!!!");
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
        try {
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
                    sleep(10);
                }
            } else {
                $this->exts->log("Login page not found");
                for ($i = 0; $i < 10; $i++) {
                    $this->exts->waitTillPresent('a[href*="login"][target="_self"]');
                    $this->exts->moveToElementAndClick('a[href*="login"][target="_self"]');
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
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

        $this->exts->waitTillPresent('div.tglr_container ul li');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('div.tglr_container ul li');
        foreach ($rows as $key => $row) {
            $download_link = $this->exts->getElement('a[href*="invoice"]:nth-child(1)', $row);
            if ($download_link != null) {
                $invoiceUrl = $download_link->getAttribute("href");
                preg_match('/invoice\/(\d+)\//', $invoiceUrl, $matches);

                if (isset($matches[1])) {
                    $invoiceName = $matches[1];
                } else {
                    $invoiceName = time();
                }


                $invoiceDate = $this->exts->extract('font:nth-child(2)', $row);
                $invoiceAmount = '';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $download_link
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
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

            $downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}
