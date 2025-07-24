<?php // handle empty invoice name case replace waitForSelector to waitFor function getting js error in waitforselector while executings

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

    // Server-Portal-ID: 37598 - Last modified: 19.06.2025 15:08:32 UTC - User: 1

    public $baseUrl = 'https://adsl.free.fr/liste-factures.pl';
    public $loginUrl = 'https://subscribe.free.fr/login/';
    public $invoicePageUrl = 'https://adsl.free.fr/liste-factures.pl';

    public $username_selector = 'input[name="login"]';
    public $password_selector = 'input[name="pass"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form#log_form input.login_button,button.login_button';

    public $check_login_failed_selector = 'div.loginalert';
    public $check_login_success_selector = 'a[href*="/logout.pl"], .monabo.mesfactures';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();

            $this->waitFor($this->check_login_success_selector, 15);

            if (!$this->exts->exists($this->check_login_success_selector) && !$this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->refresh();
                sleep(20);
                if ($this->exts->exists($this->password_selector)) {
                    $this->checkFillLogin();
                    sleep(20);
                }
            }
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->exists('div[section="home"] a[href*="liste-factures"]')) {
                $this->exts->click_by_xdotool('div[section="home"] a[href*="liste-factures"]');
            } else {
                $this->exts->moveToElementAndClick('a.navbtn.monabo');
                sleep(10);
                $this->exts->moveToElementAndClick('div.container li[role="menuitem"] a[href*="liste-factures"]');
            }

            if ($this->exts->exists('div[section="home"] a[href*="liste-factures"]')) {
                $list_invoices = $this->exts->querySelector('div[section="home"] a[href*="liste-factures"]');
                if ($list_invoices) {
                    $this->exts->click_element($list_invoices);
                    sleep(3);
                }
            } else {
                $this->exts->click_element('a[href*="home"]');
                sleep(2);
                $this->exts->waitTillPresent('span.more a[href*="facture_liste"]');
                $this->exts->click_element('span.more a[href*="facture_liste"]');
            }

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos($this->exts->extract($this->check_login_failed_selector), 'Identifiant ou mot de passe incorrect') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'sessions maximum atteint') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {

        $this->exts->waitTillPresent($this->username_selector, 10);
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->remember_me_selector != '') {
                $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("2-login-page-filled");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
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

        $this->exts->capture("4-invoices-page");
        $this->exts->waitTillPresent('div.mesfactures ul li', 30);
        $invoices = [];

        $rows = $this->exts->getElements('div.mesfactures ul li');
        $this->exts->log('No of Rows : ' . count($rows));
        foreach ($rows as $row) {
            if ($this->exts->getElement('span a', $row) != null) {
                $invoiceUrl = $this->exts->getElement('span a', $row)->getAttribute("href");

                preg_match('/no_facture=([0-9]+)/', $invoiceUrl, $matches);
                $invoiceName = $matches[1];

                $invoiceDate = $this->exts->extract('span:nth-child(2)', $row);
                $invoiceAmount = $this->exts->extract('span:nth-child(3)', $row);

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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F Y', 'Y-m-01', 'fr');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            sleep(1);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
