<?php //  replace waitTillPresent to waitFor and optimize the script code

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

    // Server-Portal-ID: 223736 - Last modified: 24.07.2025 13:22:44 UTC - User: 1

    public $baseUrl = 'https://coachy.net/';
    public $loginUrl = 'https://my.coachy.net/';
    public $username_selector = 'form input[name="email"]';
    public $password_selector = 'form input[name="pass"]';
    public $submit_login_selector = 'form button[type="submit"]';
    public $check_login_failed_selector = 'div.message.error';
    public $check_login_success_selector = 'a[href*="/abmelden"]';
    public $isNoInvoice = true;
    public $restrictPages = 3;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->waitFor('div#cookiebanner span.save.all', 10);
            if ($this->exts->exists('div#cookiebanner span.save.all')) {
                $this->exts->click_by_xdotool('div#cookiebanner span.save.all');
                sleep(5);
            }
            $this->waitFor('button[id*=AllowAll]', 10);
            if ($this->exts->exists('button[id*=AllowAll]')) {
                $this->exts->click_element('button[id*=AllowAll]');
            }
            $this->exts->click_by_xdotool('li.show-for-medium a[href*="/anmelden/"]');
            $this->checkFillLogin();
        }

        $this->waitFor($this->check_login_success_selector, 10);

        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $paths = explode('/', $this->exts->getUrl());
            $current_domain = $paths[0] . '//' . $paths[2];
            $invoice_url = $current_domain . "/verwalten/konto/rechnungen/";

            $this->exts->openUrl($invoice_url);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'anmeldung fehlgeschlagen') !== false || stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'not found') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function checkFillLogin()
    {
        $this->waitFor($this->username_selector, 10);
        if ($this->exts->querySelector($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(5);
            if ($this->exts->exists('form input[type="submit"]:not([value="Neuer Mitgliederbereich"])')) {
                $this->exts->click_by_xdotool('form input[type="submit"]:not([value="Neuer Mitgliederbereich"])');
                sleep(5);
            }
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices($paging_count = 1)
    {
        $total_invoices = 0;
        $this->waitFor('table > tbody > tr', 15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 3 && $this->exts->querySelector('a[href*="\invoice"]', $tags[2]) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="\invoice"]', $tags[2])->getAttribute("href");
                $invoiceName = explode('-', trim($tags[0]->getAttribute('innerText')))[0];
                $invoiceName = trim(preg_replace('/[^\d\w]/', '', $invoiceName));
                $invoiceDate = '';
                $invoiceAmount = '';

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
            if ($this->restrictPages != 0 && $total_invoices >= 100) break;
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : "";
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $total_invoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
        $paging_count++;
        if (
            $this->exts->config_array["restrictPages"] == '0' &&
            $paging_count < 50 &&
            $this->exts->querySelector('a:not([disabled]) i.fa-chevron-right') != null
        ) {
            $this->exts->click_by_xdotool('a:not([disabled]) i.fa-chevron-right');
            sleep(10);
            $this->processInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
