<?php // handle empty invoiceName in download code updated pagiantion logic increase 2 second after click on invoice in download code

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

    // Server-Portal-ID: 66577 - Last modified: 24.02.2025 14:40:18 UTC - User: 1

    public $baseUrl = 'https://flottenkarte.jet-tankstellen.de/default.ixsp';
    public $loginUrl = 'https://flottenkarte.jet-tankstellen.de/default.ixsp';
    public $invoicePageUrl = 'https://flottenkarte.jet-tankstellen.de/default.ixsp';

    public $username_selector = 'form#ID_frmLogin input[name="fr_LoginName"], form[name="frmLogin"] input[name="fr_LoginName"]';
    public $password_selector = 'form#ID_frmLogin input[name="fr_Password"], form[name="frmLogin"] input[name="fr_Password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form#ID_frmLogin [type="submit"], form[name="frmLogin"] [type="submit"]';

    public $check_login_failed_selector = 'div.Login_Error, .Login_InfoBox.InfoBox_Error .InfoBoxContent span.text';
    public $check_login_success_selector = 'a[id*="ID_Logout"]';
    public $user_customer_number = "";

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->user_customer_number = isset($this->exts->config_array['customer_number']) ? trim($this->exts->config_array['customer_number']) : "";

        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        if ($this->exts->exists('.uc-btn-accept-wrapper button#uc-btn-accept-banner')) {
            $this->exts->moveToElementAndClick('.uc-btn-accept-wrapper button#uc-btn-accept-banner');
            sleep(5);
        }
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('.uc-btn-accept-wrapper button#uc-btn-accept-banner')) {
                $this->exts->moveToElementAndClick('.uc-btn-accept-wrapper button#uc-btn-accept-banner');
                sleep(5);
            }
            $this->checkFillLogin();
            sleep(20);
            if ($this->exts->type_key_by_xdotool('Return')) {
                $this->exts->log("accept Alert");
                sleep(10);
            }
        }

        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);
            $this->exts->moveToElementAndClick('button#uc-btn-accept-banner');
            sleep(5);
            // Loop through all layout block, check if invoices block, click "All" button to show all invoices
            $blocks = count($this->exts->querySelectorAll('div.Container_Content'));
            for ($i = 0; $i < $blocks; $i++) {
                $block = $this->exts->querySelectorAll('div.Container_Content')[$i];
                $check_block_invoice = $this->exts->querySelectorAll('#table-record a[data-onclick*="setInvoiceLineParam"]', $block);
                $title_block = $this->exts->extract('.Text_Headline', $block, 'innerText');
                if ($check_block_invoice != null || strpos(strtolower($title_block), 'rechnungen') !== false) {
                    $this->exts->moveToElementAndClick('input[value="Alle"]');
                    sleep(10);
                    break;
                }
            }
            // click "ALL" again if can not load invoice page
            if (!$this->exts->exists('table.Table_Standard tbody tr a[onclick*="setInvoice"], a [alt*="Rechnung"]')) {
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(15);
                // Loop through all layout block, check if invoices block, click "All" button to show all invoices
                $blocks = count($this->exts->querySelectorAll('div.Container_Content'));
                for ($i = 0; $i < $blocks; $i++) {
                    $block = $this->exts->querySelectorAll('div.Container_Content')[$i];
                    $check_block_invoice = $this->exts->querySelectorAll('#table-record a[data-onclick*="setInvoiceLineParam"]', $block);
                    $title_block = $this->exts->extract('.Text_Headline', $block, 'innerText');
                    if ($check_block_invoice != null || strpos(strtolower($title_block), 'rechnungen') !== false) {
                        $this->exts->moveToElementAndClick('input[value="Alle"]');
                        sleep(15);
                        break;
                    }
                }
            }
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
                if (
                    strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'your username/password combination is not valid') !== false ||
                    strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'ihre benutzername/passwortkombination ist nicht') !== false
                ) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->querySelector($this->password_selector) != null) {
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

    private function processInvoices($paging_count = 1)
    {
        sleep(25);

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $check_customer_number = [];
        if (!empty($this->user_customer_number)) $check_customer_number = explode(",", $this->user_customer_number);
        $rows = count($this->exts->querySelectorAll('table.Table_Standard tbody tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->querySelectorAll('table.Table_Standard tbody tr')[$i];
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 7 && $this->exts->querySelector('a[href*="qs_DocRecId"]', $tags[6]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->querySelector('a[href*="qs_DocRecId"]', $tags[6]);
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' USD';

                $customer_number = trim($tags[2]->getAttribute('innerText'));
                if (!empty($customer_number) && !empty($check_customer_number)) {
                    if (!in_array($customer_number, $check_customer_number)) {
                        continue;
                    }
                }
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

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
                    sleep(7);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $pagiantionSelector = 'li.next a';
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
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
