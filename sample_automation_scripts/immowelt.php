<?php // I have updated the download code as per invoices listing ui and added date filter code to download invoices accoring to restrict page condition

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

    // Server-Portal-ID: 839163 - Last modified: 13.08.2025 10:41:19 UTC - User: 15

    public $baseUrl = 'https://immowelt-customerportal.de/Pages/invoiceportal/default.aspx';
    public $loginUrl = 'https://immowelt-customerportal.de/Pages/invoiceportal/default.aspx';
    public $invoicePageUrl = 'https://immowelt-customerportal.de/Pages/invoiceportal/default.aspx';

    public $username_selector = 'div[class*="signin-"] input[id*="_UserName"]';
    public $password_selector = 'div[class*="signin-"] input[id*="_password"]';
    public $remember_me = 'div[class*="signin-"] input[id*="_signInControl_Checkbox1"]';
    public $submit_login_selector = 'div[class*="signin-"] input[id*="_signInControl_login"]';

    public $check_login_failed_selector = '.eCareLoginBox .loginErrorMessage';
    public $check_login_success_selector = 'div.navbar-header ul li a[href="/_layouts/CustomLogout/SignOut.aspx"]';

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
        $this->exts->log('restrictPages:: ' . $this->restrictPages);

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(20);
        }


        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->queryXpath(".//label[(contains(normalize-space(.), 'Rechnung') or contains(normalize-space(.), 'The invoice'))]/input[@type='checkbox' and not(@disabled)]")) {
                $this->exts->moveToElementAndClick(".//label[(contains(normalize-space(.), 'Rechnung') or contains(normalize-space(.), 'The invoice'))]/input[@type='checkbox' and not(@disabled)]");
                sleep(5);
                $this->processBilling(1);
            }


            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract('div.alert-message')), 'an error processing the form') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('span[id*="signInControl_FailureText"] center')), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->log("Username is not a valid email address");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {

            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->remember_me != '') {
                $this->exts->moveToElementAndClick($this->remember_me);
                sleep(2);
            }

            $this->exts->capture("2-login-page-filled");
            sleep(5);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function dateRange()
    {
        if ($this->exts->querySelector('div.invoice-Years span.invoice-MoreLink') != null) {
            $this->exts->moveToElementAndClick('div.invoice-Years span.invoice-MoreLink');
            sleep(7);
        }

        $selectDate = new DateTime();
        $currentDate = $selectDate->format('Y');

        if ($this->restrictPages == 0) {
            $selectDate->modify('-3 years');
            $restrictYear = $selectDate->format('Y');
        } else {
            $selectDate->modify('-3 months');
            $restrictYear = $selectDate->format('Y');
        }

        $years = $this->exts->getElements('div.invoice-Years div.invoice-Label');
        foreach ($years as $year) {
            $yearCheckbox =  $this->exts->getElement('input[type="checkbox"]', $year);

            try {
                $yearCheckbox->click();
            } catch (\Exception $exception) {
                $this->exts->execute_javascript('arguments[0].click();', [$yearCheckbox]);
            }

            $invoiceYear =  trim($this->exts->extract('label', $year));
            $this->exts->log('invoiceYear::' . $invoiceYear);
            $this->exts->log('restrictYear::' . $restrictYear);
            if ($restrictYear == $invoiceYear) {
                break;
            }
        }
    }

    public $totalInvoices = 0;

    private function processBilling($page = 1)
    {
        sleep(15);
        $this->dateRange();
        sleep(5);
        $this->exts->capture("4-billing-page");
        $invoices = [];
        $rows = $this->exts->getElements('table#vgt-table tbody tr');
        $this->exts->log('Total Rows - ' . count($rows));
        $selectDate = new DateTime();
        $selectDate->modify('-3 months');
        $lastThreeMonth = $selectDate->format('d.m.Y');
        foreach ($rows as $key => $row) {

            if ($this->totalInvoices >= 100) {
                return;
            }

            $invoiceBtn = $this->exts->getElement('td span.invoice-MoreLink', $row);

            $invoiceName = $this->exts->extract('td span.invoice-MoreLink', $row);
            $invoiceDate = trim($this->exts->extract('td:nth-child(3)', $row));


            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . '');

            $invoiceDateObj = DateTime::createFromFormat('d.m.Y', $invoiceDate);
            $lastThreeMonthObj = DateTime::createFromFormat('d.m.Y', $lastThreeMonth);

            if ($this->restrictPages != 0 &&  $invoiceDateObj < $lastThreeMonthObj) {
                return;
            }

            $invoiceAmount = '';
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'd M Y', 'Y-m-d');
            $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf' : '';

            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
                continue;
            }

            $downloaded_file = $this->exts->click_and_download($invoiceBtn, 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {

                if (empty($invoiceFileName)) {
                    $invoiceFileName = basename($downloaded_file);
                    $this->exts->log('invoiceFileName: ' . $invoiceFileName);
                }

                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                sleep(1);
                $this->isNoInvoice = false;
                $this->totalInvoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'Immowelt Kundenportal', '2673482', 'aW5mb0B0b20taW1tb2JpbGllbi5jb20=', 'UXQlb3FrV2VAKExSYjI=');
$portal->run();
