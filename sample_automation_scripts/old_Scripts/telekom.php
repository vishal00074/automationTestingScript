<?php // added failed login message to trigger loginfailedCOnfirmed added code to trigger account not ready

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

    // Server-Portal-ID: 1203990 - Last modified: 27.08.2025 12:54:22 UTC - User: 1

    public $baseUrl = 'https://rechnungonline.geschaeftskunden.telekom.de/gk';
    public $loginUrl = 'https://rechnungonline.geschaeftskunden.telekom.de/gk/auth';
    public $invoicePageUrl = 'https://rechnungonline.geschaeftskunden.telekom.de/gk/ben_ges_dok_ueb';

    public $username_selector = 'input#labelFor_name_ID, input#name';
    public $password_selector = 'input#labelFor_password_ID, input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input.input-submit, input#loginButton';

    public $check_login_failed_selector = 'div.errorinfo';
    public $check_login_success_selector = 'a[href*="logout"]';

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
        sleep(5);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->fillForm(0);
            sleep(10);

            if ($this->exts->queryXpath(".//div[normalize-space(.)='Ab sofort finden Sie Ihre Rechnungen im Magenta Business Service Portal (MBSP)!']") !=  null) {
                $this->exts->account_not_ready();
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            sleep(2);
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('passwor')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function fillForm($count)
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
                sleep(2);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->log("Remember Me");
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                    sleep(2);
                }

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

    private function processInvoices()
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $pagingCount = 0;
        $this->exts->log('Restrict Pages: ' . $restrictPages);

        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true; // (true) in case of date filter
        $this->exts->log('Restrict Date: ' . $restrictDate);

        $maxInvoices = 5;
        $invoiceCount = 0;

        $this->exts->waitTillPresent('form#doMsgForm table tbody tr td a[href*="Rechnung"]', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('form#doMsgForm table tbody tr');
        $anchor = 'td a[href*="Rechnung"]';

        $pagingCount++;

        foreach ($rows as $index => $row) {

            $rowSelector = 'form#doMsgForm table tbody tr:nth-child(' . ($index + 1) . ') ';

            $this->exts->waitTillPresent($rowSelector . $anchor, 30);

            if ($this->exts->querySelector($rowSelector . $anchor) != null) {

                $invoiceCount++;

                $invoiceUrl = '';
                $invoiceName = $this->exts->extract($rowSelector . 'td:nth-child(4)');
                $invoiceAmount = $this->exts->extract($rowSelector . 'td:last-child');

                $invoiceDate = $this->exts->extract($rowSelector . $anchor);
                $explodeDate = explode(' ', trim($invoiceDate));
                $invoiceDate = !empty($explodeDate[2]) ? $explodeDate[2] : '';

                $downloadBtn = $this->exts->querySelector($rowSelector . $anchor);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
                ));

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date(trim($invoiceDate), 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);

                sleep(2);
                $this->exts->waitTillPresent('a[onclick*="/gk/download/pdf/Rechnung"]', 10);

                $this->exts->click_element('a[onclick*="/gk/download/pdf/Rechnung"]');

                sleep(2);

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoiceName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }

                $this->exts->log(' ');
                $this->exts->log('---------------------------INVOICE ITERATION END-------------------------');
                $this->exts->log(' ');

                $this->exts->switchToInitTab();

                sleep(1);

                $this->exts->closeAllTabsButThis();

                sleep(1);

                $this->exts->openUrl($this->invoicePageUrl);


                $lastDate = !empty($invoiceDate) && $invoiceDate <= $restrictDate;

                if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                    break;
                } else if ($restrictPages == 0 && $dateRestriction && $lastDate) {
                    break;
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
