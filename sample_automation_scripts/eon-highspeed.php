<?php // updated invoice code. updated login and home url

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

    // Server-Portal-ID: 1186021 - Last modified: 11.06.2025 05:07:35 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://service.eon-highspeed.com/home';
    public $loginUrl = 'https://service.eon-highspeed.com/login';
    public $invoicePageUrl = 'https://service.eon-highspeed.com/#/invoices';

    public $username_selector = 'input[name="username"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'div[class*="notification-error"]';
    public $check_login_success_selector = 'div.MuiGrid-root a[href="/invoices"]';

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

            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            sleep(2);
            if ($this->exts->querySelector('div.MuiGrid-root a[href="/invoices"]') != null) {
                $this->exts->moveToElementAndClick('div.MuiGrid-root a[href="/invoices"]');
                sleep(5);
            }

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            if ($this->exts->exists($this->check_login_failed_selector)) {
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
        $this->exts->waitTillPresent($this->username_selector, 5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                sleep(1);

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
    function checkLogin()
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

    public $totalInvoices = 0;

    private function processInvoices($paging_count = 1)
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->waitTillPresent('div.MuiPaper-root', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div.MuiPaper-root');
        $count = 0;

        foreach ($rows as $row) {

            if ($this->totalInvoices >= 50 && $restrictPages != 0) {
                return;
            }

            $count++;

            if ($this->exts->querySelector('div.MuiAccordionDetails-root a.itn--file-action:last-child', $row) != null) {

                $invoiceUrl = '';
                $invoiceName = '';
                $invoiceAmount = '';
                $invoiceDate = $this->exts->extract('div.MuiAccordionSummary-content p', $row);
                sleep(2);
                $downloadBtn = $this->exts->querySelector('div.MuiAccordionDetails-root a.itn--file-action:last-child', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
                ));

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                $invoiceDate = $this->exts->parse_date(trim($invoiceDate), 'F Y', 'Y-m-01');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);

                // $this->exts->execute_javascript("
				// 	var element = 'div.MuiPaper-root:nth-of-type(" . $count . ") div.MuiAccordionDetails-root a.itn--file-action:last-child';
				// 	document.querySelector(element).click();
				// ");

                sleep(10);

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceName = basename($downloaded_file, '.pdf');

                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                    $this->totalInvoices++;
                    sleep(5);
                } else {
                    $this->exts->log('Timeout when download ');
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
