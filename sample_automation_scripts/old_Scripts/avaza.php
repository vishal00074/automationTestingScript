<?php // handle empty invoice name case and removed password log from fillForm function 

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

    public $baseUrl = 'https://mobilemind.avaza.com/home/dashboard';
    public $loginUrl = 'https://any.avaza.com/account/login';
    public $invoicePageUrl = 'https://mobilemind.avaza.com/invoice';

    public $username_selector = 'input#Email';
    public $password_selector = 'input#Password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form input[type="submit"]';

    public $check_login_failed_selector = 'div.validation-summary-errors';
    public $check_login_success_selector = 'a[href*="logout"]';

    public $isNoInvoice = true;

    /**<input type="password" name="password" autocomplete="current-password" class="textinput textInput" required id="id_password">

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

            sleep(2);

            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            if ($this->exts->querySelector('a[href="/manage/"]') != null) {
                $this->exts->moveToElementAndClick('a[href="/manage/"]');
                sleep(10);
            }

            if ($this->exts->querySelector('a[href="/subscription"]') != null) {
                $this->exts->moveToElementAndClick('a[href="/subscription"]');
                sleep(10);
            }
            
            $this->processInvoicesNew();

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

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 15);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->log("Remember Me");
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
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

    private function processInvoices($paging_count = 1)
    {

        $this->exts->waitTillPresent('table#InvoiceTable tbody tr td a[href*="/invoice/view/"]', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        do {

            $this->exts->waitTillPresent('table#InvoiceTable tbody tr td a[href*="/invoice/view/"]', 30);
            $rows = $this->exts->querySelectorAll('table#InvoiceTable tbody tr');
            $anchor = 'td a[href*="/invoice/view/"]';

            foreach ($rows as $row) {
                if ($this->exts->querySelector($anchor, $row) != null) {

                    $invoiceUrl = $this->exts->querySelector($anchor, $row)->getAttribute('href');
                    $invoiceName = $this->exts->extract($anchor, $row);
                    $invoiceAmount = $this->exts->extract('td:last-child', $row);
                    $invoiceDate = $this->exts->extract('td:nth-child(4)', $row);

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl
                    ));

                    $this->isNoInvoice = false;
                }
            }

            // pagination handle
            if ($this->exts->exists('li.next:not(.disabled) a')) {
                $this->exts->log('Click Next Page in Pagination!');
                $this->exts->click_element('li.next:not(.disabled) a');
                sleep(5);
            } else {
                $this->exts->log('Last Page!');
                break;
            }
        } while (true);

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));

        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date(trim($invoice['invoiceDate']), 'd M Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->openUrl($invoice['invoiceUrl']);

            $this->exts->waitTillPresent('a[href*=".pdf"]', 30);
            $downloadBtn = $this->exts->querySelector('a[href*=".pdf"]');

            $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);

            sleep(2);

            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }

            $this->exts->log(' ');
            $this->exts->log('---------------------------INVOICE ITERATION END-------------------------');
            $this->exts->log(' ');
        }

        $this->exts->openUrl($this->invoicePageUrl);
    }

    private function processInvoicesNew($count = 0)
    {
        sleep(20);
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table#RecentPaymentHistoryTable tbody tr');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('table#RecentPaymentHistoryTable tbody tr');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('a', $row);
            if ($invoiceLink != null) {
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $invoiceName = '';
                if (preg_match('/INV\d+/', $invoiceUrl, $matches)) {
                    $invoiceName = $matches[0];
                }


                $invoiceDate = $this->exts->extract('td:nth-child(1)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(2)', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ));
                $this->isNoInvoice = false;
            }
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $pagiantionSelector = 'li.next:not(.disabled) a';
        if ($restrictPages == 0) {
            if ($count < 50 && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->moveToElementAndClick($pagiantionSelector);
                sleep(7);
                $count++;
                $this->processInvoicesNew($count);
            }
        } else {
            if ($count < $restrictPages && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->moveToElementAndClick($pagiantionSelector);
                sleep(7);
                $count++;
                $this->processInvoicesNew($count);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP("optimized-chrome-v2", 'Avaza', '2673526', 'bHVkd2lnQHN1cnZleWVuZ2luZS5jb20=', 'cHNDZms4Ny4=');
$portal->run();
