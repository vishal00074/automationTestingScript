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

    // Server-Portal-ID: 7524 - Last modified: 25.04.2025 13:18:33 UTC - User: 1

    public $baseUrl = 'https://klarmobil.de/';
    public $invoicePageUrl = 'https://www.klarmobil.de/online-service/meine-rechnungen';
    public $username_selector = 'input#username';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';
    public $check_login_success_selector = 'form#logout_form, .main-navigation a[href="/online-service/meine-daten"], button[data-testid="logout"]';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_extensions();
        sleep(5);

        // Load cookies
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        $this->accept_consent();


        if ($this->isExists('header .user__link a[href*="/onlineservice"]')) {
            $this->exts->moveToElementAndClick('header .user__link a[href*="/onlineservice"]');
        }
        sleep(5);
        $this->accept_consent();
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            if (
                strpos($this->exts->get_page_content(), 'ERR_TOO_MANY_REDIRECTS') !== false ||
                strpos($this->exts->get_page_content(), 'Access Denied') !== false ||
                strpos($this->exts->get_page_content(), 'ERR_CONNECTION_TIMED_OUT') !== false
            ) {
                $this->exts->log(__FUNCTION__ . "ERROR load page");
                $this->clearChrome();
                sleep(1);
            }

            // $this->exts->clearCookies();// Expired cookie doern't affect to login but it make download getting error, so clear it.
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);
            $this->checkFillLogin();

            if ($this->exts->getElement($this->username_selector) != null) {
                $this->checkFillLogin();
            }
        }

        if ($this->checkLogin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);

            $this->accept_consent();
            $this->check_multil_contract();

            if ($this->isExists('[data-qa="no-permission-skeleton"]')) {
                $this->exts->no_permission();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());

            // Aufgrund zu vieler fehlerhafter Login-Versuche wurde der Login zu Ihrer Sicherheit bis
            if (strpos(strtolower($this->exts->extract('span.status-message__text')), 'deine eingegebene e-mail-adresse und das passwort') !== false) {
                $this->exts->loginFailure();
            } else if ($this->exts->urlContains('onlineservice/fehler') && $this->isExists('a[href*="logout"]') || $this->exts->urlContains('/onlineservice/benutzer-verknuepfen')) {
                $this->exts->account_not_ready();
            } else if (
                $this->exts->urlContains('onlineservice/info') &&
                (strpos($this->exts->extract('span.status-message__text'), 'Zu Ihrem Zugang konnten keine aktiven') !== false ||
                    strpos($this->exts->extract('span.status-message__text'), 'Zu Deinem Online-Konto existiert kein aktiver Vertrag') !== false ||
                    strpos($this->exts->extract('span.status-message__text'), 'Zu Ihrem Online-Account existiert kein aktiver Vertrag') !== false)
            ) {
                $this->exts->account_not_ready();
            } else if ($this->exts->urlContains('benutzer-verknuepfen') || $this->exts->urlContains('user-link')) {
                $this->exts->account_not_ready();
            } else if ($this->isExists('div#error-cs-email-invalid') || $this->isExists('span[id*="error-element-password"]')) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->capture("else-login-failed-page");
                $this->exts->loginFailure();
            }
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Tab');
        $this->exts->type_key_by_xdotool('Return');
        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }
    private function accept_consent($reload_page = false)
    {
        $this->exts->switchToDefault();
        if ($this->exts->check_exist_by_chromedevtool('iframe[src*="privacy"]')) {
            $this->switchToFrame('iframe[src*="privacy"]');
            $this->waitFor('button[aria-label*="Alle akzeptieren"]', 30);
            if ($this->isExists('button[aria-label*="Alle akzeptieren"]')) {
                $this->exts->click_by_xdotool('button[aria-label*="Alle akzeptieren"]');
                sleep(5);
            }

            $this->exts->switchToDefault();
            if ($reload_page) {
                $this->exts->refresh();
                sleep(15);
            }
        }
    }

    // Custom Exists function to check element found or not
    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }


    public function waitFor($selector, $seconds = 10)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function disable_extensions()
    {
        $this->exts->openUrl('chrome://extensions/'); // disable Block origin extension
        sleep(2);
        $this->exts->execute_javascript("
        let manager = document.querySelector('extensions-manager');
        if (manager && manager.shadowRoot) {
            let itemList = manager.shadowRoot.querySelector('extensions-item-list');
            if (itemList && itemList.shadowRoot) {
                let items = itemList.shadowRoot.querySelectorAll('extensions-item');
                items.forEach(item => {
                    let toggle = item.shadowRoot.querySelector('#enableToggle[checked]');
                    if (toggle) toggle.click();
                });
            }
        }
    ");
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

    private function checkFillLogin()
    {
        $this->waitFor($this->username_selector);
        if ($this->exts->getElement($this->username_selector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(5);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);

            if ($this->remember_me_selector != '') {
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            }
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            sleep(15); // Portal itself has one second delay after showing toast

            $this->solve_login_cloudflare();

            if ($this->isExists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(10);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }


    private function solve_login_cloudflare()
    {
        $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
        $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
        $this->exts->capture("cloudflare-checking");
        if (
            !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
            $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
        ) {
            for ($waiting = 0; $waiting < 10; $waiting++) {
                sleep(2);
                if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                    sleep(3);
                    break;
                }
            }
        }

        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-1", true);
            sleep(3);
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-2", true);
                sleep(15);
            }
            if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
                $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
                sleep(5);
                $this->exts->capture("cloudflare-clicked-3", true);
                sleep(15);
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
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if ($this->isExists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }

            if ($this->isExists('div[data-qa="billing-account-selection"]')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }

            
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function check_multil_contract()
    {
        if ($this->isExists('[data-qa="period"] select[disabled]')) {
            $this->exts->moveToElementAndClick('.sub-navigation li a[href*="/meine-rechnungen"]');
            sleep(15);
        }
        if ($this->exts->getElement('div.cookie-message') != null) {
            $cookie = $this->exts->getElement('div.cookie-message');
            $this->exts->execute_javascript('arguments[0].style.display = "none";', [$cookie]);
        }

        if ($this->isExists('.select-billing-account [data-qa$="-billing-account-option"].collapsed')) {
            $this->exts->moveToElementAndClick('.select-billing-account [data-qa$="-billing-account-option"].collapsed');
            sleep(2);
        }
        $this->exts->capture("4-multi-contract-checking");

        $contracts = $this->exts->getElementsAttribute('.select-billing-account [data-qa$="-billing-account-option"] .option-preview, [data-qa$="-billing-account-option"] .customer-product', 'innerText');
        if (count($contracts) > 1) {
            foreach ($contracts as $contract_label) {
                sleep(5);
                $this->exts->log('Processing contract: ' . $contract_label);
                if ($this->isExists('.select-billing-account [data-qa$="-billing-account-option"].collapsed')) {
                    $this->exts->moveToElementAndClick('.select-billing-account [data-qa$="-billing-account-option"].collapsed');
                    sleep(2);
                }
                sleep(5);
                $options = $this->exts->getElements('.select-billing-account [data-qa$="-billing-account-option"] .option-preview, [data-qa$="-billing-account-option"] .customer-product');
                foreach ($options as $option) {
                    $option_text = $option->getAttribute('innerText');
                    if (strpos($contract_label, $option_text) !== false) {
                        $this->exts->click_element($option);
                        // $option->click();
                        break;
                    }
                }
                sleep(5);
                $this->processInvoices();
            }
        } else {
            $this->processInvoices();
        }
    }

    private function changeSelectbox($select, $value)
    {
        $this->exts->execute_javascript('
        (function() {
            const box = document.querySelector("' . addslashes($select) . '");
            if (box) {
                box.value = "' . addslashes($value) . '";
                box.dispatchEvent(new Event("change"));
            }
        })();
    ');
    }


    public $totalInvoices = 0;

    private function processInvoices($count = 1)
    {
        $this->exts->update_process_lock();
        sleep(5);
        $this->waitFor('[dataqa="invoice-item"]', 10);
        $this->exts->capture("4-invoices-page");

        $invoice_sections = $this->exts->getElements('[dataqa="invoice-item"]');
        foreach ($invoice_sections as $invoice_section) {

            if ($this->totalInvoices >= 100) {
                return;
            }

            $download_button = $this->exts->getElement('[data-qa="invoice-table-content"] [data-qa="invoice-download-pdf"]', $invoice_section);
            if ($download_button != null) {
                $this->isNoInvoice = false;
                $invoiceName = $this->exts->extract('.title-bar .subheading', $invoice_section, 'innerText');
                $invoiceName = end(explode(',', $invoiceName));
                $invoiceName = end(explode('.', $invoiceName));
                $invoiceName = trim($invoiceName);
                // in case invoice name is empty then use custom name
                if (empty($invoiceName)) {
                    $invoiceName = time();
                    sleep(1);
                }
                $invoiceFileName =  $invoiceName . '.pdf';
                $invoiceDate = $this->exts->extract('span.title-text span.title span:nth-child(1)', $invoice_section);
                $invoiceAmount = $this->exts->extract('span.title-text span.title span:nth-child(2)', $invoice_section);

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);


                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(10);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                        $this->totalInvoices++;
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No pdf ' . $invoiceFileName);
                    }
                }
            }
        }
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->log(__FUNCTION__ . 'restrictPages:: ' . $restrictPages);
        if ($restrictPages == 0 && $count < 4) {
            $years = $this->exts->getElements('select[data-qa="period-select"] option');

            $value  = $years[$count]->getHtmlAttribute("value");
            $this->exts->log(__FUNCTION__ . 'Year:: ' . $value);

            $this->changeSelectbox('select[data-qa="period-select"]', $value);
            sleep(5);

            // try second time
            $years = $this->exts->getElements('select[data-qa="period-select"] option');

            $value  = $years[$count]->getHtmlAttribute("value");
            $this->exts->log(__FUNCTION__ . 'Year:: ' . $value);

            $this->changeSelectbox('select[data-qa="period-select"]', $value);
            sleep(10);
            $count++;
            $this->processInvoices($count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
