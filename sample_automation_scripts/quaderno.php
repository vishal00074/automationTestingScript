<?php

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
    // Server-Portal-ID: 17791 - Last modified: 26.06.2024 08:52:58 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://quaderno.io/';
    public $loginUrl = 'https://quadernoapp.com/login';
    public $invoicePageUrl = 'https://fxforaliving.quadernoapp.com/invoices';
    public $billingPageUrl = 'https://ninive-7362.quadernoapp.com/settings/payment-history';

    public $username_selector = 'input#user_email';
    public $password_selector = 'input#user_password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input[type="submit"]';

    public $check_login_failed_selector = 'div.alerts.error';
    public $check_login_success_selector = 'a[href*="/logout"]';

    public $isNoInvoice = true;
    public $only_sales_invoice = 0;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        sleep(1);
        $this->only_sales_invoice = isset($this->exts->config_array["only_sales_invoice"]) ? (int)$this->exts->config_array["only_sales_invoice"] : $this->only_sales_invoice;

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        if ($this->exts->exists('#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
        }
        sleep(2);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            if ($this->exts->exists('#onetrust-accept-btn-handler')) {
                $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
            }
            sleep(2);
            if ($this->exts->exists('.localization-bar a[href="/europe-vat/"]')) {
                $this->exts->moveToElementAndClick('.localization-bar a[href="/europe-vat/"]');
                sleep(5);
            }
            if ($this->exts->exists('a[href*="quadernoapp.com/login"]')) {
                $this->exts->moveToElementAndClick('a[href*="quadernoapp.com/login"]');
            } else {
                $this->exts->openUrl($this->loginUrl);
            }
            sleep(5);
            $this->checkFillLogin();
            sleep(20);
        }


        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $currentUrl = $this->exts->getUrl();
            $this->invoicePageUrl = $currentUrl . '/invoices';
            $this->billingPageUrl = $currentUrl . '/settings/payment-history';

            if ($this->only_sales_invoice == 1) {
                // Open invoices url and download invoice
                $this->exts->openUrl($this->invoicePageUrl);

                $this->processInvoices();
            } else {
                $this->exts->openUrl($this->billingPageUrl);
                $this->processBilling();

                // Open invoices url and download invoice
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
            if (strpos(strtolower($this->exts->extract('div[data-banner-type="error"] p ')), 'Invalid email or password') !== false) {
                $this->exts->loginFailure(1);
            } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->log("Username is not a valid email address");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->username_selector);
        if ($this->exts->getElement($this->username_selector) != null) {
            $this->exts->capture("2-login-page");
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            $this->exts->type_key_by_xdotool('Ctrl+a');
            $this->exts->type_key_by_xdotool('Delete');
            $this->exts->type_text_by_xdotool($this->username);

            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            $this->exts->type_key_by_xdotool('Ctrl+a');
            $this->exts->type_key_by_xdotool('Delete');
            $this->exts->type_text_by_xdotool($this->password);
            sleep(4);
            $this->exts->capture("2-login-page-filled");

            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(10);
            if ($this->exts->getElement($this->password_selector) != null) {
                $this->exts->openUrl($this->loginUrl);
                sleep(5);
                if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                    $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                }
                $this->checkFillLoginUndetected();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }


    private function checkFillLoginUndetected()
    {
        $this->exts->waitTillPresent($this->username_selector);

        $this->exts->type_key_by_xdotool("Ctrl+t");
        sleep(13);

        $this->exts->type_key_by_xdotool("F5");

        sleep(5);

        $this->exts->type_text_by_xdotool($this->loginUrl);
        $this->exts->type_key_by_xdotool("Return");
        sleep(15);
        for ($i = 0; $i < 13; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
        }
        $this->exts->log("Enter Username");
        $this->exts->type_text_by_xdotool($this->username);
        sleep(2);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->log("Enter Password");
        $this->exts->type_text_by_xdotool($this->password);
        sleep(1);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool("Return");
        sleep(15);
    }

    private function processInvoices($pageCount = 0)
    {
        sleep(25);

        $this->exts->capture("4-invoices-page");
        $invoices = [];
        #main > ol
        $invoice_table = $this->exts->getElement('main#main');
        $rows = $this->exts->getElements('ol > li', $invoice_table);
        foreach ($rows as $row) {
            if ($this->exts->getElement("div.list-item a[href*='/invoices/']", $row) != null) {
                $invoiceUrl = $this->exts->getElement("div.list-item a[href*='/invoices/']", $row)->getAttribute("href");
                $invoiceID = explode('/', end(explode('/invoices/', $invoiceUrl)))[0];

                $invoiceName = $this->exts->getElement("div.list-item a[href*='/invoices/']", $row)->getAttribute("innerText");
                $invoiceDate = $this->exts->getElement("#invoice" . $invoiceID . " > div:nth-child(3)", $row)->getAttribute("innerText");
                $invoiceAmount1 = $this->exts->getElement("#invoice" . $invoiceID . " > div:nth-child(5)", $row)->getAttribute("innerText");
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount1)) . ' EUR';
                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl . ".pdf"
                ));
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        sleep(5);
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $this->isNoInvoice = false;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if (
                $restrictPages == 0 &&
                $pageCount < 50 &&
                $this->exts->getElement('a#next-page-link[href*="/invoices?filte"]') != null
            ) {
                $pageCount++;
                $this->exts->moveToElementAndClick('a#next-page-link[href*="/invoices?filte"]');
                sleep(5);
                $this->processInvoices($pageCount);
            }
        }
    }

    private function processBilling()
    {
        sleep(25);

        $this->exts->capture("4-billing-page");
        $invoices = [];
        #main > ol
        $invoice_table = $this->exts->getElement('main#main');
        $rows = $this->exts->getElements('table tbody tr', $invoice_table);
        foreach ($rows as $row) {
            if ($row->getAttribute('data-href') != null) {
                $invoiceUrl = $row->getAttribute("data-href");
                $cols = $this->exts->getElements("td", $row);
                $invoiceID = $cols[2]->getAttribute("innerText");

                $invoiceName = $cols[2]->getAttribute("innerText");
                $invoiceDate = $cols[0]->getAttribute("innerText");
                $invoiceAmount1 = $cols[1]->getAttribute("innerText");
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount1)) . ' EUR';
                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        $newTab =  $this->exts->openNewTab();
        sleep(5);
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(10);

            $downloaded_file = $this->exts->click_and_download('#actions [href*="quadernoapp.com/invoice/"][href*=".pdf"]', 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $this->isNoInvoice = false;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
        $this->exts->closeTab($newTab);
        sleep(4);
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
