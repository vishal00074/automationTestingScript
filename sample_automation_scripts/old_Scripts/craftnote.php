<?php // replace waitTillPresent to waitFor and adjust sleep time and updated download code accoriding to ui

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

    // Server-Portal-ID: 1387427 - Last modified: 24.08.2025 03:37:24 UTC - User: 1

    public $baseUrl = 'https://app.mycraftnote.de/';
    public $loginUrl = 'https://app.mycraftnote.de/';
    public $invoicePageUrl = 'https://app.mycraftnote.de/settings/subscription';

    public $username_selector = 'form input[type="text"]';
    public $password_selector = 'form input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form button[type="submit"]';

    public $check_login_failed_selector = 'span.mat-simple-snack-bar-content';
    public $check_login_success_selector = 'button[data-cy="nav-header-settings"]';

    public $isNoInvoice = true;

    public $isFailedLogin = false;
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        $this->exts->capture('1-init-page');
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(2);
            $this->exts->execute_javascript('
			    var el = document.querySelector("#usercentrics-root")?.shadowRoot?.querySelector(\'button[data-testid="uc-accept-all-button"]\');
			    if (el) el.click();
			');
            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("3-login-success");
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);
            $this->exts->startTrakingTabsChange();

            $this->waitFor('craftnote-package-allocation', 5);
            if ($this->exts->exists('craftnote-package-allocation')) {
                $this->exts->execute_javascript("
                    var el = document.querySelector('craftnote-package-allocation')?.shadowRoot
                        ?.querySelectorAll('button.bg-secondary')[1];
                    if (el) el.click();
                ");

                sleep(10);
                $this->exts->switchToNewestActiveTab();
                sleep(5);
            }
            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if ($this->isFailedLogin) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function closeReminderScreen()
    {
        $this->waitFor('div.remind-me-later-container a', 5);
        $this->exts->click_if_existed('div.remind-me-later-container a');
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 10);
        $this->exts->execute_javascript('
		    var el = document.querySelector("#usercentrics-root")?.shadowRoot?.querySelector(\'button[data-testid="uc-accept-all-button"]\');
		    if (el) el.click();
		');
        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->exts->capture("2-login-page");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            $this->exts->capture("2-login-page-filled");
            sleep(1);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_element($this->submit_login_selector);
                sleep(2);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->waitFor($this->check_login_failed_selector, 15);
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->isFailedLogin = true;
            }
            $this->closeReminderScreen();
            $this->waitFor($this->check_login_success_selector, 15);
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
        sleep(5);
        $this->waitFor('a[href*="invoice.stripe.com"]', 10);

        $this->exts->capture("4-invoices-page");
        // Keep clicking more but maximum upto 10 times

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0) {
            $maxAttempts = 10;
        } else {
            $maxAttempts = $restrictPages;
        }

        $paging_count = 0;

        while ($paging_count < $maxAttempts && $this->exts->exists('button[data-testid="view-more-button"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="view-more-button"]');
            sleep(4);
            $paging_count++;
        }

        $invoices = [];

        $rows = $this->exts->querySelectorAll('a[href*="invoice.stripe.com"]');
        foreach ($rows as $row) {
            array_push($invoices, array(
                'invoiceUrl' => $row->getAttribute('href')
            ));
        }
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(5);
            if ($this->exts->querySelector('div.InvoiceDetailsRow-Container > button:nth-child(1)') != null) {
                $invoiceName = $this->exts->extract('table.InvoiceDetails-table tr:nth-child(1) > td:nth-child(2)');
                $invoiceDate = $this->exts->extract('table.InvoiceDetails-table tr:nth-child(2) > td:nth-child(2)');
                $invoiceAmount = $this->exts->extract('div[data-testid="invoice-summary-post-payment"] h1[data-testid="invoice-amount-post-payment"]');
                $downloadBtn = $this->exts->querySelector('div.InvoiceDetailsRow-Container > button:nth-child(1)');

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
                $this->isNoInvoice = false;
            }
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'm.d.y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
