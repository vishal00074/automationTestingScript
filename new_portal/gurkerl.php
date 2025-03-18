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
    public $baseUrl = 'https://www.gurkerl.at/';
    public $loginUrl = 'https://www.gurkerl.at/benutzer/anmelden/';
    public $invoicePageUrl = 'https://www.gurkerl.at/benutzer/profil';

    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[data-test="btnSignIn"]';

    public $check_login_failed_selector = 'div[messagecode="login.invalid_credentials"] p span span';
    public $check_login_success_selector = 'div#headerUser';

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

        $this->switchToFrame('aside[id="usercentrics-cmp-ui"]');

        if ($this->exts->exists('button[class="accept uc-accept-button"]')) {
            $this->exts->moveToElementAndClick('button[class="accept uc-accept-button"]');
            sleep(4);
            $this->exts->refresh();
            sleep(4);
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

            $this->exts->openUrl($this->invoicePageUrl);

            $this->downloadInvoices();

            $this->exts->success();
        } else {
            if (stripos($this->exts->extract($this->check_login_failed_selector), 'Du hast eine falsche E-Mail-Adresse oder ein falsches Passwort eingegeben') !== false) {
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
                    sleep(7);
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

    private function downloadInvoices($count = 0)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('div.contentWrapper div.test-user-profile-finished-order-preview');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('div.contentWrapper div.test-user-profile-finished-order-preview');
        foreach ($rows as $key => $row) {
            $invoiceName = $this->exts->extract('span.itemInfo span', $row);
            $invoiceDate = $this->exts->extract('p.dateText', $row);
            $invoiceAmount = $this->exts->extract('p[class*="typographyRight"]', $row);

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);

            try {
                $row->click();
                sleep(7);
            } catch (Exception $e) {
                $this->exts->log('Error:: ' . $e->getMessage());
            }

            if ($this->exts->exists('button[data-test="dropdown-button"]')) {
                $this->exts->moveToElementAndClick('button[data-test="dropdown-button"]');
                sleep(4);
            }
            $invoiceFileName = $invoiceName . '.pdf';
            $parsedDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');

            $downloaded_file = $this->exts->click_and_download('div[data-test="document-item-2"]', 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $parsedDate, $invoiceAmount, $invoiceFileName);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            $this->isNoInvoice = false;
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->log('restrictPages count:: ' . $restrictPages);

        while ($count < $restrictPages && $this->exts->exists('a[data-test="paginationShowPrev"]')) {
            $this->exts->moveToElementAndClick('a[data-test="paginationShowPrev"]');
            sleep(7);
            $count++;
            $this->downloadInvoices($count);
        }
    }
}
