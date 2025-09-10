<?php // replaced waitTIllPresetnt to waitFor and chenge the download invocies process accoridng to ui 
// and use click_and_download instead on js click and download 

// added pagination logic
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


    // Server-Portal-ID: 107220 - Last modified: 26.05.2025 14:12:46 UTC - User: 1

    /*Define constants used in script*/

    public $baseUrl = "https://portal.amex-online.de/startseite";
    public $loginUrl = "https://portal.amex-online.de";
    public $invoicePageUrl = 'https://portal.amex-online.de/dokumente';
    public $username_selector = 'input[id="Email"]';
    public $password_selector = 'input[id="Password"]';
    public $submit_button_selector = 'button[id="btnLogin"]';
    public $check_login_failed_selector = 'div.validation-summary-errors ul li';
    public $check_login_success_selector = 'section[name="profil"]';
    public $login_tryout = 0;
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
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);
            $this->processInvoices();
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->waitFor($this->check_login_failed_selector, 10);
            if ($this->exts->exists($this->check_login_failed_selector)) {
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
        $this->waitFor($this->username_selector, 10);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->exts->click_by_xdotool($this->submit_button_selector);
                sleep(2); // Portal itself has one second delay after showing toast
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
            $this->waitFor($this->check_login_success_selector, 10);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }


    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function processInvoices($count = 0)
    {
        $this->waitFor('datatable-scroller > datatable-row-wrapper', 10);
        $this->exts->capture("1 invoice page");
        $rows = $this->exts->querySelectorAll('datatable-scroller > datatable-row-wrapper');
        $this->exts->startTrakingTabsChange();

        foreach ($rows as $row) {
            $this->isNoInvoice = false;

            $documentFormat = $this->exts->extract('datatable-body-cell:nth-child(3)', $row);

            $this->exts->log('documentFormat: ' . $documentFormat);

            if (trim($documentFormat) == 'DMS-XLS') {
                continue;
            }

            $invoiceName = $this->exts->extract('datatable-body-cell:nth-child(2) a', $row);
            $invoiceName = str_replace(' ', '', $invoiceName);
            $invoiceDate = $row->querySelector('datatable-body-cell:nth-child(1)');



            if ($invoiceDate != null) {
                $invoiceDateText = $invoiceDate->getText();
                $invoiceDate = $this->exts->parse_date($invoiceDateText, '', 'Y-m-d');
            }
            $invoiceAmount =  '';

            $invoiceBtn = $this->exts->getElement('datatable-body-cell:nth-child(2) a', $row);
            if ($invoiceBtn == null) {
                continue;
            }
            try {
                $this->exts->log('Click download button');
                $invoiceBtn->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click download button by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$invoiceBtn]);
            }
            sleep(5);

            $this->waitFor('section.afn-file-preview-toolbar a.items-baseline', 3);
            $this->waitFor('section.afn-file-preview-toolbar a.items-baseline', 3);
            $this->waitFor('section.afn-file-preview-toolbar a.items-baseline', 3);

            $downloadBtn = $this->exts->querySelector('section.afn-file-preview-toolbar a.items-baseline');

            if ($downloadBtn != null) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName =  !empty($invoiceName) ? $invoiceName . '.pdf' : '';

                $downloaded_file = $this->exts->click_and_download('section.afn-file-preview-toolbar a.items-baseline', 'pdf', $invoiceFileName);
                sleep(5);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
            if ($this->exts->querySelector('section.afn-file-preview-toolbar a.items-center') != null) {
                $this->exts->click_element('section.afn-file-preview-toolbar a.items-center');
                sleep(4);
            }
        }
        sleep(5);

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->log(__FUNCTION__ . '::restrictPages ' . $restrictPages);

        $pagiantionSelector = 'ul.pager li a i.datatable-icon-right';
        if ($restrictPages == 0) {
            if ($count < 50 && $this->exts->querySelector($pagiantionSelector) != null  && $this->exts->querySelector('ul.pager li.disabled a i.datatable-icon-right') == null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);
                $count++;
                $this->processInvoices($count);
            }
        } else {
            if ($count < $restrictPages && $this->exts->querySelector($pagiantionSelector) != null && $this->exts->querySelector('ul.pager li.disabled a i.datatable-icon-right') == null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);
                $count++;
                $this->processInvoices($count);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
