<?php //  replace waitTillPresent to waitFor I have added dateRange function to download invoices according to date base on restrict page conditon
// added pagiantion code use click_and_download funtion instead of js click button.

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
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673830/screens/';
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

    // Server-Portal-ID: 2867218 - Last modified: 19.08.2025 09:18:47 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = "https://www.henryschein-dental.de/global/Profiles/Myaccount.aspx";
    public $loginUrl = "https://www.henryschein-dental.de/Global/Profiles/Login.aspx";
    public $invoicePageUrl = 'https://www.henryschein-dental.de/global/olp/onlineinvoice.aspx';
    public $username_selector = 'div.LoginHomeColumn.last input[id*="Name"]';
    public $password_selector = 'div.LoginHomeColumn.last input[id*="Password"]';
    public $submit_button_selector = 'div.LoginHomeColumn.last input[type="submit"]';
    public $check_login_failed_selector = 'div.error.icon';
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

            $this->dateRange();

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

    private function fillForm($count)
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


    public function dateRange()
    {
        $selectDate = new DateTime();
        $currentDate = $selectDate->format('Y-m-d');
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0) {
            $selectDate->modify('-3 years');
            $formattedDate = $selectDate->format('Y-m-d');
        } else {
            $selectDate->modify('-3 months');
            $formattedDate = $selectDate->format('Y-m-d');
        }

        $url = $this->invoicePageUrl . '?search=Date|' . $formattedDate . '|' . $currentDate;

        $this->exts->openUrl($url);
    }


    public $totalInvoices = 0;
    private function processInvoices($count = 1)
    {
        $this->waitFor('div[id="MyAccountOrderHistoryFull"] > table > tbody table > tbody > tr', 10);
        $this->exts->capture("1 invoice page");
        $rows = $this->exts->querySelectorAll('div[id="MyAccountOrderHistoryFull"] > table > tbody table > tbody > tr');

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        foreach ($rows as $row) {

            if ($restrictPages != 0 && $this->totalInvoices >= 100) {
                return;
            }

            $this->isNoInvoice = false;

            $invoiceName = $this->exts->extract('span[id*="lblNumberOfBil"]', $row);
            $invoiceName = str_replace("Rechnungsnummer: ", "", $invoiceName);

            $invoiceDate = $row->querySelector('div.OrderHistoryItem span[id*="Date"]');
            if ($invoiceDate != null) {
                $invoiceDateText = $invoiceDate->getText();

                $parts = explode(':', $invoiceDateText);
                if (count($parts) > 1) {
                    $dateOnly = trim($parts[1]);
                    $invoiceDate = $this->exts->parse_date($dateOnly, '', 'Y-m-d');
                }
            }

            $amount = $row->querySelector('div.OrderHistoryItem span[id*="GrossValue"]');
            if ($amount != null) {
                $amountText = $amount->getText();

                $parts = explode(':', $amountText);
                if (count($parts) > 1) {
                    $amount = trim($parts[1]);
                }
            }

            $downloadBtn = $row->querySelector('tr div.OrderHistoryItem a[id*="GeneratePdf"]');

            if ($downloadBtn != null) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('amount: ' . $amount);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';

                $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);
                sleep(2);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $amount, $invoiceFileName);
                    sleep(1);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
        sleep(5);
        $count++;
        $pagination = 'tr[id*="OnlineInvoice_trOrderFilter"] span.PagingSize-Link-Span a[href*="pagenumber=' . $count . '"][class="hlPaging"]';

        if ($this->exts->querySelector($pagination) != null) {
            $url = $this->exts->getUrl();
            if (strpos($url, '&pagenumber=')) {
                $url = preg_replace('/&pagenumber=\d+/', '', $url);
            }

            $cleanedUrl = $url . '&pagenumber=' . $count;

            $this->exts->openUrl($cleanedUrl);
            sleep(7);
            $this->processInvoices($count);
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'GastroHero', '2673830', 'cmVjaG51bmdlbkBsdXVjLWV2ZW50LmRl', 'I1R1ZXJrZW5zdHJhc3NlMTc=');
$portal->run();
