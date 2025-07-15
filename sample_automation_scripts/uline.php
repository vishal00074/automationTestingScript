<?php // updated download code

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

    // Server-Portal-ID: 748576 - Last modified: 03.01.2024 13:47:25 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.uline.ca';
    public $loginUrl = 'https://www.uline.ca/SignIn/SignIn';
    public $username_selector = 'form#signinForm input[name="txtEmail"]';
    public $password_selector = 'form#signinForm input[name="txtPassword"]';
    public $remember_me_selector = '';
    //public $submit_next_selector = '.auth-content-inner form input[type="submit"]';

    public $submit_login_selector = 'form#signinForm input#btnSignIn';

    public $check_login_success_selector = '.account-group.invoices-group ul li:first-child a.myulinelink';
    public $check_login_failed_selector = 'form#signinForm span.messageListWarning';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            if ($this->exts->exists('div#CountrySelectionModal')) {
                $this->exts->log('click country');
                $this->exts->moveToElementAndClick('a[data-country-code="CA"]');
            }
            sleep(10);
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
            $this->checkFillLogin();
            sleep(10);
        }



        // then check user logged in or not
        if ($this->checkLogin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in ' . $this->exts->getUrl());
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice

            $this->exts->moveToElementAndClick($this->check_login_success_selector);
            sleep(10);

            $invoicePageSelector = '.myInvoices.myInvoicesSearchOpen.myAccountInvoiceContainer';

            for ($wait = 0; $wait < 5 && $this->exts->executeSafeScript("return !!document.querySelector('" . $invoicePageSelector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }

            $this->exts->capture("account-detail-screen-success");
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('incorrect')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {

        if ($this->exts->exists($this->username_selector)) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);


            $this->exts->capture("failed_login_screen");

            sleep(5);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent($this->check_login_success_selector, 20);
            sleep(10);
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
        $this->exts->capture('select-date-range');

        $selectDate = new DateTime();
        $currentDate = $selectDate->format('n/j/Y');

        if ($this->restrictPages == 0) {
            $selectDate->modify('-3 years');
            $formattedDate = $selectDate->format('n/j/Y');
            $this->exts->capture('date-range-3-years');
        } else {
            $selectDate->modify('-3 months');
            $formattedDate = $selectDate->format('n/j/Y');
            $this->exts->capture('date-range-3-months');
        }

        $startDateElement = $this->exts->getElement('input[name="CalendarInvoiceSearchFrom.Date"]');

        $this->exts->executeSafeScript('arguments[0].removeAttribute("readonly");', [$startDateElement]);

        $this->exts->moveToElementAndType('input[name="CalendarInvoiceSearchFrom.Date"]', '');
        $this->exts->moveToElementAndType('input[name="CalendarInvoiceSearchFrom.Date"]', $formattedDate);


        sleep(5);

        $EndDateElement = $this->exts->getElement('input[name="CalendarInvoiceSearchTo.Date"]');
        $this->exts->executeSafeScript('arguments[0].removeAttribute("readonly");', [$EndDateElement]);

        $this->exts->moveToElementAndType('input[name="CalendarInvoiceSearchTo.Date"]', '');
        $this->exts->moveToElementAndType('input[name="CalendarInvoiceSearchTo.Date"]', $currentDate);

        sleep(5);

        $secrchElement = $this->exts->getElement('input#buttonSearch');

        $secrchElement->click();
        sleep(10);
    }
    public $total_invoices = 0;

    private function processInvoices($paging_count = 1)
    {
        sleep(10);

        $this->exts->capture("4-invoices-page");
        $this->dateRange();

        $this->exts->waitTillPresent('.myInvoices.myInvoicesSearchOpen.myAccountInvoiceContainer > table > tbody > tr');

        $rows = $this->exts->getElements('.myInvoices.myInvoicesSearchOpen.myAccountInvoiceContainer div > table > tbody > tr ');

        for ($i = 0; $i < count($rows); $i++) {
            $row = $this->exts->getElements('.myInvoices.myInvoicesSearchOpen.myAccountInvoiceContainer div > table > tbody > tr ')[$i];
            $download_button = $this->exts->getElement('a.dRDownload.emailPdf', $row);

            if ($this->total_invoices >= 100) {
                return;
            }
            $this->exts->log('total_invoices ' . $this->total_invoices);

            $download_button->click();

            if ($download_button != null) {
                $invoiceName = $this->exts->extract('td:nth-child(2)', $row, 'innerText');
                $invoiceFileName = !empty($invoiceName)  ? $invoiceName . '.pdf' : '';
                $invoiceDate = trim($this->exts->extract('td:nth-child(3)', $row, 'innerText'));
                $invoiceDate = trim(explode(' ', $invoiceDate)[0]);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('td:nth-child(10)', $row, 'innerText'))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->isNoInvoice = false;

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                    $this->exts->moveToElementAndClick('a#modalClose');
                    sleep(5);
                } else {

                    sleep(10);
                    if ($this->exts->exists('div#invoiceDetail-container')) {
                        $element = $this->exts->getElement('a.link.DownloadDocument.DocumentDownloadPDF');
                        try {
                            $this->exts->log('Click download button');
                            $element->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$element]);
                        }
                    }

                    sleep(5);
                    $this->exts->moveToElementAndClick('a#modalClose');
                    sleep(2);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        $this->total_invoices++;
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
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
