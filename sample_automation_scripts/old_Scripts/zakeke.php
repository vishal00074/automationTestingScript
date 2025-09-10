<?php // updated fill form function added triggerloginFailedConfirmed in case wrong credentials
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

    // Server-Portal-ID: 221827 - Last modified: 20.03.2025 14:49:27 UTC - User: 1

    public $baseUrl = 'https://admin.zakeke.com/';
    public $loginUrl = 'https://admin.zakeke.com/';
    public $invoicePageUrl = 'https://admin.zakeke.com/en-US/Admin/User/?tab=payments';

    public $username_selector = 'form input[type="text"]';
    public $password_selector = 'form input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form button[type="submit"]';

    public $check_login_failed_selector = 'div.rnc__notification';
    public $check_login_success_selector = 'a[href*="/Admin/Settings"]';

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
            $this->exts->openUrl($this->loginUrl);


            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);

            while ($this->exts->querySelector('div.react-tabs__tab-panel--selected > div:nth-child(3) > div >div:nth-child(2) > div:nth-child(3) span') != null) {
                $this->exts->click_element('div.react-tabs__tab-panel--selected > div:nth-child(3) > div > div:nth-child(2) > div:nth-child(3) span');
                sleep(10);
            }

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if ($this->isFailedLogin) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else if ($this->exts->urlContains('Login?error=')) {
                $this->exts->capture("LoginFailed");
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function waitFor($selector, $seconds = 10)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }


    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 10);

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
            if ($this->isExists($this->submit_login_selector)) {
                $this->exts->click_element($this->submit_login_selector);
            }

            $this->exts->waitTillPresent('div.rnc__notification-message', 10);
            $error_text = strtolower($this->exts->extract('div.rnc__notification-message'));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('Incorrect username or password.')) !== false) {
                $this->exts->capture("LoginFailed-incorrect-cred-0");
                $this->exts->loginFailure(1);
            }


            if ($this->exts->urlContains('Login?error=')) {
                $this->exts->capture("LoginFailed-incorrect-cred-1");
                $this->exts->log(__FUNCTION__ . '::Use login failed');
                $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {

            $this->waitFor($this->check_login_failed_selector, 5);
            if ($this->isExists($this->check_login_failed_selector)) {
                $this->isFailedLogin = true;
            }

            $this->waitFor($this->check_login_success_selector, 15);
            if ($this->isExists($this->check_login_success_selector)) {
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
        $this->waitFor('div.react-tabs__tab-panel--selected > div:nth-child(3) > div >div:nth-child(2) > div:nth-child(2) > div', 15);

        $rows = $this->exts->querySelectorAll('div.react-tabs__tab-panel--selected > div:nth-child(3) > div >div:nth-child(2) > div:nth-child(2) > div');
        $this->exts->log('Invoices found: ' . count($rows));
        foreach ($rows as $row) {
            if ($row->querySelectorAll('span')[5]->querySelector('svg') != null) {

                $this->isNoInvoice = false;

                $invoiceAmount = $row->querySelectorAll('span')[3]->getText();

                $this->exts->log('invoice amount: ' . $invoiceAmount);

                $invoiceDate = trim($row->querySelectorAll('span')[1]->getText());

                $invoiceDate = preg_replace('/ at .*/', '', $invoiceDate);

                $this->exts->log('invoice date: ' . $invoiceDate);

                $parsedDate = $this->exts->parse_date($invoiceDate, 'F d, Y', 'Y-m-d');
                $this->exts->log('Parsed date: ' . $parsedDate);

                $row->querySelectorAll('span')[5]->querySelector('div')->click();
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
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
