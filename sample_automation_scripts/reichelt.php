<?php // replace waitTillPresent to waitFor and handle empty invoice

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
    // Server-Portal-ID: 37912 - Last modified: 04.02.2025 05:36:52 UTC - User: 1

    // Script here
    public $baseUrl = 'https://secure.reichelt.com//deen/?ACTION=10020';
    public $loginUrl = 'https://secure.reichelt.com//deen/?ACTION=10020';
    public $invoicePageUrl = 'https://portal.netcom-bw.de/rechnungen';

    public $username_selector = 'form[name="contentform"] input[name="Login"]';
    public $password_selector = 'form[name="contentform"] input[type="password"]';
    public $remember_me_selector = 'label[for="stay"]';
    public $submit_login_selector = 'button[name="signin"]';

    public $check_login_failed_selector = 'div.alert-error, div#error-div, p.myerror';
    public $check_login_success_selector = 'a#showmyreicheltnavi';

    public $isNoInvoice = true;

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        if (!$this->checkLogin()) {
            $this->fillForm(0);
            sleep(10);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);
            if ($this->exts->exists('div.cookie_notice button')) {
                $this->exts->click_element('div.cookie_notice button');
            }
            //$this->exts->openUrl($this->invoicePageUrl);
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
        $this->waitFor($this->username_selector, 5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(5);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                    sleep(1);
                }
                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
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

    private function processInvoices($paging_count = 1)
    {
        $this->waitFor('.oopen', 15); // Updated selector for the invoice structure
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('.oopen'); // Each invoice is inside <li class="oopen">
        foreach ($rows as $row) {
            $orderDate = $this->exts->extract('.orderdate .ovalue', $row);
            $totalAmount = $this->exts->extract('.ordervalue .ovalue', $row);
            $paymentMethod = $this->exts->extract('.orderpayment .ovalue', $row);
            $orderInfo = $this->exts->extract('.orderinfo .odata', $row);
            $downloadBtn = $this->exts->querySelector('a', $row); // Assuming <a> as the download button/link

            $invoiceUrl = '';
            if ($downloadBtn) {
                if (method_exists($this->exts, 'getAttribute')) {
                    $invoiceUrl = $this->exts->getAttribute('href', $downloadBtn);
                } elseif (method_exists($downloadBtn, 'getAttribute')) {
                    $invoiceUrl = $downloadBtn->getAttribute('href');
                } else {
                    // Try extracting the 'href' directly if getAttribute doesn't exist
                    $invoiceUrl = $this->exts->extract('a[href]', $row);
                }
            }


            array_push($invoices, array(
                'orderDate' => $orderDate,
                'totalAmount' => $totalAmount,
                'paymentMethod' => $paymentMethod,
                'orderInfo' => $orderInfo,
                'invoiceUrl' => $invoiceUrl
            ));

            $this->isNoInvoice = false;
        }

        // Log and process invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('Order Date: ' . $invoice['orderDate']);
            $this->exts->log('Total Amount: ' . $invoice['totalAmount']);
            $this->exts->log('Payment Method: ' . $invoice['paymentMethod']);
            $this->exts->log('Order Info: ' . $invoice['orderInfo']);
            $this->exts->log('Invoice URL: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['orderDate']) ? $invoice['orderDate'] . '.pdf': '';
            $parsedDate = $this->exts->parse_date($invoice['orderDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $parsedDate);

            $downloaded_file = $this->exts->download_capture($invoice['invoiceUrl'], $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['orderDate'], $parsedDate, $invoice['totalAmount'], $invoiceFileName);
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
