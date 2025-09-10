<?php // I have added config last_invoice_date variable and use in download code date filter to download according to config dates 
// in case config start_date not found then implemented trestrct page condition

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

    // Server-Portal-ID: 413900 - Last modified: 19.02.2025 13:34:10 UTC - User: 1

    public $baseUrl = 'https://meinekundenwelt.netcologne.de/';
    public $loginUrl = 'https://meinekundenwelt.netcologne.de/';
    public $invoicePageUrl = '';

    public $username_selector = 'input#username';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form input[type=submit]';

    public $check_login_failed_selector = 'div.alert span';
    public $check_login_success_selector = 'a[href*=logout],li[data-e2e*=link-logout]';

    public $isNoInvoice = true;
    public $restrictPages = 3;


    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
        }
        sleep(5);
        $this->exts->waitTillPresent('div.c-cookies', 10);
        if ($this->exts->exists("a[id*=AllowAll]")) {
            $this->exts->click_element("a[id*=AllowAll]");
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);
            $this->exts->waitTillPresent("li[data-e2e*=desktop] a[href*=rechnungen]", 20);
            $this->exts->click_element("li[data-e2e*=desktop] a[href*=rechnungen]");
            sleep(5);
            if ($this->exts->exists("a[id*=AllowAll]")) {
                $this->exts->click_element("a[id*=AllowAll]");
            }
            sleep(3);
            $this->filterDate();
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

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }
                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_element($this->submit_login_selector);
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
            $this->exts->waitTillPresent($this->check_login_success_selector, 20);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function filterDate()
    {
        $this->exts->waitTillPresent('input[name=fromDate]', 50);
        if (isset($this->exts->config_array["start_date"]) && $this->exts->config_array["start_date"] != '') {

            $startDate = $this->exts->config_array["start_date"];

            $this->exts->log('Config startDate:: ' . $startDate);

            $timestamp = strtotime($startDate);
            $startDay = (int)date('d', $timestamp); // Day
            $startMonth = (int)date('m', $timestamp); // Month
            $startYear = (int)date('Y', $timestamp); // Year
            $startDate = sprintf('%02d.%02d.%04d', $startDay, $startMonth, $startYear);
        } else {
            $selectDate = new DateTime();
            $startDay = (int)date('d'); // Day
            $startMonth = (int)date('m'); // Month
            $startYear = (int)date('Y'); // Year


            if ($this->restrictPages == 0) {
                $selectDate->modify('-3 years');
                $startYear = $selectDate->format('Y');
            } else {
                $selectDate->modify('-3 months');
                $startMonth = $selectDate->format('m');
            }

            $startDate = sprintf('%02d.%02d.%04d', $startDay, $startMonth, $startYear);

            $this->exts->log('startDate:: ' . $startDate);
        }
        $this->exts->moveToElementAndType('input[name=fromDate]', $startDate);

        if (isset($this->exts->config_array["last_invoice_date"]) && $this->exts->config_array["last_invoice_date"] != '') {
            $todate  = $this->exts->config_array["last_invoice_date"];

            $this->exts->log('Config toDate:: ' . $todate);
            $lasttimestamp = strtotime($todate);

            $lastDay = (int)date('d', $lasttimestamp); // Day
            $lastMonth = (int)date('m', $lasttimestamp); // Month
            $lastYear = (int)date('Y', $lasttimestamp); // Year
            $todayDate = sprintf('%02d.%02d.%04d', $lastDay, $lastMonth, $lastYear);
        } else {
            // Get today's date in the same format
            $todayDay = (int)date('d'); // Day
            $todayMonth = (int)date('m'); // Month
            $todayYear = (int)date('Y'); // Year

            $todayDate = sprintf('%02d.%02d.%04d', $todayDay, $todayMonth, $todayYear);
            $this->exts->log('todayDate:: ' . $todayDate);
        }

        $this->exts->moveToElementAndType('input[name*=toDate]', $todayDate);

        $this->processInvoices();
    }

    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('form button', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $downloadBtn = $this->exts->querySelector('form button');
        $this->exts->click_element($downloadBtn);
        sleep(10);
        $this->exts->wait_and_check_download('zip');

        $downloaded_file = $this->exts->find_saved_file('zip');
        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->extract_zip_save_pdf($downloaded_file);
        } else {
            sleep(60);
            $this->exts->wait_and_check_download('zip');
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->extract_zip_save_pdf($downloaded_file);
            }
        }
    }

    function extract_zip_save_pdf($zipfile)
    {
        $zip = new \ZipArchive;
        $res = $zip->open($zipfile);
        if ($res === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zipPdfFile = $zip->statIndex($i);
                $fileName = basename($zipPdfFile['name']);
                $fileInfo = pathinfo($fileName);
                if ($fileInfo['extension'] === 'pdf') {
                    $this->isNoInvoice = false;
                    $zip->extractTo($this->exts->config_array['download_folder'], array(basename($zipPdfFile['name'])));
                    $saved_file = $this->exts->config_array['download_folder'] . basename($zipPdfFile['name']);
                    $this->exts->new_invoice($fileInfo['filename'], "", "", $saved_file);
                    sleep(1);
                }
            }
            $zip->close();
            unlink($zipfile);
        } else {
            $this->exts->log(__FUNCTION__ . '::File extraction failed');
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
