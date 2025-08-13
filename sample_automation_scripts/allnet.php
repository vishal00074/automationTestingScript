<?php //added date filter to download invoices according to date
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


    // Server-Portal-ID: 12014 - Last modified: 17.04.2025 13:48:19 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://shop.allnet.de/account';
    public $loginUrl = 'https://shop.allnet.de/account/login';
    public $invoicePageUrl = 'https://shop.allnet.de/invoices';

    public $username_selector = 'form.login-form input[type="text"]';
    public $password_selector = 'form.login-form input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form.login-form button[type="submit"]';

    public $check_login_failed_selector = 'form.login-form div.alert.alert-danger';
    public $check_login_success_selector = 'a[href*="logout"]';

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
        $this->exts->openUrl($this->baseUrl);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);

            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->dateRange();

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

    private function dateRange()
    {
        $selectDate = new DateTime();

        $currentYear = $selectDate->format('Y');

        if ($this->restrictPages == 0) {
            // select date
            $selectDate->modify('-3 years');

            $day = $selectDate->format('d');
            $month = $selectDate->format('m');
            $year = $selectDate->format('Y');

            $this->exts->log('3 years previous date:: ' . $day . '-' . $month . '-' . $year);


            $this->exts->capture('date-range-3-years');
        } else {
            // select date
            $selectDate->modify('-3 months');

            $day = $selectDate->format('d');
            $month = $selectDate->format('m');
            $year = $selectDate->format('Y');

            $this->exts->log('3 months previous date:: ' . $day . '-' . $month . '-' . $year);

            $this->exts->capture('date-range-3-months');
        }
        for ($i = 1; $year <= $currentYear;) {
            $this->exts->log('year:: ' . $year);

            $this->processInvoices($year);
            $year++;
        }
    }


    private function processInvoices($year)
    {
        $url = "https://shop.allnet.de/invoices/?year=" . $year;
        $this->exts->openUrl($url);

        $this->exts->waitTillPresent('table tbody tr', 30);

        $rows = $this->exts->querySelectorAll('table tbody tr');
        $this->exts->log('Invoices found: ' . count($rows));
        foreach ($rows as $row) {
            if ($row->querySelector('button.download-invoice') != null) {
                $this->isNoInvoice = false;

                $invoiceAmount = trim($row->querySelectorAll('td')[3]->getText());

                $this->exts->log('invoice amount: ' . $invoiceAmount);

                $invoiceDate = trim($row->querySelectorAll('td')[2]->getText());

                $this->exts->log('invoice date: ' . $invoiceDate);

                $parsedDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Parsed date: ' . $parsedDate);

                $row->querySelectorAll('td')[4]->querySelector('button.download-invoice')->click();
                sleep(2);

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoiceName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsedDate, $invoiceAmount, $invoiceFileName);
                    sleep(5);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }


    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 30);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                sleep(2);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

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
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent($this->check_login_success_selector, 40);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
