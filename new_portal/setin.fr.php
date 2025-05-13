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
    public $baseUrl = 'https://www.setin.fr/';
    public $loginUrl = 'https://www.setin.fr/dhtml/acces.php';
    public $invoicePageUrl = 'https://www.setin.fr/dhtml/factures_setin.php';

    public $username_selector = 'form[action="acces.php"] input#acces_mail';
    public $password_selector = 'form[action="acces.php"] input#acces_password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form[action="acces.php"] a#id_valider';

    public $check_login_failed_selector = 'div.erreur';
    public $check_login_success_selector = 'a[href="home.php?deconnect=1"][id="deconnexion"]';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(7);
        $this->exts->loadCookiesFromFile();

        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('div#divCookiesGeneral a.AcceptAllBouton');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }

        if ($this->exts->exists('div#divCookiesGeneral a.AcceptAllBouton')) {
            $this->exts->moveToElementAndClick('div#divCookiesGeneral a.AcceptAllBouton');
            sleep(7);
        }

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);

            for ($wait = 0; $wait < 3 && $this->exts->executeSafeScript("return !!document.querySelector('div#divCookiesGeneral a.AcceptAllBouton');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if ($this->exts->exists('div#divCookiesGeneral a.AcceptAllBouton')) {
                $this->exts->moveToElementAndClick('div#divCookiesGeneral a.AcceptAllBouton');
                sleep(7);
            }
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->openUrl($this->invoicePageUrl);

            $this->processInvoicesMonth();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
            if (
                stripos($error_text, 'identifiant ou mot de passe incorrect !') !== false ||
                stripos($error_text, 'incorrect username or password!') !== false
            ) {

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
                sleep(5);
            }
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


    private function processInvoicesMonth()
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('input[id="startDatefr"]');

        if ($this->exts->exists('input[id="startDatefr"]')) {
            // select all custom value
            $this->exts->moveToElementAndType('input[id="startDatefr"]', 'select all');
            sleep(5);
        }
        $this->downloadInvoices();
    }


    private function downloadInvoices($count = 0)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('div.card-open div.row.fact', 25);
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('div.card-open div.row.fact');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('a', $row);
            if ($invoiceLink != null) {
                sleep(2);
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $string = $this->exts->extract('div.medium-order-2 div.listing-setin-label', $row);
                preg_match('/\d+/', $string, $matches);
                $invoice_no = $matches[0];
                $invoiceName =  $invoice_no ?? time();
                $invoiceDate = $this->exts->extract('div.medium-order-5 div.listing-setin-valeur', $row);
                $invoiceAmount = $this->exts->extract('div.medium-order-4 div.listing-setin-prix', $row);;

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

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceUrl, $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}
