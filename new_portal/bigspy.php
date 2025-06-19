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
    public $baseUrl = 'https://bigspy.com/history?app_type=3';
    public $loginUrl = 'https://bigspy.com/user/login';
    public $invoicePageUrl = '';

    public $username_selector = 'input[name="LoginForm[username]"]';
    public $password_selector = 'input[name="LoginForm[password]"]';
    public $remember_me_selector = 'input[type="checkbox"]';
    public $submit_login_selector = 'button#loginBut';

    public $check_login_failed_selector = 'div.field-loginform-password div.help-block';
    public $check_login_success_selector = 'a[href="/user/logout"]';

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

        $this->check_solve_cloudflare_page();

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(12);

            $this->check_solve_cloudflare_page();
            $this->check_solve_cloudflare_page();
            $this->check_solve_cloudflare_page();
            sleep(5);

            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->querySelector('ul.ml-auto.nav-height > li:nth-child(2)') != null) {
                $this->exts->moveToElementAndClick('ul.ml-auto.nav-height > li:nth-child(2)');
                sleep(10);
            }

            if ($this->exts->querySelector('nav.nav  a.nav-link:nth-child(4)') != null) {
                $this->exts->moveToElementAndClick('nav.nav  a.nav-link:nth-child(4)');
                sleep(10);
            }

            if ($this->exts->querySelector('iframe#userinfoPopupIframe') != null) {
                $this->switchToFrame('iframe#userinfoPopupIframe');
                sleep(4);
            }


            if ($this->exts->querySelector('div#tab-invoice') != null) {
                $this->exts->moveToElementAndClick('div#tab-invoice');
                sleep(7);
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
            $error_text_user_name = strtolower($this->exts->extract('div.field-loginform-username div.help-block'));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('Incorrect password')) !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos($error_text_user_name, strtolower('Email is not a valid email address')) !== false) {
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

    private function check_solve_cloudflare_page()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
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
            if (count($this->exts->getElements($this->check_login_success_selector)) != 0) {
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

    private function downloadInvoices($count = 1)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table tbody tr');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('table tbody tr');

        for ($i = 0; $i < count($rows); $i++) {
            $j = $i + 1;
            $invoiceBtn = $this->exts->getElement('table tbody tr:nth-child(' . $j . ') td:nth-child(5) span');
            if ($invoiceBtn != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('table tbody tr:nth-child(' . $j . ') td:nth-child(1)');
                $invoiceDate = $this->exts->extract('table tbody tr:nth-child(' . $j . ') td:nth-child(4)');
                $invoiceAmount = $this->exts->extract('table tbody tr:nth-child(' . $j . ') td:nth-child(3)');

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                try {
                    $invoiceBtn->click();
                } catch (\Exception $e) {
                    $this->exts->execute_javascript("arguments[0].click();", [$invoiceBtn]);
                }
                sleep(7);
                $this->exts->switchToNewestActiveTab();
                sleep(2);
                $this->exts->refresh();
                $downloadBtn = 'div.InvoiceDetailsRow-Container button:nth-child(1)';
                $this->waitFor($downloadBtn);

                if ($this->exts->querySelector($downloadBtn) != null) {
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $invoiceDate);

                    $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);
                    sleep(4);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }

                    $this->exts->closeCurrentTab();
                    sleep(10);

                    if ($this->exts->querySelector('iframe#userinfoPopupIframe') != null) {
                        $this->switchToFrame('iframe#userinfoPopupIframe');
                        sleep(4);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ');
                }
            }
        }
    }
}
