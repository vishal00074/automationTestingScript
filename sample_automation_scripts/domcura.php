<?php // replace waitTillPresent to wait for on test engine it showing one document download but ih ave tested the watch logs it 10 invoices download

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

    // Server-Portal-ID: 2066537 - Last modified: 19.06.2025 14:36:23 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = "https://office.domcura.de/index.php?m=domcura&c=profile&f=provabrg";

    public $loginUrl = "https://office.domcura.de/index.php?m=domcura&c=profile&f=provabrg";

    public $invoicePageUrl = 'https://office.domcura.de/index.php?m=domcura&c=profile&f=provabrg';

    public $username_selector = 'input[id="login"]';

    public $password_selector = 'input[id="pass"]';

    public $submit_button_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'p.message.error';

    public $check_login_success_selector = 'a[href*="logout"]';

    public $login_tryout = 0;

    public $isNoInvoice = true;

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
            $this->processInvoices();
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->waitFor($this->check_login_failed_selector, 20);
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 20);
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
        $this->waitFor('table[id="table_provabrg"] > tbody > tr', 10);
        $this->exts->execute_javascript("
			var selectElement = document.querySelector('div[id=\"table_provabrg_length\"] select');
			selectElement.value = '-1';
			selectElement.dispatchEvent(new Event('change', { bubbles: true }));
		");
        sleep(5);
        $this->exts->capture("1 invoice page");
        $rows = $this->exts->querySelectorAll('table[id="table_provabrg"] > tbody > tr');

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true;

        $maxInvoices = 10;
        $invoiceCount = 0;

        for ($index = 0; $index < count($rows); $index++) {
            $this->isNoInvoice = false;

            $row = $rows[$index];

            $invoiceDate = $row->querySelector('td:nth-child(2)');
            if ($invoiceDate != null) {
                $invoiceDateText = $invoiceDate->getText();
                $invoiceDate = $this->exts->parse_date($invoiceDateText, '', 'Y-m-d');
            }

            $detailsBtn = $row->querySelector('td:last-child img:first-child');

            if ($detailsBtn != null) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);

                $this->exts->execute_javascript("arguments[0].click();", [$detailsBtn]);
                sleep(5);
                $this->waitFor('div.message button', 20);
                $downloadBtn = $this->exts->querySelector('div.message button');
                if ($downloadBtn != null) {
                    $this->exts->click_element($downloadBtn);
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf');
                    $invoiceFileName = basename($downloaded_file);

                    $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                    $this->exts->log('invoiceName: ' . $invoiceName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, '0', $invoiceFileName);
                        sleep(1);
                        $invoiceCount++;
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
                $this->waitFor('li.navicon_myprofile li.current a', 20);
                $backBtn = $this->exts->querySelector('li.navicon_myprofile li.current a');

                if ($backBtn != null) {
                    $this->exts->click_element($backBtn);
                    sleep(5);
                }
            }

            $lastDate = !empty($invoiceDate) && $invoiceDate <= $restrictDate;

            if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                break;
            } else if ($restrictPages == 0 && $dateRestriction && $lastDate) {
                break;
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
