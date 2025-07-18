<?php // replace waitTillPresent to waitFor

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

    // Server-Portal-ID: 492 - Last modified: 25.02.2025 14:10:59 UTC - User: 1

    public $baseUrl = 'https://my.fastbill.com/index.php?cmd=1';
    public $loginUrl = 'https://my.fastbill.com/index.php?cmd=1';
    public $invoicePageUrl = 'https://my.fastbill.com/index.php?s=hteETP4i6c_3hfXW40owyUgyNVhGbHMMWS1C-3uI96w';

    public $username_selector = 'form.card input[name="email"]';
    public $password_selector = 'form.card input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form.card input[type="submit"]';

    public $check_login_failed_selector = 'form.card .fielderror';
    public $check_login_success_selector = 'a[href*="/logout"]';

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
        $this->waitFor($this->check_login_success_selector);
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->waitFor("//button[.//text()[contains(., 'Zustimmen')]]");
            if ($this->exts->exists("//button[.//text()[contains(., 'Zustimmen')]]")) {
                $this->exts->click_element("//button[.//text()[contains(., 'Zustimmen')]]");
            }
            // $cookie_buttons = $this->exts->getElements('div[width="100%"] >div > button');
            // $this->exts->log('Finding Completted trips button...');
            // foreach ($cookie_buttons as $key => $cookie_button) {
            //     $tab_name = trim($cookie_button->getText());
            //     if (stripos($tab_name, 'Zustimmen') !== false) {
            //         $this->exts->log('Completted trips button found');
            //         $cookie_button->click();
            //         sleep(5);
            //         break;
            //     }
            // }
            $this->checkFillLogin();
            $this->waitFor('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
                $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
                sleep(2);
            }
        }
        $this->waitFor($this->check_login_success_selector);
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
                $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
                sleep(2);
            }

            $urlInvoicePage = $this->exts->extract('#invoicesNewest .showAll', null, 'href');
            $no_sales_invoice = isset($this->exts->config_array["no_sales_invoice"]) ? (int) $this->exts->config_array["no_sales_invoice"] : 0;

            if (!empty($urlInvoicePage) && $no_sales_invoice == 0) {
                $this->exts->openUrl($urlInvoicePage);
                $this->exts->click_element('button.buttonFilter');
                $this->exts->click_element('input[value="paid"]');
                $this->exts->moveToElementAndClick('form.filter.collapse button.green');
                sleep(1);
                $this->processInvoicesSales();
            } else {
                // Subscription Invoice - Open invoices url and download invoice
                $this->exts->openUrl($this->invoicePageUrl);
                $this->processInvoices();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('.fielderror', null, 'text')), 'ist aufgetreten') !== false) {
                $this->exts->account_not_ready();
            } else if ($this->exts->urlContains('account-setup') && $this->exts->exists('div.account-setup__form')) {
                $this->exts->account_not_ready();
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
        $this->waitFor($this->password_selector);
        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(10);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices()
    {
        $this->waitFor('table.main tbody tr');
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table.main tbody tr');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 3 && $this->exts->querySelector('a[href*="/invoice/"]', $row) != null) {
                $invoiceUrl = $this->exts->querySelector('a[href*="/invoice/"]', $row)->getAttribute("href");
                $tempArr = explode("/", $invoiceUrl);
                $tempArr = explode(".pdf", $tempArr[count($tempArr) - 1]);
                $invoiceName = trim($tempArr[0]);
                $invoiceDate = trim($tags[0]->getText());
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getText())) . ' EUR';

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

    //Download updated 050924
    private function processInvoicesSales()
    {
        $this->waitFor('table.main tbody tr[rel]', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table.main tbody tr[rel]');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 7) {
                $invoiceUrl = $row->getHtmlAttribute("rel");
                $invoiceUrl = "https://my.fastbill.com/" . $invoiceUrl;

                $invoiceName = trim($this->exts->extract('td.nr', $row, 'innerText'));
                $invoiceName = trim(str_replace('-', '_', $invoiceName));
                $invoiceDate = trim($this->exts->extract('td.date', $row, 'innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('td.amount', $row, 'innerText'))) . ' EUR';

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
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->openUrl($invoice['invoiceUrl']);
            $this->waitFor('#invoiceSidebar .download button');
            $downloaded_file = $this->exts->click_and_download('#invoiceSidebar .download button', 'pdf', $invoiceFileName);

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
