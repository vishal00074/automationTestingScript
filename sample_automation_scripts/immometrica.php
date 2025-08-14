<?php // replace waitTIllPresent to waitFor and updated download code change the click process to extract invoices url  added $this->isNoInvoice

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

    // Server-Portal-ID: 409818 - Last modified: 14.02.2025 06:02:05 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = "https://www.immometrica.com/de/home";
    public $loginUrl = "https://www.immometrica.com/de/accounts/login";
    public $invoicePageUrl = 'https://www.immometrica.com/de/profile/invoices';
    public $username_selector = 'input[type="email"]';
    public $password_selector = 'input[type="password"]';
    public $submit_button_selector = 'button[data-action="submitLogin"]';
    public $check_login_failed_selector = 'div.alert-danger';
    public $check_login_success_selector = 'a.logout';
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
            $this->waitFor($this->check_login_failed_selector, 10);
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function fillForm($count)
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


    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
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

    public $totalInvoices = 0;
    private function processInvoices()
    {
        $this->waitFor('section.tables > div > div > div > div > div.card-body > div > table > tbody > tr', 10);
        $this->exts->capture("1 invoice page");
        $invoices = [];
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $rows = $this->exts->querySelectorAll('section.tables > div > div > div > div > div.card-body > div > table > tbody > tr');
        foreach ($rows as $row) {
            $invoiceLink = $this->exts->getElement('td:nth-child(2) > a', $row);
            if ($invoiceLink != null) {
                $invoiceName = $row->querySelector('td:nth-child(2) > a');
                if ($invoiceName  != null) {
                    $invoiceName = $invoiceName->getText();
                }

                $invoiceDate = $row->querySelector('td:nth-child(1)');
                if ($invoiceDate  != null) {
                    $invoiceDate = $invoiceDate->getText();
                }

                $amount = $row->querySelector('td:nth-child(3)');
                if ($amount  != null) {
                    $amount = $amount->getText();
                }

                $invoiceUrl = $invoiceLink->getAttribute("href");

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $amount,
                    'invoiceUrl' => $invoiceUrl,
                ));
            }
            $this->isNoInvoice = false;
        }
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {

            if ($this->totalInvoices >= 50 && $restrictPages != 0) {
                return;
            }

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceAmount']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $this->totalInvoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'Immowelt Kundenportal', '2673482', 'aW5mb0B0b20taW1tb2JpbGllbi5jb20=', 'UXQlb3FrV2VAKExSYjI=');
$portal->run();
