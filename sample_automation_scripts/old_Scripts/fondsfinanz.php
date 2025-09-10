<?php // replaced waitTillPresent to custom js waitFor function to handle client read time out issue 
// updated go to invoice page selector replace general selector with xapth change click_by_xdotool to movetoelementandtye for download button 
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

    // Server-Portal-ID: 62962 - Last modified: 01.09.2025 14:09:51 UTC - User: 1

    public $baseUrl = 'https://beraterwelt.fondsfinanz.de/#Dashboard';
    public $loginUrl = 'https://beraterwelt.fondsfinanz.de/#Dashboard';
    public $username_selector = 'input#loginId';
    public $password_selector = 'input#password';
    public $submit_login_selector = 'button.submit-button';
    public $check_login_failed_selector = 'div.field-error-message';
    public $check_login_success_selector = 'div.customers-initials';
    public $isNoInvoice = true;
    public $restrictPages = 3;
    public $totalInvoices = 0;
    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        sleep(3);
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->waitFor('button#cookie-consent-submit-all-button', 5);
            if ($this->exts->exists('button#cookie-consent-submit-all-button')) {
                $this->exts->click_element('button#cookie-consent-submit-all-button');
            }
            $this->fillForm(0);
            sleep(30);
            if ($this->exts->exists($this->username_selector)) {
                $this->exts->openUrl($this->loginUrl);
                $this->fillForm(0);
                sleep(10);
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);
            $this->waitFor('button#cookie-consent-submit-all-button', 5);
            if ($this->exts->exists('button#cookie-consent-submit-all-button')) {
                $this->exts->click_element('button#cookie-consent-submit-all-button');
            }
            // Open invoices page
            if ($this->exts->queryXpath(".//mat-expansion-panel-header[.//div[normalize-space(text())='Verwaltung']]") != null) {
                $this->exts->click_element(".//mat-expansion-panel-header[.//div[normalize-space(text())='Verwaltung']]");
                sleep(10);
                $this->exts->click_element("//a[.//span[contains(@class, 'list-item-label') and contains(text(), 'Abrechnung')]]");
            }
            $this->processInvoices();
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

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
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

                $this->exts->capture("1-login-page-filled");

                $this->waitFor($this->submit_login_selector, 10);
                if ($this->exts->exists($this->submit_login_selector)) {
                    // $this->exts->click_element($this->submit_login_selector);
                    $this->exts->execute_javascript("arguments[0].click();", [$this->exts->querySelector($this->submit_login_selector)]);
                    sleep(5);
                }

                $this->waitFor($this->check_login_failed_selector, 20);
                if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                    $this->exts->log("Wrong credential !!!!");
                    $this->exts->loginFailure(1);
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
            // i have set manual wait for element as it throws fatal error in test engine
            for (
                $wait = 0;
                $wait < 20
                    && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1;
                $wait++
            ) {
                $this->exts->log('Waiting for login.....');
                sleep(3);
            }
            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function extract_zip_save_pdf($zipfile)
    {
        $zip = new \ZipArchive;
        $res = $zip->open($zipfile);
        if ($res === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if ($this->restrictPages != 0 && $this->totalInvoices >= 100) {
                    return;
                };
                $zipPdfFile = $zip->statIndex($i);
                $fileName = basename($zipPdfFile['name']);
                $fileInfo = pathinfo($fileName);
                if ($fileInfo['extension'] === 'pdf') {
                    $this->isNoInvoice = false;
                    $zip->extractTo($this->exts->config_array['download_folder'], array(basename($zipPdfFile['name'])));
                    $saved_file = $this->exts->config_array['download_folder'] . basename($zipPdfFile['name']);
                    $this->exts->new_invoice($fileInfo['filename'], "", "", $saved_file);
                    sleep(1);
                    $this->totalInvoices++;
                }
            }
            $zip->close();
            unlink($zipfile);
        } else {
            $this->exts->log(__FUNCTION__ . '::File extraction failed');
        }
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(30);
        $this->exts->execute_javascript("document.querySelector('input#mat-mdc-checkbox-7-input').scrollIntoView({ behavior: 'smooth', block: 'center' });");
        sleep(3);
        $this->exts->capture("4-invoices-page");
        $selectAll = 'input#mat-mdc-checkbox-7-input';
        $this->exts->execute_javascript("arguments[0].click();", [$this->exts->querySelector($selectAll)]);
        $downloadBtn = 'app-table-checked-row button';
        sleep(5);
        $this->exts->moveToElementAndClick($downloadBtn);
        sleep(5);
        $this->exts->wait_and_check_download('zip');

        $downloaded_file = $this->exts->find_saved_file('zip');
        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->extract_zip_save_pdf($downloaded_file);
        } else {
            sleep(60);
            $this->exts->wait_and_check_download('zip');
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->extract_zip_save_pdf($downloaded_file);
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 10 &&
            $this->exts->querySelector('div.numbers div:last-child:not([class*=active])')
        ) {
            $paging_count++;
            $paginateButton = $this->exts->querySelector('div.arrow:last-child');
            $this->exts->execute_javascript("arguments[0].click();", [$paginateButton]);
            sleep(5);
            $this->processInvoices($paging_count);
        } else if (
            $restrictPages != 0 &&
            $paging_count < $restrictPages &&
            $this->exts->querySelector('div.numbers div:last-child:not([class*=active])')
        ) {
            $this->exts->log('Click paginateButton');
            $paging_count++;
            $paginateButton = $this->exts->querySelector('div.arrow:last-child');
            $this->exts->execute_javascript("arguments[0].click();", [$paginateButton]);
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP("optimized-chrome-v2", 'Fondsfinanz', '2673521', 'TUFLOTQ2NTI=', 'RTNiOHIxciE=');
$portal->run();
