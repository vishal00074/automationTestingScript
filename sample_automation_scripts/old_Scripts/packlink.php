<?php // migrated and handle empty invoice name increase sleep time  after open login url took time to load added sleep time in checkFillLogin

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
    // Server-Portal-ID: 14252 - Last modified: 02.08.2023 05:28:40 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://pro.packlink.de';
    public $loginUrl = 'https://pro.packlink.de/login';
    public $invoicePageUrl = 'https://pro.packlink.de/private/settings/billing/invoices';

    public $username_selector = 'input#login-email, input[name="email"]';
    public $password_selector = 'input#login-password, input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form#login-form button#login-submit , button[type="submit"]';

    public $check_login_success_selector = 'a[href*="logout"], .app-header-menu a.app-header-menu__item [href*="cog--light"], .app-header-menu a.app-header-menu__item[role="button"][title="Einstellungen"], .app-header-menu a.app-header-menu__item [class*="COG_LIGHT"], span[data-id="ICON-SETTINGS"]';

    public $isNoInvoice = true;
    public $restrictPages = 3;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

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
            // $this->exts->openUrl($this->loginUrl);
            if ($this->exts->exists('#navbarSupportedContent a[href*="login"]')) {
                $this->exts->moveToElementAndClick('#navbarSupportedContent a[href*="login"]');
            } else if ($this->exts->exists('a[href="https://pro.packlink.de/private"]')) {
                $this->exts->moveToElementAndClick('a[href="https://pro.packlink.de/private"]');
            } else {
                $this->exts->openUrl($this->loginUrl);
            }
            sleep(20);
            if (!$this->exts->exists('div#gatsby-focus-wrapper [role="main"]') && !$this->exts->exists($this->password_selector)) {
                $this->clearChrome();
                $this->exts->openUrl($this->baseUrl);
                sleep(10);
                if ($this->exts->exists('#navbarSupportedContent a[href*="login"]')) {
                    $this->exts->moveToElementAndClick('#navbarSupportedContent a[href*="login"]');
                } else if ($this->exts->exists('a[href="https://pro.packlink.de/private"]')) {
                    $this->exts->moveToElementAndClick('a[href="https://pro.packlink.de/private"]');
                } else {
                    $this->exts->openUrl($this->loginUrl);
                }
            }
            // click cookies button
            if ($this->exts->exists('button#didomi-notice-agree-button')) {
                $this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
                sleep(5);
            }
            $this->checkFillLogin();
            sleep(40);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null || $this->exts->exists('.shipment-list-sidebar__inboxes li[data-inbox="ALL"]')) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (
                strpos($this->exts->extract('.authentication form .notification--error'), 'Falsche Anmeldedaten') !== false ||
                strpos($this->exts->extract('article h3'), 'Falsche Anmeldedaten') !== false
            ) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        sleep(10);
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
            $this->exts->executeSafeScript('window.alert = null;window.confirm = null;');
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Tab');
        $this->exts->type_key_by_xdotool('Return');
        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 6; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    private function processInvoices($paging_count = 1)
    {
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        if ($this->exts->exists('table.eb-invoices-table > tbody > tr')) {
            $rows = $this->exts->getElements('table.eb-invoices-table > tbody > tr');
            foreach ($rows as $row) {
                $tags = $this->exts->getElements('td', $row);
                if (count($tags) >= 4 && $this->exts->getElement('a[ng-click*="downloadInvoice"]', $row) != null) {
                    $download_button = $this->exts->getElement('a[ng-click*="downloadInvoice"]', $row);
                    $invoiceName = trim($tags[0]->getAttribute('innerText'));
                    $invoiceDate = trim(explode(' ', $tags[1]->getAttribute('innerText'))[0]);
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'download_button' => $download_button
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

                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                if ($this->exts->document_exists($invoiceFileName)) {
                    continue;
                }

                try {
                    $this->exts->log('Click download button');
                    $invoice['download_button']->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$invoice['download_button']]);
                }
                sleep(15);

                $this->exts->wait_and_check_download('pdf');

                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                sleep(1);

                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->log("create file");
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                sleep(5);
            }

            if ($this->restrictPages == 0 && $paging_count < 50 && $this->exts->exists('li.pagination-next:not(.disabled) a')) {
                $paging_count++;
                $this->exts->moveToElementAndClick('li.pagination-next:not(.disabled) a');
                sleep(5);

                $this->processInvoices($paging_count);
            }
        } else {
            $rows = $this->exts->getElements('div[data-id="invoices-table"] table tbody tr');
            foreach ($rows as $row) {
                $tags = $this->exts->getElements('td', $row);
                if (count($tags) >= 5 && $this->exts->getElement('button[title*="PDF"]', $row) != null) {
                    $download_button = $this->exts->getElement('button[title*="PDF"]', $row);
                    $invoiceName = trim($tags[1]->getAttribute('innerText'));
                    $invoiceDate = trim(explode(' ', $tags[2]->getAttribute('innerText'))[0]);
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'download_button' => $download_button
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

                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                if ($this->exts->document_exists($invoiceFileName)) {
                    continue;
                }

                try {
                    $this->exts->log('Click download button');
                    $invoice['download_button']->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$invoice['download_button']]);
                }
                sleep(15);

                $this->exts->wait_and_check_download('pdf');

                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                sleep(1);

                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->log("create file");
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                sleep(5);
            }

            if ($this->restrictPages == 0 && $paging_count < 50 && $this->exts->exists('button:not([disabled]) span[data-id="ICON-CHEVRON_RIGHT"]')) {
                $paging_count++;
                $this->exts->moveToElementAndClick('button:not([disabled]) span[data-id="ICON-CHEVRON_RIGHT"]');
                sleep(5);

                $this->processInvoices($paging_count);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
