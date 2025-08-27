<?php // updated success selector

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673461/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 223925 - Last modified: 27.02.2025 13:03:16 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.duden.de/';
    public $invoicePageUrl = 'https://www.duden.de/rechnungen';
    public $loginUrl = 'http://duden.de/user/authenticate';

    public $username_selector = 'form#loginForm input[id="loginForm:username"]';
    public $password_selector = 'form#loginForm input[id="loginForm:password"]';
    public $submit_login_selector = 'form#loginForm input[id="loginForm:loginButton"]';

    public $check_login_failed_selector = 'form#loginForm ul[style*="color:red"] li';
    public $check_login_success_selector = 'div.listview__item a[href*="/user/expire"], div#user-info, a[href="/rechnungen"].upright-menu__link';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        $this->exts->waitTillAnyPresent(explode(',', $this->check_login_success_selector), 20);
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->waitTillPresent('button[title="AKZEPTIEREN UND WEITER"]');
            if ($this->exts->exists('button[title="AKZEPTIEREN UND WEITER"]')) {
                $this->exts->click_element('button[title="AKZEPTIEREN UND WEITER"]');
            }
            // $this->exts->clearCookies();
            // $loginUrl = $this->exts->extract('div.listview__item a[href*="/user/authenticate"]', null, 'href');
            $this->exts->openUrl($this->loginUrl);
            $this->checkFillLogin();
            // sleep(15);
        }
        $this->exts->waitTillAnyPresent(explode(',', $this->check_login_success_selector), 20);
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->exts->waitTillPresent('div#notice button[title="AKZEPTIEREN"]');
            if ($this->exts->exists('div#notice button[title="AKZEPTIEREN"]')) {
                $this->exts->click_by_xdotool('div#notice button[title="AKZEPTIEREN"]');
                sleep(2);
            }
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'und/oder passwort') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector);
        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

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

    private function processInvoices()
    {

        $this->exts->waitTillPresent('table > tbody > tr');
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 4 && $this->exts->querySelector('a[href*="/files"]', $tags[3]) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="/files"]', $tags[3])->getAttribute("href");
                $invoiceName = explode(
                    'files/',
                    array_pop(explode('/', $invoiceUrl))
                )[0];
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';

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
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
