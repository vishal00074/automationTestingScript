<?php // replaced waitTillPresent to waitFor and adjust sleep time and 
// second case failed due to no payment method found

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

    // Server-Portal-ID: 1919596 - Last modified: 06.08.2025 15:48:47 UTC - User: 1

    public $baseUrl = 'https://app.foreplay.co/login';
    public $loginUrl = 'https://app.foreplay.co/login';
    public $invoicePageUrl = 'https://app.foreplay.co/dashboard?settings=billing';
    public $username_selector = 'form input[type="email"]';
    public $password_selector = 'form input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';
    public $check_login_failed_selector = 'div[class*="notification"]';
    public $check_login_success_selector = "a[href='/dashboard']";
    public $isNoInvoice = true;
    public $restrictPages = 3;
    public $totalInvoices = 0;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.  

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
        }


        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);

            $this->exts->openUrl($this->invoicePageUrl);

            $this->waitFor('span[aria-label="Close"]', 5);
            $this->waitFor('span[aria-label="Close"]', 5);
            $this->waitFor('span[aria-label="Close"]', 5);
            $this->waitFor('span[aria-label="Close"]', 5);
            $this->waitFor('span[aria-label="Close"]', 5);
            $this->exts->click_element('span[aria-label="Close"]');
            sleep(10);
            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false  || stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'customer number') !== false) {
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
        $this->waitFor($this->username_selector, 5);
        $this->waitFor($this->username_selector, 5);
        $this->waitFor($this->username_selector, 5);
        $this->waitFor($this->username_selector, 5);
        $this->waitFor($this->username_selector, 5);

        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                sleep(2);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                    sleep(1);
                }

                for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('div.notification');") != 1; $wait++) {
                    $this->exts->log('Waiting for selector.....');
                    sleep(2);
                }

                sleep(1);
                for ($i = 0; $i < 5; $i++) {
                    $this->exts->log("Login Failure : " . $this->exts->extract('div.notification'));
                    if (stripos($this->exts->extract('div.notification'), 'There is no user record corresponding to this identifier') !== false || stripos($this->exts->extract('div.notification'), 'The password is invalid or the user does not have a password') !== false) {
                        $this->exts->loginFailure(1);
                    } else {
                        sleep(1);
                    }
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
    public function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector(\"a[href='/dashboard']\");") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(5);
            }

            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }


    private function processInvoices($paging_count = 1)
    {
        sleep(10);
        $this->waitFor("a[href*='pay.stripe']", 5);
        $this->waitFor("a[href*='pay.stripe']", 5);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        // Keep clicking more but maximum upto 10 times
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts && $this->exts->exists('button[class="BillingItemsTable-ShowAll"]')) {
            $this->exts->execute_javascript('
            var btn = document.querySelector("button[class=\'BillingItemsTable-ShowAll\']");
            if(btn){
                btn.click();
            }
        ');
            $attempt++;
            sleep(5);
        }

        $rows = $this->exts->querySelectorAll("a[href*='pay.stripe']");
        foreach ($rows as $row) {
            array_push($invoices, array(
                'invoiceUrl' => $row->getAttribute('href')
            ));
        }
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            if ($this->restrictPages != 0 && $this->totalInvoices >= 100) {
                return;
            };
            $this->exts->openUrl($invoice['invoiceUrl']);

            $this->waitFor("a[href*='pay.stripe']", 3);
            $this->waitFor("a[href*='pay.stripe']", 3);
            $this->waitFor("a[href*='pay.stripe']", 3);
            $this->waitFor("a[href*='pay.stripe']", 3);
           
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
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
                $this->isNoInvoice = false;
            }

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
                $this->totalInvoices++;
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
