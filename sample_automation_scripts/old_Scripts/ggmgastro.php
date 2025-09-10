<?php // added download code and updated check_login_success_selector added pagination code

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

    public $baseUrl = "https://www.ggmgastro.com/de-de-eur/";
    public $loginUrl = "https://www.ggmgastro.com/de-de-eur/my-account/orders";
    public $invoicePageUrl = 'https://www.ggmgastro.com/de-de-eur/my-account/orders';
    public $username_selector = 'input[type="email"]';
    public $password_selector = 'input[type="password"]';
    public $remember_me_selector = '';
    public $submit_button_selector = 'form button[type="submit"].from-primaryButtonColor';
    public $check_login_failed_selector = 'div.error';
    public $check_login_success_selector = 'a[data-test="logout"]';
    public $login_tryout = 0;
    public $isFailed = false;
    public $isNoInvoice = true;
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
            sleep(5);
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->openUrl($this->invoicePageUrl);

            $this->processInvoices();

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (!$this->isFailed) {
                if ($this->exts->exists($this->check_login_failed_selector)) {
                    $this->exts->log("Wrong credential !!!!");
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 20);

        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->login_tryout = (int) $this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->click_by_xdotool($this->submit_button_selector);
            sleep(1); // Portal itself has one second delay after showing toast

            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract('span.break-words'));
            if (
                stripos($error_text, strtolower('Die Kontoanmeldung war falsch oder Ihr Konto wurde vorübergehend deaktiviert. Bitte warten Sie und versuchen Sie es später erneut.')) !== false
            ) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            }
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    public function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_failed_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credential !!!!");
                $this->isFailed = true;
                $this->exts->loginFailure(1);
            } else {

                for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                    $this->exts->log('Waiting for login.....');
                    sleep(10);
                }

                if ($this->exts->exists($this->check_login_success_selector)) {
                    $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                    $isLoggedIn = true;
                }
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }


    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('table tbody tr', 30);
        $this->exts->capture("4-invoices-page");

        $rows = $this->exts->getElements('table tbody tr');
        foreach ($rows as $key => $row) {

            $this->exts->waitTillPresent('table tbody tr');

            $invoiceUrl = '';
            $invoiceName = $this->exts->extract('td:nth-child(1)', $row);
            $invoiceName = str_replace("#","",$invoiceName);
            $invoiceAmount = $this->exts->extract('td:nth-child(4)', $row);
            $invoiceDate = $this->exts->extract('td:nth-child(2)', $row);

            $downloadBtn = 'table tbody tr td:nth-child(6) span';
            if ($this->exts->querySelector($downloadBtn) != null) {
                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                $this->exts->moveToElementAndClick($downloadBtn);

                $invoiceFileName = !empty($invoiceName) ?  $invoiceName  . '.pdf' : '';

                sleep(4);
                $this->exts->execute_javascript('window.print();');
                sleep(4);
                $this->exts->capture("print-invoice-".$key);
                
                $file_ext = $this->exts->get_file_extension($invoiceFileName);

                $this->exts->wait_and_check_download($file_ext);

                $downloaded_file = $this->exts->find_saved_file($file_ext, $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                sleep(2);

                // back to listing
                $this->exts->moveToElementAndClick('button.cursor-pointer');
                sleep(5);
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        if ($paging_count < $restrictPages && $this->exts->exists('nav ul button span.icon-chevron-thin-right')) {
            $paging_count++;
            $this->exts->click_by_xdotool('nav ul button span.icon-chevron-thin-right');
            sleep(7);
            $this->processInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
