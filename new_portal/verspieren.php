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
    public $baseUrl = 'https://assurpro.verspieren.com/wps/portal#no-back-button';
    public $loginUrl = 'https://assurpro.verspieren.com/wps/portal#no-back-button';
    public $invoicePageUrl = '';

    public $username_selector = 'input[name="LoginPortletFormID"]';
    public $password_selector = 'input[name="LoginPortletFormPassword"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input[type="button"]';

    public $check_login_failed_selector = 'div.wpsStatusMsg span';
    public $check_login_success_selector = 'a[id="logoutlink"]';

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
        $this->exts->loadCookiesFromFile();

        sleep(10);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(10);

            $this->fillForm(0);
        }

        $this->exts->waitTillPresent('button.sidebar-drawer__close', 10);

        if ($this->exts->exists('button.sidebar-drawer__close')) {
            $this->exts->moveToElementAndClick('button.sidebar-drawer__close');
            sleep(5);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

        
            $this->exts->waitTillPresent('span.closebtn', 10);
            if ($this->exts->exists('span.closebtn')) {
                $this->exts->moveToElementAndClick('span.closebtn');
                sleep(5);
            }

            $this->exts->waitTillPresent('li[id*="facturation.do"] a', 10);
            if ($this->exts->exists('li[id*="facturation.do"] a')) {
                $this->exts->moveToElementAndClick('li[id*="facturation.do"] a');
                sleep(5);
            }
            $this->downloadInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos($this->exts->extract($this->check_login_failed_selector), "Le mot de passe saisi n'est pas valide.") !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    function fillForm($count)
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
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent($this->check_login_success_selector);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function downloadInvoices() {
        $this->exts->log(__FUNCTION__);
    
        $this->exts->waitTillPresent('table[id*="tableAttribut"] tbody tr');
        $this->exts->capture("4-invoices-classic");
    
        $rows = $this->exts->getElements('table[id*="tableAttribut"] tbody tr');
        foreach ($rows as $key => $row) {
            $downloadBtn = $this->exts->getElement('a', $row);
            if($downloadBtn != null) {
                
                $invoiceName = time(); // create custome invoice name
                $invoiceDate = $this->exts->extract('td[nowrap="nowrap"]', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(3)', $row);
    
                sleep(15);
                $captureName = "invoices-detail-".$key."";
                $this->exts->capture($captureName);
    
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $invoiceAmount = '';
                $invoiceFileName = $invoiceName . '.pdf';
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
    
                $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                $this->isNoInvoice = false;
    
            }
        }
    }
}
