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

    // Server-Portal-ID: 530553 - Last modified: 07.03.2025 14:31:20 UTC - User: 1

    public $baseUrl = 'https://rentmanapp.com';
    public $loginUrl = 'https://rentmanapp.com/login';
    public $invoicePageUrl = 'https://eventstar.rentmanapp.com/#/invoices';

    public $username_selector = 'input#email';
    public $password_selector = 'input#password';
    public $remember_me_selector = 'input#remember';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'span[role="alert"]';
    public $check_login_success_selector = "a[href*='logout']";

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
            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);

            $this->exts->openUrl($this->baseUrl);
            $this->multiWorkplaces();
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
                    $this->exts->click_element($this->submit_login_selector);
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



        if ($isLoggedIn) {

            if (!empty($this->exts->config_array['allow_login_success_request'])) {

                $this->exts->triggerLoginSuccess();
            }
        }

        return $isLoggedIn;
    }

    private function multiWorkplaces()
    {
        $this->exts->waitTillPresent('div.account-card', 30);
        $workplacesCount = count($this->exts->querySelectorAll('div.account-card'));
        for ($i = 0; $i < $workplacesCount; $i++) {
            $this->exts->waitTillPresent('div.account-card', 30);
            $workplaces = $this->exts->querySelectorAll('div.account-card');
            $currentWorkplace = $workplaces[$i];
            $this->exts->log('Downloading invoices for ' . $currentWorkplace->getAttribute('data-accountname'));
            $loginBtn = $this->exts->querySelector('div.account-loginbutton button', $currentWorkplace);
            $this->exts->click_element($loginBtn);
            sleep(10);
            // $this->exts->openurl('https://eventstar.rentmanapp.com/#/invoices?dates=2024-03-01_2025-03-07');
            sleep(5);
            $this->processInvoices();
            $this->exts->openUrl($this->baseUrl);
        }
    }

    private function processInvoices($paging_count = 1)
    {
        //filter date
        // $this->exts->click_element('button[class*="rm-date-filter"]');
        // sleep(3);
        // $this->exts->click_element("//button[contains(., 'Von / bis')]");
        // sleep(3);
        // $back_months = 3;
        // if ($this->exts->config_array["restrictPages"] == '0') {
        //     $back_months = 12;
        // }
        // for ($month_offset = 0; $month_offset < $back_months; $month_offset++) {
        //     $this->exts->click_element('[name="startCalendar"] button[data-qa*="navigate-back"]');
        //     sleep(1);
        // }
        // $this->exts->click_element('[aria-roledescription="date picker"] [role="grid"] [role="gridcell"][aria-label*="available"]');



        $this->exts->waitTillPresent('div[id*="-body-grid-container"] div.ui-grid-row', 70);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div[id*="-body-grid-container"] div.ui-grid-row');
        foreach ($rows as $row) {
            if ($row != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('div.ui-grid-cell:nth-child(1)', $row);
                $invoiceAmount =  $this->exts->extract('div.ui-grid-cell:nth-child(2)', $row);
                $invoiceDate =  $this->exts->extract('div.ui-grid-cell:nth-child(3)', $row);

                $downloadBtn = $row;

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
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd..m.y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->click_element($invoice['downloadBtn']);
            $this->exts->waitTillPresent('button[data-qa="toolbar-action-open-invoice"]:not([disabled])', 10);
            $this->exts->click_element('button[data-qa="toolbar-action-open-invoice"]:not([disabled])');
            sleep(5);
            // $this->exts->waitTillPresent("//button[contains(., 'download')]",30);
            // $this->exts->click_element("//button[contains(., 'download')]");
            $this->exts->execute_javascript('
            var download_button = document.evaluate("//button[contains(., \'download\')]", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
            if(download_button) {
               download_button.click();
            }
        ');

            sleep(5);
            // $this->exts->waitTillPresent("//button[contains(., 'Download als PDF')]",30);
            $this->exts->execute_javascript('
        var download_button = document.evaluate("//button[contains(., \'Download als PDF\')]", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
        if(download_button) {
            download_button.click();
        }
    ');


            sleep(2);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
            // $downloaded_file = $this->exts->click_and_download("//button[contains(., 'Download als PDF')]", 'pdf', $invoiceFileName);
            sleep(3);
            $this->exts->click_element("button[class*='sidebar-block-button']");
            sleep(3);
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
