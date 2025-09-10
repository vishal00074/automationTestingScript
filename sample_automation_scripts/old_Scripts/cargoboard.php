<?php // I have replaced waitTillPresent to waitFor and added restrctpage logic

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

    // Server-Portal-ID: 132716 - Last modified: 11.02.2025 05:34:39 UTC - User: 1

    public $baseUrl = 'https://cargoboard.com/';
    public $loginUrl = 'https://my.cargoboard.com/de/login';
    public $invoicePageUrl = 'https://my.cargoboard.com/de/account/payment';

    public $username_selector = '.form-login-email input[name="_username"], div#content input[name="email"]';
    public $password_selector = '.form-login-email input[name="_password"], div#content input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = '.form-login-email input[type="submit"], div#content button[type="submit"]';

    public $check_login_failed_selector = '.form-login-email div.alert.alert-danger';
    public $check_login_success_selector = "//a[contains(., 'Log out') or contains(., 'Abmelden')]";

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        sleep(3);
        $this->waitFor($this->check_login_success_selector, 10);
        if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
            $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        }
        $this->exts->capture('1-init-page');
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->queryXpath($this->check_login_success_selector) == null) {
            $this->checkFillLogin();
            sleep(3);
            $this->waitFor($this->check_login_success_selector, 10);
            if ($this->exts->queryXpath($this->check_login_success_selector) == null) {
                $this->exts->openUrl($this->loginUrl);
                sleep(3);
                $this->checkFillLogin();
                sleep(3);
            }
        }
        $this->waitFor($this->check_login_success_selector, 10);
        if ($this->exts->queryXpath($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->waitFor('a[data-bs-target="#billHistoryModal"]', 10);
            if ($this->exts->exists('a[data-bs-target="#billHistoryModal"]')) {
                $this->exts->moveToElementAndClick('a[data-bs-target="#billHistoryModal"]');
            }
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (stripos($this->exts->extract($this->check_login_failed_selector), 'Fehlerhafte Zugangsdaten') !== false) {
                $this->exts->loginFailure(1);
            } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->waitFor($this->password_selector, 10);
        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->click_by_xdotool($this->remember_me_selector);
            sleep(2);
            $this->solve_login_cloudflare();
            $this->exts->capture("2-login-page-filled");
            $this->exts->click_element($this->submit_login_selector);
            sleep(5);
            $this->solve_login_cloudflare();
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_element($this->submit_login_selector);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function solve_login_cloudflare()
    {
        $this->waitFor('form div.cf-turnstile#turnstile-captcha-box-login-password', 10);
        $this->exts->capture_by_chromedevtool("blocked-page-checking");
        if ($this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password') && !$this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password.-success')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            $this->exts->click_by_xdotool('form div.cf-turnstile#turnstile-captcha-box-login-password', 30, 28);
            $this->waitFor('form div.cf-turnstile#turnstile-captcha-box-login-password', 10);
            if ($this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password') && !$this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password.-success')) {
                $this->exts->click_by_xdotool('form div.cf-turnstile#turnstile-captcha-box-login-password', 30, 28);
                sleep(20);
            }
            if ($this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password') && !$this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password.-success')) {
                $this->exts->click_by_xdotool('form div.cf-turnstile#turnstile-captcha-box-login-password', 30, 28);
                sleep(20);
            }
            if ($this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password') && !$this->exts->check_exist_by_chromedevtool('form div.cf-turnstile#turnstile-captcha-box-login-password.-success')) {
                $this->exts->click_by_xdotool('form div.cf-turnstile#turnstile-captcha-box-login-password', 30, 28);
                sleep(20);
            }
        }
    }

    public $totalInvoices = 0;

    private function processInvoices($count = 0)
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->waitFor('div.ag-cell[col-id="download"]', 10);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $paths = explode('/', $this->exts->getUrl());

        $rows = $this->exts->querySelectorAll('div.ag-cell[col-id="download"]');
        foreach ($rows as $row) {

            $invoiceUrl = '';
            // invoices name not aligned with invoice button
            $invoiceName = '';


            $invoiceDate = '';
            $invoiceAmount = '';
            $downloadBtn = $row;

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl,
                'downloadBtn' => $downloadBtn
            ));
            $this->isNoInvoice = false;
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {

            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }


            $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd-M-y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                $this->totalInvoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'Immowelt Kundenportal', '2673482', 'aW5mb0B0b20taW1tb2JpbGllbi5jb20=', 'UXQlb3FrV2VAKExSYjI=');
$portal->run();
