<?php //

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

    // Server-Portal-ID: 1284066 - Last modified: 16.04.2025 14:44:40 UTC - User: 1

    public $baseUrl = 'https://parmazr-portal.dzb-bank.de/';
    public $loginUrl = 'https://parmazr-portal.dzb-bank.de/bal/login';
    public $invoicePageUrl = 'https://parmazr-portal.dzb-bank.de/';
    public $username_selector = 'input#benutzerkennung';
    public $password_selector = 'input#passwort';
    public $remember_me_selector = '';
    public $submit_login_selector = 'div#login-button';
    public $check_login_failed_selector = 'div.v-Notification.warning div.popupContent';
    public $check_login_success_selector = 'div#logout-button';
    public $isNoInvoice = true;
    public $isFailedLogin = false;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->clearChrome();
            $this->exts->openUrl($this->loginUrl);

            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);

            $this->exts->waitTillPresent('div.v-menubar > span.v-menubar-menuitem:nth-child(2)', 10);
            $this->exts->click_element('div.v-menubar > span.v-menubar-menuitem:nth-child(2)');

            $this->exts->waitTillPresent('div.v-menubar-submenu > span.v-menubar-menuitem:nth-child(1)', 10);
            $this->exts->click_element('div.v-menubar-submenu > span.v-menubar-menuitem:nth-child(1)');
            sleep(10);

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if ($this->isFailedLogin) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
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
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 20);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_element($this->submit_login_selector);
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    /**

     * Method to Check where user is logged in or not

     * return boolean true/false

     */
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent($this->check_login_failed_selector, 10);
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->isFailedLogin = true;
            }

            $this->exts->waitTillPresent($this->check_login_success_selector, 20);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('table tbody tr', 20);

        $rows = $this->exts->querySelectorAll('table tbody tr');
        $this->exts->log('Invoices found: ' . count($rows));

        foreach ($rows as $row) {
            $this->isNoInvoice = false;
            $invoiceAmount = '';
            $invoiceDate = trim($row->querySelectorAll('td')[5]->getText());
            $parsedDate = $this->exts->parse_date($invoiceDate, 'd.m.y', 'Y-m-d');

            $row->querySelectorAll('td')[7]->querySelector('img')->click();
            sleep(2);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);
            $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoiceName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $parsedDate, $invoiceAmount, $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }

            sleep(2);
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        if ($restrictPages == 0 && $paging_count < 50) {

            $maxAttempts = 10;
            $attempt = 0;

            do {
                $currentScroll = $this->exts->execute_javascript("
                var el = document.querySelector('div.v-grid-scroller.v-grid-scroller-vertical');
                el ? el.scrollTop : 0;
            ");
                $this->exts->log("Scroll moved from: " . $currentScroll);

                $this->exts->execute_javascript("
                var el = document.querySelector('div.v-grid-scroller.v-grid-scroller-vertical');
                if (el) el.scrollBy({ top: 739.2, behavior: 'smooth' });
            ");
                sleep(5);

                $newScroll = $this->exts->execute_javascript("
                var el = document.querySelector('div.v-grid-scroller.v-grid-scroller-vertical');
                el ? el.scrollTop : 0;
            ");

                $this->exts->log("Scroll moved to: " . $newScroll);

                if ($currentScroll == $newScroll) {
                    $this->exts->log('No more space to scroll.');
                    break;
                }

                $paging_count++;
                $this->processInvoices($paging_count);

                $attempt++;
            } while ($attempt < $maxAttempts);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
