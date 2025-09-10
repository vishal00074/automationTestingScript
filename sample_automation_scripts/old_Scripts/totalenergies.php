<?php // replaced waitTillPresent to waitFor

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

    // Server-Portal-ID: 1540060 - Last modified: 08.08.2025 11:56:41 UTC - User: 1

    public $baseUrl = 'https://client.mobility.totalenergies.com/group/france';
    public $loginUrl = 'https://fleet.circlek-deutschland.de/public/transverse/seconnecter/authentification.do';
    public $invoicePageUrl = 'https://client.mobility.totalenergies.com/group/france/invoices';
    public $username_selector = 'input#fixed-username';
    public $password_selector = 'input#fixed-passwd';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input#passwd-submit';
    public $check_login_failed_selector = 'div[role=alert]';
    public $check_login_success_selector = 'div.user_menu,div#btn-deconnexion';
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

            $this->waitFor('input[name="loginID"]', 10);
            $this->exts->moveToElementAndType('input[name="loginID"]', $this->username);
            sleep(2);
            $this->exts->click_element('div[name="checkLoginId"]');

            $this->waitFor("form input#tec", 5);
            if ($this->exts->exists("form input#tec")) {
                $this->exts->click_element("form input#tec");
            }

            $this->waitFor('input[name="j_password"]', 5);
            if ($this->exts->exists('input[name="j_password"]')) {
                $this->exts->moveToElementAndType('input[name="j_password"]', $this->password);
                sleep(2);
                $this->exts->click_element("div#okbtn");
            } else {
                $this->fillForm(0);
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);
            $this->waitFor('div[id="lienRechercheFacture"] a', 5);
            if ($this->exts->exists('div[id="lienRechercheFacture"] a')) {
                $invoicePg = $this->exts->querySelector('div[id="lienRechercheFacture"] a');
                $this->exts->click_element($invoicePg);
                $this->waitFor('div[name="rechercher"]', 5);
                $this->exts->click_element('div[name="rechercher"]');
                $this->processInvoices();
            } else {
                $this->exts->openUrl($this->invoicePageUrl);
                $this->processInvoicesNew();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'pass') !== false) {
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
            if ($this->exts->querySelector($this->username_selector) != null || $this->exts->querySelector($this->password_selector)) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                $this->exts->click_element("input[id*=submitLogin]");

                sleep(3);
                if (!$this->isValidEmail($this->username)) {
                    $this->exts->loginFailure(1);
                }

                $this->waitFor($this->password_selector, 15);
                sleep(10);
                $this->exts->log("Enter Password");
                $this->exts->click_by_xdotool($this->password_selector);
                $this->exts->type_text_by_xdotool($this->password);
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

    public function isValidEmail($username)
    {
        // Regular expression for email validation
        $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';


        if (preg_match($emailPattern, $username)) {
            return 'email';
        }
        return false;
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

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

    private function processInvoices($paging_count = 1)
    {
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $this->waitFor('a[name="anchorRecherche"] table tbody tr', 10);
        $this->waitFor('div.content-list table tbody tr', 5);
        if ($this->exts->exists('a[name="anchorRecherche"] table tbody tr')) {
            $rows = $this->exts->querySelectorAll('a[name="anchorRecherche"] table tbody tr');
            for ($i = 1; $i <= count($rows); $i++) {
                $this->waitFor('a[name="anchorRecherche"] table tbody tr', 10);
                $row = $this->exts->querySelector('a[name="anchorRecherche"] table tbody tr:nth-child(' . $i . ')');
                if ($this->exts->querySelector('td:nth-child(8) a', $row) != null) {
                    $invoiceUrl = '';
                    $invoiceName = $this->exts->extract('td:nth-child(3)', $row);
                    $invoiceAmount = $this->exts->extract('td:nth-child(4)', $row);
                    $invoiceDate = $this->exts->extract('td:nth-child(2)', $row);

                    $downloadPage = $this->exts->querySelector('td:nth-child(8) a', $row);
                    $fileName = $invoiceName . '.zip';
                    $this->exts->click_element($downloadPage);
                    $this->waitFor('div#telecharger', 5);
                    $downloadBtn = $this->exts->querySelector('div#telecharger');
                    $this->exts->click_element($downloadBtn);
                    sleep(10);
                    $this->exts->wait_and_check_download('zip');

                    $downloaded_file = $this->exts->find_saved_file('zip', $fileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->log("Downloaded file If extract: " . $fileName);
                        $this->extract_zip_save_pdf($downloaded_file);
                    } else {
                        sleep(60);
                        $this->exts->log("Downloaded file Else extract: " . $fileName);
                        $this->exts->wait_and_check_download('zip');
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->log("Downloaded file ElseIF extract: " . $fileName);

                            $this->extract_zip_save_pdf($downloaded_file);
                        }
                    }
                    $this->isNoInvoice = false;
                }
                $this->exts->click_element('div[name="confirmerTelechargement"]');
                $this->waitFor('div[name="rechercher"]', 5);
                $this->exts->click_element('div[name="rechercher"]');
            }
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

            $this->waitFor("//a[contains(text(), 'Weiter')]", 10);

            if (
                $restrictPages == 0 &&
                $paging_count < 50 &&
                $this->exts->queryXpath("//a[contains(text(), 'Weiter')]") != null
            ) {
                $paging_count++;
                $this->exts->log('Next invoice page found');
                $this->exts->click_element("//a[contains(text(), 'Weiter')]");
                sleep(5);
                $this->processInvoices($paging_count);
            }
        } elseif ($this->exts->exists('div.content-list table tbody tr')) {
            $rows = $this->exts->querySelectorAll('div.content-list table tbody tr');
            foreach ($rows as $row) {
                if ($this->exts->querySelector('td:nth-child(8) a', $row) != null) {
                    $invoiceUrl = '';
                    $invoiceName = $this->exts->extract('td:nth-child(3)', $row);
                    $invoiceAmount = $this->exts->extract('td:nth-child(4)', $row);
                    $invoiceDate = $this->exts->extract('td:nth-child(2)', $row);

                    $downloadBtn = $this->exts->querySelector('td:nth-child(8) a', $row);
                    $fileName = $invoiceName . '.zip';
                    $this->exts->click_element($downloadBtn);
                    sleep(10);
                    $this->exts->wait_and_check_download('zip');

                    $downloaded_file = $this->exts->find_saved_file('zip', $fileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->log("Downloaded file If extract: " . $fileName);
                        $this->extract_zip_save_pdf($downloaded_file);
                    } else {
                        sleep(60);
                        $this->exts->log("Downloaded file Else extract: " . $fileName);
                        $this->exts->wait_and_check_download('zip');
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->log("Downloaded file ElseIF extract: " . $fileName);

                            $this->extract_zip_save_pdf($downloaded_file);
                        }
                    }
                    $this->isNoInvoice = false;
                }
            }
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

            $this->waitFor("//a[contains(text(), 'Weiter')]", 10);

            if (
                $restrictPages == 0 && $paging_count < 50 &&
                $this->exts->queryXpath("(//a[contains(text(), 'Suivante')])[1]") != null
            ) {
                $paging_count++;
                $this->exts->click_element("(//a[contains(text(), 'Suivante')])[1]");
                sleep(5);
                $this->processInvoices($paging_count);
            } else if ($restrictPages > 0 && $paging_count < $restrictPages && $this->exts->queryXpath("(//a[contains(text(), 'Suivante')])[1]") != null) {
                $paging_count++;
                $this->exts->click_element("(//a[contains(text(), 'Suivante')])[1]");
                sleep(5);
                $this->processInvoices($paging_count);
            }
        }
    }

    private function processInvoicesNew($paging_count = 1)
    {
        sleep(5);
        $this->waitFor("div.active_filters_list a[filtername=documentType]", 5);
        $this->exts->click_element("div.active_filters_list a[filtername=documentType]");
        sleep(10);
        $this->exts->click_element("div.active_filters_list a[filtername=billingDate]");
        sleep(10);
        $this->waitFor("table tbody tr[role=row]", 10);
        $rows = $this->exts->querySelectorAll("table tbody tr[role=row]");
        foreach ($rows as $row) {
            $this->exts->capture("4-invoices-page");
            $downloadBtn = $this->exts->querySelector('td a', $row);
            $fileName = $this->exts->extract('td:nth-child(5)', $row) . '.zip';
            $this->exts->click_element($downloadBtn);
            sleep(10);
            $this->exts->wait_and_check_download('zip');

            $downloaded_file = $this->exts->find_saved_file('zip', $fileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->log("Downloaded file If extract: " . $fileName);
                $this->extract_zip_save_pdf($downloaded_file);
            } else {
                sleep(60);
                $this->exts->log("Downloaded file Else extract: " . $fileName);
                $this->exts->wait_and_check_download('zip');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->log("Downloaded file ElseIF extract: " . $fileName);

                    $this->extract_zip_save_pdf($downloaded_file);
                }
            }
        }
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 && $paging_count < 50 &&
            $this->exts->querySelector('a[id=invoiceListTable_next]:not([class*=disabled])') != null
        ) {
            $paging_count++;
            $this->exts->click_by_xdotool('a[id=invoiceListTable_next]:not([class*=disabled])');
            sleep(5);
            $this->processInvoicesNew($paging_count);
        } else if ($restrictPages > 0 && $paging_count < $restrictPages && $this->exts->querySelector('a[id=invoiceListTable_next]:not([class*=disabled])') != null) {
            $paging_count++;
            $this->exts->click_by_xdotool('a[id=invoiceListTable_next]:not([class*=disabled])');
            sleep(5);
            $this->processInvoicesNew($paging_count);
        }
    }

    private function extract_zip_save_pdf($zipfile)
    {
        $zip = new \ZipArchive;
        $res = $zip->open($zipfile);
        if ($res === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zipFileStat = $zip->statIndex($i);
                $fileName = $zipFileStat['name'];
                $baseName = basename($fileName);
                $fileInfo = pathinfo($baseName);
                // Define the full path for extraction
                $this->exts->log('filename: ' . $fileName);
                $extractPath = $this->exts->config_array['download_folder'] . $baseName;

                // Check if it's a PDF file

                if (isset($fileInfo['extension']) && strtolower($fileInfo['extension']) === 'pdf') {
                    $this->isNoInvoice = false;
                    $zip->extractTo($this->exts->config_array['download_folder'], [$fileName]);
                    $this->exts->new_invoice($fileInfo['filename'], "", "", $extractPath);
                    $this->exts->log("Extracted PDF: $extractPath");
                    sleep(1);
                }

                // Check if it's a ZIP file (nested ZIP)
                elseif (isset($fileInfo['extension']) && strtolower($fileInfo['extension']) === 'zip') {
                    $zip->extractTo($this->exts->config_array['download_folder'], [$fileName]);
                    $nestedZipPath = $extractPath;
                    $this->exts->log("Extracted nested ZIP: $nestedZipPath");

                    // Recursively process the nested ZIP
                    $this->extract_zip_save_pdf($nestedZipPath);
                }
            }

            $zip->close();
            unlink($zipfile); // Delete the original ZIP file

        } else {
            $this->exts->log(__FUNCTION__ . '::File extraction failed');
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
