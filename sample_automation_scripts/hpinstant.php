<?php // replace isExists and waitFor

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

    // Server-Portal-ID: 115307 - Last modified: 28.04.2025 11:29:51 UTC - User: 1

    public $baseUrl = "https://instantink.hpconnected.com";
    public $loginUrl = "https://instantink.hpconnected.com/users/signin";
    public $username_selector = 'input#username';
    public $password_selector = 'input#password';
    public $submit_button_selector = '#next_button, [type=submit]';

    public $restrictPages = 3;
    public $totalFiles = 0;
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->exts->loadCookiesFromFile();
        sleep(1);

        //Check session is expired or not
        $this->exts->openUrl('https://instantink.hpconnected.com/api/internal/critical_scopes');
        sleep(10);
        $this->exts->capture("0-check-session-expired");
        if (stripos($this->exts->extract('body pre'), '{"error":{"code":"session_expired"}}') !== false) {
            $this->clearChrome();
            sleep(1);
        }

        $this->exts->openUrl($this->baseUrl);
        sleep(20);
        $this->exts->capture("Home-page-with-cookie");

        if ($this->isExists('button#onetrust-button-group #onetrust-accept-btn-handler')) {
            $this->exts->click_element('button#onetrust-button-group #onetrust-accept-btn-handler');
            sleep(10);
        }
        if ($this->isExists('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]')) {
            $this->exts->click_element('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]');
            sleep(30);
        }
        $isCookieLoginSuccess = false;
        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            if ($this->isExists('button[data-testid="sign-in-button"]')) {
                $this->exts->click_element('button[data-testid="sign-in-button"]');
            } else {
                $this->exts->openUrl($this->loginUrl);
            }
        }

        if (!$isCookieLoginSuccess) {
            sleep(15);
            $this->fillForm();
            sleep(30);

            if ($this->isExists('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]')) {
                $this->exts->click_element('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]');
                sleep(30);
            }

            if ($this->isExists('#onetrust-accept-btn-handler')) {
                $this->exts->click_element('#onetrust-accept-btn-handler');
                sleep(30);
            }
            if ($this->isExists('#full-screen-consent-form-footer-button-continue')) {
                $this->exts->click_element('#full-screen-consent-form-footer-button-continue');
                sleep(10);
            }

            if ($this->isExists('button[name="send-email"]')) {
                $this->exts->click_element('button[name="send-email"]');
                sleep(13);
            } else if ($this->isExists('button[name="send-phone"]')) {
                $this->exts->click_element('button[name="send-phone"]');
                sleep(13);
            }
            $this->checkFillTwoFactor();

            if ($this->isExists('.onboarding-component button#full-screen-error-button')) {
                $this->exts->capture("internal-session-error");
                $this->exts->refresh();
                sleep(10);
                $this->exts->refresh();
                sleep(10);
            }
            if ($this->isExists('#full-screen-consent-form-footer-button-continue')) {
                $this->exts->click_element('#full-screen-consent-form-footer-button-continue');
                sleep(10);
            }
            if ($this->isExists('#root[style*="display: block"] [role="progressbar"]') && $this->exts->urlContains('/org-selector')) {
                // Huy added this 07-2022
                $this->exts->openUrl($this->baseUrl);
                sleep(5);
            }
            sleep(10);
            if ($this->isExists('[aria-describedby="org-selector-modal-desc"] #org-selector-modal-desc label')) {
                $this->exts->click_element('[aria-describedby="org-selector-modal-desc"] #org-selector-modal-desc label');
                sleep(5);
                $this->exts->click_element('[aria-describedby="org-selector-modal-desc"] button[type="button"]');
                sleep(15);
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {
                if (
                    strpos(strtolower($this->exts->extract('.caption.text')), 'invalid username or password') !== false ||
                    strpos(strtolower($this->exts->extract('.caption.text')), 'ltiger benutzername oder') !== false
                ) {
                    $this->exts->loginFailure(1);
                } else if ($this->isExists('#username-helper-text a.error-link')) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        } else {
            sleep(10);
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
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
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    private function fillForm($count = 0)
    {
        $this->exts->capture("1-pre-login");
        $this->waitFor($this->username_selector, 20);
        if ($this->isExists($this->username_selector)) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->click_element('input#RememberMe, .remember-me label');
            $this->exts->capture("1-username-filled");
            $this->exts->click_element($this->submit_button_selector);
            sleep(6);
            $this->exts->capture("1-username-submitted");
            $this->exts->capture("1-password-page");
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }

        if ($this->isExists($this->password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->exts->capture("1-password-filled");
            $this->exts->click_element($this->submit_button_selector);
            sleep(15);
        }

        if ($this->isExists($this->username_selector) && $count < 3) {
            $count++;
            $this->fillForm($count);
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

    public function waitFor($selector, $seconds = 10)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[name="code"], input#code';
        $two_factor_message_selector = 'div.email-header p, div.sms-header p, p';
        $two_factor_submit_selector = 'button#submit-code , button#submit-auth-code';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->click_element($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    private function checkLogin()
    {
        for ($wait_count = 1; $wait_count <= 10 && $this->exts->querySelector('#menu-avatar-container, div[data-testid*="avatar_menu"]') == null; $wait_count++) {
            $this->exts->log('Waiting for login...');
            sleep(5);
        }
        if ($this->exts->querySelector('#menu-avatar-container, div[data-testid*="avatar_menu"]') != null) {
            return true;
        }
        return $this->isExists('#desktop-header a[href="/users/logout"], [data-testid="sign-out-button"], [data-value="Sign out"], [data-value="Abmelden"], [data-testid="avatar-container"] [aria-haspopup="true"], #menu-avatar-container, div[data-testid*="avatar_menu"]');
    }

    private function invoicePage()
    {
        $this->exts->log("Invoice page");
        $this->exts->refresh();
        sleep(30);
        if ($this->isExists('#onetrust-accept-btn-handler')) {
            $this->exts->click_element('#onetrust-accept-btn-handler');
            sleep(2);
        }
        if ($this->isExists('[data-testid="special-savings-modal"] div.vn-modal--content button')) {
            $this->exts->click_element('[data-testid="special-savings-modal"] div.vn-modal--content button');
            sleep(5);
        }
        if ($this->isExists('div[aria-describedby="paper-new-plan-offer-main-div-desc"] button.vn-modal__close')) {
            $this->exts->click_element('div[aria-describedby="paper-new-plan-offer-main-div-desc"] button.vn-modal__close');
            sleep(5);
        }

        $this->exts->click_element('li[data-testid="print-plans-menu"]');
        sleep(5);

        $this->exts->click_element('[data-testid="plan-overview-submenu"]');
        sleep(10);

        $this->waitFor('#status-card, [data-testid="status-card"]', 15);


        $this->exts->click_element('[data-testid="printer-selector"]');
        sleep(2);
        $this->exts->capture('printers-checking');
        $printers = count($this->exts->getElements('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]'));
        $this->exts->log('Number of Print: ' . $printers);
        if ($printers > 1) {
            for ($p = 0; $p < $printers; $p++) {
                if (!$this->isExists('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]')) {
                    $this->exts->click_element('[data-testid="printer-selector"]');
                    sleep(2);
                }

                $target_printer = $this->exts->getElements('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]')[$p];
                try {
                    $this->exts->log('Select target_printer');
                    $target_printer->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Select target_printer by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$target_printer]);
                }
                sleep(5);
                $this->waitFor('[data-testid="status-card"], #print-history-page', 15);
                $this->exts->click_element('.print-history-link a[href*="/print_history"], [data-testid="status-card"] a[data-testid="view-print-history"]');
                sleep(5);

                $this->downloadInvoice();

                $this->exts->click_element('div[data-testid="print-history-section"] #history-table-section');
                sleep(20);

                $this->processPaymentHistory();
            }
        } else {
            $this->exts->click_element('.print-history-link a[href*="/print_history"], [data-testid="status-card"] a[data-testid="view-print-history"]');
            sleep(5);
            $this->downloadInvoice();

            $this->exts->click_element('div[data-testid="print-history-section"] #history-table-section');

            $this->processPaymentHistory();
        }
        //page has changed 23.12.2022
        // changed other organisation
        $this->exts->click_element('div#menu-avatar-container');
        sleep(8);
        $organisations = $this->exts->getElements('div[data-testid="menu-modal"] button [aria-label="Chevron Right"]');
        if (count($organisations) > 0) {
            for ($i = 0; $i < count($organisations); $i++) {
                try {
                    $organisations[$i]->click();
                } catch (\Exception $exception) {
                    $this->exts->execute_javascript('arguments[0].click();', [$organisations[$i]]);
                }
                sleep(35);
                if ($this->isExists('[data-testid="special-savings-modal"] div.vn-modal--content button')) {
                    $this->exts->click_element('[data-testid="special-savings-modal"] div.vn-modal--content button');
                    sleep(5);
                }
                $this->exts->click_element('li[data-testid="print-plans-menu"]');
                sleep(5);

                $this->exts->click_element('[data-testid="plan-overview-submenu"]');
                sleep(10);

                $this->waitFor('#status-card, [data-testid="status-card"], [data-testid="printer-selector"]', 15);


                $this->exts->click_element('[data-testid="printer-selector"]');
                sleep(2);
                $this->exts->capture('printers-checking');
                $printers = count($this->exts->getElements('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]'));
                $this->exts->log('Number of Print: ' . $printers);
                if ($printers > 1) {
                    for ($p = 0; $p < $printers; $p++) {
                        if (!$this->isExists('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]')) {
                            $this->exts->click_element('[data-testid="printer-selector"]');
                            sleep(2);
                        }

                        $target_printer = $this->exts->getElements('[data-testid="printer-selector"] [class^="selector__selector-options"] [class^="selector__option-label-container-padding"]')[$p];
                        try {
                            $this->exts->log('Select target_printer');
                            $target_printer->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Select target_printer by javascript');
                            $this->exts->execute_javascript("arguments[0].click()", [$target_printer]);
                        }
                        sleep(5);
                        $this->waitFor('[data-testid="status-card"], #print-history-page', 15);
                        $this->exts->click_element('.print-history-link a[href*="/print_history"], [data-testid="status-card"] a[data-testid="view-print-history"]');
                        sleep(5);

                        $this->downloadInvoice();
                        $this->exts->click_element('div[data-testid="print-history-section"] #history-table-section');

                        $this->processPaymentHistory();
                    }
                } else {
                    $this->exts->click_element('.print-history-link a[href*="/print_history"], [data-testid="status-card"] a[data-testid="view-print-history"]');
                    sleep(5);
                    $this->downloadInvoice();
                    $this->exts->click_element('div[data-testid="print-history-section"] #history-table-section');
                    $this->processPaymentHistory();
                }

                $this->exts->click_element('div#menu-avatar-container');
                sleep(5);
            }
        }


        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }
    private function downloadInvoice()
    {
        sleep(5);
        $this->exts->capture('4-account_history');
        $this->exts->click_element('[data-testid="select-billing-cycle-parity"] [aria-haspopup="listbox"][role="button"]');
        sleep(2);
        $this->exts->capture('4-billing-cycle');
        $cycle_dropdown_id = $this->exts->extract('[data-testid="select-billing-cycle-parity"] [aria-haspopup="listbox"][role="button"]', null, 'id');
        $bill_list_selector = '[role="listbox"]#' . $cycle_dropdown_id . '-listbox li[data-value]';
        $this->exts->log('cycle_dropdown_id ' . $cycle_dropdown_id);
        $this->exts->log('bill_list_selector ' . $bill_list_selector);

        $bill_values = $this->exts->getElementsAttribute($bill_list_selector, 'data-value');
        foreach ($bill_values as $bill_value) {
            $invoiceName = $bill_value;
            $this->isNoInvoice = false;
            if ($this->exts->invoice_exists($invoiceName)) {
                $this->exts->log('Invoice Existed: ' . $invoiceName);
                continue;
            }
            if (!$this->isExists($bill_list_selector)) {
                $this->exts->click_element('[data-testid="select-billing-cycle-parity"] [aria-haspopup="listbox"][role="button"]');
                sleep(2);
            }
            $this->exts->click_element('[role="listbox"]#' . $cycle_dropdown_id . '-listbox li[data-value="' . $bill_value . '"]');
            sleep(5);
            if ($this->isExists('[class*="printHistory__columnB"] a[data-testid="download_invoice_pdf"]')) {
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ');
                $this->exts->log('invoiceAmount: ');

                $this->exts->click_element('[class*="printHistory__columnB"] a[data-testid="download_invoice_pdf"]');
                sleep(3);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                $this->totalFiles += 1;
            }
        }
    }

    private function processPaymentHistory($paging_count = 1)
    {
        $this->exts->capture("4-PaymentHistory-page");
        $invoices = [];
        $this->waitFor('div[aria-expanded="false"][id="Druck--und Zahlungsverlauf"]', 10);
        $this->exts->click_element('div[aria-expanded="false"][id="Druck--und Zahlungsverlauf"]');
        $this->waitFor('table[data-testid*="-print-hitory-table"] tbody tr', 10);
        sleep(10);
        $rows = $this->exts->getElements('table[data-testid*="-print-hitory-table"] tbody tr');
        foreach ($rows as $row) {
            $downloadBtn = $this->exts->getElement('div[class*="invoiceDownloadLink"] a', $row);
            if ($downloadBtn != null) {
                $invoiceDate = $this->exts->extract('td:nth-child(1)', $row);
                $parse_date = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
                if ($parse_date == '') {
                    $parse_date = $this->exts->parse_date($invoiceDate, 'm/d/Y', 'Y-m-d');
                }
                $this->exts->log('Date parsed: ' . $parse_date);
                $invoiceAmount = '';


                try {
                    $downloadBtn->click();
                } catch (\Exception $exception) {
                    $this->exts->executeSafeScript('arguments[0].click();', [$downloadBtn]);
                }

                $this->exts->wait_and_check_download('pdf');
                // $filename = $invoiceName.'.pdf';
                $downloaded_file = $this->exts->find_saved_file('pdf');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceFileName = basename($downloaded_file);
                    $invoiceName = trim(array_pop(explode('#', explode('.pdf', $invoiceFileName)[0])));
                    $invoiceName = trim(array_pop(explode('(', explode(')', $invoiceName)[0])));
                    // $invoiceName = trim(explode('_', end(explode('obile_', $invoiceName)))[0]);
                    $this->exts->log('Final invoice name: ' . $invoiceName);
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->isNoInvoice = false;
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ');
                }
            }



            $this->waitFor('div[data-testid="critical-scopes-modal"] button:last-child');
            if ($this->isExists('div[data-testid="critical-scopes-modal"] button:last-child')) {
                $this->exts->click_element('div[data-testid="critical-scopes-modal"] button:last-child');
                $this->fillForm();
                $this->exts->openUrl('https://portal.hpsmart.com/de/de/print_plans/account_history');
                if ($this->checkLogin()) {
                    $this->processPaymentHistory();
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));

        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 10 &&
            $this->exts->querySelector('button.next:not([disabled])')
        ) {
            $paging_count++;
            $paginateButton = $this->exts->querySelector('button.next:not([disabled])');
            $this->exts->execute_javascript("arguments[0].click();", [$paginateButton]);
            sleep(5);
            $this->processPaymentHistory($paging_count);
        } else if (
            $restrictPages != 0 &&
            $paging_count < $restrictPages &&
            $this->exts->querySelector('button.next:not([disabled])')
        ) {
            $this->exts->log('Click paginateButton');
            $paging_count++;
            $paginateButton = $this->exts->querySelector('button.next:not([disabled])');
            $this->exts->execute_javascript("arguments[0].click();", [$paginateButton]);
            sleep(5);
            $this->processPaymentHistory($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
