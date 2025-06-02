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
    public $baseUrl = 'https://makler.inter.de/e-abrechnungsservice/login';
    public $loginUrl = 'https://makler.inter.de/e-abrechnungsservice/login';
    public $invoicePageUrl = 'https://makler.inter.de/e-abrechnungsservice/login';

    public $username_selector = 'input[id="username"]';
    public $password_selector = 'input[id="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form[name="login"] button[type="submit"]';

    public $check_login_failed_selector = 'div.alert-danger';
    public $check_login_success_selector = 'div.inter-header-bar-gray a[href*="[action]=logout"].btn';

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

        $this->switchToFrame('div#usercentrics-root');
        sleep(2);

        if ($this->exts->exists('button[data-testid="uc-accept-all-button"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="uc-accept-all-button"]');
            sleep(2);
        }
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->querySelector('div#usercentrics-root') != null) {
                $this->switchToFrame('div#usercentrics-root');
                sleep(2);

                if ($this->exts->exists('button[data-testid="uc-accept-all-button"]')) {
                    $this->exts->moveToElementAndClick('button[data-testid="uc-accept-all-button"]');
                    sleep(5);
                }

                $this->exts->switchToDefault();
                sleep(5);
            }

            if ($this->exts->exists('li:nth-child(1) a.btn-block-mobile')) {
                $this->exts->moveToElementAndClick('li:nth-child(1) a.btn-block-mobile');
                sleep(5);
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
            if (stripos($error_text, strtolower('Ihr Benutzername oder ihr Passwort ist falsch')) !== false) {
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

    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }

    private function downloadInvoices($count = 1)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('ul.list-group li');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('ul.list-group li');
        foreach ($rows as $key => $row) {
            $invoicePdf = $this->exts->extract('a:nth-child(1)', $row);
            $this->exts->log('invoicePdf:: ' . $invoicePdf);
            if (stripos($invoicePdf, strtolower('.pdf')) !== false || stripos($invoicePdf, strtolower('.zip'))) {
                $invoiceLink = $this->exts->extract('a:nth-child(2)', $row);
                $invoiceUrl = '';
                if ($invoiceLink != null) {
                    $invoiceUrl = $invoiceLink->getAttribute("href");
                }

                $invoiceDate = '';
                $invoiceAmount = '';

                array_push($invoices, array(
                    'invoiceName' => $invoicePdf,
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

            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            if (stripos($invoice['invoiceUrl'], strtolower('.pdf')) !== false) {
                $invoiceName = preg_replace('/\.pdf.*/i', '', $invoice['invoiceUrl']);
                $this->exts->log('invoiceName: ' . $invoiceName);

                $invoiceFileName = !empty($invoiceName) ?  $invoiceName  . '.pdf' : '';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            } elseif (stripos($invoice['invoiceUrl'], strtolower('.zip'))) {
                $this->exts->openUrl($invoice['invoiceUrl']);
                sleep(15);
                $this->exts->wait_and_check_download('zip');

                $downloaded_file = $this->exts->find_saved_file('zip');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->extract_zip_save_pdf($downloaded_file);
                } else {
                    sleep(60);
                    $this->exts->wait_and_check_download('zip');
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->extract_zip_save_pdf($downloaded_file);
                    }
                }
            } else {
                $this->exts->log('No invoice found with pdf and zip extension');
            }
        }
    }

    private function extract_zip_save_pdf($zipfile)
    {
        $this->isNoInvoice = false;
        $zip = new \ZipArchive;
        $res = $zip->open($zipfile);
        if ($res === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zipPdfFile = $zip->statIndex($i);
                $fileInfo = pathinfo($zipPdfFile['name']);
                if ($fileInfo['extension'] === 'pdf') {
                    $invoice_name = basename($zipPdfFile['name'], '.pdf');
                    $zip->extractTo($this->exts->config_array['download_folder'], array(basename($zipPdfFile['name'])));
                    $saved_file = $this->exts->config_array['download_folder'] . basename($zipPdfFile['name']);
                    $this->exts->new_invoice($invoice_name, "", "", $saved_file);
                    sleep(1);
                }
            }
            $zip->close();
            unlink($zipfile);
        } else {
            $this->exts->log(__FUNCTION__ . '::File extraction failed');
        }
    }
}
