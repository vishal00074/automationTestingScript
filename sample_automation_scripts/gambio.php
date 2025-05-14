<?php // migrated the script on reomte chrome and handle empty inovice name condition

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

    // Server-Portal-ID: 2205 - Last modified: 17.07.2024 14:31:54 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.gambio-support.de/';
    public $loginUrl = 'https://www.gambio-support.de/';
    public $invoicePageUrl = 'https://account.gambiocloud.com/de/invoices';

    public $username_selector = 'input#email';
    public $password_selector = 'input#password';
    public $remember_me_selector = 'div.login form label[for="remember-me"]';
    public $submit_login_selector = 'div.login form button[type="submit"]';

    public $shop_username_selector = 'input[name="email_address"]';
    public $shop_password_selector = 'input[name="password"]';
    public $shop_submit_login_selector = 'form[name="login"] button[type="submit"], .dropdown-menu-login form input[type="submit"]';

    public $check_login_failed_selector = 'div.alert-danger';
    public $check_login_success_selector = 'a[href*="/logout"], a[href*="/logoff.php"]';

    public $restrictPages = 3;
    public $shopUrl = '';
    public $sales_invoice = 0;
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : $this->restrictPages;
        $this->shopUrl = isset($this->exts->config_array["shop_url"]) ? @$this->exts->config_array["shop_url"] : $this->shopUrl;
        $this->exts->log('Shop Url - ' . $this->shopUrl);
        $this->sales_invoice = isset($this->exts->config_array["sales_invoice"]) ? (int)@$this->exts->config_array["sales_invoice"] : $this->sales_invoice;
        $this->exts->log('sales_invoice - ' . $this->sales_invoice);

        if (!empty($this->shopUrl) && trim($this->shopUrl) != '') {
            $this->exts->openUrl($this->shopUrl);
            sleep(1);

            // Load cookies
            $this->exts->loadCookiesFromFile();
            sleep(1);
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);
            $this->exts->capture('1-init-page');

            // If user hase not logged in from cookie, clear cookie, open the login url and do login
            if ($this->exts->getElement($this->check_login_success_selector) == null) {
                $this->exts->log('NOT logged via cookie');
                $this->exts->openUrl($this->shopUrl);
                sleep(15);
                $this->exts->capture('shopurl-page');

                if (!$this->exts->exists($this->shop_username_selector) && !$this->exts->exists($this->shop_password_selector)) {
                    $this->exts->openUrl($this->baseUrl);
                    sleep(15);

                    $this->checkFillLogin();
                    sleep(20);
                }

                $this->checkFillShopLogin();
                sleep(20);
            }
        } else {
            $this->exts->openUrl($this->baseUrl);
            sleep(1);

            // Load cookies
            $this->exts->loadCookiesFromFile();
            sleep(1);
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
            $this->exts->capture('1-init-page');

            // If user hase not logged in from cookie, clear cookie, open the login url and do login
            if ($this->exts->getElement($this->check_login_success_selector) == null) {
                $this->exts->log('NOT logged via cookie');
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
                $this->checkFillLogin();
                sleep(20);
            }
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if (!empty($this->shopUrl) && trim($this->shopUrl) != '') {

                if ((int)$this->sales_invoice == 1) {
                    $this->exts->moveToElementAndClick('li.gambio-admin');
                    sleep(20);
                    $this->exts->moveToElementAndClick('a[href*="admin.php?do=InvoicesOverview"]');
                    sleep(15);

                    $this->processSalesInvoice();
                    sleep(5);
                }
                if ($this->exts->exists('a[href*="/redirect_to_customer_portal.php"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/redirect_to_customer_portal.php"]');
                    sleep(10);

                    if ($this->exts->getElement($this->shop_password_selector) != null) {
                        $this->checkFillShopLogin();
                        sleep(10);
                    }
                }
            }

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);

            if ($this->exts->getElement($this->check_login_failed_selector) != null && (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Du hast keine Berechtigung') !== false)) {
                $this->exts->no_permission();
            } else {
                if ($this->exts->exists('button[data-context="YES"]')) {
                    $this->exts->moveToElementAndClick('button[data-context="YES"]');
                    sleep(1);
                }

                if (strpos($this->exts->getUrl(), 'gambio-support.de/invoices') !== false) {
                    $this->processInvoicesSupport();
                } else {
                    $this->processInvoices();
                }

                // Final, check no invoice
                if ($this->isNoInvoice) {
                    $this->exts->no_invoice();
                }
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->getElement($this->check_login_failed_selector) != null && (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'E-Mail-Adresse oder Passwort ist falsch') !== false || stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Kein User mit dieser Email gefunden') !== false)) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
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
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function checkFillShopLogin()
    {
        if ($this->exts->getElement($this->shop_password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->shop_username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->shop_password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->shop_submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoicesSupport()
    {
        sleep(25);

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('div.content_inside table tbody tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->getElement('a[href*="/invoices/pdf"]', $tags[5]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/invoices/pdf"]', $tags[5])->getAttribute("href");
                $invoiceName = trim($tags[0]->getAttribute('innerText'));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

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

    private function processInvoices()
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table[class="table table-striped"] tbody tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('a[href*="invoices/view"]', $tags[4]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="invoices/view"]', $tags[4])->getAttribute("href");
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

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
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(8);
            if (!$this->exts->exists('a[href*="invoices/pdf"]')) {
                sleep(5);
            }
            if ($this->exts->exists('a[href*="invoices/pdf"]')) {
                $invoice['invoiceUrl'] = $this->exts->getElement('a[href*="invoices/pdf"]')->getAttribute("href");
                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    public function processSalesInvoice($pageCount = 1)
    {
        sleep(25);

        $this->exts->capture("4-sales-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table[class*="table"] tbody tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('a[href*="request_port.php?module=OrderAdmin&action=downloadPdf&type=invoice"]', $row) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="request_port.php?module=OrderAdmin&action=downloadPdf&type=invoice"]', $row)->getAttribute("href");
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceDate = trim($tags[2]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

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
            $date_parsed = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            if ($date_parsed == '') {
                $date_parsed = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.y - H:i', 'Y-m-d');
            }
            $this->exts->log('Date parsed: ' . $date_parsed);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $date_parsed, $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        if ($this->restrictPages == 0 && $this->exts->exists('thead:not([class*="-helper"]) .page-navigation .pull-right button.next:not([disabled])') && $pageCount < 10) {
            $this->exts->moveToElementAndClick('thead:not([class*="-helper"]) .page-navigation .pull-right button.next:not([disabled])');
            sleep(5);
            $pageCount++;

            $this->processSalesInvoice($pageCount);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
