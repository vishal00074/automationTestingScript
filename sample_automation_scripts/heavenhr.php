<?php // increase time after open the base url beacause script took time to ui added restrict invoices logic in download code. replace waitTillPresetn to waitFor

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

    // Server-Portal-ID: 3138 - Last modified: 02.08.2023 05:28:40 UTC - User: 1

    /*Define constants used in script*/
    // Server-Portal-ID: 30025 - Last modified: 12.02.2025 07:12:17 UTC - User: 1

    public $baseUrl = "https://www.heavenhr.com/dashboard";
    public $loginUrl = "https://www.heavenhr.com/web/DE/de/login";
    public $username_selector = 'input[name="_username"]';
    public $password_selector = 'input[name="_password"]';
    public $submit_button_selector = 'button[data-test-id="login-submit"]';
    public $login_tryout = 0;
    public $restrictPages = 3;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");
        $this->exts->loadCookiesFromFile();

        $this->exts->openUrl($this->baseUrl);
        sleep(20);


        $this->checkAndCloseCookiePopup();

        if (!$this->checkLogin()) {

            $this->fillForm(0);
            sleep(2);

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {
                $this->exts->capture("LoginFailed");
                if (
                    strpos(strtolower($this->exts->extract('div[class*="Login_pag"] p.errorMessage')), 'please check your registration data') !== false
                    || strpos(strtolower($this->exts->extract('div[class*="Login_pag"] p.errorMessage')), 'fen sie ihre anmeldedaten') !== false
                ) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        } else {
            sleep(10);
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(1);
            $this->waitFor($this->password_selector, 10);
            if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
                sleep(1);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(1);
                $err_msg = $this->exts->extract('form#formSignIn div#errorBag.alert-danger');
                if ($err_msg != "" && $err_msg != null) {
                    $this->exts->log($err_msg);
                    $this->exts->loginFailure(1);
                }
            }

            sleep(10);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    function checkAndCloseCookiePopup()
    {
        if ($this->exts->exists('#compliAcceptCookies')) {
            $this->exts->moveToElementAndClick('#compliAcceptCookies .bannerFooter > button');
            sleep(2);
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
        $this->waitFor('nav li#headerUser', 25);
        try {
            if ($this->exts->exists('nav li#headerUser')) {
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
    function invoicePage()
    {
        $this->exts->log("Invoice page");

        $this->exts->moveToElementAndClick('div.sidebar-offcanvas a#at-sidebar-page-company-dashboard-icon-employees-label');
        sleep(5);
        $this->exts->moveToElementAndClick('div.sidebar-offcanvas a[href="/employee-details"]');
        sleep(5);

        if ($this->exts->exists('table.employees-list-table')) {
            $employees = $this->exts->getElements('table.employees-list-table tbody tr');
            foreach ($employees as $i => $employee) {
                $this->exts->moveToElementAndClick('table.employees-list-table tbody tr:nth-child(' . ($i + 1) . ') a[href*="/employee-details/dashboard/"]');
                sleep(5);
                if (!$this->exts->exists('a#at-sidebar-page-employee-details-docs-pays-intranet-icon-label')) {
                    sleep(5);
                }
                $this->exts->moveToElementAndClick('a#at-sidebar-page-employee-details-docs-pays-intranet-icon-label');
                sleep(3);
                $this->exts->moveToElementAndClick('a[href*="/employee-details/payslips/"]');
                sleep(5);

                $this->downloadInvoice();

                $this->exts->moveToElementAndClick('div#companyMiniNavigation a[href="/employee-details"]');
                sleep(3);
            }
        }

        if ($this->totalFiles == 0) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    /**
     *method to download incoice
     */
    public $totalFiles = 0;
    public $totalInvoices = 0;
    function downloadInvoice($count = 1, $pageCount = 1)
    {
        $this->exts->log("Begin download invoice");
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log("restrictPages:: " . $restrictPages);


        $this->exts->capture('4-List-invoice');

        try {
            if ($this->exts->getElement('table.hhr-table-new tbody tr') != null) {
                $receipts = $this->exts->getElements('table.hhr-table-new tbody tr');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {

                    $tags = $this->exts->getElements('td', $receipt);
                    if (count($tags) == 2 && $this->exts->getElement('td a[href*="/payslips/download/"]', $receipt) != null) {
                        $receiptDate = trim($tags[1]->getAttribute('innerText'));
                        $receiptFileName = trim($tags[0]->getAttribute('innerText'));
                        $receiptUrl = $this->exts->extract('td a[href*="/payslips/download/"]', $receipt, 'href');
                        $receiptName = str_replace('.pdf', '', $receiptFileName);
                        $receiptAmount = '';

                        $this->exts->log("_____________________" . ($i + 1) . "___________________________________________");
                        $this->exts->log("Invoice Date: " . $receiptDate);
                        $this->exts->log("Invoice Name: " . $receiptName);
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $this->exts->log("Invoice Url: " . $receiptUrl);
                        $this->exts->log("Invoice FileName: " . $receiptFileName);
                        $this->exts->log("________________________________________________________________");

                        $invoice = array(
                            'receiptDate' => $receiptDate,
                            'receiptName' => $receiptName,
                            'receiptAmount' => $receiptAmount,
                            'receiptUrl' => $receiptUrl,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }

                $this->exts->log(">>>>>>>>>>>>>>>>>>>Invoice found: " . count($invoices));

                $this->totalFiles = count($invoices);
                $count = 1;
                foreach ($invoices as $invoice) {
                    if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                        return;
                    }
                    $invoiceDate = $this->exts->parse_date($invoice['receiptDate']);
                    if ($invoiceDate == '') {
                        $invoiceDate = $invoice['receiptDate'];
                    }

                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    $this->exts->log("Download file: " . $downloaded_file);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoiceDate, $invoice['receiptAmount'], $downloaded_file);
                        sleep(1);
                        $count++;
                        $this->totalInvoices++;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
