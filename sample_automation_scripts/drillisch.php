<?php // replace waitTillPresent to waitFor added datefilter to download invoices

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

    // Server-Portal-ID: 778099 - Last modified: 05.05.2025 13:53:33 UTC - User: 1

    public $baseUrl = 'https://service.drillisch-online.de/';
    public $loginUrl = 'https://service.drillisch-online.de/';
    public $invoicePageUrl = 'https://service.winsim.de/mytariff/invoice/showAll';

    public $username_selector = 'input[id="UserLoginType_alias"]';
    public $password_selector = 'input#UserLoginType_password';
    public $remember_me_selector = 'input#UserLoginType_rememberUsername';
    public $submit_login_selector = 'a[title="Login"]';

    public $check_login_failed_selector = 'div.alert-error';
    public $check_login_success_selector = 'span[id="logoutLink"], div#logoutLink';

    public $isNoInvoice = true;
    public $restrictPages = 3;

    private function initPortal($count)
    {
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
            $this->waitFor('a.loginlinks', 5);
            if ($this->exts->exists('a.loginlinks')) {
                $this->exts->click_by_xdotool('a.loginlinks');
                $this->fillForm(1);
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(2);
            $this->waitFor('form.sw-info-layer button.c-overlay-close, dialog a.c-overlay-close', 5);
            if ($this->exts->exists('form.sw-info-layer button.c-overlay-close, dialog a.c-overlay-close')) {
                $this->exts->click_element('form.sw-info-layer button.c-overlay-close, dialog a.c-overlay-close');
            }
            $this->waitFor('button[id="consent_wall_optin"]', 5);
            if ($this->exts->exists('button[id="consent_wall_optin"]')) {
                $this->exts->click_element('button[id="consent_wall_optin"]');
            }
            // $this->exts->openUrl($this->invoicePageUrl);
            $this->exts->click_by_xdotool('a.block[href*="/mytariff/invoice/showAll"]');
            $this->processInvoices();
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

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 10);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
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

    public function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->waitFor($this->check_login_success_selector, 10);
            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }


    private function dateRange($invoiceDate)
    {

        $selectDate = new DateTime();
        if ($this->restrictPages == 0) {
            $selectDate->modify('-3 years');
            $restrictDate = $selectDate->format('d.m.Y');
        } else {
            $selectDate->modify('-3 months');
            $restrictDate = $selectDate->format('d.m.Y');
        }

        $this->exts->log("formattedDate:: " . $restrictDate);
        $restrictDateTimestamp = strtotime($restrictDate);
        $invoiceDateTimestamp = strtotime($invoiceDate);


        if ($restrictDateTimestamp < $invoiceDateTimestamp) {
            return true;
        } else {
            return false;
        }
    }

    public $totalInvoices = 0;

    private function processInvoices($paging_count = 1)
    {
        $this->waitFor("a[href*='PDF']", 15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div[data-name*="rechnungsjahr"]');
        foreach ($rows as $row) {
            $downloadBtn = $this->exts->querySelector("a[href*='PDF']", $row);
            if ($downloadBtn != null) {
                $invoiceUrl = $downloadBtn->getAttribute('href');
                $invoiceName = '';
                $invoiceAmount =  '';
                $dateText =  $this->exts->extract('summary', $row);
                $dateText = str_replace("Rechnung vom", "", $dateText);

                $invoiceDate = trim($dateText);

                $downloadBtn = $row;

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {

            if ($this->totalInvoices >= 100) {
                return;
            }
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $dateRange = $this->dateRange($invoice['invoiceDate']);

            if (!$dateRange) {
                return;
            }

            $invoiceFileName = '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {

                $invoiceName = basename($downloaded_file);
                $invoice['invoiceName'] = substr($invoiceName, 0, strrpos($invoiceName, '.'));
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);

                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $this->totalInvoices++;
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
