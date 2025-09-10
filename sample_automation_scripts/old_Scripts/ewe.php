<?php // updated download code added code to load more invoices  update download button selector 
//
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

    // Server-Portal-ID: 9248 - Last modified: 12.08.2025 15:14:31 UTC - User: 1

    public $baseUrl = 'https://mein.ewe.de/ewetelcss/secure/billingOverview.xhtml';
    public $loginUrl = 'https://mein.ewe.de/ewetelcss/secure/billingOverview.xhtml';
    public $invoiceECarePageUrl = 'https://tkgk.mein.ewe.de/eCare/billing';
    public $invoicePageUrl = 'https://mein.ewe.de/ewetelcss/secure/billingOverview.xhtml';
    public $username_selector = 'div#formLogin input#username';
    public $password_selector = 'div#formLogin input#password';
    public $submit_login_selector = 'div#formLogin button[type="submit"]';
    public $check_login_failed_selector = 'form#frm_login .css__errorbubble, #error_INVALID';
    public $check_login_e_care_success_selector = 'button[class*="_header-ecare_btnLogout"]';
    public $check_login_success_selector = 'a[id="logoutLink"]';
    public $isNoInvoice = true;
    public $err_msg = '';
    public $metadataError = 'fieldset';
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->loadCookiesFromFile();
        // Load cookies
        sleep(20);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        $this->exts->waitTillAnyPresent([$this->check_login_e_care_success_selector, $this->check_login_success_selector], 20);
        if ($this->exts->querySelector($this->check_login_e_care_success_selector) == null || $this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
                $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
                sleep(5);
            }
            $this->checkFillLogin();
            sleep(10);
        }

        sleep(20);
        $this->exts->waitTillAnyPresent([$this->check_login_e_care_success_selector, $this->check_login_success_selector], 20);
        if ($this->exts->querySelector($this->check_login_e_care_success_selector) != null || $this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            //Open invoices url and download invoice
            if ($this->exts->querySelector($this->check_login_e_care_success_selector) != null) {
                $this->exts->openUrl($this->invoiceECarePageUrl);
                $this->processECareInvoices();
            } else {
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
            if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'die eingegebenen zugangsdaten sind nicht korrekt') !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos($this->err_msg, 'Die eingegebenen Zugangsdaten sind nicht korrekt.') !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos($this->exts->extract($this->metadataError, null, 'innerText'), 'Metadata not found') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector, 20);
        if ($this->exts->querySelector($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(2);

            if ($this->exts->exists($this->check_login_failed_selector, 15)) {
                $this->err_msg = $this->exts->extract($this->check_login_failed_selector, null, 'innerText');
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processECareInvoices($count = 1)
    {
        sleep(25);

        $this->exts->log('Begin Process Invoices');
        if ($this->exts->querySelector('div[class*="ecare-recent-bills-box_ecare-recent-bills-box"]  div[class*="content-box_bottom-link"]') != null) {
            $this->exts->execute_javascript("document.querySelector(\"div[class*='ecare-recent-bills-box_ecare-recent-bills-box'] div[class*='content-box_bottom-link'] a\").click();");
            sleep(5);
        }

        $this->exts->waitTillPresent('table > tbody > tr', 20);
        $this->exts->capture("1 invoice page");
        $rows = $this->exts->querySelectorAll('table > tbody > tr');

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true;

        $maxInvoices = 5;
        $invoiceCount = 0;

        foreach ($rows as $row) {
            $this->isNoInvoice = false;
            $invoiceName = $this->exts->extract('td:nth-child(6)', $row);
            $invoiceDate = $row->querySelector('td:nth-child(5)');
            if ($invoiceDate != null) {
                $invoiceDateText = $invoiceDate->getText();
                $invoiceDate = $this->exts->parse_date($invoiceDateText, '', 'Y-m-d');
            }

            $amount = $row->querySelector('td:nth-child(3)');
            if ($amount != null) {
                $amount = $amount->getText();
            }

            $downloadBtn = $this->exts->querySelector('td:nth-child(8) a', $row);

            if ($downloadBtn != null) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('amount: ' . $amount);

                $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoiceName);
                sleep(2);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName,  $invoiceDate, $amount, $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }

            $lastDate = !empty($invoiceDate) && $invoiceDate <= $restrictDate;

            if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                break;
            } else if ($restrictPages == 0 && $dateRestriction && $lastDate) {
                break;
            }
        }
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(15);
        $this->exts->log('Begin Process Invoices');
        $this->exts->waitTillPresent('table.tableBorder > tbody > tr', 20);
        $this->exts->capture("1 invoice page");
        $rows = $this->exts->querySelectorAll('table.tableBorder > tbody > tr');

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true;

        $maxInvoices = 5;
        $invoiceCount = 0;

        foreach ($rows as $row) {
            $this->isNoInvoice = false;

            $invoiceDate = $row->querySelector('td:nth-child(3)');
            if ($invoiceDate != null) {
                $invoiceDateText = $invoiceDate->getText();
                $invoiceDate = $this->exts->parse_date($invoiceDateText, '', 'Y-m-d');
            }

            $amount = $row->querySelector('td:nth-child(6)');
            if ($amount != null) {
                $amount = $amount->getText();
            }

            $downloadBtn = $row->querySelector('td:nth-child(7) a');

            if ($downloadBtn != null) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('amount: ' . $amount);

                $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoiceName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $amount, $invoiceFileName);
                    sleep(1);
                    $invoiceCount++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }

            $lastDate = !empty($invoiceDate) && $invoiceDate <= $restrictDate;

            if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                break;
            } else if ($restrictPages == 0 && $dateRestriction && $lastDate) {
                break;
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$portal = new PortalScriptCDP("optimized-chrome-v2", 'grover business', '2673335', 'Y2hyaXN0b3BoQGJsYWNrY2FiaW4uZGU=', 'NTAwNlRpZ2VyMjAyMCE=');
$portal->run();
