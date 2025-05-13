<?php // added condition to handle empty cases


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

    // Server-Portal-ID: 19864 - Last modified: 01.04.2025 14:54:59 UTC - User: 1

    public $baseUrl = "https://www.mollie.com";
    public $loginUrl = "https://www.mollie.com/dashboard/login?lang=en";
    public $invoiceUrl = "https://www.mollie.com/dashboard/administration/invoices";
    public $settlementUrl = "https://www.mollie.com/dashboard/administration/settlements";
    public $currentInvoiceUrl = "";
    public $currentSettlementUrl = "";

    public $submit_btn = "form.auth-form button[type='submit']:not([disabled])";
    public $username_selector = 'form.auth-form input#username, input#email';
    public $password_selector = 'form.auth-form input#password';
    public $remember_me_selector = "b.checkbox__button";
    public $login_error_msg_selector = "div.errorbox";

    public $logout_link = "button[data-testid='organization-switcher-toggle'],button.c-user__button, button.c-user__button, a[href*='/logout'], a[href*='payments']";
    public $login_tryout = 0;
    public $restrictPages = 0;
    public $last_state = array();
    public $current_state = array();
    public $invoice_data_arr = array();
    public $isNoInvoice = true;

    public $user_account_ids = array();
    public $download_settlements = 0;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->download_settlements = isset($this->exts->config_array["download_settlements"]) ? (int)@$this->exts->config_array["download_settlements"] : $this->download_settlements;
        $account_numbers = isset($this->exts->config_array["account_numbers"]) ? trim($this->exts->config_array["account_numbers"]) : '';
        $this->exts->log('Account Number Provided by user - ' . $account_numbers);
        if (trim($account_numbers) != '' && !empty($account_numbers)) {
            $this->user_account_ids = explode(',', $account_numbers);
        }
        $this->exts->log('Account Number Provided by user - ' . print_r($this->user_account_ids, true));
        $this->exts->openUrl($this->invoiceUrl);
        sleep(4);
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->invoiceUrl);
        sleep(10);
        $this->exts->capture("1-init-page");
        if (!$this->checkLogin()) {
            $this->exts->openUrl($this->loginUrl);
            // $this->checkFillRecaptcha();
            $this->fillForm(0);
            sleep(5);
            if ($this->exts->urlContains("challengePage=true")) {
                $this->exts->capture("challengePage");
                sleep(5);
                if ($this->exts->exists($this->submit_btn)) {
                    $this->fillForm(0);
                    sleep(5);
                    if ($this->exts->querySelector('form.js-two-factor-authentication-form') != null || $this->exts->urlContains('twofactorauthentication')) {
                        $this->chooseMethodTwoFactor();
                    } else {
                        $this->exts->log('--- Not found 2FA page ---');
                    }
                }
            } else {
                if ($this->exts->querySelector('form.js-two-factor-authentication-form') != null || $this->exts->urlContains('twofactorauthentication')) {
                    $this->chooseMethodTwoFactor();
                } else {
                    $this->exts->log('--- Not found 2FA page ---');
                }
                sleep(5);
            }
            $this->exts->capture("2.2-after-login");
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("3-LoginSuccess");
            $this->processAfterLogin($count);
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if ($this->exts->urlContains('onboarding') && $this->exts->getElement('[data-control-name="countryCode"]') != null) {
                $this->exts->account_not_ready();
            }
            if (
                stripos(strtolower($this->exts->extract($this->login_error_msg_selector)), 'invalid combination of email') !== false ||
                stripos(strtolower($this->exts->extract($this->login_error_msg_selector)), 'kombination aus e-mail-adresse') !== false ||
                stripos(strtolower($this->exts->extract($this->login_error_msg_selector)), 'invalid login details') !== false
            ) {
                $this->exts->capture("LoginFailed-confirmed");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    }

    function fillForm($count)
    {
        if ($this->exts->exists($this->password_selector)) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(1);
            $this->exts->capture("2-login-page-filled");
            sleep(5);
            $this->exts->click_element($this->submit_btn);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function chooseMethodTwoFactor()
    {
        $this->exts->capture('2.1-two-factor-page');
        if ($this->exts->exists('form.js-two-factor-authentication-form input.js-char-input')) {
            $this->checkFillTwoFactorOTP();
        } else {
            // choose other methord
            if ($this->exts->querySelector('form.js-two-factor-authentication-app') != null && $this->exts->querySelector('.form-group--button a[href*="twofactorauthentication/method"]') != null) {
                $this->exts->moveToElementAndClick('.form-group--button a[href*="twofactorauthentication/method"]');
                sleep(5);
                if ($this->exts->querySelector('ul li a.auth-form__nav-link[href*="totp"]') != null) {
                    // fill code from authenticator app
                    $this->exts->moveToElementAndClick('ul li a.auth-form__nav-link[href*="totp"]');
                    sleep(5);
                    $this->checkFillTwoFactorOTP();
                } else if ($this->exts->querySelector('ul li a.auth-form__nav-link[href*="sms"]') != null) {
                    // fill code send to sms
                    $this->exts->moveToElementAndClick('ul li a.auth-form__nav-link[href*="sms"]');
                    sleep(5);
                    $this->checkFillTwoFactorOTP('SMS');
                } else if ($this->exts->querySelector('ul li a.auth-form__nav-link[href*="switch/app"]') != null) {
                    // accept on user device
                    $this->exts->moveToElementAndClick('ul li a.auth-form__nav-link[href*="switch/app"]');
                    sleep(5);
                    $this->checkFillTwoFactorPush();
                } else if ($this->exts->querySelector('ul li a.auth-form__nav-link[href*="backup_code"]') != null) {
                    // fill backup code
                    $this->exts->moveToElementAndClick('ul li a.auth-form__nav-link[href*="backup_code"]');
                    sleep(5);
                    $this->checkFillTwoFactorOTP("Backup Code");
                } else {
                    $this->exts->log("----- Not found any 2FA method -----");
                }
            } else {
                $this->exts->log("----- Not found button choose other 2FA method -----");
            }
        }
    }

    public function checkFillTwoFactorOTP($type = "Authenticator App")
    {
        if ($this->exts->exists('form.js-two-factor-authentication-form input.js-char-input')) {
            $two_factor_selector = 'form.js-two-factor-authentication-form input.js-char-input';
            $two_factor_message_selector = 'form.js-two-factor-authentication-form p.auth-form__verify-paragraph, .auth-form-title';
            $two_factor_submit_selector = 'form.js-two-factor-authentication-form button[type="submit"]';

            if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->log("Two factor page found.");
                $this->exts->capture("2.1-two-factor-otp");

                $this->exts->two_factor_notif_msg_en = "CHECK your " . $type . "\n";
                $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
                if ($this->exts->two_factor_attempts == 2) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                    $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
                }

                $two_factor_code = trim($this->exts->fetchTwoFactorCode());
                if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                    $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                    $this->exts->click_by_xdotool($two_factor_selector);
                    sleep(2);
                    $this->exts->type_text_by_xdotool($two_factor_code);
                    $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                    sleep(3);
                    $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                    $this->exts->moveToElementAndClick($two_factor_submit_selector);
                    sleep(15);

                    if ($this->exts->querySelector($two_factor_selector) == null) {
                        $this->exts->log("Two factor solved");
                    } else if ($this->exts->two_factor_attempts < 3) {
                        $this->exts->two_factor_attempts++;
                        $this->checkFillTwoFactorOTP();
                    } else {
                        $this->exts->log("Two factor can not solved");
                    }
                } else {
                    $this->exts->log("Not received two factor code");
                }
            }
        }
    }

    private function checkFillTwoFactorPush()
    {
        $two_factor_message_selector = 'form.js-two-factor-authentication-app  p.auth-form-text';
        $two_factor_submit_selector = 'form.js-two-factor-authentication-app .on-verification-pending button[data-request-status="success"]';
        sleep(5);
        if ($this->exts->querySelector($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor-push");
            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($two_factor_message_selector, 'innerText'));
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . ' Please input "OK" when finished!!';
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $two_factor_code = trim(strtolower($this->exts->fetchTwoFactorCode()));
            if (!empty($two_factor_code) && trim(strtolower($two_factor_code)) == 'ok') {
                $this->exts->log("checkFillTwoFactorForMobileAcc: Entering two_factor_code." . $two_factor_code);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                sleep(15);
                if ($this->exts->querySelector($two_factor_message_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactorPush();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $isLoginForm = $this->exts->exists($this->username_selector);
            if (!$isLoginForm) {
                if ($this->exts->exists($this->logout_link)) {
                    $this->exts->log(">>>>>>>>>>>>>>>Login successful 1!!!!");
                    $isLoggedIn = true;
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    function processAfterLogin($count)
    {
        $this->exts->log("processAfterLogin:: Begin " . $count);

        if ($this->exts->exists('button.c-feature-modal__close')) {
            $popups = $this->exts->querySelectorAll('button.c-feature-modal__close');
            foreach ($popups as $popup) {
                try {
                    $popup->click();
                } catch (\Exception $exception) {
                    $this->exts->executeSafeScript('arguments[0].click();', [$popup]);
                }
                sleep(1);
            }
        }

        $accounURLs = array();
        if ($this->exts->exists('button[data-testid="organization-switcher-toggle"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="organization-switcher-toggle"]');
            sleep(10);
            $accounts = $this->exts->querySelectorAll('button[data-testid="organization-item"]');
            foreach ($accounts as $account) {
                $accounURLs[] = 'https://my.mollie.com/dashboard/' . $account->getAttribute('data-organization-id') . '/home';
            }
        }
        $this->exts->log('Total Accounts - ' . count($accounURLs));

        $this->exts->log('Account URL - ' . $accounURLs);
        if (!empty($accounURLs)) {
            foreach ($accounURLs as $accounURL) {
                $account_found = true;
                if (!empty($this->user_account_ids)) {
                    $tempArr = explode('/dashboard/org_', $accounURL);
                    $tempArr = explode('/', end($tempArr));
                    $current_account_number = trim($tempArr[0]);

                    if (!in_array($current_account_number, $this->user_account_ids)) {
                        $account_found = false;
                    }
                }

                if ($account_found) {
                    $this->exts->openUrl($accounURL);
                    sleep(10);

                    $this->exts->openUrl($this->invoiceUrl);
                    sleep(10);
                    $this->currentInvoiceUrl = $this->exts->getUrl();
                    if ($this->download_settlements == 0) {
                        if ($this->restrictPages == 0) {
                            $this->exts->log("processAfterLogin:: process all years");
                            $this->processAllYears();
                        } else {
                            $this->exts->log("processAfterLogin:: process current year");

                            $this->processDownloadInvoices(0, date("Y"));
                            $this->processDownloadInvoicesNew(0, date("Y"));
                            $this->processDownloadInvoiceslatest(0, date("Y"));
                        }
                        sleep(10);
                    } else if ($this->download_settlements == 1 && $this->exts->exists('a[href*="/administration/settlements"]')) {
                        $this->exts->moveToElementAndClick('a[href*="/administration/settlements"]');
                        sleep(10);

                        $this->currentSettlementUrl = $this->exts->getUrl();

                        if ($this->restrictPages == 0) {
                            $this->exts->log("processAfterLogin:: process all years");
                            $this->processAllSettelemtYears();
                        } else {
                            $this->exts->log("processAfterLogin:: process current year");

                            $this->processDownloadSettlement(0, date("Y"));
                        }
                        sleep(10);
                    }
                } else {
                    $this->exts->log('This URL is not having account number provided by user.' . $accounURL);
                }
            }
        } else {
            $this->currentInvoiceUrl = $this->exts->getUrl();
            if ($this->download_settlements == 0) {
                if ($this->restrictPages == 0) {
                    $this->exts->log("processAfterLogin:: process all years");
                    $this->processAllYears();
                } else {
                    $this->exts->log("processAfterLogin:: process current year");
                    $this->processDownloadInvoices(0, date("Y"));
                    $this->processDownloadInvoicesNew(0, date("Y"));
                    $this->processDownloadInvoiceslatest(0, date("Y"));
                }
            } else if ($this->download_settlements == 1 && $this->exts->exists('a[href*="/reports/settlements"]')) {
                $this->exts->moveToElementAndClick('a[href*="/reports/settlements"]');
                sleep(10);

                $this->currentSettlementUrl = $this->exts->getUrl();

                if ($this->restrictPages == 0) {
                    $this->exts->log("processAfterLogin:: process all years");
                    $this->processAllSettelemtYears();
                } else {
                    $this->exts->log("processAfterLogin:: process current year");

                    $this->processDownloadSettlement(0, date("Y"));
                }
                sleep(10);
            }
        }
    }

    function processAllYears()
    {
        $this->exts->log("processAllYears:: Begin");
        try {
            $years = array(date("Y"), date("Y", strtotime("-1 year")), date("Y", strtotime("-2 year")));
            foreach ($years as $key => $year) {
                $this->exts->log("processAllYears:: Now processing year " . $year);
                $this->processDownloadInvoices(0, $year);
                $this->processDownloadInvoicesNew(0, $year);
                $this->processDownloadInvoiceslatest(0, $year);
            }

            $this->exts->success();
        } catch (\Exception $ex) {
            $this->exts->log("processAllYears:: Exception " . $ex);
        }
    }

    function processAllSettelemtYears()
    {
        $this->exts->log("processAllSettelemtYears:: Begin");
        try {
            $years = array(date("Y"), date("Y", strtotime("-1 year")), date("Y", strtotime("-2 year")));
            foreach ($years as $key => $year) {
                $this->exts->log("processAllSettelemtYears:: Now processing year " . $year);
                $this->processDownloadSettlement(0, $year);
            }

            $this->exts->success();
        } catch (\Exception $ex) {
            $this->exts->log("processAllSettelemtYears:: Exception " . $ex);
        }
    }

    function processDownloadInvoices($count, $year)
    {
        $this->exts->log("Begin :: processDownloadInvoices " . $count);
        $url = $this->currentInvoiceUrl . '?year=' . $year;
        $this->exts->openUrl($url);
        sleep(5);
        $this->exts->log("processDownloadInvoices :: opened invoice url " . $this->exts->getUrl());

        $this->exts->capture("invoice-page");
        $invoice_rows = $this->exts->querySelector('div.administration-invoices-table div.grid-table__data div.administration-invoices-table__row.grid-table-row');
        if ($invoice_rows != null) {
            $this->exts->log("processDownloadInvoices :: Found invoices");
            try {
                $rows = $this->exts->querySelectorAll('div.administration-invoices-table div.grid-table__data div.administration-invoices-table__row.grid-table-row');
                $this->exts->log("processDownloadInvoices :: Invoice Rows - " . count($rows));

                if (count($rows) > 0) {
                    foreach ($rows as $key => $rowItem) {
                        try {
                            $this->exts->log("processDownloadInvoices:: Now processing " . $key);
                            $lis = $this->exts->querySelectorAll("div.administration-invoices-table__cell", $rowItem);
                            if (count($lis) > 4) {
                                $invoice_date = trim($lis[0]->getText());
                                $invoice_amount = preg_replace('/[^\d.,]/', "", trim($lis[4]->getText())) . " EUR";
                                $invoice_number = preg_replace('/[^\d]/', '', trim($lis[1]->getText()));

                                $download_button = $this->exts->querySelector("button", $lis[1]);
                                if ($download_button != null) {
                                    $this->exts->execute_javascript("arguments[0].setAttribute('id', 'invoice_download_btn_' + arguments[1])", [$download_button, $key]);

                                    $invoice_date = preg_replace("/[.]/", "", $invoice_date);
                                    if (stripos($invoice_date, "März") !== false) {
                                        $invoice_date = str_replace("März", "March", $invoice_date);
                                    }
                                    $invoice_date = $this->exts->parse_date($invoice_date);

                                    $this->exts->log("processDownloadInvoices::Invoice Date - " . $invoice_date);
                                    $this->exts->log("processDownloadInvoices::Invoice Amount - " . $invoice_amount);
                                    $this->exts->log("processDownloadInvoices::Invoice Name - " . $invoice_number);
                                    sleep(2);
                                    $filename = !empty($invoice_number) ? $invoice_number . ".pdf": '';

                                    $downloaded_file = $this->exts->click_and_download('button#invoice_download_btn_' . $key, "pdf", $filename);
                                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                        $pdf_content = file_get_contents($downloaded_file);
                                        if (stripos($pdf_content, "%PDF") !== false) {
                                            $this->exts->new_invoice($invoice_number, $invoice_date, $invoice_amount, $downloaded_file);
                                            $this->isNoInvoice = false;
                                        } else {
                                            $this->exts->log("processDownloadInvoices :: Not Valid PDF - " . $filename);
                                        }
                                    } else {
                                        $this->exts->log("processDownloadInvoices :: No File Downloaded ? - " . $downloaded_file);
                                    }
                                }
                            }
                        } catch (\Exception $ex1) {
                            $this->exts->log("processDownloadInvoices:: Exception processing record " . $ex1);
                        }

                        if ($this->exts->exists('button.c-feature-modal__close')) {
                            $popups = $this->exts->querySelectorAll('button.c-feature-modal__close');
                            foreach ($popups as $popup) {
                                try {
                                    $popup->click();
                                } catch (\Exception $exception) {
                                    $this->exts->executeSafeScript('arguments[0].click();', [$popup]);
                                }
                                sleep(1);
                            }
                        }
                    }
                } else {
                    $this->exts->log("processDownloadInvoices:: No Invoice");
                    $this->exts->success();
                }
            } catch (\Exception $ex2) {
                $this->exts->log("processDownloadInvoices:: Exception in invoice details " . $ex2);
            }
        }
    }

    function processDownloadInvoicesNew($count, $year)
    {
        $this->exts->log("Begin :: processDownloadInvoicesNew " . $count);
        $url = $this->currentInvoiceUrl . '?year=' . $year;
        $this->exts->openUrl($url);
        $this->exts->waitTillPresent('.grid-table__data .grid-table-row');
        $this->exts->log("processDownloadInvoices :: opened invoice url " . $this->exts->getUrl());

        $this->exts->capture("invoice-page");
        $invoice_rows = $this->exts->querySelector('.grid-table__data .grid-table-row');
        if ($invoice_rows != null) {
            $this->exts->log("processDownloadInvoices :: Found invoices");
            try {
                $rows = $this->exts->querySelectorAll('.grid-table__data .grid-table-row');
                $this->exts->log("processDownloadInvoices :: Invoice Rows - " . count($rows));

                if (count($rows) > 0) {
                    foreach ($rows as $key => $rowItem) {
                        try {
                            $this->exts->log("processDownloadInvoices:: Now processing " . $key);
                            $lis = $this->exts->querySelectorAll(".cell", $rowItem);
                            if (count($lis) > 4) {
                                $invoice_date = trim($lis[0]->getText());
                                $invoice_amount = preg_replace('/[^\d.,]/', "", trim($lis[4]->getText())) . " EUR";
                                $invoice_number = preg_replace('/[^\d]/', '', trim($lis[1]->getText()));

                                $download_button = $this->exts->querySelector("button", $lis[1]);
                                if ($download_button != null) {
                                    //$this->exts->execute_javascript("arguments[0].setAttribute('id', 'invoice_download_btn_' + arguments[1])", [$download_button, $key]);

                                    $invoice_date = preg_replace("/[.]/", "", $invoice_date);
                                    if (stripos($invoice_date, "März") !== false) {
                                        $invoice_date = str_replace("März", "March", $invoice_date);
                                    }
                                    $invoice_date = $this->exts->parse_date($invoice_date);

                                    $this->exts->log("processDownloadInvoices::Invoice Date - " . $invoice_date);
                                    $this->exts->log("processDownloadInvoices::Invoice Amount - " . $invoice_amount);
                                    $this->exts->log("processDownloadInvoices::Invoice Name - " . $invoice_number);
                                    sleep(2);
                                    $filename = !empty($invoice_number) ? $invoice_number . ".pdf": '';

                                    try {
                                        $download_button->click();
                                    } catch (\Exception $exception) {
                                        $this->exts->executeSafeScript('arguments[0].click();', [$download_button]);
                                    }
                                    sleep(15);

                                    $this->exts->wait_and_check_download('pdf');
                                    $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                                    //$downloaded_file = $this->exts->click_and_download('button#invoice_download_btn_' . $key, "pdf", $filename);
                                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                        $pdf_content = file_get_contents($downloaded_file);
                                        if (stripos($pdf_content, "%PDF") !== false) {
                                            $this->exts->new_invoice($invoice_number, $invoice_date, $invoice_amount, $downloaded_file);
                                            $this->isNoInvoice = false;
                                        } else {
                                            $this->exts->log("processDownloadInvoices :: Not Valid PDF - " . $filename);
                                        }
                                    } else {
                                        $this->exts->log("processDownloadInvoices :: No File Downloaded ? - " . $downloaded_file);
                                    }
                                }
                            }
                        } catch (\Exception $ex1) {
                            $this->exts->log("processDownloadInvoices:: Exception processing record " . $ex1);
                        }
                    }
                } else {
                    $this->exts->log("processDownloadInvoices:: No Invoice");
                    $this->exts->success();
                }
            } catch (\Exception $ex2) {
                $this->exts->log("processDownloadInvoices:: Exception in invoice details " . $ex2);
            }
        }
    }

    function processDownloadInvoiceslatest($count, $year)
    {
        $this->exts->log("Begin :: processDownloadInvoiceslatest " . $count);
        $url = $this->currentInvoiceUrl . '?year=' . $year;
        $this->exts->openUrl($url);
        $this->exts->waitTillPresent('table.mollie-ui-table > tbody > tr');
        $this->exts->log("processDownloadInvoices :: opened invoice url " . $this->exts->getUrl());

        $this->exts->capture("invoice-page");
        $invoice_rows = $this->exts->querySelector('table.mollie-ui-table > tbody > tr');
        if ($invoice_rows != null) {
            $this->exts->log("processDownloadInvoices :: Found invoices");
            try {
                $rows = $this->exts->querySelectorAll('table.mollie-ui-table > tbody > tr');
                $this->exts->log("processDownloadInvoices :: Invoice Rows - " . count($rows));

                if (count($rows) > 0) {
                    foreach ($rows as $key => $rowItem) {
                        try {
                            $this->exts->log("processDownloadInvoices:: Now processing " . $key);
                            $lis = $this->exts->querySelectorAll("td", $rowItem);
                            if (count($lis) > 4) {


                                $invoice_date = trim($lis[2]->getText());
                                $invoice_amount = preg_replace('/[^\d.,]/', "", trim($lis[5]->getText())) . " EUR";
                                $invoice_number = trim($lis[1]->getText());
                                $invoice_date = $this->exts->parse_date($invoice_date, 'M d, Y', 'Y-m-d');

                                $this->exts->log("processDownloadInvoices::Invoice Date - " . $invoice_date);
                                $this->exts->log("processDownloadInvoices::Invoice Amount - " . $invoice_amount);
                                $this->exts->log("processDownloadInvoices::Invoice Name - " . $invoice_number);
                                $download_button = $this->exts->querySelector("a[download]", $lis[1]);
                                if ($download_button != null) {
                                    //$this->exts->execute_javascript("arguments[0].setAttribute('id', 'invoice_download_btn_' + arguments[1])", [$download_button, $key]);

                                    $invoice_date = preg_replace("/[.]/", "", $invoice_date);
                                    if (stripos($invoice_date, "März") !== false) {
                                        $invoice_date = str_replace("März", "March", $invoice_date);
                                    }
                                    $invoice_date = $this->exts->parse_date($invoice_date);

                                    $this->exts->log("processDownloadInvoices::Invoice Date - " . $invoice_date);
                                    $this->exts->log("processDownloadInvoices::Invoice Amount - " . $invoice_amount);
                                    $this->exts->log("processDownloadInvoices::Invoice Name - " . $invoice_number);
                                    sleep(2);
                                    $filename = !empty($invoice_number) ? $invoice_number . ".pdf": '';

                                    try {
                                        $download_button->click();
                                    } catch (\Exception $exception) {
                                        $this->exts->executeSafeScript('arguments[0].click();', [$download_button]);
                                    }
                                    sleep(15);

                                    $this->exts->wait_and_check_download('pdf');
                                    $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                                    //$downloaded_file = $this->exts->click_and_download('button#invoice_download_btn_' . $key, "pdf", $filename);
                                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                        $pdf_content = file_get_contents($downloaded_file);
                                        if (stripos($pdf_content, "%PDF") !== false) {
                                            $this->exts->new_invoice($invoice_number, $invoice_date, $invoice_amount, $downloaded_file);
                                            $this->isNoInvoice = false;
                                        } else {
                                            $this->exts->log("processDownloadInvoices :: Not Valid PDF - " . $filename);
                                        }
                                    } else {
                                        $this->exts->log("processDownloadInvoices :: No File Downloaded ? - " . $downloaded_file);
                                    }
                                }
                            }
                        } catch (\Exception $ex1) {
                            $this->exts->log("processDownloadInvoices:: Exception processing record " . $ex1);
                        }
                    }
                } else {
                    $this->exts->log("processDownloadInvoices:: No Invoice");
                    $this->exts->success();
                }
            } catch (\Exception $ex2) {
                $this->exts->log("processDownloadInvoices:: Exception in invoice details " . $ex2);
            }
        }
    }

    function processDownloadSettlement($count, $year)
    {
        $this->exts->log("Begin :: processDownloadSettlement " . $count);
        $url = $this->currentSettlementUrl . '?year=' . $year;
        $this->exts->openUrl($url);
        sleep(5);
        $this->exts->log("processDownloadSettlement :: opened invoice url " . $this->exts->getUrl());

        $this->exts->capture("settlement-page");
        $invoice_rows = $this->exts->querySelector('.grid-table__data .grid-table-row');
        if ($invoice_rows != null) {
            $this->exts->log("processDownloadSettlement :: Found Settelemests");
            try {
                $rows = $this->exts->querySelectorAll('.grid-table__data .grid-table-row');
                $this->exts->log("processDownloadSettlement :: Settlement Rows - " . count($rows));

                if (count($rows) > 0) {
                    foreach ($rows as $key => $rowItem) {
                        try {
                            $this->exts->log("processDownloadSettlement:: Now processing " . $key);
                            $lis = $this->exts->querySelectorAll(".cell", $rowItem);
                            if (count($lis) >= 7) {
                                $invoice_date = trim($lis[1]->getText());
                                $invoice_amount = preg_replace('/[^\d.,]/', "", trim($lis[6]->getText())) . " EUR";
                                $invoice_number = preg_replace('/[^\d]/', '', trim($lis[2]->getText()));

                                $download_button = $this->exts->querySelector("button", $lis[2]);
                                if ($download_button != null) {

                                    $invoice_date = preg_replace("/[.]/", "", $invoice_date);
                                    if (stripos($invoice_date, "März") !== false) {
                                        $invoice_date = str_replace("März", "March", $invoice_date);
                                    }
                                    $invoice_date = $this->exts->parse_date($invoice_date);

                                    $this->exts->log("processDownloadInvoices::Invoice Date - " . $invoice_date);
                                    $this->exts->log("processDownloadInvoices::Invoice Amount - " . $invoice_amount);
                                    $this->exts->log("processDownloadInvoices::Invoice Name - " . $invoice_number);
                                    sleep(2);
                                    $filename = !empty($invoice_number) ? $invoice_number . ".pdf": '';

                                    try {
                                        $download_button->click();
                                    } catch (\Exception $exception) {
                                        $this->exts->executeSafeScript('arguments[0].click();', [$download_button]);
                                    }
                                    sleep(2);

                                    $this->exts->moveToElementAndClick('.c-export .c-export__table-row:nth-child(2) button');
                                    sleep(15);

                                    $this->exts->wait_and_check_download('pdf');
                                    $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                                    //$downloaded_file = $this->exts->click_and_download('button#invoice_download_btn_' . $key, "pdf", $filename);
                                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                        $pdf_content = file_get_contents($downloaded_file);
                                        if (stripos($pdf_content, "%PDF") !== false) {
                                            $this->exts->new_invoice($invoice_number, $invoice_date, $invoice_amount, $downloaded_file);
                                            $this->isNoInvoice = false;
                                        } else {
                                            $this->exts->log("processDownloadSettlement :: Not Valid PDF - " . $filename);
                                        }
                                    } else if ($this->exts->exists('.ReactModalPortal button[data-testid="cancel-btn"]')) {
                                        $this->exts->moveToElementAndClick('.ReactModalPortal button[data-testid="cancel-btn"]');
                                        sleep(5);
                                        $this->exts->wait_and_check_download('pdf');
                                        $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                            $pdf_content = file_get_contents($downloaded_file);
                                            if (stripos($pdf_content, "%PDF") !== false) {
                                                $this->exts->new_invoice($invoice_number, $invoice_date, $invoice_amount, $downloaded_file);
                                                $this->isNoInvoice = false;
                                            } else {
                                                $this->exts->log("processDownloadSettlement :: Not Valid PDF - " . $filename);
                                            }
                                        } else {
                                            $this->exts->log("processDownloadSettlement :: No File Downloaded ? - " . $downloaded_file);
                                        }
                                    } else {
                                        $this->exts->log("processDownloadSettlement :: No File Downloaded ? - " . $downloaded_file);
                                    }
                                }
                            }
                        } catch (\Exception $ex1) {
                            $this->exts->log("processDownloadSettlement:: Exception processing record " . $ex1);
                        }
                    }
                } else {
                    $this->exts->log("processDownloadSettlement:: No Invoice");
                    $this->exts->success();
                }
            } catch (\Exception $ex2) {
                $this->exts->log("processDownloadSettlement:: Exception in invoice details " . $ex2);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
