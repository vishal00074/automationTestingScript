<?php // migrated

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

    // Server-Portal-ID: 47981 - Last modified: 06.02.2024 13:53:05 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'http://www.urbansportsclub.com';
    public $loginUrl = 'https://urbansportsclub.com/login';
    public $invoicePageUrl = 'https://urbansportsclub.com/en/profile/payment-history';

    public $username_selector = '.container form#login-form input#email';
    public $password_selector = '.container form#login-form input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = '.container form#login-form input.btn-lg';

    public $check_login_failed_selector = '.container form#login-form .alert-danger';
    public $check_login_success_selector = '.smm-navmenu a[href*="/logout"]';

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
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        // after load cookies and open base url, check if user logged in
        // Wait for selector that make sure user logged in
        sleep(5);
        $this->checkAndLogin();
    }

    private function checkAndLogin()
    {
        sleep(5);
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(2);
        }
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, open the login url and wait for login form
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->getElement($this->password_selector) != null) {
                sleep(3);
                $this->exts->capture("2-login-page");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->getElement($this->remember_me_selector) != null)
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(2);

                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(20);
            } else {
                $this->exts->log('Login page not found');
                $this->exts->loginFailure();
            }
        }
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log('User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log('Timeout waitForLogin');
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(25);
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(2);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('.smm-payment-history__table .smm-payment-history__table-row');
        if (count($rows) > 0) {
            foreach ($rows as $key => $row) {
                $tags = $this->exts->getElements('.smm-payment-history__text', $row);
                if (count($tags) >= 4 && trim($tags[0]->getAttribute('innerText')) != '') {
                    $invoiceSelector = $tags[4];
                    $invoiceName = trim($tags[0]->getAttribute('innerText'));
                    $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getText())) . ' €';
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    //$this->exts->log('invoiceSelector: '.$invoiceSelector);
                    if ($this->exts->getElement('a', $invoiceSelector) == null) {
                        continue;
                    }
                    $this->isNoInvoice = false;
                    if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $downloadIcon = $this->exts->getElement('a', $invoiceSelector);
                        try {
                            $this->exts->log('Click download button');

                            $downloadIcon->click();

                            sleep(10);
                            sleep(2);
                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);



                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                                sleep(3);
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                            }
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$downloadIcon]);
                        }
                    }
                }
            }
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 5;
            if (
                $restrictPages == 0 &&
                $paging_count < 10 &&
                $this->exts->getElement('div[class="smm-pagination row"][style="display: block;"] > .smm-simple-button') != null
            ) {
                $paging_count++;
                $paginateButton = $this->exts->getElement('div[class="smm-pagination row"][style="display: block;"] > .smm-simple-button');
                $paginateButton->click();
                sleep(5);
                $this->processInvoices($paging_count);
            } else if (
                $restrictPages != 0 &&
                $paging_count < $restrictPages &&
                $this->exts->getElement('div[class="smm-pagination row"][style="display: block;"] > .smm-simple-button') != null
            ) {

                $paging_count++;
                $paginateButton = $this->exts->getElement('div[class="smm-pagination row"][style="display: block;"] > .smm-simple-button');
                $paginateButton->click();
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
