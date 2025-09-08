<?php // updated password and login button selector remove unused function processInvoicesOld
// updated loginfailed selector define isNoInvoice and trigger no_invoice in case invoices not found added success trigger after login
// added code open invoice page manually invoice url redirect on login page always 
// added code to click load more invoices button invoice page 
// added restrictpage condition to download limited invoices based on conditon
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

    // Server-Portal-ID: 10058 - Last modified: 18.02.2025 14:27:23 UTC - User: 1

    public $baseUrl = 'https://www.seobility.net/de/dashboard/';
    public $loginUrl = 'https://www.seobility.net/de/login/index/';
    public $invoicePageUrl = 'https://www.seobility.net/de/settings/invoices/';

    public $username_selector = 'form input#email';
    public $password_selector = 'form input#pw, input#password';
    public $remember_me = '';
    public $submit_login_btn = 'form input.btn-success[type="submit"], button#btn-login';

    public $checkLoginFailedSelector = '.accountmessage.alert-danger, div.shadow-color-error';
    public $checkLoggedinSelector = 'a[href*="/login/logout.do"]';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(1);
        $this->exts->capture("Home-page-without-cookie");

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        // after load cookies and open base url, check if user logged in

        $this->exts->waitTillPresent($this->checkLoggedinSelector);

        if ($this->exts->exists($this->checkLoggedinSelector)) {

            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            $this->waitForLogin();
        } else {

            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');
            $this->exts->clearCookies();

            $this->exts->openUrl($this->loginUrl);
            $this->checkFillLogin();
            $this->waitForLogin();
        }
    }

    private function checkFillLogin()
    {

        $this->exts->waitTillPresent($this->username_selector, 10);

        if ($this->exts->exists($this->username_selector)) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("1-filled-login");

            if ($this->exts->exists($this->submit_login_btn)) {
                $this->exts->moveToElementAndClick($this->submit_login_btn);
                sleep(2);
            }
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }

    private function waitForLogin()
    {

        if ($this->exts->exists($this->checkLoggedinSelector)) {
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");

            if ($this->exts->querySelector('a[href*="settings/userdata/"]') != null) {
                $this->exts->moveToElementAndClick('a[href*="settings/userdata/"]');
                sleep(5);
            }

            if ($this->exts->querySelector('a[href*="settings/invoices/"]') != null) {
                $this->exts->moveToElementAndClick('a[href*="settings/invoices/"]');
                sleep(5);
            }

            // Open invoices url
            // commented due to redirect on login page set manual click
            // $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log('Timeout waitForLogin');
            $this->exts->capture("LoginFailed");

            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->checkLoginFailedSelector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('authentifizierung fehlgeschlagen')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function processInvoices($paging_count = 1)
    {
        $total_invoices = 0;
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

        $this->exts->waitTillPresent('table tbody tr', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        // Keep clicking more but maximum upto 10 times
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts && $this->exts->queryXpath('.//a[normalize-space(.)="Weitere Rechnungen anzeigen"]') != null) {
            $this->exts->click_element('.//a[normalize-space(.)="Weitere Rechnungen anzeigen"]');
            $attempt++;
            sleep(4);
        }

        $rows = $this->exts->querySelectorAll('table tbody tr');

        $this->exts->log('Total No of Invoices : ' . count($rows));

        foreach ($rows as $row) {
            if ($this->exts->querySelector('td a', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('td a', $row)->getAttribute('href');
                preg_match('/txid=([0-9]+)/', $invoiceUrl, $matches);
                $invoiceId = $matches[1] ?? '';
                $invoiceName = "Invoice_" . $invoiceId;
                $invoiceAmount = $this->exts->extract('td:nth-child(2)', $row);
                $invoiceDate =  $this->exts->extract('td:first-child', $row);

                $downloadBtn = $this->exts->querySelector('td a', $row);

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
            if ($restrictPages != 0 && $total_invoices >= 100) {
                return;
            }
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
                $total_invoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}
