<?php // 

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

    // Server-Portal-ID: 2189592 - Last modified: 07.07.2025 12:06:38 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://www.youngliving.com/us/en/myaccount';
    public $loginUrl = 'https://www.youngliving.com/us/en/myaccount';
    public $invoicePageUrl = 'https://www.youngliving.com/vo/#/account-information/order-history';

    public $continue_login_selector = 'div.menu-signIn';

    public $username_selector = 'form input#loginUsername';
    public $password_selector = 'form input#loginPassword';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form button[type="submit"]';

    public $check_login_failed_selector = 'div[role="alert"]';
    public $check_login_success_selector = 'span#menu-user-name-id';

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
            sleep(10);
            $this->exts->waitTillPresent($this->continue_login_selector, 20);
            if ($this->exts->querySelector($this->continue_login_selector) != null) {
                $this->exts->click_element($this->continue_login_selector);
            }

            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->openUrl($this->invoicePageUrl);
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
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $pagingCount = 0;
        $this->exts->log('Restrict Pages: ' . $restrictPages);

        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true; // (true) in case of date filter
        $this->exts->log('Restrict Date: ' . $restrictDate);

        $maxInvoices = 15;
        $invoiceCount = 0;

        $terminateLoop = false;


        $this->exts->waitTillPresent('div.panel', 30);
        $this->exts->capture("4-invoices-page");

        do {

            $pagingCount++;

            $this->exts->waitTillPresent('div.panel', 30);
            $rows = $this->exts->querySelectorAll('div.panel');

            foreach ($rows as $index => $row) {
                $this->exts->waitTillPresent('div.panel', 30);

                $invoiceCount++;

                $this->isNoInvoice = false;

                $invoiceDate = trim($this->exts->querySelectorAll('div.panel')[$index]?->querySelector('.date')?->getText());

                $this->exts->log('invoice date: ' . $invoiceDate);

                $parsedDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Parsed date: ' . $parsedDate);

                $this->exts->querySelectorAll('div.panel')[$index]?->querySelector('a[ng-click*="showDe"]')?->click();
                sleep(2);
                $this->exts->waitTillPresent('.currency-amount');
                $invoiceAmount = $this->exts->querySelector('.currency-amount')?->getText();
                $this->exts->log('invoice amount: ' . $invoiceAmount);

                $this->exts->waitTillPresent('a[ng-click*="print"]');
                $this->exts->click_element('a[ng-click*="print"]');

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
                $this->exts->log(' ');
                $this->exts->log('---------------------------INVOICE ITERATION END-------------------------');
                $this->exts->log(' ');


                $lastDate = !empty($parsedDate) && $parsedDate <= $restrictDate;

                if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                    $terminateLoop = true;
                    break;
                } elseif ($restrictPages == 0 && $dateRestriction && $lastDate) {
                    $terminateLoop = true;
                    break;
                }
                $this->exts->click_element('a[ng-click*="navigateBack"]');
                sleep(5);
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
        $this->exts->log('Invoices found: ' . $invoiceCount);
    }


    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 20);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_element($this->submit_login_selector);
                    sleep(2);
                }
                $this->exts->waitTillPresent($this->password_selector, 20);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->capture("1-login-page-filled");
                sleep(1);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_element($this->submit_login_selector);
                    sleep(6);
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
