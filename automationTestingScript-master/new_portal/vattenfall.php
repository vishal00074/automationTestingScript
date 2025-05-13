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

    /*Define constants used in script*/
    public $baseUrl = 'https://portal.realestate.vattenfall.de/';
    public $loginUrl = 'https://portal.realestate.vattenfall.de/';
    public $invoicePageUrl = '';

    public $username_selector = 'input[name="username"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input[name="login"]';

    public $check_login_failed_selector = 'span[class*="kc-feedback-text"]';
    public $check_login_success_selector = 'li a[href*="bundleCustomerNumber"]';

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
        if ($this->exts->exists('div.cookie-container button')) {
            $this->exts->moveToElementAndClick('div.cookie-container button');
            sleep(7);
        }
        $this->exts->loadCookiesFromFile();
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            sleep(5);
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
            if ($this->exts->exists('div.cookie-container button')) {
                $this->exts->moveToElementAndClick('div.cookie-container button');
                sleep(7);
            }

            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(7);

            if ($this->exts->exists('div.cookie-container button')) {
                $this->exts->moveToElementAndClick('div.cookie-container button');
                sleep(7);
            }

            if ($this->exts->exists('a[href*="tab=invoices#bundle-consumption-costs-overview"]')) {
                $this->exts->moveToElementAndClick('a[href*="tab=invoices#bundle-consumption-costs-overview"]');
            }
            $this->processInvoices();

            $this->exts->success();
        } else {
            if (stripos($this->exts->extract($this->check_login_failed_selector), 'Der Zugriff wurde verweigert. Bitte überprüfen Sie Ihre Eingabedaten.') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } elseif (stripos($this->exts->extract($this->check_login_failed_selector), 'Die Aktion ist nicht mehr gÃ¼ltig. Bitte fahren Sie nun mit der Anmeldung fort.') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);

        $this->exts->waitTillPresent($this->username_selector);
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
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(2);
                }

                $this->exts->capture("1-login-page-filled");

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                    sleep(5);
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
    public  function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
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

    public function processInvoices()
    {
        $this->exts->log(__FUNCTION__);

        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('input#invoice');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }

        if ($this->exts->exists('input#invoice')) {
            $this->exts->moveToElementAndClick('input#invoice');
            sleep(2);
            $this->exts->moveToElementAndClick('div#documentsDownloadButton');
            sleep(7);
            $this->exts->moveToElementAndClick('button.Button--success');

            $this->downloadInvoices();
        }

        if ($this->exts->exists('input#collectiveInvoice')) {
            $this->exts->moveToElementAndClick('input#collectiveInvoice');
            sleep(7);
            $this->exts->moveToElementAndClick('div#documentsDownloadButton');
            sleep(7);
            $this->exts->moveToElementAndClick('button.Button--success');
            $this->downloadInvoices();
        }

        if ($this->exts->exists('input#budgetBillingPlan')) {
            $this->exts->moveToElementAndClick('input#budgetBillingPlan');
            sleep(7);
            $this->exts->moveToElementAndClick('div#documentsDownloadButton');
            sleep(7);
            $this->exts->moveToElementAndClick('button.Button--success');
            $this->downloadInvoices();
        }
    }

    private function downloadInvoices($count = 0)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table#bundleRootBillingDocumentsTable tbody tr');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('table#bundleRootBillingDocumentsTable tbody tr');
        foreach ($rows as $key => $row) {
            $download_link = $this->exts->getElement('td:nth-child(5) a', $row);
            if ($download_link != null) {
                $invoiceUrl = $download_link->getAttribute("href");
                $invoiceDate = $this->exts->extract('td:nth-child(1)', $row);
                $invoiceAmount = '';

                array_push($invoices, array(
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $this->exts->openUrl($invoice['invoiceUrl']);

            $this->exts->wait_and_check_download('zip');
            $downloaded_file = $this->exts->find_saved_file('zip');

            $invoiceFileName = basename($downloaded_file);
            $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoiceName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->extract_single_zip_save_pdf($downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            sleep(2);
        }
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        if (!$this->exts->exists('li[id="bundleRootBillingDocumentsTable_next"][class="paginate_button next disabled"] ')) {
            while ($count < $restrictPages && $this->exts->exists('li[id="bundleRootBillingDocumentsTable_next"]')) {
                $this->exts->moveToElementAndClick('li[id="bundleRootBillingDocumentsTable_next"]');
                sleep(7);
                $count++;
                $this->downloadInvoices($count);
            }
        }
    }

    /**
     * Processes a ZIP file.
     *
     * @param string $zipfile The ZIP file to process.
     * @return void
     */
    public function extract_single_zip_save_pdf($zipfile)
    {
        $zip = new \ZipArchive;
        $res = $zip->open($zipfile);
        if ($res === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zipPdfFile = $zip->statIndex($i);
                $fileName = basename($zipPdfFile['name']);

                $this->exts->log(__FUNCTION__ . '::Extracted file name: ' . $fileName);

                $fileInfo = pathinfo($fileName);

                $this->exts->log(__FUNCTION__ . '::Pathinfo: ' . print_r($fileInfo, true));

                if (isset($fileInfo['extension'])) {
                    if (strtolower($fileInfo['extension']) === 'zip') {
                        $this->exts->log('zip file verified');
                    }
                    if (strtolower($fileInfo['extension']) === 'pdf') {
                        $this->exts->log('pdf file verified');
                    }
                    $this->isNoInvoice = false;
                    $zip->extractTo($this->exts->config_array['download_folder'], $fileName);
                    $saved_file = $this->exts->config_array['download_folder'] . $fileName;

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
