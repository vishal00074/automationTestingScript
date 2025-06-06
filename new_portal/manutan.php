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
    public $baseUrl = 'https://www.manutan.fr/fr/maf';
    public $loginUrl = 'https://www.manutan.fr/LogonForm';
    public $invoicePageUrl = '';

    public $username_selector = 'input[name="logonId"][id*="FormInput"]';
    public $password_selector = 'input[name="logonPassword"][id*="FormInput"]';
    public $remember_me_selector = 'input[name="remember"]';
    public $submit_login_selector = 'div.form-footer a[role="button"]';

    public $check_login_failed_selector = 'div[class="form-part-alert"] label';
    public $check_login_success_selector = 'a[href*="logout"]';

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
        sleep(10);
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(2);
        }
        $this->exts->loadCookiesFromFile();

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();

            if ($this->exts->exists('a[href*="LogonForm"]')) {
                $this->exts->moveToElementAndClick('a[href*="LogonForm"]');
                sleep(5);
            }
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                sleep(2);
            }

            if ($this->exts->exists('div > a[href*="AjaxLogonForm"]')) {
                $this->exts->moveToElementAndClick('div > a[href*="AjaxLogonForm"]');
                sleep(10);
            }

            if ($this->exts->querySelector('div.box-part li a[href*="TrackOrderStatus"]') != null) {
                $this->exts->moveToElementAndClick('div.box-part li a[href*="TrackOrderStatus"]');
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
            if (stripos($error_text, strtolower("L'identifiant de connexion ou le mot de passe saisi est incorrect. Saisissez à nouveau les données.")) !== false) {
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

    private function changeSelectbox($value)
    {
        $this->exts->execute_javascript('
            let selectBox = document.querySelector("select#durationSelect");
            selectBox.value = "' . addslashes($value) . '";
            selectBox.dispatchEvent(new Event("change"));
        ');
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

        $this->exts->waitTillPresent('div#orderList div.list-item', 30);
        $this->exts->capture("4-invoices-classic- " . $count);
        $this->exts->log('restrictPages ' .  $this->restrictPages);

        $invoices = [];
        $rows = $this->exts->getElements('div#orderList div.list-item');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('div#orderList div.list-item a[href*="OrderDetail"].btn.btn-default ', $row);
            if ($invoiceLink != null) {
                $invoiceUrl =  $invoiceLink->getAttribute("href");
                $invoiceName = $this->exts->extract('div.row div:nth-child(2) strong', $row);
                $invoiceDate = $this->exts->extract('div.row div:nth-child(1) strong', $row);
                $invoiceAmount = $this->exts->extract('div.row div:nth-child(2) strong', $row);

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

            if ($this->totalInvoices >= 100) {
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

            $newTab = $this->exts->openNewTab($invoice['invoiceUrl']);
            sleep(5);
            $this->waitFor('a#documentsButton');
            $this->exts->moveToElementAndClick('a#documentsButton');
            sleep(5);

            $num = count($this->exts->getElements('div.documentList a[href*="MIDownloadOrderDocumentCmd"]'));

            $button = 'div.documentList:nth-child(' . $num . ') a';
            $downloaded_file = $this->exts->click_and_download($button, 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $this->totalInvoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            $this->exts->closeTab($newTab);
            sleep(4);
        }

        // download for last three year by default download last six month no filters for 3 months in portal
        // less than 100 invoices
        sleep(8);
        if ($this->restrictPages == 0) {
            $selectDate = new DateTime();
            $year = null;
            if ($count == 1) {
                $year = $selectDate->format('Y');
            } else if ($count == 2) {
                $selectDate->modify('-1 years');
                $year = $selectDate->format('Y');
            } else if ($count == 3) {
                $selectDate->modify('-2 years');
                $year = $selectDate->format('Y');
            } else if ($count == 4) {
                $selectDate->modify('-3 years');
                $year = $selectDate->format('Y');
            }
            $this->exts->capture("4-invoices-years- " . $year);
            $this->exts->log('<-----Invoice Year ' . $year . ' ---->');
            if ($year != null) {
                $this->changeSelectbox($year);
                sleep(5);
                $count++;
                $this->downloadInvoices($count);
            }
        }
    }
}
