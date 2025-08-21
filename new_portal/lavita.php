<?php // replace waitTillPresent to waitfor and added dateFilters and updated download code use invoice url from downling the invoices instead of take print screen

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

    // Server-Portal-ID: 1194003 - Last modified: 10.08.2025 13:34:42 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://account.lavita.com/de';
    public $loginUrl = 'https://account.lavita.com/de/login';
    public $invoicePageUrl = 'https://account.lavita.com/de/orders';

    public $username_selector = 'input#login';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form button[type="submit"]';

    public $check_login_failed_selector = 'div#credentials_invalid';
    public $check_login_success_selector = 'a[href*="orders"]';

    public $isNoInvoice = true;

    /**<input type="password" name="password" autocomplete="current-password" class="textinput textInput" required id="id_password">

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        $selectLangBtn = 'button#locales-menu-trigger';
        if ($this->exts->exists($selectLangBtn)) {
            $this->exts->click_element($selectLangBtn);
        }

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(6);
            $this->waitFor($selectLangBtn, 4);
            if ($this->exts->exists($selectLangBtn)) {
                $this->exts->click_element($selectLangBtn);
            }

            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(2);
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            $this->dateRange();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            if ($this->exts->exists($this->check_login_failed_selector)) {
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
        $this->waitFor($this->username_selector);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->log("Remember Me");
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                    sleep(1);
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
    function checkLogin()
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
        $currentDate = $selectDate->format('d.m.Y');

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        if ($this->exts->exists('button#filter')) {
            $this->exts->moveToElementAndClick('button#filter');
            sleep(7);
        }

        if ($restrictPages == 0) {
            $invoicesUrls = [];
            $rows =  $this->exts->getElements('input[name="order_filter"]');

            foreach ($rows as $key => $row) {
                $date = $row->getAttribute('value');

                if ($date != null && $date != 'last_30_days') {
                    $url = $this->invoicePageUrl . '?filter=' . $date;

                    array_push($invoicesUrls, array(
                        'invoiceUrl' => $url
                    ));
                }
                if ($key >= 3) {
                    break;
                }
            }

            foreach ($invoicesUrls as $invoicesUrl) {
                $this->exts->openUrl($invoicesUrl['invoiceUrl']);
                sleep(2);
                $this->processInvoices();
            }
        } else {
            $url = $this->invoicePageUrl . '?filter=last_6_months';

            $this->exts->openUrl($url);
            $this->processInvoices();
        }
    }

    private function processInvoices($paging_count = 1)
    {

        $this->waitFor('main#main > section a[href*="/de/orders/"]', 15);

        if ($this->exts->queryElement('div[class="af df"] a[href="/de/orders"][lang="de"]') != null) {
            $this->exts->moveToElementAndClick('div[class="af df"] a[href="/de/orders"][lang="de"]');
            sleep(7);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('main#main > section');
        $anchor = 'a[href*="/invoice?"]';

        foreach ($rows as $row) {
            if ($this->exts->querySelector($anchor, $row) != null) {

                $invoiceUrl = $this->exts->querySelector($anchor, $row)->getAttribute('href');
                $invoiceName =  '';
                // extract invoiceName from url
                if (preg_match('#/order/(\d+)/invoice#', $invoiceUrl, $matches)) {
                    $invoiceName =  $matches[1]; // Outputs: 15851863
                }

                $invoiceAmount = '';

                $invoiceDate = $this->exts->extract('div.ma > div.Kb > div > span', $row);
                $explodeDate = explode(': ', $invoiceDate);
                $invoiceDate = !empty($explodeDate[1]) ? trim($explodeDate[1]) : '';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
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
            $invoice['invoiceDate'] = $this->exts->parse_date(trim($invoice['invoiceDate']), 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }

            $this->exts->log(' ');
            $this->exts->log('---------------------------INVOICE ITERATION END-------------------------');
            $this->exts->log(' ');
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
