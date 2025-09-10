<?php // updated loginfailedconfimered message added pagiantion login according to restrictPage
/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673482/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // load cookies from file for desktop app
                if (!empty($this->exts->config_array["without_password"])) {
                    $this->exts->loadCookiesFromFile();
                }
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 1301874 - Last modified: 13.08.2025 17:33:38 UTC - User: 1

    public $baseUrl = 'https://pcard.mein-contipark.de/konto/login-';
    public $loginUrl = 'https://pcard.mein-contipark.de/konto/login-';
    public $invoicePageUrl = 'https://pcard.mein-contipark.de/konto/transaktionen-download';

    public $username_selector = 'input[name="accountName"]';
    public $password_selector = 'input[type="password"][name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input[type="submit"]';

    public $check_login_failed_selector = '.global-messages-message.error-message';
    public $check_login_success_selector = 'a.dropdown-toggle.loggedIn';

    public $isNoInvoice = true;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            if ($this->exts->exists('button#accept-recommended-btn-handler')) {
                $this->exts->click_by_xdotool('button#accept-recommended-btn-handler');
            }
            $this->fillForm(0);
            sleep(3);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(7);
            // Invoices not loaded first time then open invoice url again
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(2);
            if ($this->exts->exists('button#accept-recommended-btn-handler')) {
                $this->exts->click_by_xdotool('button#accept-recommended-btn-handler');
            }

            $monthDate = 6;
            $dateMonthsBack = date('j.m.Y', strtotime("-$monthDate months"));
            $this->exts->log('invoiceName: ' . $dateMonthsBack);
            $this->exts->moveToElementAndType('input[name="mainForm-FromDate-Calendar_input"]', $dateMonthsBack);
            $this->exts->type_key_by_xdotool('Return');
            sleep(3);

            if ($this->exts->exists('tbody#mainForm-settlementDataTable_data')) {
                $this->processInvoices();
            } else {
                $this->genrateInvoices();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (
                stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false ||
                stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'login fehlgeschlagen') !== false
            ) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('div.global-message-summary')) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->log("Enter  password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }
                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
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
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent($this->check_login_success_selector, 30);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function genrateInvoices($paging_count = 1)
    {
        $this->exts->log('Begin-Generate-Invoices');
        $this->exts->capture("Generate-invoices-page");
        $this->exts->waitTillPresent('table tbody tr', 30);

        if ($this->exts->exists('th .ui-chkbox-box.ui-widget.ui-corner-all.ui-state-default')) {
            $this->exts->click_element('th .ui-chkbox-box.ui-widget.ui-corner-all.ui-state-default');
        }

        sleep(5);
        if ($this->exts->exists('button[name="mainForm-j_idt245"]')) {
            $this->exts->click_element('button[name="mainForm-j_idt245"]');
        }
    }

    private function processInvoices($count = 0)
    {
        $this->exts->log('Begin-Process-Invoices');
        $this->exts->capture("4-invoices-page");
        $this->exts->waitTillPresent('table tbody tr', 30);

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;

        $rows = $this->exts->querySelectorAll('table tbody tr');
        $invoices = [];
        foreach ($rows as $row) {

            $invoiceDateTime = $this->exts->extract('td:nth-child(1)', $row);
            $invoiceDate = explode(' ', $invoiceDateTime)[0];
            $invoiceAmount = $this->exts->extract('td:nth-child(3)', $row);
            $downloadBtn = $this->exts->querySelector('td:nth-child(4) button.fa-file-text-o', $row);

            array_push($invoices, array(
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'downloadBtn' => $downloadBtn
            ));
            $this->isNoInvoice = false;
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);

            $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        $pagiantionSelector = 'div#mainForm-settlementDataTable_paginator_bottom  a.ui-paginator-next:not(.ui-state-disabled)';
        if ($restrictPages == 0) {
            if ($count < 50 && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->moveToElementAndClick($pagiantionSelector);
                sleep(7);
                $count++;
                $this->processInvoices($count);
            }
        } else {
            if ($count < $restrictPages && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->moveToElementAndClick($pagiantionSelector);
                sleep(7);
                $count++;
                $this->processInvoices($count);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'Immowelt Kundenportal', '2673482', 'aW5mb0B0b20taW1tb2JpbGllbi5jb20=', 'UXQlb3FrV2VAKExSYjI=');
$portal->run();
