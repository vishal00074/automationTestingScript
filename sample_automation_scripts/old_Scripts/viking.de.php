<?php // remove unused closeNewsLetterBox function  handle empty invoice name case  replace waitTillPresent to waitFor updated login failed message
 // added  $this->exts->success(); in initPortal function
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

    // Server-Portal-ID: 9599 - Last modified: 03.07.2025 14:47:17 UTC - User: 1

    public $baseUrl = 'https://www.viking.de/de/login';
    public $loginUrl = 'https://www.viking.de/de/login';
    public $invoicePageUrl = 'https://service.vattenfall.de/meinerechnung';

    public $username_selector = 'input[id="username"]';
    public $password_selector = 'input[id="password"]';
    public $remember_me_selector = 'input[id="rememberMe"]';
    public $submit_login_selector = 'button[id="loginSubmit"]';

    public $check_login_failed_selector = 'div.alert.alert-danger';
    public $check_login_success_selector = 'input.btn-logout, button#logoutButton';
    public $invoice_portal_selector = 'a[href*="/my-account/e-billing-info"]';
    public $invoice_portal_page_selector = 'a[class="odExternalLink"], a[href*="e-billing"]';
    public $invoice_selector = 'body > table > tbody > tr > td:nth-child(2) > table > tbody > tr:nth-child(4) > td > table > tbody > tr > td:nth-child(3) > font > center > table > tbody > tr > td > table > tbody > tr:nth-child(4) > td > table > tbody > tr';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    { // starting point for any portal script, do not change method name as it referenced from outside
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile(); // This function loads any cookies/session storage existing for this credential (in your server this loads cookies from /home/ubuntu/selenium/screens/cookie.txt)
        sleep(1);
        $this->exts->openUrl($this->baseUrl); //Load same url again for cookies to reflect () exts is the util object that has useful functions.)
        sleep(10);
        $this->exts->capture('1-init-page'); // capture the screen for debugging purposes, not: do not capture too many screens

        // If cookie login didn't work, clear cookie, open the login url and login again
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged in via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if (strpos(strtolower($this->exts->extract('body h1')), 'access denied')) {
                $this->exts->refresh();
                sleep(15);
            }
            if (strpos(strtolower($this->exts->extract('body h1')), 'access denied') !== false) {
                $this->clearChrome();
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
            }
            $this->checkFillLogin();
        }


        $this->waitFor($this->check_login_failed_selector, 10);
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->exts->moveToElementAndClick("#footerCookiePolicyClose");
            sleep(1);

            $this->exts->moveToElementAndClick('#newsletter-box span.close');
            sleep(1);

            // Open invoices url and download invoice
            $this->exts->openUrl('https://www.viking.de/de/my-account/e-billing-info');
            sleep(10);
            $this->exts->moveToElementAndClick('a[href="/de/my-account/e-billing"]');
            sleep(15);
            // you need to switch to new tab like this
            $this->exts->switchToNewestActiveTab();
            sleep(5);

            if (stripos($this->exts->getUrl(), 'vikdeODSaccount.php') !== FALSE) {
                $this->exts->moveToElementAndClick('[align="RIGHT"] input[type="submit"]');
                sleep(5);
            }

            $this->exts->moveToElementAndClick('a[href="/app/customer/retrieved-documents"]');
            sleep(10);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $error_text = $this->exts->extract($this->check_login_failed_selector, null, 'innerText');
            $this->exts->log('Error Text:: ' . $error_text);
            if (strpos($error_text, 'Ihrem korrekten Benutzernamen oder E-Mail Adresse ein') !== false) {
                $this->exts->loginFailure(1); // param 1 means, userid/pwd is definitely wrong, don't call this unless you're sure the credentials are incorrect
            } else {
                $this->exts->loginFailure(); // unknown reason, so call loginFailed with no params
            }
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function checkFillLogin()
    {
        sleep(10);
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");
            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                sleep(2);
            }
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Tab');
        $this->exts->type_key_by_xdotool('Return');
        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    private function processInvoices()
    {
        sleep(15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = count($this->exts->getElements('#documents table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('#documents table > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (
                count($tags) >= 16 && $this->exts->getElement('.fa-download', $tags[3]) != null
                && stripos($tags[2]->getAttribute('innerText'), 'Rechnung') !== false
            ) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('.fa-download', $tags[3]);
                $invoiceName = trim($tags[3]->getAttribute('innerText'));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = trim($tags[4]->getAttribute('innerText'));
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd-m-Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
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
