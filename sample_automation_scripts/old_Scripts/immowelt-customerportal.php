<?php // migrated nad handle invoices name upated lginfailedConfirmed selector added code to restrict invoices updated download click to download button functionaltiy

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


    // Server-Portal-ID: 839163 - Last modified: 08.03.2024 13:24:34 UTC - User: 1

    public $baseUrl = 'https://immowelt-customerportal.de/Pages/invoiceportal/default.aspx';
    public $loginUrl = 'https://immowelt-customerportal.de/Pages/invoiceportal/default.aspx';
    public $invoicePageUrl = 'https://immowelt-customerportal.de/Pages/invoiceportal/default.aspx';

    public $username_selector = 'div[class*="signin-"] input[id*="_UserName"]';
    public $password_selector = 'div[class*="signin-"] input[id*="_password"]';
    public $remember_me = 'div[class*="signin-"] input[id*="_signInControl_Checkbox1"]';
    public $submit_login_selector = 'div[class*="signin-"] input[id*="_signInControl_login"]';

    public $check_login_failed_selector = '.eCareLoginBox .loginErrorMessage';
    public $check_login_success_selector = 'div.navbar-header ul li a[href="/_layouts/CustomLogout/SignOut.aspx"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(20);
        }


        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->exts->moveToElementAndClick('div[class*="invoice-Types"] [class*="invoice-Label"]:nth-child(3) input');
            sleep(5);

            $this->processBilling(1);

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (strpos(strtolower($this->exts->extract('div.alert-message')), 'an error processing the form') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('span[id*="signInControl_FailureText"] center')), 'passwor') !== false) {
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
        if ($this->exts->getElement($this->password_selector) != null) {

            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->remember_me != '') {
                $this->exts->moveToElementAndClick($this->remember_me);
                sleep(2);
            }

            $this->exts->capture("2-login-page-filled");
            sleep(5);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public $totalInvoices = 0;

    private function processBilling($page = 1)
    {
        sleep(15);
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->capture("4-billing-page");
        $invoices = [];
        $rows = $this->exts->getElements('table#vgt-table tbody tr');
        $this->exts->log('Total Rows - ' . count($rows));

        foreach ($rows as $key => $row) {

            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }

            $tags = $this->exts->getElements('td', $row);
            $this->exts->log('$tags: ' . count($tags));
            if (count($tags) >= 4 && $this->exts->getElement('td .invoice-MoreLink', $row) != null) {
                $invoiceBtn = $this->exts->getElement('td span.invoice-MoreLink', $row);
                $invoiceDate = trim($tags[2]->getAttribute('innerText'));
                $invoiceAmount = '';

                $this->isNoInvoice = false;

                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd M Y', 'Y-m-d');
                $invoiceFileName = '';


                try {
                    $invoiceBtn->click();
                } catch (\Exception $exception) {
                    $this->exts->execute_javascript('arguments[0].click();', [$invoiceBtn]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');

                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoice_name = basename($downloaded_file, '.pdf');
                    $this->exts->log('invoiceName: ' . $invoice_name);
                    $this->exts->new_invoice($invoice_name, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                    $this->isNoInvoice = false;
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
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
