<?php

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

    // Server-Portal-ID: 1902245 - Last modified: 14.06.2025 00:52:05 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://smartmobility.enbw.com/stats';
    public $loginUrl = 'https://smartmobility.enbw.com/welcome';
    public $invoicePageUrl = 'https://smartmobility.enbw.com/profile';

    public $username_selector = 'input#emailinput';
    public $password_selector = 'input#passwordinput';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[id="loginbtn"]';

    public $check_login_failed_selector = 'div.flying-wrapper__error-message';
    public $check_login_success_selector = 'div.es-profile-dropdown-content-bottom a';

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
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->click_element('button#onetrust-accept-btn-handler');
        }
        sleep(1);
        $this->exts->click_element('button.login-button');
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(1);
            $this->exts->click_element('button.login-button');
            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->click_element('button#onetrust-accept-btn-handler');
            }
            $this->fillForm(0);
        }
        sleep(5);
        $this->exts->waitTillPresent('//span[text()="Profil"]', 20);
        $this->exts->click_element('//span[text()="Profil"]');
        sleep(5);

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();

            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->click_element('button#onetrust-accept-btn-handler');
            }

            $this->exts->openUrl($this->invoicePageUrl);
            sleep(12);

            if ($this->exts->exists('span[data-cy="profile-invoices-tab"]')) {
                $this->exts->click_element('span[data-cy="profile-invoices-tab"]');
                sleep(2);
            }

            $this->downloadInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }
                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
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
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
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

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function downloadInvoices($count = 1)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('datatable-body-row');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('datatable-body-row');
        foreach ($rows as $key => $row) {
            $invoiceBtn = $this->exts->getElement('a.datatable-icon-right', $row);
            if ($invoiceBtn != null) {
                sleep(2);
                $invoiceUrl = '';
                $invoiceName =  $this->exts->extract('span[data-cy*="profile-invoice-table-date"]', $row);
                $invoiceDate = $this->exts->extract('span[data-cy*="profile-invoice-table-invoice-number"]', $row);
                $invoiceAmount = $this->exts->extract('span[data-cy*="profile-invoice-table-amount"]', $row);

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' .  $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' .  $invoiceUrl);
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' .  $invoiceDate);

                $downloadBtn = $this->exts->getElement('a[class="download-file ng-star-inserted"][data-cy="row-0-profile-invoice-table-detail-download"]');
                if ($downloadBtn == null) {
                    $this->exts->log('Click to open Invoice Button');
                    try {
                        $invoiceBtn->click();
                    } catch (\Exception $e) {
                        $this->exts->execute_javascript("arguments[0].click();", [$invoiceBtn]);
                    }
                    sleep(5);
                }
                $this->waitFor('a[class="download-file ng-star-inserted"][data-cy="row-0-profile-invoice-table-detail-download"]');


                if ($downloadBtn != null) {
                    try {
                        $downloadBtn->click();
                    } catch (\Exception $e) {
                        $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                    }
                    sleep(7);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $invoiceName = basename($downloaded_file, '.pdf');

                        $this->exts->log('invoiceName: ' . $invoiceName);
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                        sleep(5);
                    } else {
                        $this->exts->log('Timeout when download ');
                    }
                } else {
                    $this->exts->log('Download button not founds');
                }

                try {
                    $invoiceBtn->click();
                } catch (\Exception $e) {
                    $this->exts->execute_javascript("arguments[0].click();", [$invoiceBtn]);
                }
                sleep(7);
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $pagiantionSelector = 'div.button span.next';
        if ($restrictPages == 0) {
            if ($count < 50 && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);
                $count++;
                $this->downloadInvoices($count);
            }
        } else {
            if ($count < $restrictPages && $this->exts->querySelector($pagiantionSelector) != null) {
                $this->exts->click_by_xdotool($pagiantionSelector);
                sleep(7);
                $count++;
                $this->downloadInvoices($count);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
