<?php // 
/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673482/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // load cookies from file for desktop app
                if (!empty($this->exts->config_array["without_password"])) {
                    $this->exts->loadCookiesFromFile();
                }
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 62962 - Last modified: 07.08.2025 14:01:20 UTC - User: 1

    public $baseUrl = 'https://beraterwelt.fondsfinanz.de/#Dashboard';
    public $loginUrl = 'https://beraterwelt.fondsfinanz.de/#Dashboard';
    public $username_selector = 'input#loginId';
    public $password_selector = 'input#password';
    public $submit_login_selector = 'button.submit-button';
    public $check_login_failed_selector = 'div.field-error-message';
    public $check_login_success_selector = 'div.customers-initials';
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
            if ($this->exts->exists('#mat-expansion-panel-header-1')) {
                $this->exts->click_element('#mat-expansion-panel-header-1');
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
        $this->exts->click_by_xdotool($downloadBtn);
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

    private function extract_zip_save_pdf($zipfile)
    {
        $zip = new \ZipArchive;
        $res = $zip->open($zipfile);
        if ($res === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zipPdfFile = $zip->statIndex($i);
                $fileName = basename($zipPdfFile['name']);
                $fileInfo = pathinfo($fileName);
                if ($fileInfo['extension'] === 'pdf') {
                    $this->isNoInvoice = false;
                    $zip->extractTo($this->exts->config_array['download_folder'], array(basename($zipPdfFile['name'])));
                    $saved_file = $this->exts->config_array['download_folder'] . basename($zipPdfFile['name']);
                    $this->exts->new_invoice($fileInfo['filename'], "", "", $saved_file);
                    sleep(1);
                }
            }
            $zip->close();
            unlink($zipfile);
        } else {
            $this->exts->log(__FUNCTION__ . '::File extraction failed');
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'Immowelt Kundenportal', '2673482', 'aW5mb0B0b20taW1tb2JpbGllbi5jb20=', 'UXQlb3FrV2VAKExSYjI=');
$portal->run();
