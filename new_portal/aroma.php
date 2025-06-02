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
    public $baseUrl = 'https://www.aroma-zone.com/my-account/order-history';
    public $loginUrl = 'https://www.aroma-zone.com/signin';
    public $invoicePageUrl = 'https://www.aroma-zone.com/my-account/order-history';

    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'div.az-input-error p';
    public $check_login_success_selector = 'a[href="/my-account/my-addresses"]';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        $this->exts->loadCookiesFromFile();

        if ($this->exts->exists('button[class="base-button az-button--full-width confirm-button"]')) {
            $this->exts->moveToElementAndClick('button[class="base-button az-button--full-width confirm-button"]');
            sleep(2);
        }

        if ($this->exts->exists('button[id="axeptio_btn_acceptAll"]')) {
            $this->exts->moveToElementAndClick('button[id="axeptio_btn_acceptAll"]');
            sleep(2);
        }

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('button[id="axeptio_btn_acceptAll"]')) {
                $this->exts->moveToElementAndClick('button[id="axeptio_btn_acceptAll"]');
                sleep(2);
            }
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('button[id="axeptio_btn_acceptAll"]')) {
                $this->exts->moveToElementAndClick('button[id="axeptio_btn_acceptAll"]');
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
            if (stripos($error_text, strtolower('Il y a eu une erreur lors de la connexion')) !== false) {
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

        $this->exts->waitTillPresent('div#web div.orders-list a');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('div#web div.orders-list a');
        foreach ($rows as $key => $row) {
            $invoiceName = '';
            $invoiceUrl = $row->getAttribute("href");
            $string = $this->exts->extract('span.order-card__number', $row);
            preg_match('/#(\d+)/', $string, $matches);
            if (isset($matches[1])) {
                $invoiceName = $matches[1];
            }

            $invoiceDate = $this->exts->extract('span.order-card__date', $row);
            $invoiceAmount = '';

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
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->openNewTab($invoice['invoiceUrl']);
            sleep(15);
            $this->exts->waitTillPresent('a.order-details__download');
            if ($this->exts->querySelector('a.order-details__download') != null) {
                $this->exts->moveToElementAndClick('a.order-details__download');
                sleep(15);
                if ($this->exts->document_exists($invoiceFileName)) {
                    continue;
                }
                $this->exts->no_margin_pdf = 1;
                $this->exts->execute_javascript('window.print();');
                sleep(4);
                $file_ext = $this->exts->get_file_extension($invoiceFileName);
                $this->exts->wait_and_check_download($file_ext);
                $downloaded_file = $this->exts->find_saved_file($file_ext, $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
               
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }

            $this->exts->switchToOldestActiveTab();
            sleep(2);
            $this->exts->closeAllTabsButThis();
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        if ($count < $restrictPages && $this->exts->exists('nav[class="az-pagination orders-pagination"]  button[data-testid="pagination-button-next"][class="base-button"]')) {
            $this->exts->click_by_xdotool('nav[class="az-pagination orders-pagination"]  button[data-testid="pagination-button-next"][class="base-button"]');
            sleep(7);
            $count++;
            $this->downloadInvoices($count);
        }
    }
}
