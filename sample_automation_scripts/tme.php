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

    // Server-Portal-ID: 832514 - Last modified: 10.07.2025 19:58:26 UTC - User: 1

    /*Start script*/

    public $baseUrl = 'https://www.tme.eu/de/login';
    public $loginUrl = 'https://www.tme.eu/de/login';
    public $invoicePageUrl = 'https://www.tme.eu/de/Profile/Orders/InvoiceHistory.html';
    public $username_selector = 'input#login';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[data-testid="submit-button"]';
    public $check_login_failed_selector = 'span[class*="error"]';
    public $check_login_success_selector = '.js-logout';
    public $isNoInvoice = true;
    public $restrictPages = 3;
    public $only_sales_invoice = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->restrictPages =  isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->only_sales_invoice =  isset($this->exts->config_array["only_sales_invoice"]) ? (int)@$this->exts->config_array["only_sales_invoice"] : 0;

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        $this->acceptCookies();
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->check_solve_cloudflare_page();
            $this->exts->openUrl($this->loginUrl);
            $this->check_solve_cloudflare_page();
            $this->acceptCookies();
            $this->fillForm(0);
            sleep(8);
            $this->exts->openUrl($this->loginUrl);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);

            if ($this->only_sales_invoice) {
                $this->exts->execute_javascript('let selectBox = document.querySelector("select#invoice_filters_type");
                    selectBox.value = "F2";
                    selectBox.dispatchEvent(new Event("change"));');
                sleep(5);
                $this->processInvoices();
            } else {
                $this->processInvoices();
            }

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

    private function fillForm($count)
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
                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                }
                sleep(5);
                $this->check_solve_cloudflare_page();
                sleep(5);
                $this->exts->capture("1-login-page-filled");
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function acceptCookies()
    {
        $this->exts->waitTillPresent('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll', 10);
        if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
            $this->exts->click_element('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        }
    }

    private function check_solve_cloudflare_page()
    {
        sleep(10);
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(8);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(8);
            }
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

    private function dateRange()
    {
        $this->exts->waitTillPresent('input[name="created_after"]');
        $this->exts->capture('select-date-range');

        $selectDate = new DateTime();
        $currentDate = $selectDate->format('Y-m-d\TH:i');

        if ($this->restrictPages == 0) {
            $selectDate->modify('-3 years');
            $formattedDate = $selectDate->format('Y-m-d\TH:i');
            $this->exts->capture('date-range-3-years');
        } else {
            $selectDate->modify('-3 months');
            $formattedDate = $selectDate->format('Y-m-d\TH:i');
            $this->exts->capture('date-range-3-months');
        }

        // Proper JavaScript string escaping
        $this->exts->execute_javascript('document.querySelector("input[name=\'created_after\']").value = "' . $formattedDate . '";');
        sleep(2);
        $this->exts->execute_javascript('document.querySelector("input[name=\'created_before\']").value = "' . $currentDate . '";');
        sleep(2);
        $this->exts->moveToElementAndClick('input[name="commit"]');
        sleep(10);
    }

    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('input[name="invoice_filters_datefrom"]');
        $dateOffset = 1;

        while ($this->exts->querySelector('table#tb_invoice_history tbody tr td a[onclick*="download"]') == null) {
            $fromDate = date('d-m-Y', strtotime("-$dateOffset months"));

            $this->exts->click_by_xdotool('input[name="invoice_filters_datefrom"]');
            sleep(1);
            $this->exts->type_key_by_xdotool('ctrl+a');
            sleep(1);
            $this->exts->type_key_by_xdotool('Delete');
            sleep(1);
            $this->exts->type_text_by_xdotool($fromDate);
            sleep(1);
            $this->exts->click_element('input[type="submit"]');
            sleep(5);

            $dateOffset++;
            if ($dateOffset > 25) {
                break;
            }
        }
        $this->exts->waitTillPresent('table#tb_invoice_history tbody tr', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table#tb_invoice_history tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td a[onclick*="download"]', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('td:nth-child(1)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(8)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(3)', $row);

                $downloadBtn = $this->exts->querySelector('td a[onclick*="download"]', $row);

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

            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd-m-Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
            sleep(2);
            $this->exts->waitTillPresent('div#popup_container a[class*="bottom_close_button"]', 5);
            if ($this->exts->exists('div#popup_container a[class*="bottom_close_button"]')) {
                $this->exts->click_element('div#popup_container a[class*="bottom_close_button"]');
            }
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));


            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
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
