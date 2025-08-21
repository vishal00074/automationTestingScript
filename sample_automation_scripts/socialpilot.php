<?php // handle empty invoice case updated billing history xpath added logs for invoiceName, invoiceAmount, invoiceDate

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

    // Server-Portal-ID: 29597 - Last modified: 07.07.2025 15:13:00 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://app.socialpilot.co/launchpad';
    public $username_selector = 'input#companyloginform-email , input[name="email"]';
    public $password_selector = 'input#companyloginform-password , input[name="password"]';
    public $submit_login_selector = 'button[type="submit"]';
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
        sleep(7);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->isLoggedin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->baseUrl);
            sleep(8);
            $this->checkFillLogin();
            sleep(20);
        }

        if ($this->isLoggedin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->exts->openUrl('https://my.socialpilot.co/setting/subscriptions');
            sleep(10);
            $this->exts->click_element('.//*[contains(text(), "Rechnungshistorie") or contains(text(), "billing history")]');
            sleep(2);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->exists('a.exiper_upgrade_btn') && $this->exts->urlContains('users/lock')) {
                $this->exts->log("Your account has been locked.");
                $this->exts->account_not_ready();
            } else {
                if (stripos($this->exts->extract('.signin-container  .invalid-feedback'), "couldn't recognize that login information") !== false) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        }
    }
    private function checkFillLogin()
    {
        if ($this->exts->querySelector($this->password_selector) != null) {
            sleep(1);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    private function isLoggedin()
    {
        return $this->exts->querySelector('.notification-icons-header');
    }

    private function processInvoices()
    {
        if ($this->exts->exists('#cb-frame[src*="billing.socialpilot"]')) {
            $invoice_iFrame = $this->exts->makeFrameExecutable('#cb-frame[src*="billing.socialpilot"]');
        }

        if (isset($invoice_iFrame)) {
            for ($w = 0; $w < 15; $w++) {
                $billing_button = $invoice_iFrame->querySelector('.billing-history-link');
                if ($billing_button != null) {
                    try {
                        $billing_button->click();
                    } catch (\Exception $exception) {
                        $invoice_iFrame->execute_javascript("arguments[0].click()", [$billing_button]);
                    }
                    break;
                }
                sleep(1);
            }
            sleep(7);
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if ($restrictPages == 0) {
                $this->exts->log('Trying to scroll to bottom');
                for ($i = 0; $i < 5; $i++) {
                    $invoice_iFrame->execute_javascript('
                        var scrollBar = document.querySelector("#billing_history");
                        scrollBar.scrollTop = scrollBar.scrollHeight;
                    ');
                    sleep(5);
                }
            }
            $this->exts->capture("4-invoices-page");

            $rows_len = count($invoice_iFrame->querySelectorAll('div.cb-invoice'));

            $this->exts->log('Two Rows: ' . $rows_len);

            for ($i = 0; $i < $rows_len; $i++) {
                $row = $invoice_iFrame->querySelectorAll('div.cb-invoice')[$i];
                $download_button = $invoice_iFrame->querySelector('div[data-cb-id="download_invoice"]', $row);
                if ($download_button != null) {
                    $this->isNoInvoice = false;
                    $invoiceName = preg_replace('/[\s\,]/', '', trim($invoice_iFrame->extract('span.cb-invoice__text', $row, 'innerText')));
                    $invoiceDate = trim($invoice_iFrame->extract('span.cb-invoice__text', $row, 'innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoice_iFrame->extract('span.cb-invoice__price', $row, 'innerText'))) . ' USD';
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $invoiceDate = $this->exts->parse_date($invoiceDate, 'M d, Y', 'Y-m-d');
                    $this->exts->log('Date parsed: ' . $invoiceDate);


                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                    if ($this->exts->document_exists($invoiceFileName)) {
                        $this->exts->log('Invoice Existed ' . $invoiceName);
                        continue;
                    }

                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $invoice_iFrame->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
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
