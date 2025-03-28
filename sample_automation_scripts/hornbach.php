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

    // Server-Portal-ID: 409689 - Last modified: 06.03.2025 14:02:59 UTC - User: 1

    /*start script*/

    public $baseUrl = 'https://www.hornbach.de/customer/';
    public $loginUrl = 'https://www.hornbach.de/customer/';
    public $invoicePageUrl = 'https://www.hornbach.de/customer/account/purchases/';

    public $username_selector = 'input[type="email"]';
    public $password_selector = 'input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = '.submit-buttons.one-button input[type="submit"]';

    public $check_login_failed_selector = 'label.notification-type-error';
    public $check_login_success_selector = 'a[data-testid="logout-customer" ]';

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
            $this->fillForm(0);

            sleep(2);
            if ($this->exts->exists('button[data-testid="uc-accept-all-button"]')) {
                $this->exts->click_element('button[data-testid="uc-accept-all-button"]');
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);
            if ($this->exts->exists('button[data-testid="uc-accept-all-button"]')) {
                $this->exts->click_element('button[data-testid="uc-accept-all-button"]');
            }
            $this->exts->openUrl($this->invoicePageUrl);
            $this->downloadInvoices();
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

    private function downloadInvoices($count = 0)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('div[class*="ebui-MerchantGroupWrapper"]');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('div[class*="ebui-MerchantGroupWrapper"]');
        foreach ($rows as $key => $row) {
            $invoiceStatus =  $this->exts->extract('span[class*="ebui-T-connected"]', $row);
            $this->exts->log('invoiceStatus:: ' . $invoiceStatus);
            if ($invoiceStatus == 'completed' || $invoiceStatus == 'abgeschlossen') {


                $orderNumber =  $this->exts->extract('p > span[class*="ebui-T-text"]:not([class*="ebui-T-textBold"]):not(:has(span))', $row);
                $this->exts->log('orderNumber:: ' . $orderNumber);
                if (is_numeric($orderNumber)) {
                    $invoiceUrl = '';
                    $invoiceName =  $orderNumber;
                    $invoiceDate = $this->exts->extract('h1[class*="ebui-clearMargin"]:not([class*="ebui-titleHeadline"])', $row);
                    $invoiceAmount = '';

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'orderNumber' => $orderNumber,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl,
                        'invoiceDate' => $invoiceDate
                    ));
                    $this->isNoInvoice = false;
                }
            }
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);
            $this->exts->log('orderNumber: ' . $invoice['orderNumber']);
            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $orderDetail = 'https://www.hornbach.de/customer/account/purchases/#/details/orders/'.$invoice['orderNumber'];
            $this->exts->openUrl($orderDetail);
            sleep(4);
            $this->exts->waitTillPresent('div#PurchasePage');
            $this->exts->execute_javascript('window.print();');
            sleep(4);
            $file_ext = $this->exts->get_file_extension($invoiceFileName);

            $this->exts->wait_and_check_download($file_ext);
            
            $downloaded_file = $this->exts->find_saved_file($file_ext, $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
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
