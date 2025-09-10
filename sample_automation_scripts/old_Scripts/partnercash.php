<?php // migrtaed

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

    // Server-Portal-ID: 20419 - Last modified: 02.08.2023 05:28:40 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.partnercash.com';
    public $loginUrl = 'https://www.partnercash.com/public/de/login.php';
    public $invoicePageUrl = 'https://www.partnercash.com/member/de/cashaccount.php';

    public $username_selector = '.loginForm input[name="puser"]';
    public $password_selector = '.loginForm input[name="ppwd"]';
    public $remember_me_selector = '';
    public $submit_login_btn = '.loginForm input[name="login"]';

    public $checkLoginFailedSelector = '.loginForm .singlemessage';
    public $checkLoggedinSelector = 'a[href*="/logout.php"]';
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(1);
        $this->exts->capture("Home-page-without-cookie");

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        // after load cookies and open base url, check if user logged in
        sleep(5);
        $this->exts->waitTillPresent($this->checkLoggedinSelector);

        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            $this->waitForLogin();
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');

            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->waitForLoginPage();
        }
    }

    private function waitForLoginPage()
    {
        $this->exts->waitTillPresent($this->password_selector);
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("1-filled-login");
            $this->exts->moveToElementAndClick($this->submit_login_btn);
            $this->waitForLogin();
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }

    private function waitForLogin()
    {
        $this->exts->waitTillPresent($this->checkLoggedinSelector);

        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");

            // Open invoices url
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(20);
            $totalOfYears = count($this->exts->getElements('select#inp_period option'));
            if ($totalOfYears > 0) {
                if ($totalOfYears > 3) $totalOfYears = 3;
                for ($i = 0; $i < $totalOfYears; $i++) {
                    $this->exts->moveToElementAndClick('select#inp_period');
                    sleep(1);
                    $this->exts->getElements('select#inp_period option')[$i]->click();
                    sleep(1);
                    $this->exts->moveToElementAndClick('input.abrechnungsbutton');
                    sleep(5);
                    $this->processInvoices();
                }
            } else {
                $this->processInvoices();
            }

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
        } else {
            $this->exts->log('Timeout waitForLogin');
            $this->exts->capture("LoginFailed");

            if (count($this->exts->getElements($this->checkLoginFailedSelector)) > 0) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function processInvoices()
    {
        $driver = $this->exts->webdriver;
        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            $this->exts->log('Invoices found');
            $this->exts->capture("4-page-opened");
            $invoices = [];

            $rows = $this->exts->getElements('table > tbody > tr');
            foreach ($rows as $row) {
                $tags = $this->exts->getElements('td',  $row);
                if (count($tags) < 5) {
                    continue;
                }
                $as = $tags[2]->getElements('a[href*="creditnote.php?id="]');
                if (count($as) == 0) {
                    continue;
                }

                $invoiceUrl = $as[0]->getAttribute("href");
                $invoiceName = explode(
                    '&',
                    array_pop(explode('id=', $invoiceUrl))
                )[0];
                $invoiceDate = trim($tags[0]->getText());
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getText())) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
            }

            // Download all invoices
            $this->exts->log('Invoices: ' . count($invoices));
            $count = 1;
            $totalFiles = count($invoices);

            foreach ($invoices as $invoice) {
                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';

                $this->exts->log('date before parse: ' . $invoice['invoiceDate']);

                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'Y-m-d', 'Y-m-d');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->log('Dowloading invoice ' . $count . '/' . $totalFiles);

                    $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);

                    sleep(2);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->isNoInvoice = false;
                        $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                        $count++;
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                }
            }
        } else {
            $this->exts->log('Timeout processInvoices');
            $this->exts->capture('4-no-invoices');
        }
    }

    // helper function
    private function waitFor($func_or_ec, $timeout_in_second = 15, $interval_in_millisecond = 500)
    {
        $this->exts->log('Waiting for condition...');
        try {
            $this->exts->webdriver->wait($timeout_in_second, $interval_in_millisecond)->until($func_or_ec);
            return true;
        } catch (\Exception $exception) {
            // $this->exts->log($exception);
            return false;
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$portal = new PortalScriptCDP("optimized-chrome-v2", 'WeWork Account Central', '2675013', 'Y2hyaXN0aWFuLndpbGRAc2VuZi5hcHA=', 'SGFsbG9TZW5mMTIz');
$portal->run();
