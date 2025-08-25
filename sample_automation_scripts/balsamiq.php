<?php // updated success selector and handle empty invoices case updated load more buton remove unused processInvoiceLatest function

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673461/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 61478 - Last modified: 16.07.2025 13:51:50 UTC - User: 1

    public $baseUrl = 'https://balsamiq.cloud';
    public $loginUrl = 'https://balsamiq.cloud/#login';
    public $invoicePageUrl = 'https://balsamiq.cloud';

    public $username_selector = 'input#dialog-login-email';
    public $password_selector = 'input#dialog-login-password';
    public $remember_me_selector = '';
    public $submit_login_btn = 'button#dialog-login-submit';

    public $checkLoginFailedSelector = '#dialog-login-error';
    public $checkLoggedinSelector = 'div[class*="userview__myUser"], div.lcBody form[action="/logout"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        // $this->fake_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.71 Safari/537.36');
        $this->exts->openUrl($this->baseUrl);
        sleep(1);
        $this->exts->capture("Home-page-without-cookie");

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        // after load cookies and open base url, check if user logged in

        // Wait for selector that make sure user logged in
        $this->exts->waitTillPresent($this->checkLoggedinSelector);

        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            $this->waitForLogin();
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');

            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            for ($i = 0; $i < 5 && $this->exts->exists('div#balsamiq-loading-screen'); $i++) {
                sleep(15);
            }
            $this->waitForLoginPage();
        }
    }

    private function waitForLoginPage()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("1-filled-login");
            $this->exts->moveToElementAndClick($this->submit_login_btn);
            sleep(15);

            $this->waitForLogin();
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            if ($this->exts->getElement('input[class*="DialogNewSite__SiteNameInput"]') != null) {
                $this->exts->account_not_ready();
            }

            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }

    private function waitForLogin()
    {
        sleep(10);
        $currentUrl = $this->exts->getUrl();

        $this->exts->log('Current URL :' . $currentUrl);

        $parsed_url = parse_url($currentUrl);

        $path_parts = explode('/', $parsed_url['path']);
        $path_parts = array_slice($path_parts, 0, 2);
        $path_parts[] = 'billing';
        $new_path = implode('/', $path_parts);

        $new_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $new_path;

        $this->exts->log('New URL :' . $new_url);

        $this->exts->openUrl($new_url);
        sleep(15);

        if ($this->exts->getElement('button[data-testid="menubar-menuUser"]') != null) {
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");
            $this->exts->moveToElementAndClick('button[data-testid="menubar-menuUser"]');
            sleep(5);
            $this->exts->moveToElementAndClick('div[data-testid="menu-item-Manage Subscription"]');
            sleep(3);
            $this->exts->switchToNewestActiveTab();
            $this->exts->waitTillPresent('div.content  > div:nth-child(3) a');
            if ($this->exts->exists('div.content  > div:nth-child(3) a')) {
                $this->exts->moveToElementAndClick('div.content  > div:nth-child(3) a');
                sleep(15);
                $this->processInvoiceUpdated();
            }

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            sleep(3);
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");

            sleep(2);
            if ($this->exts->queryXpath(".//a[@href='#' and normalize-space(text())='Manage Billing Info']")) {
                $this->exts->moveToElementAndClick(".//a[@href='#' and normalize-space(text())='Manage Billing Info']");
                sleep(15);
                $this->processInvoiceUpdated();
            } else {
                sleep(15);
                // Open invoices url
                $this->exts->moveToElementAndClick('a[href*="/settings"]');
                $this->processInvoices();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log('Timeout waitForLogin');
            $this->exts->capture("LoginFailed");
            sleep(2);
            if ($this->exts->exists('div[class*="windowframe__titleLabel"]')) {
                $this->exts->account_not_ready();
                sleep(2);
            }

            if (stripos($this->exts->extract($this->checkLoginFailedSelector), 'Passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->getElement('input[class*="DialogNewSite__SiteNameInput"]') != null) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public $moreBtn = true;

    public function viewMore()
    {
        $this->exts->moveToElementAndClick('div button[class="UnstyledLink ButtonLink IconParent Flex-flex"]:nth-child(2)');
    }

    private function processInvoices()
    {

        sleep(2);
        if ($this->exts->getElement('//li/a[contains(@href, "balsamiq.com/transaction/")]/../following-sibling::li/a[@href="#"]', null, 'xpath') != null) {
            $this->exts->getElement('//li/a[contains(@href, "balsamiq.com/transaction/")]/../following-sibling::li/a[@href="#"]', null, 'xpath')->click();
            sleep(2);
        }

        $this->exts->log('Invoices found');
        $this->exts->capture("4-page-opened");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('li a[href*="balsamiq.com/transaction/"]');
        foreach ($rows as $row) {
            $invoiceUrl = str_replace('/public/', '/downloadTransactionPdf/', $row->getAttribute("href"));
            $invoiceName = explode(
                '?',
                array_pop(explode('/', $invoiceUrl))
            )[0];
            $invoiceDate = trim(
                explode('-', $row->getText())[0]
            );
            $invoiceAmount = trim(
                array_pop(explode('-', $row->getText()))
            );
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount)) . ' USD';
            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
            ));

            $this->isNoInvoice = false;
        }

        // Download all invoices
        $this->exts->log('Invoices: ' . count($invoices));
        $count = 1;
        $totalFiles = count($invoices);

        foreach ($invoices as $invoice) {
            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';

            $this->exts->log('date before parse: ' . $invoice['invoiceDate']);

            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F d, Y', 'Y-m-d');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->log('Dowloading invoice ' . $count . '/' . $totalFiles);
                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                sleep(2);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                    sleep(1);
                    $count++;
                } else {
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
                }
            }
        }
    }

    private function processInvoiceUpdated()
    {
        sleep(10);
        $this->exts->capture("4-invoices-page");
        // Keep clicking more but maximum upto 10 times

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0) {
            $maxAttempts = 10;
        } else {
            $maxAttempts = $restrictPages;
        }

        $paging_count = 0;

        while ($paging_count < $maxAttempts && $this->exts->exists('button[data-testid="view-more-button"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="view-more-button"]');
            sleep(4);
            $paging_count++;
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
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'm.d.y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoiceDate);

            $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
