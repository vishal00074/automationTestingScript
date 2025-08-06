<?php // I have added the restrictPages condition in invoices function I have added code to download  stripe invoice

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
    // Server-Portal-ID: 777017 - Last modified: 20.05.2025 10:04:10 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://www.mentimeter.com/app/home';
    public $loginUrl = 'https://www.mentimeter.com/auth/login';
    public $invoicePageUrl = 'https://www.mentimeter.com/app/billing';

    public $username_selector = 'input#email';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form button[type=submit]';

    public $check_login_failed_selector = 'span.text';
    public $check_login_success_selector = 'button#profile-image-thumbnail';

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
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->exts->waitTillPresent("button#onetrust-accept-btn-handler", 10);
            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->click_element('button#onetrust-accept-btn-handler');
            }
            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);

            if ($this->exts->exists('div.z-modal > button')) {
                $this->exts->click_element('div.z-modal > button');
                sleep(2);
            }

            $this->exts->openUrl($this->invoicePageUrl);
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

    public $totalInvoices = 0;
    private function processInvoices($paging_count = 1)
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log("restrictPages:: " . $restrictPages);

        $this->exts->waitTillPresent('a[href*=receipt]', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        //div.items-start > div
        $rows = $this->exts->querySelectorAll('a[href*="receipt"]');
        foreach ($rows as $row) {

            $invoiceUrl = $row->getAttribute("href");
            if ($invoiceUrl != null) {
                array_push($invoices, array(
                    'invoiceUrl' => $invoiceUrl,
                ));
                $this->isNoInvoice = false;
            }
        }

        $rowsStripe = $this->exts->querySelectorAll('a[href*="invoice.stripe"]');
        foreach ($rowsStripe as $rowStripe) {
            $invoiceUrl = $rowStripe->getAttribute("href");
            if ($invoiceUrl != null) {
                array_push($invoices, array(
                    'invoiceUrl' => $invoiceUrl,
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }
            $invoiceFileName = '';
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(2);

            if (stripos(strtolower($invoice['invoiceUrl']), 'invoice.stripe') !== false) {
                $this->exts->waitTillPresent('div.InvoiceDetailsRow-Container button[data-testid="download-invoice-receipt-pdf-button"]', 10);
                $downloaded_file = $this->exts->click_and_download('div.InvoiceDetailsRow-Container button[data-testid="download-invoice-receipt-pdf-button"]', 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceName = basename($downloaded_file, '.pdf');

                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                    sleep(1);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ');
                }
            } else {
                $this->exts->waitTillPresent(".//button[.//span[contains(text(), 'Print this page') or contains(text(), 'Drucken Sie diese Seite')]]", 10);
                $downloadBtn = $this->exts->queryXpath(".//button[.//span[contains(text(), 'Print this page') or contains(text(), 'Drucken Sie diese Seite')]]");

                $this->exts->click_element($downloadBtn);
                sleep(7);
                $this->exts->wait_and_check_download('pdf');

                // find new saved file and return its path
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceName = basename($downloaded_file, '.pdf');

                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
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

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
