<?php // 
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

    // Server-Portal-ID: 2214165 - Last modified: 26.08.2025 10:27:57 UTC - User: 1

    // Start Script 

    public $baseUrl = 'https://app.meetovo.de/dashboard/meine-funnels';
    public $loginUrl = 'https://app.meetovo.de/dashboard/login';
    public $invoicePageUrl = 'https://app.meetovo.de/dashboard/account';
    public $username_selector = 'input#email-login_email';
    public $password_selector = 'input[type="password"]';
    public $submit_login_selector = "//button[@type='submit' and span[text()='Einloggen']]";
    public $start_login_selector = 'div.section-layout-3-main .box-shadow-1 > a';
    public $check_login_failed_selector = 'div.ant-alert-error span.ant-alert-description';
    public $check_login_success_selector = 'section .ant-layout-sider-children';
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
            sleep(10);
            $this->exts->waitTillPresent($this->start_login_selector, 20);
            if ($this->exts->exists($this->start_login_selector)) {
                $this->exts->click_element($this->start_login_selector);
            }

            $this->fillForm(0);
            sleep(10);
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(20);
            if ($this->exts->exists('div.ant-tabs-nav-scroll > div > div > div[role="tab"]:nth-child(2)')) {
                $this->exts->moveToElementAndClick('div.ant-tabs-nav-scroll > div > div > div[role="tab"]:nth-child(2)');
            }
            sleep(5);
            $this->processInvoices();


            // Final, check no invoice 
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);

            if (stripos($error_text, strtolower('Login-Daten sind falsch oder deine E-Mail-Adresse')) !== false) {
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
        $this->exts->waitTillPresent($this->username_selector, 20);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(3);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(3);
                $this->exts->capture("1-login-page-filled");
                sleep(2);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_element($this->submit_login_selector);
                     sleep(10);
                }
               

                $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

                $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);

                if (stripos($error_text, strtolower('Login-Daten sind falsch oder deine E-Mail-Adresse')) !== false) {
                    $this->exts->log("Wrong credential !!!!");
                    $this->exts->loginFailure(1);
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

    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('.invoices table tbody  tr', 30);
        sleep(10);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('.invoices table tbody  tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(3) a', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('td:nth-child(3) a', $row)->getAttribute('href');
                preg_match('#/receipt/([^/]+)/#', $invoiceUrl, $matches);
                $invoiceName = $matches[1];
                $invoiceAmount = $this->exts->extract('td:nth-child(2)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(1)', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
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

            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(5);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";

            if ($this->exts->getElement('//h1[text()="Receipt "]') != null) {
                $downloaded_file = $this->exts->download_current($invoiceFileName, 5);
                sleep(10);
            }

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(2);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
