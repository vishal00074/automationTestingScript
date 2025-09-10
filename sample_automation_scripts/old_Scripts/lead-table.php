<?php // replace exists to isExists and handle empty invoiceName
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


    // Server-Portal-ID: 2166346 - Last modified: 11.08.2025 18:27:44 UTC - User: 1

    public $baseUrl = 'https://portal.lead-table.com/signin';
    public $loginUrl = 'https://portal.lead-table.com/signin';
    public $invoicePageUrl = 'https://portal.lead-table.com/settings/billing';
    public $username_selector = 'input[type="email"]';
    public $password_selector = 'input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form button.primary';
    public $check_login_failed_selector = 'div.alertText';
    public $check_login_success_selector = 'div.emailHeader';
    public $isNoInvoice = true;
    public $totalInvoices = 0;
    public $restrictPages = 3;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
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
            $this->exts->startTrakingTabsChange();
            $this->exts->openUrl($this->invoicePageUrl);
            $this->exts->waitTillPresent("//button[contains(@class, 'v-btn') and .//span[contains(text(), 'Zur Rechnungsverwaltung')]]", 30);
            $this->exts->click_element("//button[contains(@class, 'v-btn') and .//span[contains(text(), 'Zur Rechnungsverwaltung')]]");
            sleep(20);
            $download_invoice_tab = $this->exts->findTabMatchedUrl(['stripe']);
            if ($download_invoice_tab != null) {
                $this->exts->switchToTab($download_invoice_tab);
            }
            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'fehlgeschlagen') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    private function fillForm($count)
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

                if ($this->isExists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }

                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(2); // Portal itself has one second delay after showing toast
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
            for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(3);
            }

            sleep(1);
            if ($this->isExists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }

    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('a[href*="invoice.stripe.com"]', 20);
        $this->exts->capture("4-invoices-page");
        // Keep clicking more but maximum upto 10 times
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts && $this->isExists('button[data-testid="view-more-button"]')) {
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
            if ($this->restrictPages != 0 && $this->totalInvoices >= 100) {
                return;
            };
            sleep(1);
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

            // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
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
