<?php // I have updated loginfailed message in case wrong credentials tirgger loginfailedConfirmed and
// added sleep after click on submit login button
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


    // Server-Portal-ID: 524610 - Last modified: 08.08.2025 12:47:42 UTC - User: 1

    public $baseUrl = 'https://eu.charge.mercedes.me/web/de/daimler-de/dashboard/start';
    public $loginUrl = 'https://eu.charge.mercedes.me/web/de/daimler-de';
    public $invoicePageUrl = 'https://eu.charge.mercedes.me/web/de/daimler-de/dashboard/invoices';

    public $username_selector = 'input[id="username"]';
    public $password_selector = 'input#password';
    public $remember_me_selector = 'input#rememberMe';
    public $submit_login_selector = 'button[id="confirm"]';

    public $check_login_failed_selector = 'div[id="server-errors"] ul li';
    public $check_login_success_selector = 'a[href*="logout"]';

    public $isNoInvoice = true;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            // $this->exts->waitTillPresent("a[href*='login']", 10);
            sleep(10);
            $this->exts->moveToElementAndClick("a[href*='login']");
            sleep(10);
            $this->fillForm(0);
        }

        sleep(5);

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if (stripos($error_text, strtolower('Invalid login details')) !== false) {
            $this->exts->loginFailure(1);
        }

        if ($this->exts->exists('iframe[src*="oneDoCSPA"]')) {
            $iframe_block = $this->exts->makeFrameExecutable('iframe[src*="oneDoCSPA"]');
            $iframe_block->moveToElementAndClick('button#saveAllTitleButton');
            sleep(5);
            // $accept_all_button = $this->exts->getElementByText('button[type=submit]','Einwilligen in alles')
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);
            if ($this->exts->exists('button[id="cookieacceptbutton"]')) {
                $this->exts->click_element('button[id="cookieacceptbutton"]');
            }
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            if (!$this->exts->urlContains('dashboard/invoices') && $this->exts->exists('#page-content-wrapper li a[href*="dashboard/invoices"]')) {
                $this->exts->moveToElementAndClick('#page-content-wrapper li a[href*="dashboard/invoices"]');
                sleep(15);
            }

            if ($this->exts->exists('ul.options')) {
                $yearOptions = $this->exts->querySelectorAll('ul.options li');
                $totalYears = count($yearOptions);

                for ($i = 0; $i < $totalYears; $i++) {
                    $yearOptions = $this->exts->querySelectorAll('ul.options li');
                    $this->exts->click_element($yearOptions[$i]);
                    sleep(3);
                    $this->processInvoices();
                }
            } else {
                $this->processInvoices();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'access data') !== false) {
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
                sleep(3);

                if ($this->exts->exists('button[id="continue"]')) {
                    $this->exts->click_by_xdotool('button[id="continue"]');
                }
                sleep(3);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(3);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }
                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                    sleep(5);
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

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

    private function processInvoices()
    {
        $this->exts->waitTillPresent('div.dcs-invoice-panel', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div.dcs-invoice-panel');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('a', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('div.dcs-invoice-panel__number', $row);
                $invoiceAmount =  $this->exts->extract('div.dcs-invoice-panel__price', $row);

                $invoiceDate =  explode(' - ', $this->exts->extract('h4', $row))[0];

                $downloadBtn = $this->exts->querySelector('a', $row);

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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

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
