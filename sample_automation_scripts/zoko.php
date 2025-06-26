<?php

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

    // Server-Portal-ID: 1079868 - Last modified: 16.06.2025 15:01:23 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://app.live.zoko.io/login';
    public $loginUrl = 'https://app.live.zoko.io/login';
    public $invoicePageUrl = 'https://app.live.zoko.io/more/billing';

    public $username_selector = 'form input[type="text"]';
    public $password_selector = 'form input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form button#login_button';

    public $check_login_failed_selector = 'form input[type="password"], form input[type="tel"]';
    public $check_login_success_selector = 'a[href*="analytics"]';

    public $isNoInvoice = true;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(2);

            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);
            $this->exts->startTrakingTabsChange();

            $this->exts->waitTillPresent('button[class*="stripe"]');
            $this->exts->click_element('button[class*="stripe"]');
            sleep(2);
            $this->exts->switchToNewestActiveTab();
            $this->exts->waitTillPresent('button[data-testid="view-more-button"]');
            $this->exts->click_if_existed('button[data-testid="view-more-button"]');
            sleep(5);
            $this->processInvoices();
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


    private function processInvoices($paging_count = 1)
    {
        // In case of date filter:
        // If $restrictPages == 0, then download upto 3 years of invoices.
        // If $restrictPages != 0, then download upto 3 months of invoices with maximum 100 invoices.

        // In case of pagination and no date filter:
        // If $restrictPages == 0, then download all available invoices on all pages.
        // If $restrictPages != 0, then download upto pages in $restrictPages with maximum 100 invoices.

        // In case of no date filter and no pagination:
        // If $restrictPages == 0, then download all available invoices.
        // If $restrictPages != 0, then download upto 100 invoices.


        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $pagingCount = 0;
        $this->exts->log('Restrict Pages: ' . $restrictPages);

        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true; // (true) in case of date filter
        $this->exts->log('Restrict Date: ' . $restrictDate);

        $maxInvoices = 15;
        $invoiceCount = 0;

        $terminateLoop = false;


        $this->exts->waitTillPresent('a[href*="invoice.stripe.com"]', 30);
        $this->exts->capture("4-invoices-page");

        do {

            $pagingCount++;

            $this->exts->waitTillPresent('a[href*="invoice.stripe.com"]', 30);
            $rows = $this->exts->querySelectorAll('a[href*="invoice.stripe.com"]');

            foreach ($rows as $row) {
                $invoiceCount++;

                $this->isNoInvoice = false;
                $row->click();
                sleep(2);
                $this->exts->switchToNewestActiveTab();
                $this->exts->waitTillPresent('[data-testid="invoice-amount-post-payment"]');


                $invoiceAmount = trim($this->exts->querySelector('[data-testid="invoice-amount-post-payment"]')->getText());

                $this->exts->log('invoice amount: ' . $invoiceAmount);

                $invoiceDate = trim($this->exts->querySelectorAll('div.App-InvoiceDetails table.InvoiceDetails-table tbody tr')[1]->querySelectorAll('td')[1]->getText());

                $this->exts->log('invoice date: ' . $invoiceDate);

                $parsedDate = $this->exts->parse_date($invoiceDate, 'F d, Y', 'Y-m-d');
                $this->exts->log('Parsed date: ' . $parsedDate);

                $this->exts->click_element('div.InvoiceDetailsRow-Container button');

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoiceName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsedDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                $this->exts->closeCurrentTab();

                $this->exts->log(' ');
                $this->exts->log('---------------------------INVOICE ITERATION END-------------------------');
                $this->exts->log(' ');


                $lastDate = !empty($invoiceDate) && $invoiceDate <= $restrictDate;

                if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                    $terminateLoop = true;
                    break;
                } elseif ($restrictPages == 0 && $dateRestriction && $lastDate) {
                    $terminateLoop = true;
                    break;
                }
            }


            if ($restrictPages != 0 && $pagingCount == $restrictPages) {
                break;
            } elseif ($terminateLoop) {
                break;
            }

            // pagination handle			
            if ($this->exts->exists('li.ant-pagination-next:not(.ant-pagination-disabled) > button:not([disabled])')) {
                $this->exts->log('Click Next Page in Pagination!');
                $this->exts->click_element('li.ant-pagination-next:not(.ant-pagination-disabled) > button:not([disabled])');
                sleep(5);
            } else {
                $this->exts->log('Last Page!');
                break;
            }
        } while (true);

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoiceCount));
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 20);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->capture("1-login-page-filled");
                sleep(1);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_element($this->submit_login_selector);
                    sleep(2);
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
    private function checkLogin()
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
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
