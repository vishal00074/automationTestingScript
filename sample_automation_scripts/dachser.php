<?php // udpated download code

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

    // Server-Portal-ID: 145456 - Last modified: 10.06.2025 08:50:59 UTC - User: 1

    public $baseUrl = 'https://elogistics.dachser.com/login/home?1';
    public $loginUrl = 'https://elogistics.dachser.com/login/home?1';
    public $invoicePageUrl = 'https://elogistics.dachser.com/downloads/index';
    public $username_selector = 'input[name="user:unit:textfield"]';
    public $password_selector = 'input[type="password"][name="password:password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'div#login-container input[type="submit"][name="p::submit"]';
    public $check_login_failed_selector = 'li.feedbackPanelERROR';
    public $check_login_success_selector = 'a[href*="-header-navigation-logout"]';
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
            sleep(5);
            $this->fillForm(0);
            sleep(5);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            // $this->exts->openUrl($this->invoicePageUrl);
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

            if ($this->exts->querySelector('div.menu-app-section:nth-child(4) a.application-name-link') != null) {
                $this->exts->moveToElementAndClick('div.menu-app-section:nth-child(4) a.application-name-link');
                sleep(4);
            }

            $this->exts->waitTillPresent('input[wicketpath="tabs_panel_pnlInvoiceCenterSearch_frmSearch_datePicker"]', 15);
            if ($this->exts->exists('input[wicketpath="tabs_panel_pnlInvoiceCenterSearch_frmSearch_datePicker"]')) {
                // Define how many months back the date should be

                if ($restrictPages != 0) {
                    $monthsBack = 6;
                    $dateMonthsBack = date('m/d/Y', strtotime("-{$monthsBack} months"));
                    $this->exts->log('Date for search: ' . $dateMonthsBack);

                    $this->exts->moveToElementAndType(
                        'input[wicketpath="tabs_panel_pnlInvoiceCenterSearch_frmSearch_datePicker"]',
                        $dateMonthsBack
                    );

                    // Click the search button
                    $this->exts->click_element('div#startbutton input[type="submit"]');
                    sleep(15);
                } else if ($restrictPages == 0) {
                    $monthsBack = 36;
                    $dateMonthsBack = date('m/d/Ys', strtotime("-{$monthsBack} months"));
                    $this->exts->log('Date for search: ' . $dateMonthsBack);

                    $this->exts->moveToElementAndType(
                        'input[wicketpath="tabs_panel_pnlInvoiceCenterSearch_frmSearch_datePicker"]',
                        $dateMonthsBack
                    );

                    // Click the search button
                    $this->exts->click_element('div#startbutton input[type="submit"]');
                    sleep(15);
                }

                // Process the invoices
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

                $this->exts->log("Enter  password");
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
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent($this->check_login_success_selector, 30);
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
        sleep(25);
        $total_invoices = 0;
        $this->exts->waitTillPresent('div[wicketpath="tabs_panel_pnlInvoiceCenterContent"] table tbody tr', 25); // Wait until the row is visible
        $this->exts->capture("4-invoices-page");

        $rows = $this->exts->getElements('div[wicketpath="tabs_panel_pnlInvoiceCenterContent"] table tbody tr'); // Updated selector to match rows
        $invoices = [];



        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 7 && $this->exts->getElement('a span[class*="pdfImage"]', $tags[5]) != null) {
                $pdfDownloadElement = $this->exts->getElement('a span[class*="pdfImage"]', $tags[5]);
                $invoiceName = trim($tags[2]->getAttribute('innerText'));
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'downloadButton' => $pdfDownloadElement, // PDF as default
                ));
                $this->isNoInvoice = false;
            }
        }

        // Log the number of invoices found
        $this->exts->log('Invoices found: ' . count($invoices));

        // Download each invoice
        foreach ($invoices as $invoice) {
            if ($total_invoices >= 100) break;
            $this->exts->log('--------------------------');
            $this->exts->log('Invoice name: ' . $invoice['invoiceName']);
            $this->exts->log('Invoice Date: ' . $invoice['invoiceDate']);
            $this->exts->log('Invoice Amount: ' . $invoice['invoiceAmount']);

            // Parse date format
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y', 'Y-m-d');
            $this->exts->log('Date Parsed: ' . $invoice['invoiceDate']);

            // Generate a file name for the invoice
            $invoiceFileName =  $invoice['invoiceName'] . '.pdf';

            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {

                $this->exts->click_element($invoice['downloadButton']);
                sleep(2);
                $this->exts->waitTillPresent('div[wicketpath="dlgPdfDisplay_content"] servicearea_application_content a[wicketpath="dlgPdfDisplay_content_frmButtons_lnkDownload"]', 8); //
                $this->exts->moveToElementAndClick('div[wicketpath="dlgPdfDisplay_content"] servicearea_application_content a[wicketpath="dlgPdfDisplay_content_frmButtons_lnkDownload"]');

                sleep(4);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                // $downloaded_file = $this->exts->click_and_download($downloadElement,'pdf',$invoiceFileName);

                if (trim($downloaded_file) !== '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice(
                        $invoice['invoiceName'],
                        $invoice['invoiceDate'],
                        $invoice['invoiceAmount'],
                        $invoiceFileName
                    );
                    $total_invoices++;
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }

                $closePoupElement = $this->exts->querySelector('div[wicketpath="dlgPdfDisplay_content"] servicearea_application_content input[wicketpath="dlgPdfDisplay_content_frmButtons_btnClose"]');
                $this->exts->click_element($closePoupElement);
                sleep(1);
            }
        }


        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->querySelector('a[wicketpath="tabs_panel_pnlInvoiceCenterContent_navigator2_next"]') != null
        ) {
            $paging_count++;
            $this->exts->log('Next invoice page found');
            $this->exts->click_element('a[wicketpath="tabs_panel_pnlInvoiceCenterContent_navigator2_next"]');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
