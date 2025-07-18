<?php //  added $this->check_solve_cloudflare_page(); function 

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

    // Server-Portal-ID: 492230 - Last modified: 17.02.2025 13:29:39 UTC - User: 1

    public $baseUrl = 'https://app.landbot.io/signin/';
    public $loginUrl = 'https://app.landbot.io/signin/';
    public $invoicePageUrl = 'https://app.landbot.io/gui/subscription/invoices';

    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'p[class*="sc-iwyWfK iOFnUH"]';
    public $check_login_success_selector = 'div[class="MainNavbar__Content"] figure';

    public $isNoInvoice = true;

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->check_solve_cloudflare_page();
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->check_solve_cloudflare_page();
            if ($this->exts->exists('button[data-cookiefirst-button="primary"]')) {
                $this->exts->click_by_xdotool('button[data-cookiefirst-button="primary"]');
                sleep(3);
            }
            $this->fillForm(0);
            sleep(3);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(2);
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);
            $this->check_solve_cloudflare_page();
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 5);
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
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(5);
                }


                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                    sleep(10);
                }

                $this->check_solve_cloudflare_page();
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function check_solve_cloudflare_page()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
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

    function checkLogin()
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

        $this->exts->waitTillPresent('div[role="row"]', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div[role="row"]');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('div[role="gridcell"]:nth-child(5) a', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('div[role="gridcell"]:nth-child(1)', $row);
                $invoiceAmount = $this->exts->extract('div[role="gridcell"]:nth-child(3) div', $row);
                $invoiceDate = $this->exts->extract('div[role="gridcell"]:nth-child(2) div', $row);

                $downloadBtn = $this->exts->querySelector('div[role="gridcell"]:nth-child(5) a', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            $downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
