<?php // failed due to Connection reset  added pagiantion code based on restrict page 
// uncommented loadCookiesFromFile added code to get invoice name from basename of download file if invoiceFileName is empty and added code to extract name from ui
// added code to trigger loginfailedConfirmed in case wrong credntials
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

    // Server-Portal-ID: 417 - Last modified: 12.08.2025 11:07:47 UTC - User: 15

    public $baseUrl = 'https://www.otto-office.com/de/app/account/statement/main';
    public $loginUrl = 'https://www.otto-office.com/de/app/account/statement/main';
    public $invoicePageUrl = 'https://www.otto-office.com/de/app/account/statement/main';
    public $username_selector = 'input[name*="login[login]"]';
    public $password_selector = 'input[name*="login[password]"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form#anmelden button[type="submit"]';
    public $check_login_failed_selector = 'span.oo-alert-text';
    public $check_login_success_selector = 'li[role="menuitem"] > a[href*="logout"]';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        $this->exts->clearCookies();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        if ($this->exts->exists('button#oo-overlay-close')) {
            $this->exts->moveToElementAndClick('button#oo-overlay-close');
        }

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);

            if ($this->exts->exists('button#oo-overlay-close')) {
                $this->exts->moveToElementAndClick('button#oo-overlay-close');
            }

            if ($this->exts->exists('div#top-notification_accept-machmichweg-guidelines button')) {
                $this->exts->moveToElementAndClick('div#top-notification_accept-machmichweg-guidelines button');
                sleep(3);
            }
            sleep(5);
            if ($this->exts->exists('button.close-accept')) {
                $this->exts->click_element('button.close-accept');
            }

            sleep(5);
            if ($this->exts->exists('button[id*="overlay-close"]')) {
                $this->exts->click_element('button[id*="overlay-close"]');
            }
            sleep(10);
            $this->checkFillLogin();
            sleep(20);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            if ($this->exts->exists('div#overlay_login img')) {
                $this->exts->moveToElementAndClick('div#overlay_login img');
                sleep(3);
            }
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->getElement($this->password_selector) != null) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                if ($this->remember_me_selector != '')
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(2);

                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(15);
            }
            sleep(10);
            if ($this->exts->exists("[id*='navigation-left'] ul li:nth-child(2) a[href*='account/statement/']")) {
                $this->exts->click_element('[id*="navigation-left"] ul li:nth-child(2) a[href*="account/statement/"]');
            }

            // Open invoices url and download invoice
            // $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('anmeldeproblemen die passwor')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->exists('div#top-notification_accept-machmichweg-guidelines button')) {
            $this->exts->moveToElementAndClick('div#top-notification_accept-machmichweg-guidelines button');
            sleep(5);
        }
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(10);

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

    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('div.meinebestellungen-info div#statement-list > div div.rounded-md', 40);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div.meinebestellungen-info div#statement-list > div div.rounded-md');
        $this->exts->log('Total No of Rows : ' . count($rows));
        foreach ($rows as $row) {
            if ($this->exts->querySelector('div:nth-child(3) a.textlinksmall[title*="PDF"]', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('div:nth-child(2) a', $row);;
                $invoiceAmount =  '';
                $invoiceDate =  $this->exts->extract('div:nth-child(1)', $row);

                $downloadBtn = $this->exts->querySelector('div:nth-child(3) a.textlinksmall[title*="PDF"]', $row);

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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $this->exts->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                if (empty($invoiceFileName)) {
                    $invoiceFileName = basename($downloaded_file);
                    $invoice['invoiceName'] = basename($downloaded_file, '.pdf');
                    $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                }

                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
        sleep(5);
        $this->exts->switchToOldestActiveTab();
        sleep(4);
        $this->exts->closeAllTabsExcept();
        sleep(4);

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $pagiantionSelector = 'a.pager-arrow-right';
        if ($restrictPages == 0) {
            if ($paging_count < 50 && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->moveToElementAndClick($pagiantionSelector);
                sleep(7);
                $paging_count++;
                $this->processInvoices($paging_count);
            }
        } else {
            if ($paging_count < $restrictPages && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->moveToElementAndClick($pagiantionSelector);
                sleep(7);
                $paging_count++;
                $this->processInvoices($paging_count);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP("optimized-chrome-v2", 'Avaza', '2673526', 'bHVkd2lnQHN1cnZleWVuZ2luZS5jb20=', 'cHNDZms4Ny4=');
$portal->run();
