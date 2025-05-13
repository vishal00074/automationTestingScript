<?php

// I have added condition to handle empty invoice name and updated download code

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

    // Server-Portal-ID: 811765 - Last modified: 28.04.2025 06:26:30 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://community.halloklarheit.de/feed';
    public $loginUrl = 'https://login.circle.so/sign_in?request_host=app.circle.so#email';
    // https://login.circle.so/sign_in?request_host=app.circle.so
    public $invoicePageUrl = 'https://community.halloklarheit.de/settings/billing';

    public $username_selector = 'input#user_email';
    public $password_selector = 'input#user_password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form button';
    public $check_login_failed_selector = 'div.react-portal output';
    public $check_login_success_selector = 'div[data-testid="user-profile"]';
    public $isNoInvoice = true;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        $this->check_solve_blocked_page();
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->fillForm(0);
        }
        sleep(5);
        $this->check_solve_blocked_page();

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(2);


            $this->processAccounts();

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


    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }
                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                    sleep(5);
                    $this->check_solve_blocked_page();
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function check_solve_blocked_page()
    {
        $this->exts->capture_by_chromedevtool("blocked-page-checking");

        for ($i = 0; $i < 5; $i++) {
            if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
                $this->exts->refresh();
                sleep(10);

                $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
                sleep(15);

                if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                    break;
                }
            } else {
                break;
            }
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
        try {
            sleep(40);
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

    private function processAccounts()
    {
        // $this->exts->openUrl('https://app.ausha.co/app/show/all');
        $this->exts->waitTillPresent('ul li a[data-testid="community-switcher-link"]', 20);
        $accounts = $this->exts->queryElementAll('ul li a[data-testid="community-switcher-link"]');

        for ($i = 1; $i <= count($accounts); $i++) {
            // $this->exts->openUrl('https://app.ausha.co/app/show/all');

            sleep(3);
            $this->exts->waitTillPresent('button[aria-label="User menu options"]', 20);
            $this->exts->click_element('button[aria-label="User menu options"]');
            sleep(5);
            $this->exts->waitTillPresent('a[href="/account/billing"]', 10);
            if ($this->exts->exists('a[href="/account/billing"]')) {
                $this->exts->click_element('a[href="/account/billing"]');
            }
            sleep(5);
            $this->exts->waitTillPresent('#fullscreen-modal-body button:nth-child(2)', 20);
            $this->exts->click_element('#fullscreen-modal-body button:nth-child(2)');


            $this->exts->waitTillPresent('ul li a[data-testid="community-switcher-link"]', 20);
            // $this->exts->click_by_xdotool("div[class*='ActiveShows__ShowList'] div[class*='Show__ShowWrapper']:nth-child(" . $i . " >  a");
            $this->exts->click_by_xdotool("ul li:nth-child(" . $i . " ) >   a[data-testid='community-switcher-link']");

            $this->processInvoices();
        }

        if(count($accounts) == 0){
            $this->exts->openUrl('https://podiom.circle.so/settings/paywall_charges?tab=all');
            sleep(10);
            $this->processInvoices();
        }
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(10);
        $this->exts->waitTillPresent('a[href*="invoice.stripe.com"]', 20);
        $this->exts->capture("4-invoices-page");
        // Keep clicking more but maximum upto 10 times

        if ($this->exts->exists('a[href*="invoice.stripe.com"]')) {
            $maxAttempts = 10;
            $attempt = 0;

            while ($attempt < $maxAttempts && $this->exts->exists('button[data-testid="view-more-button"]')) {
                $this->exts->execute_javascript('
              var btn = document.querySelector("button[data-testid=\'view-more-button\']");
              if(btn){
                  btn.click();
              }
          ');
                $attempt++;
                sleep(5);
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
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'm.d.y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        } else {

            $this->exts->waitTillPresent('a[href="/account/billing"]', 15);
            $this->exts->exists('a[href="/account/billing"]');

            sleep(4);

            $this->exts->waitTillPresent('table tbody tr', 30);
            $this->exts->capture("4-invoices-page");
            $invoices = [];

            $rows = $this->exts->querySelectorAll('table tbody tr');
            foreach ($rows as $row) {
                $this->exts->waitTillPresent(' td button[type="button"]');
                $this->exts->click_element(' td button[type="button"]', $row);

                sleep(3);
                $stripe_invoice_tab = $this->exts->findTabMatchedUrl(['stripe']);
                if ($stripe_invoice_tab != null) {
                    $this->exts->switchToTab($stripe_invoice_tab);
                }
                sleep(5);

                // $this->exts->openUrl($invoice['invoiceUrl']);
                $this->exts->waitTillPresent("a[href*='pay.stripe']", 20);
                sleep(5);
                if ($this->exts->querySelector("a[href*='pay.stripe']") != null) {
                    $invoiceName = '';
                    preg_match('/Paid\s+([A-Za-z]+\s+\d{1,2},\s+\d{4})/', $this->exts->extract("//span[contains(text(), 'Paid')]"), $matches);
                    $invoiceDate = $matches[1];
                    $invoiceAmount = $this->exts->extract("//span[contains(text(), '$')][1]");
                    $downloadBtn = $this->exts->querySelector("a[href*='pay.stripe']");

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    // $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
                    $this->isNoInvoice = false;
                }
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'm.d.y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);


                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }


                $this->exts->switchToInitTab();
                $this->exts->closeAllTabsButThis();
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
