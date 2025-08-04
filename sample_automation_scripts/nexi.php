<?php // handle empty invoiceName case otpimize the script code by increase sleep time in filterInvoices function

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

    // Server-Portal-ID: 2161800 - Last modified: 03.08.2025 00:42:37 UTC - User: 1

    public $baseUrl = 'https://login.nexi.de/';
    public $loginUrl = 'https://login.nexi.de/';
    public $invoicePageUrl = 'https://portal.nexi.de/web/Download';
    public $username_selector = 'input#input-username';
    public $password_selector = 'input#input-password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button#button-login';
    public $check_login_failed_selector = 'span.text-danger';
    public $check_login_success_selector = 'a[href*="Logout"], a[href="/web/documents"], li[data-menu-id*="-menu-dashboard"]';
    public $isNoInvoice = true;
    public $restrictPages = 3;
    public $totalInvoices = 0;

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            if ($this->exts->exists('div#iubenda-cs-banner button.iubenda-cs-accept-btn, button#didomi-notice-agree-button')) {
                $this->exts->click_by_xdotool('div#iubenda-cs-banner button.iubenda-cs-accept-btn, button#didomi-notice-agree-button');
            }
            $this->fillForm(0);
            sleep(20);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            // $this->exts->success();
            if ($this->exts->exists('div#iubenda-cs-banner button.iubenda-cs-accept-btn, button#didomi-notice-agree-button')) {
                $this->exts->click_by_xdotool('div#iubenda-cs-banner button.iubenda-cs-accept-btn, button#didomi-notice-agree-button');
            }
            sleep(2);

            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);
            if (!$this->exts->urlContains('404')) {
                $this->filterInvoices();
            }
            $this->exts->openUrl('https://portal.nexi.de/web/documents');
            $this->processInvoicesNew();
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

                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(2); // Portal itself has one second delay after showing toast
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkLogin()
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

    public function filterInvoices()
    {
        $this->exts->waitTillPresent('button#button-filter-DownloadFilter', 20);
        $this->exts->moveToElementAndClick('button#button-filter-DownloadFilter');
        sleep(7);
        if ($this->exts->exists('div#s2id_Filter_DocumentType')) {
            $this->exts->moveToElementAndClick('div#s2id_Filter_DocumentType');
            sleep(7);
            $this->exts->click_element(".//div[contains(@class, 'select2-result-label') and contains(text(), 'Rechnung')]");
        }
        sleep(7);
        if ($this->exts->exists('div#s2id_Filter_State')) {
            $this->exts->moveToElementAndClick('div#s2id_Filter_State');
            sleep(7);
            $this->exts->click_element(".//div[contains(@class, 'select2-result-label') and contains(text(), 'Abgerufen')]");
        }
        sleep(7);
        $this->exts->moveToElementAndClick('button#button-search');
        // DOWNLOAD INVOCIES
        $this->processInvoices();

        $this->exts->execute_javascript("window.scrollTo({ top: 0, behavior: 'smooth' });");
        sleep(7);

        $this->exts->waitTillPresent('button#button-filter-DownloadFilter', 20);
        $this->exts->moveToElementAndClick('button#button-filter-DownloadFilter');
        sleep(7);

        if ($this->exts->exists('div#s2id_Filter_DocumentType')) {
            $this->exts->moveToElementAndClick('div#s2id_Filter_DocumentType');
            sleep(7);
            $this->exts->click_element(".//div[contains(@class, 'select2-result-label') and contains(text(), 'Statement')]");
            sleep(7);
        }
        $this->exts->moveToElementAndClick('button#button-search');
        sleep(7);
        // DOWNLOAD STATEMENTS
        $this->processInvoices();
    }

    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('table tbody > tr', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        sleep(5);
        // $this->exts->waitTillPresent(' div[role="tabpanel"]:not([aria-hidden="true"]) table tbody > tr', 15);
        $rows = $this->exts->getElements('table tbody > tr');
        foreach ($rows as $row) {
            if ($this->restrictPages != 0 && $this->totalInvoices >= 100) {
                return;
            };
            if ($this->exts->getElement('td a.download-single', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('td a.download-single', $row)->getAttribute('href');
                $invoiceName = explode('/', $invoiceUrl);
                $invoiceName = end($invoiceName);
                $invoiceDate = '';
                $invoiceAmount =  '';
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";

                $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
                $this->isNoInvoice = false;

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
        // next page
        sleep(10);
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->querySelector("div.pagination-button a:nth-child(2):not(.disabled)") != null
        ) {
            $paging_count++;
            $this->exts->log('Next invoice page found');
            $this->exts->moveToElementAndClick("div.pagination-button a:nth-child(2):not(.disabled)");
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }

    private function processInvoicesNew($paging_count = 1)
    {
        sleep(15);
        $this->exts->waitTillPresent('button[testing-id="load-more-button"]', 15);

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0) {
            $maxAttempts = 50;
        } else {
            $maxAttempts = $restrictPages;
        }
        for ($paging_count = 0; ($paging_count < $maxAttempts && $this->exts->exists('button[testing-id="load-more-button"]')); $paging_count++) {
            $this->exts->click_element('button[testing-id="load-more-button"]');
            sleep(10);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            sleep(3);
            if ($this->exts->querySelector('td:nth-child(6) i[class*=download]', $row) != null) {
                $invoiceName = $row->getHtmlAttribute('data-row-key');
                $invoiceUrl = '';
                if ($invoiceName == '') {
                    $invoiceUrl = $this->exts->querySelector('td:nth-child(6) i[class*=download]', $row)->getAttribute('href');
                    $invoiceName = explode('/', $invoiceUrl);
                    $invoiceName = end($invoiceName);
                }
                $invoiceDate = '';
                $invoiceAmount =  '';
                $downloadBtn = $this->exts->querySelector('td:nth-child(6) i[class*=download]', $row);

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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);
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
