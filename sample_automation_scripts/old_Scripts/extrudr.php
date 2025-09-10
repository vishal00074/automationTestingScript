<?php // replace waitTillPresent to waitFor and uncomment loadCookiesFromFile added restrctPage logic

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

    // Server-Portal-ID: 852727 - Last modified: 20.05.2025 14:15:25 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = 'https://www.extrudr.com/de/de/account/login/';
    public $loginUrl = 'https://www.extrudr.com/de/de/account/login/';
    public $invoicePageUrl = 'https://www.extrudr.com/de/de/account/preferences/';

    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'p[class*="TextInput-module_text-input-error-caption"]';
    public $check_login_success_selector = 'div[class*="Navbar_user-dropdown"] li.cursor-pointer';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->waitFor('button[id="c-p-bn"]', 5);
        if ($this->exts->exists('button[id="c-p-bn"]')) {
            $this->exts->click_element('button[id="c-p-bn"]');
        }

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->waitFor('button[id="c-p-bn"]', 5);
            if ($this->exts->exists('button[id="c-p-bn"]')) {
                $this->exts->click_element('button[id="c-p-bn"]');
            }
            sleep(5);
            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();

            sleep(5);
            $this->exts->openUrl($this->invoicePageUrl);

            sleep(10);
            $this->waitFor('#page-content-section a[href*="/orders/"]', 7);
            if ($this->exts->exists('#page-content-section a[href*="/orders/"]')) {
                $this->exts->click_element('#page-content-section a[href*="/orders/"]');
                sleep(5);
            }

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'Anmeldeinformationen') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {
                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_element($this->remember_me_selector);
                    sleep(1);
                }

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

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
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
            $this->waitFor($this->check_login_success_selector, 15);
            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    public $totalInvoices = 0;
    private function processInvoices()
    {
        $this->waitFor('table.w-full tbody tr', 15);
        $this->exts->capture("4-invoices-page");
        while ($this->exts->exists('nav button[type="button"]')) {
            $this->exts->click_element('nav button[type="button"]');
            sleep(5);
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $rows = $this->exts->querySelectorAll('table.w-full tbody tr');
        $this->exts->log('rows count: ' . count($rows));
        for ($i = 0; $i < count($rows); $i++) {

            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }

            $this->exts->log('current count: ' . $i);
            $this->waitFor('table.w-full tbody tr', 15);
            sleep(5);
            while ($this->exts->exists('nav button[type="button"]')) {
                $this->exts->click_element('nav button[type="button"]');
                sleep(5);
            }
            $row = $this->exts->getElements('table.w-full tbody tr')[$i];
            if ($this->exts->querySelector('td:nth-child(1)', $row) != null) {
                $invoiceUrl = '';
                $invoiceName =  $this->exts->extract('td:nth-child(1)', $row);
                $invoiceDate =  $this->exts->extract('td:nth-child(2)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(4)', $row);
                $invoicePage = $this->exts->querySelector('td:nth-child(1)', $row);
                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";
                $this->exts->execute_javascript("arguments[0].click();", [$invoicePage]);
                sleep(3);
                $this->waitFor('.download a[href*="/invoice"]', 5);
                if ($this->exts->exists('.download a[href*="/invoice"]')) {
                    $downloadBtn = $this->exts->querySelector('.download a[href*="/invoice"]');
                    $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                        $this->totalInvoices++;
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
                sleep(3);
                $this->exts->openUrl('https://www.extrudr.com/de/de/account/orders/');
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
