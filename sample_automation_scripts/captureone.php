<?php // replace waitTillPresent to custom js waitFor function updated invoice history selector and 
// extract invoice name from html use click_and_download 
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

    // Server-Portal-ID: 399171 - Last modified: 21.07.2025 14:33:21 UTC - User: 1

    public $baseUrl = "https://www.captureone.com/en/account";
    public $loginUrl = "https://www.captureone.com/en/account";
    public $invoicePageUrl = '';
    public $username_selector = 'input[id="signInName"]';
    public $password_selector = 'input[id="password"]';
    public $submit_button_selector = 'button[type="submit"]';
    public $check_login_failed_selector = 'div[class="error pageLevel"] > p';
    public $check_login_success_selector = 'div.welcome-statement';
    public $login_tryout = 0;
    public $isNoInvoice = true;
    public $totalFiles = 0;
    public $restrictPages = 3;
    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */

    private function initPortal($count)
    {
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
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
            $this->processInvoices();
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->waitFor($this->check_login_failed_selector, 10);
            sleep(5);
            if ($this->exts->exists($this->check_login_failed_selector) && $this->exts->querySelector($this->check_login_failed_selector)->getText() != '') {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->log("Failed due to unknown reasons");
                $this->exts->loginFailure();
            }
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 10);
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
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->waitFor($this->check_login_success_selector, 10);
            if ($this->exts->exists($this->check_login_success_selector)) {

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

    private function processInvoices()
    {
        $this->waitFor('section.container > div > div:nth-child(3) > p > a', 10);
        $invoicePage = $this->exts->querySelector('section.container > div > div:nth-child(3) > p > a');
        $this->exts->execute_javascript("arguments[0].click();", [$invoicePage]);
        sleep(1);
        $this->waitFor('div[ng-repeat="subscription in subscriptions.active"] a.showBillingHistory', 15);
        if ($this->exts->exists('div[ng-repeat="subscription in subscriptions.active"] a.showBillingHistory')) {
            $this->exts->click_element('div[ng-repeat="subscription in subscriptions.active"] a.showBillingHistory');
        }

        sleep(5);
        $this->waitFor('div[role="document"] div.modal-body > table > tbody > tr', 10);
        $this->exts->capture("1 invoice page");
        $rows = $this->exts->querySelectorAll('div[role="document"] div.modal-body > table > tbody > tr');
        $this->exts->startTrakingTabsChange();
        foreach ($rows as $row) {
            if ((int) @$this->restrictPages != 0 && $this->totalFiles >= 50) {
                break;
            }

            $invoiceDate = $row->querySelector('td:nth-child(1) > span');
            if ($invoiceDate  != null) {
                $invoiceDate = $invoiceDate->getText();
            }

            $amount = $row->querySelector('td:nth-child(3)');
            if ($amount  != null) {
                $amount = $amount->getText();
            }

            $invoiceLink = $row->querySelector('td:nth-child(4) > a');
            if ($invoiceLink != null) {

                $invoiceUrl = $invoiceLink->getAttribute('href');
                $this->exts->openNewTab($invoiceUrl);
                sleep(5);
                $this->exts->switchToNewestActiveTab();
                $this->waitFor('a.downloadAndPrintIcon', 5);
                $invoiceName = trim($this->exts->extract('h3#orderIdArea'));
                // cleaned Invoice name
                if (preg_match('/CO\d+-\d+-\d+/', $invoiceUrl, $matches)) {
                    $invoiceName = $matches[0];
                }
                $downloadBtn = $this->exts->querySelector('a.downloadAndPrintIcon');
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $amount);

                $invoiceFileName =  !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                if ($downloadBtn != null) {
                    $this->isNoInvoice = false;

                    $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $amount, $invoiceFileName);
                        sleep(1);
                        $this->totalFiles++;
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }

                    $this->exts->closeCurrentTab();
                    sleep(2);
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
