<?php // updated download code add switchToOldestActiveTab and closeAllTabsExcept to close unncessary tabs and added restrctPages and 
//added js code to click on invoice button

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
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673461/screens/';
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

    // Server-Portal-ID: 3014647 - Last modified: 25.08.2025 23:10:42 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://kundenportal.rabot.energy/';
    public $loginUrl = 'https://kundenportal.rabot.energy/login';
    public $invoicePageUrl = 'https://kundenportal.rabot.energy/invoices';


    public $username_selector = 'form input[data-testid="rabot-input-email"]';
    public $password_selector = 'form input[data-testid="rabot-input-password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form button[type="submit"]';

    public $check_login_failed_selector = 'div.Toastify__toast--error';
    public $check_login_success_selector = 'a[href="/invoices"]';

    public $isNoInvoice = true;

    public $isFailedLogin = false;


    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        $this->acceptCookies();


        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->acceptCookies();

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
            if ($this->exts->exists($this->isFailedLogin)) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    public $totalInvoices = 0;

    private function processInvoices($paging_count = 1)
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

        $this->exts->startTrakingTabsChange();
        $this->exts->waitTillPresent('div[data-testid="InvoiceDetailBillogramCTA"]', 30);

        $rows = $this->exts->querySelectorAll('div[data-testid="InvoiceDetailBillogramCTA"]');
        $this->exts->log('Invoices found: ' . count($rows));
        foreach ($rows as $index => $row) {

            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }

            $this->isNoInvoice = false;

            $this->exts->execute_javascript("arguments[0].click()", [$row]);


            sleep(10);
            $this->exts->switchToNewestActiveTab();
            $this->exts->waitTillPresent('div.billogram-web-invoice-property__value', 20);

            $invoiceAmount = $this->exts->querySelectorAll('div.billogram-web-invoice-property')[0]?->querySelector('div.billogram-web-invoice-property__value')?->getText();

            $this->exts->log('invoice amount: ' . $invoiceAmount);


            $invoiceDate =  $this->exts->querySelectorAll('div.billogram-web-invoice-property')[1]?->querySelector('div.billogram-web-invoice-property__value')?->getText();
            $this->exts->log('invoice date: ' . $invoiceDate);

            $parsedDate = $this->exts->parse_date($invoiceDate, '', 'Y-m-d');

            $this->exts->click_element('div.billogram-web-invoice-section-actions button');

            sleep(2);

            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoiceName);


            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $parsedDate, $invoiceAmount, $invoiceFileName);
                sleep(1);
                $this->totalInvoices++;
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            sleep(2);
            $this->exts->switchToOldestActiveTab();
            sleep(2);
            $this->exts->closeAllTabsExcept();
            sleep(2);
        }
    }



    private function acceptCookies()
    {
        $this->exts->waitTillPresent('div[data-testid="consent"] button.primary', 10);
        if ($this->exts->exists('div[data-testid="consent"] button.primary')) {
            $this->exts->querySelector('div[data-testid="consent"] button.primary')->click();
        }
    }


    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 15);
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
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {

            $this->exts->waitTillPresent($this->check_login_failed_selector, 10);
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->isFailedLogin = true;
            }

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

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
