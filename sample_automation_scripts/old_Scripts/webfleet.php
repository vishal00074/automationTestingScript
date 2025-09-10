<?php // updated download code added hardcoded for testing the script on test engine

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

    // Server-Portal-ID: 228699 - Last modified: 26.02.2025 13:44:08 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.webfleet.com/';
    public $loginUrl = 'https://www.webfleet.com/webfleet/products/login/';
    public $invoicePageUrl = 'https://live.webfleet.com/web/index.html#/invoices';

    public $username_selector = 'form#frmWebfleetLogin input[name="username"],  form#kc-form-login input[name="useraccount"]';
    public $password_selector = 'form#frmWebfleetLogin input[name="password"], form#kc-form-login input[name="password"]';
    public $remember_me_selector = 'form#frmWebfleetLogin input[name="rememberme"]';
    public $submit_login_selector = 'form#frmWebfleetLogin button#formWebfleetLoginSubmit, form#kc-form-login button#submit_btn';

    public $username_selector_new = 'input#useraccount';
    public $password_selector_new = 'input#password';
    public $remember_me_selector_new = 'input#rememberMe';
    public $submit_login_selector_new = 'button#submit_btn';

    public $check_login_failed_selector = '#wf-login-content .kc-feedback-text';
    public $check_login_success_selector = 'a[href*="/invoices"]';

    // added the webfleet account hardcoded 
    public $webfleet_account_name = '';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->webfleet_account_name = (isset($this->exts->config_array["webfleet_account_name"]) && !empty($this->exts->config_array["webfleet_account_name"])) ? $this->exts->config_array["webfleet_account_name"] : $this->webfleet_account_name;
        
        // I have added hardcoded value for account name
        $this->webfleet_account_name = 'blumenwelt';

        // $this->exts->openUrl($this->baseUrl);
        // sleep(1);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->checkFillLogin();
            sleep(20);
        }
        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->exists('[data-wf-gtm-name="welcomescreenButtonCancel"]')) {
                $this->exts->moveToElementAndClick('[class*="checkbox-unchecked"] [data-wf-gtm-name="welcomescreenCheckboxDoNotDisplayAgain"]');
                $this->exts->moveToElementAndClick('[data-wf-gtm-name="welcomescreenButtonCancel"]');
                sleep(2);
            }
            sleep(10);
            if ($this->exts->exists('div[class*=Modal] div[data-testid="checkbox"]')) {
                $this->exts->moveToElementAndClick('div[class*=Modal] div[data-testid="checkbox"]');
                $this->exts->moveToElementAndClick('div[class*=Modal] button[class*=cancel]');
                sleep(2);
            }

            // Open invoices url and download invoice
            if ($this->exts->querySelector('a[href*="/invoices"]') != null) {
                $this->exts->moveToElementAndClick('a[href*="/invoices"]');
            } else {
                $this->exts->openUrl($this->invoicePageUrl);
                if ($this->exts->exists('div[class*=Modal] div[data-testid="checkbox"]')) {
                    $this->exts->moveToElementAndClick('div[class*=Modal] div[data-testid="checkbox"]');
                    $this->exts->moveToElementAndClick('div[class*=Modal] button[class*=cancel]');
                    sleep(2);
                }
            }
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'account name or password') !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos($this->exts->extract('.kc-feedback-text', null, 'innerText'), 'nvalid username or password') !== false || stripos($this->exts->extract('.kc-feedback-text', null, 'innerText'), 'Benutzername oder Passwort') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->webfleet_account_name === '') {
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('section#step1-2fa-apps')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector);
        if ($this->exts->querySelector($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Accountname");
            $this->exts->moveToElementAndType('form#kc-form-login input[name="accountname"]', $this->webfleet_account_name);
            sleep(1);

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '') {
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            }
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else if ($this->exts->querySelector($this->password_selector_new) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Accountname : " . $this->webfleet_account_name);
            $this->exts->moveToElementAndType('input#accountname', $this->webfleet_account_name);
            sleep(1);

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector_new, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector_new, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '') {
                $this->exts->moveToElementAndClick($this->remember_me_selector_new);
            }
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector_new);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('table tbody tr:not([class*="datagrid-row-empty"]');
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr:not([class*="datagrid-row-empty"]');
        for ($i = 0; $i < count($rows); $i++) {
            $row = $this->exts->querySelectorAll('table tbody tr:not([class*="datagrid-row-empty"]')[$i];
            if ($row != null) {
                try {
                    $this->exts->log(__FUNCTION__ . ' trigger click.');
                    $row->click();
                } catch (\Exception $exception) {
                    $this->exts->log(__FUNCTION__ . ' by javascript' . $exception);
                    $this->exts->executeSafeScript("arguments[0].click()", [$row]);
                }
                sleep(3);
                $invoice_button = $this->exts->querySelector('div[data-testid="button-dropdown"] button');
                if ($invoice_button != null) {

                    try {
                        $this->exts->log(__FUNCTION__ . ' trigger click.');
                        $invoice_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log(__FUNCTION__ . ' by javascript' . $exception);
                        $this->exts->executeSafeScript("arguments[0].click()", [$invoice_button]);
                    }
                    sleep(7);
                    $invoiceName = $this->exts->querySelector('[class*="invoice-nr-column"]', $row)->getText();
                    $invoiceAmount = '';
                    $invoiceDate = '';

                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $this->exts->log($invoiceFileName);
                    if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->moveToElementAndClick('button[class*="invoice-download-detailed"]');
                        sleep(12);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                    $this->isNoInvoice = false;
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
