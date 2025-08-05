<?php // migrate and added added condition to handle empty invoices and close cookies button after login and i have added limit download only 50 invoices if restrictPages != 0 

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

    // Server-Portal-ID: 3138 - Last modified: 02.08.2023 05:28:40 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://www.iloxx.de/myiloxx/orders/invoices.aspx';

    public $username_selector = '#ContentPlaceHolder1_txtLogin';
    public $password_selector = '#ContentPlaceHolder1_txtPassword';
    public $submit_login_selector = '#ContentPlaceHolder1_btnLogin';

    public $check_login_failed_selector = '#ContentPlaceHolder1_labErrors';
    public $check_login_success_selector = '#divUserLoginImage, a#btnLogout';

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
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            if ($this->exts->exists('button[data-cf-action="accept"]')) {
                $this->exts->moveToElementAndClick('button[data-cf-action="accept"]');
                sleep(5);
            }
            if ($this->exts->exists('a#btnFlatrateLayer_Close')) {
                $this->exts->moveToElementAndClick('a#btnFlatrateLayer_Close');
                sleep(5);
            }
            $this->checkFillLogin();
            sleep(20);
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            if ($this->exts->querySelector('a[id*="btnTrustedShopsConsentLayerClose_X"]') != null) {
                $this->exts->moveToElementAndClick('a[id*="btnTrustedShopsConsentLayerClose_X"]');
                sleep(2);
            }
            $this->exts->openUrl($this->baseUrl);
            sleep(15);

            //sometimes login screen is appearing
            if ($this->exts->getElement($this->password_selector) != null) {
                $this->checkFillLogin();
                sleep(20);
                $this->exts->openUrl($this->baseUrl);
                sleep(15);
            }
            if ($this->exts->querySelector('a[id*="btnTrustedShopsConsentLayerClose_X"]') != null) {
                $this->exts->moveToElementAndClick('a[id*="btnTrustedShopsConsentLayerClose_X"]');
                sleep(2);
            }

            $this->processInvoicesNew();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->exitSuccess();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
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

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }


    public $totalInvoices = 0;

    private function processInvoicesNew($pageCount = 1)
    {
        sleep(25);
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log("restrictPages:: " . $restrictPages);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('#tblAddresses tbody tr');
        foreach ($rows as $key => $row) {
            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }
            $row = $this->exts->getElements('#tblAddresses tbody tr')[$key];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 3 && $this->exts->getElement('a[id*="btnNewBill"]', $row) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('a[id*="btnNewBill"]', $row);
                $invoiceName = trim($tags[0]->getAttribute('innerText'));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName  . '.pdf' : '';


                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                $parsed_date = is_null($invoiceDate) ? null : $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                if (empty($parsed_date)) {
                    $parsed_date = is_null($invoiceDate) ? null : $this->exts->parse_date($invoiceDate, 'F d, Y', 'Y-m-d');
                }
                $invoiceAmount = null;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
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
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        sleep(1);
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
