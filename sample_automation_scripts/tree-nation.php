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

    // Server-Portal-ID: 778989 - Last modified: 13.03.2025 06:01:10 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = "https://tree-nation.com";
    public $loginUrl = "https://tree-nation.com/login";
    public $invoicePageUrl = 'https://tree-nation.com/user-profile/invoices';
    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $submit_button_selector = 'button[type="submit"]';
    public $check_login_failed_selector = 'div.noty_layout';
    public $check_login_success_selector = 'button[data-testid="header_account_btn"]';
    public $login_tryout = 0;
    public $isNoInvoice = true;
    public $isFailed = false;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processAllInvoices();
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (!$this->isFailed) {
                if ($this->exts->exists($this->check_login_failed_selector)) {
                    $this->exts->log("Wrong credential !!!!");
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 20);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->exts->click_by_xdotool($this->submit_button_selector);
                sleep(2); // Portal itself has one second delay after showing toast
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
            $this->exts->waitTillPresent($this->check_login_failed_selector, 10);
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credential !!!!");
                $this->isFailed = true;
                $this->exts->loginFailure(1);
            } else {
                $this->exts->waitTillPresent($this->check_login_success_selector, 20);
                if ($this->exts->exists($this->check_login_success_selector)) {

                    $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                    $isLoggedIn = true;
                }
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function processAllInvoices()
    {
        $this->exts->waitTillPresent('div.billing-list table > tbody  > tr', 20);
        $stop = 0;
        while (true && $stop < 20) {
            $moreBtn = $this->exts->querySelector('button.btn-outline-primary');

            if ($moreBtn != null) {
                $this->exts->execute_javascript("arguments[0].click();", [$moreBtn]);
                $this->exts->waitTillPresent('a[href*="filter=payment_receipts"]', 5);
            } else {
                break;
            }
            $stop++;
        }

        $this->processInvoices();
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('div.billing-list table > tbody  > tr', 20);
        $this->exts->capture("1 invoice page");
        $rows = $this->exts->querySelectorAll('div.billing-list table > tbody  > tr');
        foreach ($rows as $row) {
            $this->isNoInvoice = false;

            $invoiceName = $row->querySelector('td:nth-child(5)');
            if ($invoiceName != null) {
                $invoiceName = $invoiceName->getText();
            }

            $invoiceDate = $row->querySelector('td:nth-child(1)');
            if ($invoiceDate != null) {
                $invoiceDate = $invoiceDate->getText();
            }

            $amount = $row->querySelector('td:nth-child(4)');
            if ($amount != null) {
                $amount = $amount->getText();
            }

            $downloadBtn = $row->querySelector('td:nth-child(6) > a');

            if ($downloadBtn != null) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('amount: ' . $amount);

                $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                sleep(3);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoiceName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $amount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
