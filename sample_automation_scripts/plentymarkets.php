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

    // Server-Portal-ID: 159733 - Last modified: 16.05.2025 14:07:33 UTC - User: 1

    public $baseUrl = 'https://plentymarkets-cloud-de.com';
    public $plenty_id = '';
    public $check_login_success_selector = 'div#fullscreen-container terra-nav-bar nav ul  li a[href*="/personal-settings"]';
    public $document_types = ['Rechnung', 'Gutschrift'];
    public $isNoInvoice = true;
    public $lang = "de";

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->plenty_id = trim($this->exts->config_array['plenty_id']);
        // Load cookies
        // $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        //cookies
        if ($this->plenty_id != '' && $this->exts->exists('a[href*="' . $this->plenty_id . '"]')) {
            $this->exts->moveToElementAndClick('a[href*="' . $this->plenty_id . '"]');
            sleep(20);
            // $handles = $this->exts->webdriver->getWindowHandles();
            // if (count($handles) > 1) {
            //     $this->exts->webdriver->switchTo()->window(end($handles));
            // }
        }
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->checkFillLogin();
            sleep(15);
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            if ($this->exts->getElement('//li/a//*[contains(text(), "Orders")]', null, 'xpath') != null) {
                $this->lang = "eng";
                $this->document_types = ['Invoice', 'Credit note'];
            }
            $this->click_element('//li/a//*[contains(text(), "Auftr") or contains(text(), "Orders")]');
            sleep(5);
            foreach ($this->document_types as $document_type) {
                $this->search_documents($document_type);
                sleep(3);
                $this->processInvoices();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            $mes = strtolower($this->exts->extract('#error-message', null, 'innerText'));
            $this->exts->log($this->exts->extract('#error-message', null, 'innerText'));

            $loginError = strtolower($this->exts->extract('div.alert-dismissible', null, 'innerText'));
            $this->exts->log(__FUNCTION__ . 'loginError :: ' . $loginError);

            if (strpos($mes, 'gesperrt') !== false) {
                $this->exts->account_not_ready();
            }
            if ($this->exts->getElementByText('span#error-message', ['PlentyID not found.'], null, false)) {
                $this->exts->loginFailure(1);
            }
            if (strpos($mes, 'benutzername oder passwort falsch') !== false || strpos($mes, 'sie wurden zu einem anderen standort weitergeleitet') !== false || strpos($mes, 'you have been redirected to another location') !== false || $this->exts->config_array['plenty_id'] == null || trim($this->exts->config_array['plenty_id']) == '') {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('span#error-message', null, 'innerText')), 'rong user name or password') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos($loginError, strtolower('Incorrect email address or password')) !== false || strpos($loginError, 'sign in failed. incorrect username, pid or password') ) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function checkFillLogin()
    {
        $button_login_username = $this->exts->getElement('//span[contains(text(), " Sign in with plentyID and username ")]', null, 'xpath');
        if ($button_login_username != null && $this->plenty_id != '') {
            try {
                $this->exts->log('Click button_login_username button');
                $button_login_username->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click button_login_username button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$button_login_username]);
            }
            sleep(15);
        }

        if ($this->exts->exists('input[name="password"], input[formcontrolname="password"]')) {
            sleep(3);
            $this->exts->capture("2-login-page");
            $this->exts->log("Enter PlentyID");
            $this->exts->moveToElementAndType('input[name="pid"]', $this->plenty_id);

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType('input[name="username"], input[formcontrolname="email"]', $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType('input[name="password"], input[formcontrolname="password"]', $this->password);
            sleep(5);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick('button[type="submit"]');
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function click_element($selector_or_object)
    {
        if ($selector_or_object == null) {
            $this->exts->log(__FUNCTION__ . ' Can not click null');
            return;
        }

        $element = $selector_or_object;
        if (is_string($selector_or_object)) {
            $this->exts->log(__FUNCTION__ . '::Click selector: ' . $selector_or_object);
            $element = $this->exts->getElement($selector_or_object);
            if ($element == null) {
                $element = $this->exts->getElement($selector_or_object, null, 'xpath');
            }
            if ($element == null) {
                $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
            }
        }
        if ($element != null) {
            try {
                $this->exts->log(__FUNCTION__ . ' trigger click.');
                $element->click();
            } catch (\Exception $exception) {
                $this->exts->log(__FUNCTION__ . ' by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$element]);
            }
        }
    }

    private function search_documents($document_type)
    {
        $this->exts->switchToDefault();
        $this->exts->switchToFrame('iframe#gwtIframe');
        $this->click_element($this->exts->getElementByText('div.plentyListBoxCrossBrowserCSSDivInput div.gwt-Label', ['Dokument', 'Gutschrift', 'Rechnung', 'Document', 'Invoice', 'Credit note'], null, true));
        sleep(2);
        $this->click_element($this->exts->getElementByText('ul.PlentyItemListContentCrossBrowserWrapper span.gwt-InlineLabel', [$document_type], null, true));
        if ($this->exts->config_array['restrictPages'] == '0') {
            if ($this->lang == "de") {
                $date_from = date('d.m.y', strtotime('-1 years'));
            } else {
                $date_from = date('m/d/y', strtotime('-1 years'));
            }
        } else {
            if ($this->lang == "de") {
                $date_from = date('d.m.y', strtotime('-2 months'));
            } else {
                $date_from = date('m/d/y', strtotime('-2 months'));
            }
        }
        $this->exts->moveToElementAndType('input.PlentyDateBox', $date_from);
        $this->exts->capture('4.filled-document-type-' . $document_type);
        $this->exts->moveToElementAndClick('button[data-icon="icon-search"]');
    }
    private function processInvoices($paging_count = 1)
    {
        sleep(20);
        if ($this->exts->exists('iframe#gwtIframe')) {
            $this->exts->switchToFrame('iframe#gwtIframe');
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $row = $this->exts->getElement('div.PlentyPortlet');
        for ($i = 0; $row != null; $i++) {
            $this->exts->log('--------------------------');
            $row = $this->exts->getElements('div.PlentyPortlet')[$i];
            if ($row != null) {
                // $row->getLocationOnScreenOnceScrolledIntoView();
                $download_button = $this->exts->getElement('span.OrderInvoiceLabel span', $row);
                if ($download_button  != null) {
                    $this->isNoInvoice = false;
                    $invoiceName = $download_button->getAttribute('innerText');
                    $invoiceName = preg_replace('/[^\w]/', '', $invoiceName);
                    $invoiceDate = trim($this->exts->extract('span.OrderInsertTimeLabel', $row, 'innerText'));
                    $invoiceAmount = '';

                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ');
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';

                    if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->click_element($download_button);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }

                        // Close download window
                        // $handles = $this->exts->webdriver->getWindowHandles();
                        // if (count($handles) > 1) {
                        //     $this->exts->webdriver->switchTo()->window(end($handles));
                        //     $this->exts->webdriver->close();
                        //     $handles = $this->exts->webdriver->getWindowHandles();
                        // }

                        $handles = $this->exts->get_all_tabs();
                        if (count($handles) > 1) {
                            $lastTab = end($handles);
                            $this->exts->closeTab($lastTab);
                            $handles = $this->exts->get_all_tabs();
                            if (count($handles) > 1) {
                                $this->exts->switchToTab($handles[0]);
                            }
                        }
                        // $this->exts->webdriver->switchTo()->window($handles[0]);
                        if ($this->exts->exists('iframe#gwtIframe')) {
                            $this->exts->switchToFrame('iframe#gwtIframe');
                        }
                    }
                }
            }
        }

        if ($this->exts->allExists(['span.icon-reload + div + div + div.gwt-Label', 'span.icon-reload + div div.gwt-Label'])) {
            $total_record = trim(array_pop(explode('von ', $this->exts->extract('span.icon-reload + div + div + div.gwt-Label'))));
            $record_per_page = trim($this->exts->extract('span.icon-reload + div div.gwt-Label'));
            $total_page = $total_record == 0 ? 0 : intdiv($total_record, $record_per_page);
            // next paging
            if ($this->exts->exists('span.icon-next') && $paging_count < $total_page) {
                $this->exts->moveToElementAndClick('span.icon-next');
                sleep(1);
                $paging_count++;
                $this->processInvoices($paging_count);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
