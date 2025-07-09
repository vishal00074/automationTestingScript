<?php // handle empty name case and added isNoInvoice variable  added custom js waitFor

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

    // Server-Portal-ID: 1621997 - Last modified: 09.07.2025 09:56:41 UTC - User: 1

    public $baseUrl = "https://app.triplewhale.com/";
    public $loginUrl = "https://app.triplewhale.com/";
    public $invoicePageUrl = 'https://app.triplewhale.com/store-settings/orders-invoices';
    public $username_selector = "input#login-email-input";
    public $password_selector = "input#login-password-input";
    public $submit_button_selector = 'button[id="continue-btn-unknown login-button"]';
    public $check_login_failed_selector = "div.Toastify__toast-container";
    public $check_login_success_selector = 'div[id="user-settings-popover"]';
    public $login_tryout = 0;
    public $isNoInvoice = true;
    public $isFailed = false;
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
            // $this->exts->waitTillPresent('a[href*=signin]');
            // if ($this->exts->exists('a[href*=signin]')) {
            //     $this->exts->click_element('a[href*=signin]');
            // }
            $this->fillForm(0);
        }
        if (!$this->checkLogin() && $this->exts->urlContains('com/signin') && $this->exts->getElement($this->username_selector) == null) {
            // site redirect to error page
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(10);
            // $this->waitForSelectors('div[data-rbd-droppable-id="stores-nav-drop-zone"] > div', 15, 5);
            // $this->exts->click_element('div[data-rbd-droppable-id="stores-nav-drop-zone"] > div');
            // if (!$this->exts->exists('div[data-rbd-droppable-id="stores-nav-drop-zone"] > div')) {
            //     $this->exts->click_by_xdotool('div[aria-haspopup="dialog"]:first-child button');
            //     sleep(3);
            //     $this->exts->click_by_xdotool('div[role="dialog"]  div:nth-child(2)');
            // }
            // $this->waitForSelectors('div[data-marketing-target="market-target-settings-toggler"]', 15, 5);

            // click setting
            $this->exts->openUrl('https://app.triplewhale.com/pod-settings');
            sleep(15);
            $stores = count($this->exts->getElements('.Polaris-Card__Section .option-section [data-polaris-tooltip-activator="true"]'));
            for ($i = 0; $i < $stores; $i++) {
                $store = $this->exts->getElements('.Polaris-Card__Section .option-section [data-polaris-tooltip-activator="true"]')[$i];
                try {
                    $this->exts->log('Click store button');
                    $store->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click store button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$store]);
                }
                sleep(15);
                // $this->exts->openUrl($this->invoicePageUrl);
                $this->exts->moveToElementAndClick('a[href*="/store-settings/orders-invoices"], a[data-marketing-target="market-target-Invoices"]');
                sleep(15);

                $this->processInvoices();
                $this->exts->openUrl('https://app.triplewhale.com/pod-settings');
                sleep(25);
            }


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


    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 15);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->exts->click_by_xdotool($this->submit_button_selector);
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

            // $this->waitForSelectors($this->check_login_success_selector, 20);
            for ($i = 0; $i < 35 && $this->exts->getElement($this->check_login_success_selector) == null; $i++) {
                sleep(1);
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

    public function processInvoices($paging_count = 1)
    {
        // $this->waitForSelectors('table tbody tr', 15);
        for ($k = 0; $k < 20 && $this->exts->getElement('table tbody tr') == null; $k++) {
            sleep(1);
        }
        $this->exts->capture("4-invoices-page");

        if ($this->exts->getElements('div[class="Polaris-Card__Section"] .row')) {

            $rows = $this->exts->getElements('div[class="Polaris-Card__Section"] .row');
            $this->exts->log(count($rows) . ' Download Invoices found');

            for ($i = 0; $i < count($rows); $i++) {
                // $this->waitForSelectors('div[class="Polaris-Card__Section"] .row', 15);
                for ($i = 0; $i < 20 && $this->exts->getElement('div[class="Polaris-Card__Section"] .row') == null; $i++) {
                    sleep(1);
                }
                $row = $this->exts->getElements('div[class="Polaris-Card__Section"] .row')[$i];
                if ($this->exts->querySelector('div img[id="downloadInvoice"]', $row) != null) {
                    $invoiceName = $this->exts->extract('div[class="product-name"]', $row);
                    $invoiceAmount =  $this->exts->extract('div:nth-child(2).middle', $row);
                    $invoiceDate =  $this->exts->extract('div p.created-date', $row);
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    // Download by click button_download
                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceFileName: ' . $invoiceFileName);
                    $download_button = $this->exts->getElement('div img[id="downloadInvoice"]', $row);
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        if ($download_button != null) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoiceName, $invoiceDate, '', $invoiceFileName);
                                $this->isNoInvoice = false;
                                sleep(1);
                            } else {
                                if ($this->exts->exists('div[data-testid="hip-app-container"] .ContentCard')) {
                                    $this->exts->log("This link expired");
                                    $this->exts->execute_javascript('window.history.back()');
                                }
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                            }
                        }
                        $this->exts->log('check scroll');
                        $this->exts->execute_javascript('
                        let interval = setInterval(() => {
                                window.scrollBy(0, 70);
                            }, 5000);
                    ');
                    }
                }
            }
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if ($restrictPages == 0 && $paging_count < 50 && $this->exts->querySelector('#nextURL:not(.Polaris-Button--disabled)') != null) {
                $paging_count++;
                $this->exts->click_by_xdotool('#nextURL:not(.Polaris-Button--disabled)');
                sleep(5);
                $this->processInvoices($paging_count);
            } else if ($restrictPages > 0 && $paging_count < $restrictPages && $this->exts->querySelector('#nextURL') != null) {
                $paging_count++;
                $this->exts->click_by_xdotool('#nextURL:not(.Polaris-Button--disabled)');
                sleep(5);
                $this->processInvoices($paging_count);
            }
        } elseif ($this->exts->exists('table tbody tr')) {
            $this->exts->capture("4-invoices-page");
            $invoices = [];

            $rows = $this->exts->querySelectorAll('table tbody tr');
            for ($i = 0; $i < count($rows); $i++) {
                // $this->waitForSelectors('table tbody tr', 5);
                for ($j = 0; $j < 20 && $this->exts->getElement('table tbody tr') == null; $j++) {
                    sleep(1);
                }
                $invoicePageink = $this->exts->getUrl();
                $this->exts->log('Table Row no. : ' . $i);
                $row = $this->exts->getElements('table tbody tr')[$i];
                if ($this->exts->querySelector('td:nth-child(4) button', $row) != null) {
                    $invoiceUrl = '';
                    $invoiceName = $this->exts->extract('th div div:first-child', $row);
                    $invoiceAmount =  $this->exts->extract('td:nth-child(2)', $row);
                    $invoiceDate =  $this->exts->extract('th div div:last-child', $row);

                    $downloadBtn = $this->exts->querySelector('td:nth-child(4) button', $row);

                    $this->isNoInvoice = false;

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'F j, Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                    $this->exts->execute_javascript('arguments[0].click()', [$downloadBtn]);
                    sleep(10);
                    $downloaded_file =  $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if ($this->exts->urlContains('invoice.stripe') || $this->exts->exists('input[id="recoverLinkEmail"]')) {
                        $this->exts->openUrl($invoicePageink);
                        sleep(5);
                    } else {
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                }
            }

            // Download all invoices
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if (
                $restrictPages == 0 &&
                $paging_count < 10 &&
                $this->exts->querySelector('div.mantine-Pagination-root button:last-child:not([disabled])')
            ) {
                $paging_count++;
                $paginateButton = $this->exts->querySelector('div.mantine-Pagination-root button:last-child:not([disabled])');
                $this->exts->execute_javascript("arguments[0].click();", [$paginateButton]);
                sleep(5);
                $this->processInvoices($paging_count);
            } else if (
                $restrictPages != 0 &&
                $paging_count < $restrictPages &&
                $this->exts->querySelector('div.mantine-Pagination-root button:last-child:not([disabled])')
            ) {
                $this->exts->log('Click paginateButton');
                $paging_count++;
                $paginateButton = $this->exts->querySelector('div.mantine-Pagination-root button:last-child:not([disabled])');
                $this->exts->execute_javascript("arguments[0].click();", [$paginateButton]);
                sleep(5);
                $this->processInvoices($paging_count);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
