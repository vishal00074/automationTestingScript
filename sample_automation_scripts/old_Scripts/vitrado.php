<?php // check_solve_cloudflare_page function in login form updated submit_login_selector , check_login_failed_selector added restrict page for pagination
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

    // Server-Portal-ID: 532740 - Last modified: 06.03.2025 10:27:48 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://app.vitrado.de';
    public $loginUrl = 'https://app.vitrado.de/v2/login';
    public $invoicePageUrl = 'https://app.vitrado.de/v2/partner-profile/receipts';

    public $username_selector = 'form.loginForm input#username';
    public $password_selector = 'form.loginForm input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[value="Login"], form.loginForm input[type="submit"]';

    public $check_login_failed_selector = 'div.callout-danger p';
    public $check_login_success_selector = 'li a[href*="/partner-profile"]';

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
            sleep(5);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), strtolower('Ein Fehler ist aufgetreten')) !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('table.table-statistic tbody tr', 15);

        $rows = $this->exts->querySelectorAll('table.table-statistic tbody tr');
        $this->exts->log('Invoices found: ' . count($rows));
        foreach ($rows as $row) {
            $this->isNoInvoice = false;

            $invoiceAmount = trim($row->querySelectorAll('td')[2]->getText());

            $this->exts->log('invoice amount: ' . $invoiceAmount);


            $invoiceDate =  $row->querySelectorAll('td')[0]->getText();
            $this->exts->log('invoice date: ' . $invoiceDate);

            $parsedDate = $this->exts->parse_date($invoiceDate, '', 'Y-m-d');

            $row->querySelectorAll('td')[1]->querySelector('a')->click();
            sleep(2);


            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoiceName);


            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, '', $invoiceAmount, $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            sleep(2);
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $nextButton = $this->exts->querySelector('li.page-next:not(.disabled) a');

        if ($paging_count < $restrictPages && $nextButton != null) {
            $this->exts->querySelector('li.page-next:not(.disabled) a')->click();
            sleep(10);
            $paging_count++;
            $this->processInvoices($paging_count);
        }
    }


    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 15);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->check_solve_cloudflare_page();

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->exts->capture("1-login-page-filled");
                sleep(5);
                $this->check_solve_cloudflare_page();
                sleep(5);

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function check_solve_cloudflare_page() {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if(
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) && 
            $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ){
            for ($waiting=0; $waiting < 10; $waiting++) {
                sleep(2);
                if($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])){
                    sleep(3);
                    break;
                }
            }
        }
    
        if($this->exts->exists($unsolved_cloudflare_input_xpath)){
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if($this->exts->exists($unsolved_cloudflare_input_xpath)){
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if($this->exts->exists($unsolved_cloudflare_input_xpath)){
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
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
