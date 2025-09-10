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

    // Server-Portal-ID: 33364 - Last modified: 14.07.2025 13:52:22 UTC - User: 1

    public $baseUrl = 'https://www.sonepar.de';
    public $loginUrl = 'https://www.sonepar.de/authentication';
    public $invoicePageUrl = 'https://www.sonepar.de/sp/orders';

    public $region_selector = 'select#org';
    public $customer_id_selector = 'input#cust';
    public $username_selector = 'input#user';
    public $email_selector = 'input#email';
    public $login_with_email_selector = 'div#login-email-link';
    public $password_selector = 'input[type="password"]';
    public $remember_me_selector = 'input[type="checkbox"]';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'div.sonepar-alert-error';
    public $check_login_success_selector = 'a[href="/sp/overview"]';
    public $start_date = '.filter-container__scroll-area label:nth-child(1) input';

    public $customer_id = '';

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
        sleep(3);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);

            $this->exts->waitTillPresent("#usercentrics-root", 20);
            if ($this->exts->exists("#usercentrics-root")) {
                $this->exts->execute_javascript("
                    var shadowHost = document.querySelector('#usercentrics-root');
                    if (shadowHost && shadowHost.shadowRoot) {
                        var button = shadowHost.shadowRoot.querySelector('button[data-testid=\"uc-accept-all-button\"]');
                        if (button) {
                            button.click();
                        }
                    }
                ");
                sleep(1);
            }

            $this->fillForm(0);
            sleep(5);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(5);
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(12);

            if ($this->exts->exists('[class="overview-dashboard"] button[class*="s-date-range-select__dropdown"]')) {
                $this->applyFilters();
            }

            $Orders = $this->processInvoices();

            $this->downloadInvoices($Orders);

            
            // Final check: no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } elseif ($this->exts->exists("//div[text()='Please enter your 6-digit customer ID.']")) {
            $this->exts->log("Please enter your 6-digit customer ID.");
            $this->exts->loginFailure(1);
            sleep(1);
        } else {
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credentials !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            if (isset($this->exts->config_array['CUSTOMER_ID'])) {
                $this->customer_id = trim($this->exts->config_array['CUSTOMER_ID']);
            } else if (isset($this->exts->config_array['customer_id'])) {
                $this->customer_id = trim($this->exts->config_array['customer_id']);
            }
            if ($this->customer_id != '') {
                $this->exts->waitTillPresent($this->username_selector, 10);
                if ($this->exts->querySelector($this->username_selector) != null) {


                    $this->exts->capture("1-pre-login");
                    $this->exts->log("Select Region");
                    $this->exts->moveToElementAndClick($this->region_selector);
                    sleep(1);
                    $this->exts->execute_javascript("document.getElementById('org').value = '" . $this->exts->getConfig('region') . "'; document.getElementById('org').dispatchEvent(new Event('change'));");

                    sleep(2);


                    $this->exts->log("Enter Customer-Id");
                    $this->exts->moveToElementAndType($this->customer_id_selector, $this->customer_id);
                    sleep(1);
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);

                    sleep(2);

                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(1);

                    if ($this->exts->exists($this->remember_me_selector)) {
                        $this->exts->click_by_xdotool($this->remember_me_selector);
                        sleep(1);
                    }
                    $this->exts->capture("1-login-page-filled");
                    sleep(2);
                    if ($this->exts->exists($this->submit_login_selector)) {
                        $this->exts->click_by_xdotool($this->submit_login_selector);
                    }
                }
            } else {
                sleep(10);
                $this->exts->click_element($this->login_with_email_selector);
                sleep(2);
                $this->exts->waitTillPresent($this->email_selector, 10);
                if ($this->exts->querySelector($this->email_selector) != null) {
                    $this->exts->log("Enter Email");
                    $this->exts->moveToElementAndType($this->email_selector, $this->username);
                    sleep(2);
                    if (!$this->isValidEmail($this->username)) {
                        $this->exts->loginFailure(1);
                    }
                    sleep(2);
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(1);

                    $this->exts->capture("1-login-page-filled");
                    sleep(5);
                    if ($this->exts->exists($this->submit_login_selector)) {
                        $this->exts->click_by_xdotool($this->submit_login_selector);
                    }
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }



    public function isValidEmail($username)
    {
        // Regular expression for email validation
        $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        if (preg_match($emailPattern, $username)) {
            return 'email';
        }
        return false;
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

    private function applyFilters()
    {
        $this->exts->moveToElementAndClick('[class="overview-dashboard"] button[class*="s-date-range-select__dropdown"]');
        sleep(5);
        $this->exts->click_by_xdotool($this->start_date);
        sleep(2);
        $this->exts->type_key_by_xdotool("ctrl+a");
        sleep(2);
        $this->exts->type_key_by_xdotool("Delete");
        sleep(2);
        $sixMonthsBefore = date('d.m.Y', strtotime('-6 months'));
        $this->exts->moveToElementAndType($this->start_date, $sixMonthsBefore);
        sleep(2);
        $this->exts->type_key_by_xdotool("Return");
        sleep(2);
        $this->exts->moveToElementAndClick('div[data-test="status-filter"]');
        sleep(2);
        $this->exts->moveToElementAndClick('div[class="q-virtual-scroll__content"] div:nth-child(5)');
        sleep(10);
    }

    private function processInvoices($paging_count = 1)
    {
        $rows = $this->exts->querySelectorAll('table tbody tr');
        $this->exts->log('Total No of Rows: ' . count($rows));

        $invoiceOrders = []; // Initialize an empty array to store invoice orders

        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(5)', $row) != null) {
                $invoiceOrder = $this->exts->extract('td:nth-child(5)', $row);
                $invoiceOrders[] = $invoiceOrder; // Append to the array

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceOrder: ' . $invoiceOrder);
            }
        }

        $this->exts->log('All Invoice Orders (Page ' . $paging_count . '): ' . implode(', ', $invoiceOrders));

        // Handle pagination
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;

        if (
            ($restrictPages == 0 || $paging_count < $restrictPages) &&
            $paging_count < 50 &&
            $this->exts->querySelector('.s-pagination__btn--next [aria-disabled="true"]') == null // FIX: Continue if NOT disabled
        ) {
            $paging_count++;
            $this->exts->log('Next invoice page found, moving to page ' . $paging_count);
            $this->exts->click_element('.s-pagination__btn--next');
            sleep(5);

            // Merge current results with next pages' results
            $invoiceOrders = array_merge($invoiceOrders, $this->processInvoices($paging_count));
        }

        return $invoiceOrders;
    }

    private function downloadInvoices($Orders = [])
    {
        $this->exts->capture("4-invoices-page");
        $invoices = $Orders;

        foreach ($invoices as $invoice) {

            $orderUrl = 'https://www.sonepar.de/sp/orders/history/' . $invoice;
            // Download invoice if it not exisited
            $invoiceName = $invoice;
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceFileName: ' . $invoiceFileName);
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $this->exts->openUrl($orderUrl);
                sleep(10);

                if ($this->exts->exists('.order-details button[data-test="order-selection-button"]')) {
                    $this->exts->moveToElementAndClick('.order-details button[data-test="order-selection-button"]');
                    sleep(2);

                    if ($this->exts->exists('a[href*="/invoice"]')) {
                        $downloadBtn = $this->exts->querySelector('a[href*="/invoice"]');



                        $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                        sleep(5);
                        $this->exts->wait_and_check_download('pdf');

                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                        $this->isNoInvoice = false;

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                            sleep(1);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    } else if ($this->exts->exists('div.order-details')) {
                        $this->exts->execute_javascript('document.querySelector("div#q-app").innerHTML = document.querySelector("div.order-details").outerHTML;');
                        sleep(1);
                        $downloaded_file = $this->exts->download_current($invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                            sleep(1);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                        }
                    }
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
