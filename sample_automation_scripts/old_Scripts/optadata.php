<?php // I have updated login and download

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

    // Server-Portal-ID: 167039 - Last modified: 13.06.2025 13:52:46 UTC - User: 1

    public $baseUrl = 'https://kundencenter.optadata-gruppe.de';
    public $username_selector = 'input[name="username"]';
    public $password_selector = 'input[name="password"]';
    public $submit_login_selector = '#submitButton, #submit-button';
    public $check_login_success_selector = '[id$="_abmelden"], div[data-test="logout"]';

    public $accounting_documents = 0;
    public $restrictPages = 3;
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $this->accounting_documents = isset($this->exts->config_array["accounting_documents"]) ? (int)$this->exts->config_array["accounting_documents"] : 0;
        // $this->accounting_documents = 1;
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(10);
            $this->checkFillLogin();

            $this->waitFor('div[id = "errorMessageDiv"]');

            if ($this->exts->exists('div[id = "errorMessageDiv"]') || $this->exts->exists('table[class="login_table"]')) {
                $this->exts->openUrl('https://login-one.de/');
                $this->exts->log('new Login Portal');
                sleep(10);
                $this->checkFillLogin();
                $this->waitFor($this->check_login_success_selector);
            }
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->exists('div[data-test = "logout"]')) {
                $this->processNewPortalInvoice();
            } else {
                $this->doAfterLogin();
            }

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $this->exts->log(__FUNCTION__ . 'Login failed Status' . $this->exts->extract('div.alert-danger'));

            $error_text = strtolower($this->exts->extract('div#errorMessageDiv'));

            $this->exts->log(__FUNCTION__ . 'error_text' .  $error_text);

            if ($this->exts->exists('div.alert-danger')) {
                $errorMessage = $this->exts->extract('div.alert-danger');

                switch ($errorMessage) {
                    case 'Die Aktion ist nicht mehr gültig. Bitte fahren Sie nun mit der Anmeldung fort.':
                        $this->exts->account_not_ready();
                        break;
                    case 'Ungültiger Benutzername oder Passwort.':
                        $this->exts->loginFailure(1);
                        break;
                    default:
                        $this->exts->loginFailure();
                        break;
                }
            } elseif (stripos($error_text, strtolower('Sie konnten sich leider nicht erfolgreich anmelden, bitte überprüfen Sie Ihre E-Mail-Adresse oder Ihren Benutzernamen')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function checkFillLogin()
    {
        $this->waitFor($this->password_selector);
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->moveToElementAndClick('img[src="/img/header.png"]');
            sleep(4);
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(3);
            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }


    private function doAfterLogin()
    {
        $date_from = date('d.m.Y', strtotime('-3 months'));
        if ($this->restrictPages == 0) {
            $date_from = date('d.m.Y', strtotime('-800 days'));
        }
        // Open invoices url and download invoice

        $this->exts->moveToElementAndClick('[id$="datencenter"][id^="menu"]');
        sleep(2);
        $invoiceButton = $this->exts->getElements('tr[id*="Rechnungen"]  td[id*="Rechnungen_text"]')[1];
        $invoiceButton->click();
        sleep(5);

        // $this->exts->moveToElementAndType('.general_invoice_searchDialog input[type="text"]#bookingFrom', $date_from);
        $this->exts->moveToElementAndType('#searchDialog input#accountDateFrom', $date_from);
        $this->exts->capture("4-accounting-documents-search");
        $this->exts->moveToElementAndClick('#searchDialog span#buttonOK');
        sleep(5);
        if ($this->exts->exists('span.dijitButtonContents #dijit_form_Button_2_label')) {
            $this->exts->moveToElementAndClick('span.dijitButtonContents #dijit_form_Button_2_label');
        }

        $this->exts->capture("4-invoices-search");
        sleep(10);
        $this->processInvoices();


        if ($this->accounting_documents == 1) {
            // Open accounting documents page
            $this->exts->moveToElementAndClick('[id$="datencenter"][id^="menu"]');
            $this->exts->moveToElementAndClick('[id$="Abrechnungen_text"][id^="subMenuItem"]');
            sleep(5);
            $this->exts->moveToElementAndType('#searchDialog input#accountDateFrom', $date_from);
            $this->exts->capture("4-accounting-documents-search");
            $this->exts->moveToElementAndClick('#searchDialog span#buttonOK');
            sleep(5);


            if ($this->exts->exists('span.dijitButtonContents #dijit_form_Button_2_label')) {
                $this->exts->moveToElementAndClick('span.dijitButtonContents #dijit_form_Button_2_label');
            }

            $this->processAccountingDocuments();
        }

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }
    private function processInvoices($paging_count = 1)
    {
        sleep(10);
        $this->exts->capture("4-invoices-page");
        $rows = count($this->exts->getElements('[dojoattachpoint="contentNode"] [role="row"]'));
        $this->exts->log("total invoices count: " . $rows);
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('[dojoattachpoint="contentNode"] [role="row"]')[$i];

            $invoiceRow = $this->exts->getElements('table.dojoxGridRowTable tr')[$i];
            if ($this->exts->getElement('[role="row"]', $row) != null) continue;
            if ($this->exts->getElement('table tr', $row) == null) continue;

            $invoiceName = $this->exts->extract('td:nth-child(3)', $row, 'innerText');
            $invoiceDate = $this->exts->extract('td:nth-child(2)', $row, 'innerText');
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('td:nth-child(6)', $row, 'innerText'))) . ' EUR';
            $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('Date parsed: ' . $parsed_date);

            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $invoiceRow->click();
                $this->exts->moveToElementAndClick('span.dijitButtonContents #dijit_form_Button_2_label');

                if ($this->exts->exists('span[id*="toolbar.download"] > span.pdfIconDownload')) {
                    $this->exts->moveToElementAndClick('span[id*="toolbar.download"] > span.pdfIconDownload');
                    sleep(5);
                }
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
            $this->isNoInvoice = false;
        }

        // next page
        if ($this->exts->exists('.dojoxGridWardButton.dojoxGridnextPageBtn')) {
            if (
                $paging_count < 25 &&
                $this->exts->getElement('.dojoxGridWardButton.dojoxGridnextPageBtn') != null
            ) {
                $paging_count++;
                $this->exts->moveToElementAndClick('.dojoxGridWardButton.dojoxGridnextPageBtn');
                sleep(5);
                $this->processInvoices($paging_count);
            }
        }
    }
    // commented due to processInvoices and  processAccountingDocuments is similar code
    private function processAccountingDocuments()
    {
        sleep(10);
        $this->exts->capture("4-accounting-documents");
        $rows = count($this->exts->getElements('[dojoattachpoint="contentNode"] [role="row"]'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('[dojoattachpoint="contentNode"] [role="row"]')[$i];
            if ($this->exts->getElement('[role="row"]', $row) != null) continue;
            if ($this->exts->getElement('table tr', $row) == null) continue;

            $invoiceName = $this->exts->extract('td:nth-child(2)', $row, 'innerText');
            $invoiceDate = $this->exts->extract('td:nth-child(6)', $row, 'innerText');
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('td:nth-child(8)', $row, 'innerText'))) . ' EUR';
            $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
            $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('Date parsed: ' . $parsed_date);

            $this->isNoInvoice = false;

            // Download invoice if it not exisited
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice existed ' . $invoiceFileName);
            } else {
                $invoiceButton = 'div[id="postEntryTable_rowSelector_' . $i . '"]';
                // select
                if ($this->exts->exists($invoiceButton)) {
                    $this->exts->moveToElementAndClick($invoiceButton);
                    sleep(5);
                }

                if ($this->exts->exists('span[id*="toolbar.download"] > span.pdfIconDownload')) {
                    $this->exts->moveToElementAndClick('span[id*="toolbar.download"] > span.pdfIconDownload');
                    sleep(5);
                }

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }

                // unselect
                if ($this->exts->exists($invoiceButton)) {
                    $this->exts->moveToElementAndClick($invoiceButton);
                    sleep(2);
                }
            }
        }
    }


    public function processNewPortalInvoice()
    {
        $this->exts->openUrl('https://okc.optadata.de/');
        $this->waitFor('div[class="row ng-star-inserted"]');

        if (!$this->exts->exists('div[class="row ng-star-inserted"]')) {
            $this->checkFillLogin();
            $this->waitFor('div[class="row ng-star-inserted"]');
        }

        $rows = $this->exts->getElements('div[class="row ng-star-inserted"]  div[class*="status-card"]');
        $this->exts->log('Billing Count ' . count($rows));
        foreach ($rows  as $key => $row) {
            $row->click();
            sleep(10);
            $exportBtn = $this->exts->getElements('div[class*="control-bar"] button[class*="trigger-export"]')[3];

            $this->exts->click_element($exportBtn);
            sleep(4);

            $pdfBtn = $this->exts->getElements('div[class="export-panel overlay"] input[value="pdf"]')[3];
            $this->exts->click_element($pdfBtn);
            sleep(2);
            $downloadBtn =  $this->exts->getElements('div[class="col-buttons border-top"] button[class="btn btn-primary btn-block"]')[3];


            $url = $this->exts->getUrl();
            preg_match('/billing\/(\d+)/', $url, $matches);
            $number = $matches[1] ?? 'Invoice_' . $key; // use custom name if getting null

            $invoiceName = $number;
            $invoiceDate = '';
            $invoiceAmount = '';
            $parsed_date = '';
            $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf' : '';
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);
            $this->exts->log('Date parsed: ' . $parsed_date);


            $downloaded_file = $this->exts->click_and_download($downloadBtn, 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            $this->isNoInvoice = false;
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
