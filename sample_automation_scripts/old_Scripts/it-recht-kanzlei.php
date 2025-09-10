<?php // replace waitTIllPresent to waitFor and handle empty invoice name

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

    // Server-Portal-ID: 14229 - Last modified: 27.02.2025 12:54:52 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.it-recht-kanzlei.de/Portal/index.php';
    public $loginUrl = 'https://www.it-recht-kanzlei.de/Portal/login.php';
    public $invoicePageUrl = 'https://www.it-recht-kanzlei.de/Portal/rechnungen.php';

    public $username_selector = 'input[name="login"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form[action*="login.php"] input[type="submit"]';

    public $check_login_failed_selector = 'p.errorMsg';
    public $check_login_success_selector = 'a[href*="logout"]';

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

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        $this->exts->capture('1-init-page');
        $this->waitFor($this->check_login_success_selector, 10);
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            $this->checkFillLogin();
        }
        $this->waitFor($this->check_login_success_selector, 10);
        // then check user logged in or not
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            // $this->exts->click_by_xdotool('#SideBar .itrk-dropdown-list ul li a[href*="/rechnungen.php"]');

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'falsche zugangsdaten oder abgelaufener') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->waitFor($this->password_selector, 10);
        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->click_by_xdotool($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function processInvoices()
    {
        $this->waitFor('table.itrk-table tbody tr', 15);

        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $paths = explode('/', $this->exts->getUrl());
        $currentDomainUrl = $paths[0] . '//' . $paths[2];
        $rows = $this->exts->querySelectorAll('table.itrk-table tbody tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 5 && $this->exts->querySelector('a[href*="downloadBill.php?rid"]', $tags[0]) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="downloadBill.php?rid"]', $tags[0])->getAttribute("href");
                if (strpos($invoiceUrl, $currentDomainUrl) === false && strpos($invoiceUrl, 'http') === false) {
                    $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                }
                $invoiceName = trim($tags[0]->getText());
                $invoiceDate = trim(explode(' ', $tags[2]->getText())[0]);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getText())) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            } else if (count($tags) >= 5 && $this->exts->querySelector('a[href*="downloadBill.php?rid"]', $tags[1]) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="downloadBill.php?rid"]', $tags[1])->getAttribute("href");
                if (strpos($invoiceUrl, $currentDomainUrl) === false && strpos($invoiceUrl, 'http') === false) {
                    $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                }
                $invoiceName = trim($this->exts->extract('a[href*="downloadBill.php?rid"]', $tags[1]));
                $invoiceDate = trim(explode("\n", $tags[0]->getText())[0]);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getText())) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) == '' && !file_exists($downloaded_file)) {
                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            }
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
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
