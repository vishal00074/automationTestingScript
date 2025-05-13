<?php

/**
 * Replace waitTillPresent and exists
 */

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

    // Server-Portal-ID: 8818 - Last modified: 14.04.2025 13:48:38 UTC - User: 1

    public $baseUrl = 'https://billing.ups.com/home';
    public $username_selector = 'input[name="userID"]';
    public $password_selector = 'input[name="password"]';
    public $check_login_failed_selector = 'form[name="LoginTest"] p#errorMessages';
    public $check_login_success_selector = 'a[href*="/logout"]';
    public $restrictPages = 3;
    public $isNoInvoice = true;
    public $account_number = '';
    public $user_lang = '';
    public $only_plan_invoice = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_extensions();

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->account_number = isset($this->exts->config_array["account_number"]) ? $this->exts->config_array["account_number"] : '';
        $this->user_lang = isset($this->exts->config_array["user_lang"]) ? $this->exts->config_array["user_lang"] : '';
        $this->only_plan_invoice = isset($this->exts->config_array["only_plan_invoice"]) ? (int) $this->exts->config_array["only_plan_invoice"] : 0;

        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            if ($this->check_solve_fobidden()) {
                $this->exts->openUrl($this->baseUrl);
                sleep(15);
            }
            if ($this->check_solve_fobidden()) {
                $this->exts->openUrl($this->baseUrl);
                sleep(15);
            }
            //Redirecting to Login...
            if ($this->isExists('#ups-main-container .ups-landing-container')) {
                sleep(10);
            }
            $this->checkFillLogin();
            sleep(15);
            if ($this->check_solve_fobidden()) {
                $this->exts->openUrl($this->baseUrl);
                $this->checkFillLogin();
                sleep(15);
            }

            if (stripos($this->exts->extract('#ups-main-container'), 'Anmelden im Rechnungscenter') !== false) {
                sleep(15);
            }
            // message: "Logging in Billing Center .... We're sorry, but there's a problem, please try again later.". open url and login again
            if (stripos($this->exts->extract('div.ups-landing-container'), 're sorry, but there\'s a problem, please try again later') !== false) {
                $this->clearChrome();
                $this->exts->openUrl($this->baseUrl);
                sleep(15);
                $this->checkFillLogin();
                sleep(25);
            }
            if ($this->isExists('input[name="legalAccepted"]')) {
                $this->exts->moveToElementAndClick('input[name="legalAccepted"]:not(:checked) + label');
                $this->exts->moveToElementAndClick('button[name="accept"]');
                sleep(15);
            }
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            if ($this->isExists('#__tealiumImplicitmodal[style*="display: block"] button.close_btn_thick')) {
                $this->exts->moveToElementAndClick('#__tealiumImplicitmodal[style*="display: block"] button.close_btn_thick');
            }

            $this->exts->openUrl($this->baseUrl);
            sleep(9);
            $this->exts->click_element('button.btn-change-country');
            sleep(5);
            $this->exts->click_element('select[name="globalBusinessUnitType"]');
            sleep(5);
            $this->exts->capture("3-business-unit-checking");
            $business_units = $this->exts->getElementsAttribute('select[name="globalBusinessUnitType"] option', 'value');
            $this->exts->click_element('select[name="globalBusinessUnitType"]'); // click to close select box
            sleep(5);
            foreach ($business_units as $business_unit_id) {
                $this->select_language_and_business_unit($business_unit_id);
                sleep(10);

                if ($this->only_plan_invoice == 1) {
                    // https://billing.ups.com/ups/billing/plan
                    $this->exts->moveToElementAndClick('a#side-nav-link-plan-invoices');
                    sleep(10);
                    $this->processPlanInvoices();
                } else {
                    $this->exts->moveToElementAndClick('a#side-nav-link-my-invoices');
                    sleep(10);
                    $this->processInvoices();
                }
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());
            if (stripos($this->exts->extract('#Login #generic_error', null, 'innerText'), 'again or visit the Forgot Username/Password') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('p.ups-formError', null, 'innerText')), 'or maybe you mistyped the') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->isExists('.ups-enroll-section #enrollmentAccountDetailsSection, form[action="/lasso/veremail"] a[href*="javascript:processEmailResend"]')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function checkFillLogin()
    {

        $this->exts->capture("2-login-page");
        $this->waitFor($this->username_selector, 15);
        if ($this->exts->getElement($this->username_selector) != null) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->capture("2-username-filled");
            // $this->exts->moveToElementAndClick('button[name="getTokenWithPassword1"]');
            $this->exts->click_element('button[name="getTokenWithPassword1"]');
            sleep(10);
        }
        $this->waitFor($this->password_selector, 15);
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->log("Enter Password");
            // $this->exts->moveToElementAndType($this->password_selector, $this->password);
            $this->exts->click_by_xdotool($this->password_selector, 5, 5);
            sleep(3);
            $this->exts->type_text_by_xdotool($this->password);
            sleep(3);
            $this->exts->capture("2-password-filled");
            $this->checkFillRecaptcha();


            $this->exts->moveToElementAndClick('button[name="getTokenWithPassword"]');
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    private function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->isExists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas = $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->execute_javascript('
            if(document.querySelector("[data-callback]") != null){
                return document.querySelector("[data-callback]").getAttribute("data-callback");
            }

            var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ return;}console.log(prop);
                        try {
                            if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
                                if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                                } else { deep++;
                                     for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                                    }
                                } catch(ex) { console.log("ERROR in function: " + ex); return; }
                            }

            recurse(___grecaptcha_cfg.clients[0], "", 0);
            return found ? "___grecaptcha_cfg.clients[0]." + result : null;
    ');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

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

    private function check_solve_fobidden()
    {
        $is_error = false;
        if (stripos($this->exts->extract('div.redErrorBold', null, 'innerText'), 'application encountered an error during processing') !== false) {
            $this->exts->capture('processing-error');
            $is_error = true;
        }
        if (stripos($this->exts->extract('body h1', null, 'innerText'), '403 forbidden') !== false) {
            $this->exts->capture('forbidden');
            $is_error = true;
        }
        if (stripos($this->exts->extract('body'), 'Access Denied') !== false) {
            $this->exts->capture('access-denied');
            $is_error = true;
        }

        if ($is_error) {
            $this->clearChrome();
        }

        return $is_error;
    }
    // solve block
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

    private function clearChrome()
    {

        $this->exts->log("Clearing browser history, cookies, and cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);

        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
        }
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(1);

        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
        }
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(10);
        $this->exts->capture("after-clear");
    }

    private function processInvoices()
    {
        sleep(15);
        if ($this->isExists('#__tealiumImplicitmodal[style*="display: block"] button.close_btn_thick')) {
            $this->exts->moveToElementAndClick('#__tealiumImplicitmodal[style*="display: block"] button.close_btn_thick');
        }


        if (!$this->isExists('input[type="checkbox"][name*="invoice-"]:not(:checked) + label') && $this->isExists('#clearFilterLink')) {
            $this->exts->capture("no-invoice-by-filter");
            $this->exts->click_element('#clearFilterLink');
            sleep(15);
            $this->exts->capture("4-clear_filter");
        }
        $this->exts->click_element('button.btn-calendar');
        sleep(5);

        $this->exts->click_element('label[for="calendarOptionType_AVAILABLE"]');
        sleep(4);
        $this->exts->click_element('button#invoice-search-date-btn-apply');
        sleep(15);

        $user_selected_accounts = [];
        $this->account_number = trim($this->account_number);
        if ($this->account_number != '') {
            $user_selected_accounts = explode(',', $this->account_number);
            array_walk($user_selected_accounts, function (&$element) {
                $element = trim($element);
            });
        }

        $this->waitFor('.recent-payments-container table > tbody > tr');
        $this->exts->capture("4-invoices-page");
        for ($paging = 1; $paging < 50; $paging++) {
            $rows_count = count($this->exts->getElements('.recent-payments-container table > tbody > tr'));
            for ($i = 0; $i < $rows_count; $i++) {
                $row = $this->exts->getElements('.recent-payments-container table > tbody > tr')[$i];
                if ($this->exts->getElement('input[type="checkbox"][name*="invoice-"]:not(:checked) + label', $row) != null) {
                    $check_box = $this->exts->getElement('input[type="checkbox"][name*="invoice-"]:not(:checked) + label', $row);
                    $tags = $this->exts->getElements('td', $row);
                    $account_number_1 = $this->exts->extract('td:nth-child(5)', $row);
                    $account_number_1 = trim($account_number_1);
                    $this->exts->log('Invoice account number ' . $account_number_1);
                    $account_number_2 = $tags[1]->getText();
                    $account_number_2 = trim($account_number_2);
                    $this->exts->log('Invoice account number ' . $account_number_2);
                    // $this->exts->log('Selected account ' . $user_selected_accounts);
                    $message = 'Selected account: ' . implode(', ', $user_selected_accounts);
                    $this->exts->log($message);

                    // if ($check_box != null && (in_array($account_number, $user_selected_accounts) || count($user_selected_accounts) == 0)) {
                    if (isset($check_box) && (in_array($account_number_2, $user_selected_accounts) || in_array($account_number_1, $user_selected_accounts) || empty($user_selected_accounts))) {
                        $this->exts->log('selected-check-box');
                        $this->exts->capture('selected-check-box');
                        $this->exts->click_element($check_box);
                        sleep(1);
                        if ($this->isExists('.modal.fade.top.show button.close')) {
                            $this->exts->moveToElementAndClick('.modal.fade.top.show button.close');
                            sleep(1);
                        }
                        $this->isNoInvoice = false;
                    }
                }
            }

            if ($this->isExists('button.btn-download-invoices:not([disabled])')) {
                $this->exts->click_element('button.btn-download-invoices:not([disabled])');
                sleep(5);
                $this->exts->click_element('.modal-dialog[role="document"] label[for="downloadOptionType_pdf"]');
                sleep(5);
                $this->exts->execute_javascript("arguments[0].click();", [$this->exts->querySelector('.modal-dialog[role="document"] button#download-multiple-invoice-btn-download')]);
                // $this->exts->click_element('.modal-dialog[role="document"] button#download-multiple-invoice-btn-download');
                sleep(7);
                if (count($this->exts->querySelectorAll('.modal-dialog[role="document"] table tbody tr')) == 1) {
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf');
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice('', '', '', $downloaded_file);
                    } else {
                        $this->exts->wait_and_check_download('zip');
                        $this->exts->wait_and_check_download('zip');
                        $downloaded_file = $this->exts->find_saved_file('zip');

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->log($downloaded_file);
                            $pdf_files = $this->extract_zip_save_pdf($downloaded_file);
                            foreach ($pdf_files as $pdf_file) {
                                $invoiceFileName = basename($pdf_file);
                                $invoiceName = explode('.PDF', $invoiceFileName)[0];
                                $this->exts->log($invoiceName);
                                if ($this->exts->invoice_exists($invoiceName)) {
                                    $this->exts->log('Invoice existed ' . $invoiceName);
                                } else {
                                    $this->exts->new_invoice($invoiceName, '', '', $pdf_file);
                                }
                            }
                        }
                    }
                } else {
                    $this->exts->wait_and_check_download('zip');
                    $this->exts->wait_and_check_download('zip');
                    $this->exts->wait_and_check_download('zip');
                    $downloaded_file = $this->exts->find_saved_file('zip');

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->log($downloaded_file);
                        $pdf_files = $this->extract_zip_save_pdf($downloaded_file);
                        foreach ($pdf_files as $pdf_file) {
                            $invoiceFileName = basename($pdf_file);
                            $invoiceName = explode('.PDF', $invoiceFileName)[0];
                            $this->exts->log($invoiceName);
                            if ($this->exts->invoice_exists($invoiceName)) {
                                $this->exts->log('Invoice existed ' . $invoiceName);
                            } else {
                                $this->exts->new_invoice($invoiceName, '', '', $pdf_file);
                            }
                        }
                    } else {
                        $this->exts->wait_and_check_download('pdf');
                        // If just one invoice, It doesn't create zip file, pdf file downloaded instead
                        $downloaded_file = $this->exts->find_saved_file('pdf');
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice('', '', '', $downloaded_file);
                        }
                    }
                }

                $this->exts->click_element('.modal-dialog[role="document"] button.close');
                sleep(1);
            } else {
                $this->exts->capture("4-no-download-button");
            }

            // Uncheck all downloaded row
            $checked_boxs = $this->exts->getElements('.recent-payments-container table input[type="checkbox"][name*="invoice-"]:checked + label');
            foreach ($checked_boxs as $checked_box) {
                $this->exts->click_element($checked_box);
            }
            sleep(3);

            if ($this->isExists('a.paginate_button.next:not(.disabled)') && $this->restrictPages == 0) {
                $this->exts->click_element('a.paginate_button.next:not(.disabled)');
                sleep(5);
            } else {
                break;
            }
        }
    }

    private function processPlanInvoices()
    {
        sleep(10);
        $this->exts->click_element('button.btn-calendar');
        sleep(4);

        $this->exts->click_element('label[for="calendarOptionType_AVAILABLE"]');
        sleep(2);
        $this->exts->click_element('button#invoice-search-date-btn-apply');
        sleep(15);
        $this->exts->capture("4-plan-invoices");
        $current_row_index = 0;
        $current_page_number = 1;

        $this->waitFor('.recent-payments-container tbody tr', 15);
        for ($i = 0; $i < 5000; $i++) {
            $rows = $this->exts->getElements('.recent-payments-container tbody tr');
            $current_row = $rows[$current_row_index];
            if ($current_row) {
                $plan_overview_button = $this->exts->getElement('th button.btn-datatable-text-link', $current_row); // Click plan number, will open plan overview
                $this->exts->click_element($plan_overview_button);
                sleep(5);
                // select each invoice
                $invoice_element_count = count($this->exts->getElements('.recent-payments-container table > tbody > tr'));
                for ($e = 0; $e < $invoice_element_count; $e++) {
                    $invoice_element = $this->exts->getElements('.recent-payments-container table > tbody > tr')[$e];
                    $check_box = $this->exts->getElement('input[type="checkbox"][name*="invoice-"]:not(:checked) + label', $invoice_element);
                    $account_number = $this->exts->extract('th + td', $invoice_element, 'innerText');
                    $account_number = trim($account_number);
                    $this->exts->log('Invoice account number ' . $account_number);
                    if ($check_box != null && (in_array($account_number, $user_selected_accounts) || count($user_selected_accounts) == 0)) {
                        $this->exts->click_element($check_box);
                        sleep(1);
                        if ($this->isExists('.modal.fade.top.show button.close')) {
                            $this->exts->moveToElementAndClick('.modal.fade.top.show button.close');
                            sleep(1);
                        }
                        $this->isNoInvoice = false;
                    }
                }

                // then download zip file of all invoices
                if ($this->isExists('button.btn-download-invoices:not([disabled])')) {
                    $this->exts->click_element('button.btn-download-invoices:not([disabled])');
                    sleep(5);
                    $this->exts->click_element('.modal-dialog[role="document"] label[for="downloadOptionType_pdf"]');
                    sleep(5);
                    $this->exts->execute_javascript("arguments[0].click();", [$this->exts->querySelector('.modal-dialog[role="document"] button#download-multiple-invoice-btn-download')]);
                    // $this->exts->click_element('.modal-dialog[role="document"] button#download-multiple-invoice-btn-download');
                    sleep(7);
                    if (count($this->exts->getElements('.modal-dialog[role="document"] table tbody tr')) == 1) { // one invoice, file is pdf
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf');
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice('', '', '', $downloaded_file);
                        } else {
                            $this->exts->wait_and_check_download('zip');
                            $this->exts->wait_and_check_download('zip');
                            $downloaded_file = $this->exts->find_saved_file('zip');

                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->log($downloaded_file);
                                $pdf_files = $this->extract_zip_save_pdf($downloaded_file);
                                foreach ($pdf_files as $pdf_file) {
                                    $invoiceFileName = basename($pdf_file);
                                    $invoiceName = explode('.PDF', $invoiceFileName)[0];
                                    $this->exts->log($invoiceName);
                                    if ($this->exts->invoice_exists($invoiceName)) {
                                        $this->exts->log('Invoice existed ' . $invoiceName);
                                    } else {
                                        $this->exts->new_invoice($invoiceName, '', '', $pdf_file);
                                    }
                                }
                            }
                        }
                    } else {
                        $this->exts->wait_and_check_download('zip');
                        $this->exts->wait_and_check_download('zip');
                        $downloaded_file = $this->exts->find_saved_file('zip');

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->log($downloaded_file);
                            $pdf_files = $this->extract_zip_save_pdf($downloaded_file);
                            foreach ($pdf_files as $pdf_file) {
                                $invoiceFileName = basename($pdf_file);
                                $invoiceName = explode('.PDF', $invoiceFileName)[0];
                                $this->exts->log($invoiceName);
                                if ($this->exts->invoice_exists($invoiceName)) {
                                    $this->exts->log('Invoice existed ' . $invoiceName);
                                } else {
                                    $this->exts->new_invoice($invoiceName, '', '', $pdf_file);
                                }
                            }
                        } else {
                            // If just one invoice, It doesn't create zip file, pdf file downloaded instead
                            $downloaded_file = $this->exts->find_saved_file('pdf');
                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice('', '', '', $downloaded_file);
                            }
                        }
                    }

                    $this->exts->click_element('.modal-dialog[role="document"] button.close');
                    sleep(1);
                }

                $this->exts->click_element('.ups-breadcrumb-item a[href="/ups/billing/plan"]'); // back to plan page
                sleep(2);
                $this->waitFor('.recent-payments-container tbody tr');
                sleep(1);
                // Must re-filter by date
                $this->exts->click_element('button.btn-calendar');
                sleep(1);

                $this->exts->click_element('label[for="calendarOptionType_AVAILABLE"]');
                sleep(1);
                $this->exts->click_element('button#invoice-search-date-btn-apply');
                sleep(5);
                $this->waitFor('.recent-payments-container tbody tr');
                sleep(1);

                if ($current_page_number > 1) {
                    for ($page = 2; $page <= $current_page_number && $this->isExists('.paginate_button.next:not(.disabled)') && $this->restrictPages == 0; $page++) {
                        $this->exts->click_element('.paginate_button.next:not(.disabled)');
                        sleep(1);
                    }
                }

                $current_row_index++;
            } else {
                if ($this->isExists('.paginate_button.next:not(.disabled)') && $this->restrictPages == 0) {
                    $this->exts->click_element('.paginate_button.next:not(.disabled)');
                    sleep(2);
                    $current_row_index = 0;
                    $current_page_number++;
                } else {
                    break;
                }
            }
        }
    }

    private function select_language_and_business_unit($business_type_id = '')
    {
        if ($this->isExists('button.btn-change-country')) {
            $this->exts->click_element('button.btn-change-country');
        }

        sleep(3);
        if ($this->user_lang != '') {
            $this->exts->click_element('select[name="globalCountryOption"]');
            sleep(1);
            $this->exts->capture("3-country-checking");
            if ($this->user_lang == 'en' || $this->user_lang == 'en-GB') {
                $this->exts->click_element('select[name="globalCountryOption"] option[value="en-GB"]');
            } else if ($this->user_lang == 'en-GB') {
                $this->exts->click_element('select[name="globalCountryOption"] option[value="en-GB"]');
            } else if ($this->isExists('select[name="globalCountryOption"] option[value="de-DE"]')) {
                $this->exts->click_element('select[name="globalCountryOption"] option[value="de-DE"]');
            } else if ($this->isExists('select[name="globalCountryOption"] option[value="en-CH"]')) {
                $this->exts->click_element('select[name="globalCountryOption"] option[value="en-CH"]');
            } else {
                $this->exts->log('no option for language: ' . $this->user_lang);
                // click to close this select box
                $this->exts->click_element('select[name="globalCountryOption"]');
            }
        } else if ($this->isExists('select[name="globalCountryOption"] option[value="de-DE"]')) {
            $this->exts->click_element('select[name="globalCountryOption"] option[value="de-DE"]');
        }

        $this->exts->click_element('select[name="globalBusinessUnitType"]');
        sleep(1);
        $this->exts->click_element('select[name="globalBusinessUnitType"] option[value="' . $business_type_id . '"]');
        sleep(1);

        $this->exts->capture("3-country-and-business-" . $business_type_id);
        $this->exts->click_element('.global-ups-country button.btn-primary');
        sleep(3);

        if ($this->exts->allExists(['.modal.fade.top.show button.btn-primary', '.modal.fade.top.show label[for="do-not-show-again"]'])) {
            $this->exts->click_element('.modal.fade.top.show label[for="do-not-show-again"]');
            sleep(1);
            $this->exts->click_element('.modal.fade.top.show button.btn-primary');
        }
    }
    public $files_copied = [];
    public $folders_need_rm = [];

    public function extract_zip_save_pdf($zipfile)
    {
        $saved_file = '';
        $saved_files = [];
        $zip = new \ZipArchive;
        $res = $zip->open($zipfile);
        if ($res === TRUE) {
            $zip->extractTo($this->exts->config_array['download_folder']);
            $this->exts->log($zip->numFiles);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zipPdfFile = $zip->statIndex($i);
                if (stripos($zipPdfFile['name'], '.pdf') !== false) {
                    $saved_file = $this->exts->config_array['download_folder'] . basename($zipPdfFile['name']);
                    array_push($saved_files, $saved_file);
                }
            }

            $this->exts->log(count($saved_files));
            $this->exts->log($saved_files[0]);
            $zip->close();
            unlink($zipfile);
            $this->exts->log($this->exts->config_array['download_folder']);
            $folders = $this->copyPdfFileToDownloadFolder([$this->exts->config_array['download_folder']]);
            while (true) {
                if (count($folders) > 0) {
                    $folders = $this->copyPdfFileToDownloadFolder($folders);
                } else {
                    break;
                }
            }

            usort($this->folders_need_rm, function ($a, $b) {
                return strlen($b) - strlen($a);
            });

            foreach ($this->folders_need_rm as $folder_n) {
                rmdir($folder_n);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::File extraction failed');
        }

        return $saved_files;
    }

    public function copyPdfFileToDownloadFolder($folders = [])
    {
        $this->exts->log('copyPdfFileToDownloadFolder');
        $folder_array = [];
        foreach ($folders as $filename) {
            $this->exts->log('filename: ' . $filename);

            if ($filename == $this->exts->config_array['download_folder']) {
                $files = glob($filename . "*");
            } else {
                $files = glob($filename . "/*");
            }

            foreach ($files as $file_n) {
                $this->exts->log('file_n: ' . $file_n);
                if (strpos($file_n, '.pdf') !== false && !in_array($file_n, $this->files_copied)) {
                    if (!copy($file_n, $this->exts->config_array['download_folder'] . end(explode('/', $file_n)))) {
                        $this->exts->log("not copy $file_n");
                    } else {
                        array_push($this->files_copied, $this->exts->config_array['download_folder'] . end(explode('/', $file_n)));
                        unlink($file_n);
                    }
                } else if (is_dir($file_n)) {
                    array_push($folder_array, $file_n);
                } else {
                    continue;
                }
            }

            if ($filename != $this->exts->config_array['download_folder']) {
                array_push($this->folders_need_rm, $filename);
            }
        }
        return $folder_array;
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
