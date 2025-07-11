<?php // 

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

    // Server-Portal-ID: 653 - Last modified: 09.04.2024 13:53:43 UTC - User: 1

    /*Define constants used in script*/
    // Server-Portal-ID: 9892 - Last modified: 12.02.2025 06:17:57 UTC - User: 1

    public $baseUrl = 'https://gls-group.eu/DE/de/home';
    public $loginUrl = 'https://gls-group.eu/DE/de/home';
    public $invoicePageUrl = 'https://gls-group.eu/app/service/closed/page/DE/de/wiir001';

    public $username_selector = 'input[name="username"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[id*="login_submit"],button[name="login"]';

    public $check_login_failed_selector = 'div.message-box-forms.danger-message';
    public $check_login_success_selector = 'a[ng-click="callLogout();"]';
    public $restrictPages = 3;
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        // $this->exts->loadCookiesFromFile();
        // sleep(1);
        // $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(12);


            $this->exts->execute_javascript('
                var shadow = document.querySelector("#usercentrics-cmp-ui");
                if(shadow){
                    shadow.shadowRoot.querySelector(\'button[id="accept"]\').click();
                }
            ');
             sleep(2);
            $this->exts->waitTillPresent('li a[href*="authenticate"]', 10);

            //$this->exts->moveToElementAndClick('ul .btn-login.js-login-holder');
            if ($this->exts->exists('li a[href*="authenticate"]')) {
                $this->exts->moveToElementAndClick('li a[href*="authenticate"]');
            }


            sleep(2);
            $this->checkFillLogin();
            sleep(20);
        }
        // then check user logged in or not
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->querySelector($this->check_login_success_selector) == null; $wait_count++) {
        // 	$this->exts->log('Waiting for login...');
        // 	sleep(5);
        // }
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->doAfterLogin();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->getElement("//*[contains(text(), 'Invalid username or password.')]", '', 'xpath') != null) {
                $this->exts->log('Invalid username or password.');
                $this->exts->loginFailure(1);
            } else if ($this->exts->querySelector('a[ng-click*="gls-one.de"], a[id="countries.DE"]') != null) {
                $this->exts->log('This user belong to gls-one.');
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->username_selector);
        if ($this->exts->querySelector($this->password_selector) != null || $this->exts->querySelector($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            if ($this->exts->exists('button[name="login"]')) {
                $this->exts->moveToElementAndClick('button[name="login"]');
            }
            sleep(5);
            if (stripos($this->exts->extract('div.message-box-forms.danger-message'), 'Your login attempt timed out. Login will start from the beginning') !== false) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                if ($this->exts->exists('button[name="login"]')) {
                    $this->exts->moveToElementAndClick('button[name="login"]');
                }
                sleep(5);
            }
            if ($this->exts->exists('a[id*=loginContinue]')) {
                $this->exts->click_element('a[id*=loginContinue]');
                sleep(3);
            }
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function doAfterLogin()
    {
        if ($this->exts->config_array["only_custom_documents"] == '1') {
            $exportbelege_link = $this->getElementByText('a.nav-item', ['Exportbelege'], null, true);
            if ($exportbelege_link != null) {
                $this->exts->openUrl($exportbelege_link->getAttribute('href'));
                $this->downloadDocuments();
            }
        } elseif ($this->exts->exists('a[href*=documents]')) {
            $this->exts->openUrl('https://kundenportal.gls-group.eu/documents');
            $this->processInvoiceDocuments();
        } else {
            $this->exts->openUrl($this->invoicePageUrl);
            $this->exts->waitTillPresent('iframe#appIntegration', 20);
            $this->switchToFrame('iframe#appIntegration');
            sleep(2);
            $this->exts->capture("3.1-invoice-page");

            //Select Rechung type
            $this->exts->moveToElementAndClick('div.nice-select[id*="filter_document_type"]');
            sleep(2);
            $this->exts->moveToElementAndClick('div.nice-select[id*="filter_document_type"] li:nth-child(2)');
            sleep(3);
            $numberOfMonth = 6;
            if ((int)$this->restrictPages == 0) {
                $numberOfMonth = 24;
            }


            //Select accounts
            $this->exts->moveToElementAndClick('.gls-autocomplete-select.custom-select.form-control');
            sleep(5);


            $values = [];

            $rows = $this->exts->querySelectorAll('ul.gls-autocomplete-dropdown li p:nth-child(4)');
            foreach ($rows as $key => $row) {
                array_push($values, array(
                    'text' => trim($row->getText())
                ));
            }



            print_r($values);


            foreach ($values as $value) {
                $invoiceDate = $value['text'];
                $this->exts->log("invoiceDat:- " . $invoiceDate);

                $element = $this->exts->getElement('//ul[contains(@class, "gls-autocomplete-dropdown")]/li/p[contains(text(),"' . $invoiceDate . '")][1]', null, 'xpath');
                $this->exts->executeSafeScript("arguments[0].scrollIntoView({ behavior: 'smooth' });", [$element]);

                sleep(5);

                $selectAccount = $this->exts->getElement('//ul[contains(@class, "gls-autocomplete-dropdown")]/li/p[contains(text(),"' . $invoiceDate . '")][1]', null, 'xpath');

                $this->exts->log("invoiceDat:- " . $selectAccount->getAttribute("id"));
                if ($selectAccount !== null) {
                    try {
                        $this->exts->log('Click download button');

                        $selectAccount->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$selectAccount]);
                    }
                }



                sleep(5);
                for ($i = 0; $i <=  $numberOfMonth; $i++) {
                    $this->selectYear(date('Y', strtotime('-' . $i . ' month')));
                    $this->selectMonth(date('n', strtotime('-' . $i . ' month')));
                    $this->exts->moveToElementAndClick('button[id*="search_submit_button"]');
                    sleep(5);

                    if ($this->exts->exists("gls-data-table , table > tbody > tr")) {

                        $this->processInvoices();
                    }
                }

                $this->exts->moveToElementAndClick('.gls-autocomplete-select.custom-select.form-control');
                sleep(5);
            }
        }

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
    }
    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }

    private function selectMonth($month = 0)
    {
        $this->exts->moveToElementAndClick('div#wiir001_search_date_period_month_value_nice_select');
        sleep(1);
        $this->exts->moveToElementAndClick('div#wiir001_search_date_period_month_value_nice_select li:nth-child(' . $month . ')');
        sleep(2);
    }

    private function selectYear($year = 0)
    {
        $this->exts->moveToElementAndClick('span#wiir001_search_date_period_year_value_current');
        sleep(1);
        $option_year = $this->getElementByText('div#wiir001_search_date_period_year_value_container li', [$year], null, true);
        if ($option_year != null) {
            try {
                $option_year->click();
            } catch (Exception $e) {
                $this->exts->executeSafeScript("arguments[0].click()", array($option_year));
            }
        }
        sleep(2);
    }

    private function getElementByText($selector, $multi_language_texts, $parent_element = null, $is_absolutely_matched = true)
    {
        $this->exts->log(__FUNCTION__);
        if (is_array($multi_language_texts)) {
            $multi_language_texts = join('|', $multi_language_texts);
        }
        // Seaching matched element
        $object_elements = $this->exts->getElements($selector, $parent_element);
        foreach ($object_elements as $object_element) {
            $element_text = trim($object_element->getAttribute('textContent'));
            // First, search via text
            // If is_absolutely_matched = true, seach element matched EXACTLY input text, else search element contain the text
            if ($is_absolutely_matched) {
                $multi_language_texts = explode('|', $multi_language_texts);
                foreach ($multi_language_texts as $searching_text) {
                    if (strtoupper($element_text) == strtoupper($searching_text)) {
                        $this->exts->log('Matched element found');
                        return $object_element;
                    }
                }
                $multi_language_texts = join('|', $multi_language_texts);
            } else {
                if (preg_match('/' . $multi_language_texts . '/i', $element_text) === 1) {
                    $this->exts->log('Matched element found');
                    return $object_element;
                }
            }

            // Second, is search by text not found element, support searching by regular expression
            if (@preg_match($multi_language_texts, '') !== FALSE) {
                if (preg_match($multi_language_texts, $element_text) === 1) {
                    $this->exts->log('Matched element found');
                    return $object_element;
                }
            }
        }
        return null;
    }

    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('table > tbody > tr', 20);
        $this->exts->capture("4-invoices-page");

        $rows = count($this->exts->querySelectorAll('table > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->querySelectorAll('table > tbody > tr')[$i];
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 3 && $this->exts->querySelector('button.fa-file-pdf-o', $tags[2]) != null) {
                $this->isNoInvoice = false;
                $invoiceSelector = $this->exts->querySelector('button.fa-file-pdf-o', $tags[2]);
                $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-download-button-" . $i . "');", [$invoiceSelector]);
                $invoiceName = trim($this->exts->extract('div[id*="invoiceNumber"]', $row, 'innerText'));
                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = '';
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->moveToElementAndClick('button#custom-pdf-download-button-' . $i);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, '', $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }

        if ((int)$this->restrictPages == 0 && $paging_count < 10 && $this->exts->querySelector('button#next_page_button:not([disabled="disabled"])') != null) {
            $paging_count++;
            $this->exts->moveToElementAndClick('button#next_page_button:not([disabled="disabled"])');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }

    private function processInvoiceDocuments($paging_count = 1)
    {
        $this->exts->waitTillPresent('table tbody tr', 30);
        if (!$this->exts->exists('table tbody tr')) {
            $this->exts->refresh();
            $this->exts->waitTillPresent('table tbody tr', 30);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(6) div cup-button[data-test-id*="download"]', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('td:nth-child(3)', $row);
                $invoiceAmount =  '';
                $invoiceDate =  $this->exts->extract('td:nth-child(2)', $row);

                $downloadBtn = $this->exts->querySelector('td:nth-child(6) div cup-button[data-test-id*="download"]', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    // 'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
                ));
                $this->isNoInvoice = false;
            }
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            $downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);
            sleep(3);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        if ($restrictPages == 0 && $paging_count < 50 && $this->exts->querySelector('button[data-test-id*="next-button"]:not([disabled])')) {
            $paging_count++;
            $this->exts->log('Next invoice page found');
            $this->exts->click_element('button[data-test-id*="next-button"]:not([disabled])');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }

    function downloadDocuments()
    {
        $this->exts->waitTillPresent('th input[type="checkbox"]');
        $this->exts->capture('4-Document-page');
        $this->exts->log("Begin download invoice ");
        $this->exts->moveToElementAndClick('th input[type="checkbox"]');
        sleep(3);
        $this->exts->moveToElementAndClick('button[id*="search_download_button"]');
        sleep(10);
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
    }

    private function extract_zip_save_pdf($zipfile)
    {
        $this->isNoInvoice = false;
        $zip = new \ZipArchive;
        $res = $zip->open($zipfile);
        if ($res === TRUE) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zipPdfFile = $zip->statIndex($i);
                $fileInfo = pathinfo($zipPdfFile['name']);
                if ($fileInfo['extension'] === 'pdf') {
                    $invoice_name = basename($zipPdfFile['name'], '.pdf');
                    $zip->extractTo($this->exts->config_array['download_folder'], array(basename($zipPdfFile['name'])));
                    $saved_file = $this->exts->config_array['download_folder'] . basename($zipPdfFile['name']);
                    $this->exts->new_invoice($invoice_name, "", "", $saved_file);
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

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
