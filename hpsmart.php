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

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            try {
                // Start portal script execution
                $this->initPortal(0);
            } catch (\Exception $exception) {
                $this->exts->log('Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }


            $this->exts->log('Execution completed');

            $this->exts->process_completed();
            $this->exts->dump_session_files();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 1314677 - Last modified: 02.04.2025 13:34:04 UTC - User: 1

    /*Start script*/

    public $baseUrl = 'https://www.hpsmart.com/';
    public $loginUrl = 'https://www.hpsmart.com/';
    public $invoicePageUrl = '';
    public $username_selector = 'input#username';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';
    public $check_login_failed_selector = 'p#password-helper-text';
    public $check_login_success_selector = 'button[data-testid*="avatar_menu"], div[data-testid*="organizationList"]';
    public $isNoInvoice = true;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        // $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
            }
            sleep(5);
            $this->exts->execute_javascript('
            var btn = document.querySelector("button#sign-in-link");
            if(btn){
                btn.click();
            }
        ');
            $this->fillForm(0);
        }

        $this->waitFor("button[data-testid*='continue_button']");

        if ($this->exts->exists("button[data-testid*='continue_button']")) {
            $this->exts->click_element("button[data-testid*='continue_button']");
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    function fillForm($count = 1)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                $this->exts->click_element($this->submit_login_selector);
                $this->exts->waitTillPresent($this->password_selector, 10);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }

                $this->exts->click_element($this->submit_login_selector);
                sleep(2); // Portal itself has one second delay after showing toast

            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

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

    private function waitFor($selector, $iterationNumber = 2)
    {
        for ($wait = 0; $wait < $iterationNumber && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selector.....');
            sleep(10);
        }
    }

    private function reLogin($paging_count)
    {
        $this->waitFor('.vn-modal--footer .buttonsContainer button', 5);
        $relogin_button = $this->exts->getElementByText('.vn-modal--footer .buttonsContainer button', 'Anmelden', null, false);
        if ($relogin_button != null || $this->exts->exists($this->username_selector)) {
            if ($relogin_button != null) {
                $this->exts->execute_javascript("arguments[0].click()", [$relogin_button]);
            }
            $this->fillForm();
            $this->processInvoices($paging_count);
        }
    }


    private function processInvoices($paging_count = 1)
    {
        $this->waitFor('[data-testid*="side-menu-item-HP"]', 3);
        $this->exts->moveToElementAndClick('[data-testid*="side-menu-item-HP"]');
        sleep(5);
        $this->waitFor('li[data-testid*="Payment History"]');
        $this->exts->moveToElementAndClick('li[data-testid*="Payment History"]');

        $this->waitFor('iv[id="Druck--und Zahlungsverlauf"][aria-expanded="false"]', 5);
        if ($this->exts->exists('div[id="Druck--und Zahlungsverlauf"][aria-expanded="false"]')) {
            $this->exts->moveToElementAndClick("div#history-table-section > div > div > div[role='button']");
        }
        // $this->exts->waitTillPresent('table[data-testid="veneer-print-hitory-table"] tbody tr', 50);

        $invoices = [];

        $this->waitFor('table[data-testid=\'veneer-print-hitory-table\'] tbody tr', 5);
        $rows = $this->exts->getElements('table[data-testid="veneer-print-hitory-table"] tbody tr');
        if (count($rows) > 0) {
            $this->exts->execute_javascript("arguments[0].click()", [$rows[0]]);
            $this->reLogin($paging_count);
        }

        // If user is on 3 page and user is prompted to re-login than after relogin start downloading invoices from 3 page only
        for ($i = 1; $i < $paging_count; $i++) {
            if (
                $this->exts->querySelector('nav[aria-label="Pagination Navigation"] button[aria-label="Next"]:not([disabled])') != null
            ) {
                $this->exts->log($paging_count);
                $this->exts->click_by_xdotool('nav[aria-label="Pagination Navigation"] button[aria-label="Next"]:not([disabled])');
                sleep(5);
            }
        }

        $this->waitFor('table[data-testid=\'veneer-print-hitory-table\'] tbody tr', 5);
        $rows = $this->exts->getElements('table[data-testid="veneer-print-hitory-table"] tbody tr');
        $this->exts->capture("4-invoices-page");
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(3) a', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = preg_replace("/\//", "-", $this->exts->extract('td:nth-child(1)', $row));

                $invoiceAmount = '';
                $invoiceDate = $this->exts->extract('td:nth-child(1)', $row);

                $downloadBtn = $this->exts->querySelector('td:nth-child(3) a', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            if (file_exists($invoiceFileName)) {
                $this->exts->log('Invoice already exists');
            } else {
                // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                $this->reLogin($paging_count);
                $this->exts->execute_javascript("arguments[0].click()", [$invoice['downloadBtn']]);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                $this->reLogin($paging_count);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }

        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 && $paging_count < 50 &&
            $this->exts->querySelector('nav[aria-label="Pagination Navigation"] button[aria-label="Next"]:not([disabled])') != null
        ) {
            $paging_count++;
            $this->exts->click_by_xdotool('nav[aria-label="Pagination Navigation"] button[aria-label="Next"]:not([disabled])');
            sleep(5);
            $this->processInvoices($paging_count);
        } else if ($restrictPages > 0 && $paging_count <= $restrictPages && $this->exts->querySelector('nav[aria-label="Pagination Navigation"] button[aria-label="Next"]:not([disabled])') != null) {
            $paging_count++;
            $this->exts->click_by_xdotool('nav[aria-label="Pagination Navigation"] button[aria-label="Next"]:not([disabled])');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
