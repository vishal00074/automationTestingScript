<?php // i have added the restrictPages condition in invoices function

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

    // Server-Portal-ID: 336 - Last modified: 21.07.2025 14:04:16 UTC - User: 1

    // Script here
    public $baseUrl = 'https://mein.exali.de/';
    public $loginUrl = 'https://mein.exali.de/';
    public $invoicePageUrl = 'URL_url_to_invoice_page';

    public $username_selector = 'input[name="mebe_username"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button#loginBtn';

    public $check_login_failed_selector = 'div#div_fail, div.error--text';
    public $check_login_success_selector = 'a#logoutButton';

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
        $this->exts->openUrl($this->baseUrl);
        sleep(3);
        $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector, 'div.user-login span.user-login__text']);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null && $this->exts->getElementByText('div.user-login span.user-login__text', ['logout'], null, false) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->checkFillLogin();
            $this->exts->waitTillPresent($this->check_login_success_selector);
        }

        if ($this->exts->exists('div.modal-header button.close')) {
            $this->exts->moveToElementAndClick('div.modal-header button.close');
            sleep(3);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null || $this->exts->getElementByText('div.user-login span.user-login__text', ['logout'], null, false) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            //$this->exts->openUrl($this->invoicePageUrl);
            if ($this->exts->exists('div.schritt_2ME')) {
                $this->processInvoices();
            } else {
                $invoice_page = $this->exts->getElement('//span[contains(text(), "Meine Versicherungen")]', null, 'xpath');
                try {
                    $this->exts->log('Click invoice_page button');
                    $invoice_page->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click invoice_page button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$invoice_page]);
                }
                $this->processInvoicesnew();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->getElement('div.error--text') != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
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
            sleep(3);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public $totalInvoices = 0;
    private function processInvoices()
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log("restrictPages:: " . $restrictPages);
        sleep(5);
        $this->exts->moveToElementAndClick('div.schritt_2ME');
        sleep(5);
        $this->exts->moveToElementAndClick('div.widget-links div#step2_2');
        sleep(10);

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table#tableDocExali tbody tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 3 && $this->exts->getElement('a[href*="verwaltung/ithp_dl_file"]', $tags[0]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="verwaltung/ithp_dl_file"]', $tags[0])->getAttribute("href");
                $check_is_invoice = trim($tags[1]->getAttribute('innerText'));
                if (strpos($check_is_invoice, 'Beitragsrechnung') !== false) {
                    continue;
                }
                $invoiceName = explode(
                    '&',
                    array_pop(explode('file_id=', $invoiceUrl))
                )[0];
                $invoiceDate = trim(explode(' ', trim($tags[1]->getAttribute('innerText')))[1]);
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
            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                $this->totalInvoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
    // page has changed 11.04.2023
    private function processInvoicesnew()
    {
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log("restrictPages:: " . $restrictPages);

        sleep(3);
        $this->exts->waitTillPresent('div.row--dense [class*="col"] button');

        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = count($this->exts->getElements('div.row--dense'));
        for ($i = 0; $i < $rows; $i++) {
            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }
            $row = $this->exts->getElements('div.row--dense')[$i];
            $tags = $this->exts->getElements('[class*="col"]', $row);
            if (
                count($tags) >= 2 && stripos($this->exts->extract('button', $tags[1], 'innerText'), 'Download') !== false
                && stripos($this->exts->extract('.text--primary', $tags[0], 'innerText'), 'Beitragsrechnung') !== false
            ) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('button', $tags[1]);
                $invoiceName = str_replace('/', '-', trim($tags[0]->getAttribute('innerText')));
                $invoiceName = str_replace('.pdf', '', trim($invoiceName));
                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = '';
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        $this->totalInvoices++;
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
