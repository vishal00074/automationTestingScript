<?php // added condition to check  invoice exists condition and document exists
// updated login form filling process code 

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

    // Server-Portal-ID: 8551 - Last modified: 31.07.2025 13:06:18 UTC - User: 1

    public $baseUrl = 'https://accountcentral.wework.com/member/content/#/app/dashboard';
    public $loginUrl = 'https://accountcentral.wework.com/member/content/login';
    public $invoicePageUrl = 'https://accountcentral.wework.com/member/content/#/app/myaccount';
    public $username_selector = 'input[type="email"], input[name="username"]';
    public $password_selector = 'input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'div.submit-button:not(.disabled)';
    public $check_login_failed_selector = 'div[class*="hint--error"], .fieldset-error-list-item, div.auth0-global-message';
    public $check_login_success_selector = 'a[ng-click="logOut()"], div[data-testid="logout-item"]';
    public $isNoInvoice = true;
    public $legacyAccount = false;
    public $restrictPages = 3;
    public $totalInvoices = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log('Begin initPortal ' . $count);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->check_solve_cloudflare_page();

        $this->exts->waitTillAnyPresent([$this->check_login_success_selector, 'a[ng-click="loginWithAuth0WW()"]']);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            if ($this->exts->exists('a[ng-click="loginWithAuth0WW()"]')) {
                $this->exts->moveToElementAndClick('a[ng-click="loginWithAuth0WW()"]');
                sleep(10);
                $this->check_solve_cloudflare_page();
                $this->exts->waitTillPresent($this->username_selector);
                $this->checkFillLogin();
                $this->exts->waitTillAnyPresent([$this->check_login_success_selector, 'a[href="https://accounts.wework.com/"].redirect-button']);
                if ($this->exts->exists('a[href="https://accounts.wework.com/"].redirect-button')) {
                    $this->legacyAccount = true;
                    $this->exts->moveToElementAndClick('a[href="https://accounts.wework.com/"].redirect-button');
                    sleep(3);
                    $this->exts->waitTillAnyPresent([$this->check_login_success_selector, 'button[data-testid="login__button"]']);
                    if ($this->exts->exists('button[data-testid="login__button"]')) {
                        $this->exts->moveToElementAndClick('button[data-testid="login__button"]');
                        sleep(5);
                        $this->exts->waitTillPresent($this->username_selector);
                        $this->checkFillLogin();
                        $this->exts->waitTillPresent($this->check_login_success_selector);
                    }
                }
            }
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in. Current URL: ' . $this->exts->getUrl());
            $this->exts->capture("3-login-success");

            if ($this->legacyAccount) {
                $this->exts->openUrl('https://accounts.wework.com/dashboard/balance-and-invoices');
                $this->processLegacyInvoices();
            } else {
                $this->exts->openUrl($this->invoicePageUrl);
                $this->processInvoices();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->loginFailure(1);
            }
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div[class*="NotAuthorized_titleText"]', null, 'innerText')), 'you do not have permission to view') !== false) {
                $this->exts->account_not_ready();
            } else if (strpos(strtolower($this->exts->extract('div.NotAuthorized_textWrapper__3KiOn', null, 'innerText')), 'hast keine berechtigung zum anzeigen dieser informationen') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function check_solve_cloudflare_page()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->exts->exists(selector_or_xpath: '#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
            }
        }
    }


    private function checkFillLogin()
    {
        if ($this->exts->exists($this->username_selector)) {
            $this->exts->capture("2-login-page-new");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->moveToElementAndClick('button[name="action"]');
            sleep(7);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType('input[name="password"]', $this->password);
            sleep(1);

            if ($this->remember_me_selector != '' && $this->exts->exists($this->remember_me_selector))
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-new-filled");
            $this->exts->moveToElementAndClick('button[type="submit"]');
            sleep(5);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices()
    {
        sleep(3);
        $this->exts->waitTillPresent('i[ng-click*="makePaymentDetails"]');
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $invoice_details_buttons = $this->exts->querySelectorAll('i[ng-click*="makePaymentDetails"]');
        foreach ($invoice_details_buttons as $key => $invoice_details_button) {
            if ($this->restrictPages != 0 && $this->totalInvoices >= 100) {
                return;
            };
            $this->exts->click_element($invoice_details_button);
            sleep(3);
            $this->exts->waitTillPresent('a[ng-click*="generateInvoicePDFAndDownload"]');
            if ($this->exts->exists('a[ng-click*="generateInvoicePDFAndDownload"]')) {
                $this->isNoInvoice = false;
                $invoiceName = array_pop(explode('#', $this->exts->extract('h4#invoice-title')));

                $this->exts->log('---------------------------');
                $this->exts->log('invoiceName ' . $invoiceName);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";

                if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                    continue;
                }

                $downloaded_file = $this->exts->click_and_download('a[ng-click*="generateInvoicePDFAndDownload"]', 'pdf', $invoiceFileName);
                sleep(2);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                    sleep(1);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
            $this->exts->moveToElementAndClick('div.modal-content button.close');
            sleep(1);
        }
    }

    private function processLegacyInvoices()
    {
        sleep(3);
        $this->exts->waitTillPresent('i[ng-click*="makePaymentDetails"]');
        $this->exts->capture("4-legacy-invoices-page");
        $invoices = [];

        $invoice_urls = $this->exts->getElementsAttribute('a[href*="balance-and-invoices/invoices/"]', 'href');
        foreach ($invoice_urls as $key => $invoice_url) {
            if ($this->restrictPages != 0 && $this->totalInvoices >= 100) {
                return;
            };
            $invoiceName = array_pop(explode('balance-and-invoices/invoices/', $invoice_url));
            $this->exts->openUrl($invoice_url);
            sleep(3);
            $this->exts->waitTillPresent('div.right.floated button.dropdown.primary.basic');
            if ($this->exts->exists('div.right.floated button.dropdown.primary.basic')) {
                $this->isNoInvoice = false;
                $this->exts->moveToElementAndClick('div.right.floated button.dropdown.primary.basic');
                sleep(1);
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";
                $downloaded_file = $this->exts->click_and_download('div.visible.menu.transition div[role="option"]:first-child', 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                    sleep(1);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$portal = new PortalScriptCDP("optimized-chrome-v2", 'WeWork Account Central', '2675013', 'Y2hyaXN0aWFuLndpbGRAc2VuZi5hcHA=', 'SGFsbG9TZW5mMTIz');
$portal->run();
