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

    // Server-Portal-ID: 10252 - Last modified: 04.03.2025 13:49:23 UTC - User: 1

    public $baseUrl = "https://www.amazon.it";
    public $orderPageUrl = "https://www.amazon.it/gp/css/order-history/ref=nav_youraccount_orders";
    public $messagePageUrl = "https://www.amazon.it/gp/message?ie=UTF8&cl=4&ref_=ya_mc_bsm&#!/inbox";
    public $businessPrimeUrl = "https://www.amazon.it/businessprimeversand";
    public $login_button_selector = 'div[id="nav-flyout-ya-signin"] a, div[id="nav-signin-tooltip"] a, div#nav-tools a#nav-link-yourAccount';

    public $username_selector = '#ap_email';
    public $password_selector = '#ap_password';
    public $remember_me_selector = 'input[name="rememberMe"]';
    public $submit_login_selector = '#signInSubmit';
    public $continue_button_selector = "form input#continue";

    public $check_login_failed_selector = 'div#auth-error-message-box div.a-alert-content';
    public $check_login_success_selector = 'a#nav-item-signout, a#nav-item-signout-sa';

    public $isNoInvoice = true;
    public $login_tryout = 0;
    public $msg_invoice_triggerd = 0;
    public $restrictPages = 3;
    public $all_processed_orders = array();
    public $amazon_download_overview;
    public $download_invoice_from_message;
    public $auto_request_invoice;
    public $only_years;
    public $auto_tagging;
    public $marketplace_invoice_tags;
    public $order_overview_tags;
    public $amazon_invoice_tags;
    public $start_page = 0;
    public $dateLimitReached = 0;
    public $msgTimeLimitReached = 0;
    public $last_invoice_date = "";
    public $procurment_report = 0;
    public $last_state = array();
    public $current_state = array();
    public $invalid_filename_keywords = array('agb', 'terms', 'datenschutz', 'privacy', 'rechnungsbeilage', 'informationsblatt', 'gesetzliche', 'retouren', 'widerruf', 'allgemeine gesch', 'mfb-buchung', 'informationen zu zahlung', 'nachvertragliche', 'retourenschein', 'allgemeine_gesch', 'rcklieferschein');
    public $start_date = '';
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        if ($this->exts->docker_restart_counter == 0) {
            $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            $this->amazon_download_overview = isset($this->exts->config_array["download_overview_pdf"]) ? (int)$this->exts->config_array["download_overview_pdf"] : 0;
            $this->download_invoice_from_message = isset($this->exts->config_array["download_invoice_from_message"]) ? (int)$this->exts->config_array["download_invoice_from_message"] : 0;
            $this->auto_request_invoice = isset($this->exts->config_array["auto_request_invoice"]) ? (int)$this->exts->config_array["auto_request_invoice"] : 0;
            $this->only_years = isset($this->exts->config_array["only_years"]) ? $this->exts->config_array["only_years"] : '';
            $this->auto_tagging = isset($this->exts->config_array["auto_tagging"]) ? $this->exts->config_array["auto_tagging"] : '';
            $this->marketplace_invoice_tags = isset($this->exts->config_array["marketplace_invoice_tags"]) ? $this->exts->config_array["marketplace_invoice_tags"] : '';
            $this->order_overview_tags = isset($this->exts->config_array["order_overview_tags"]) ? $this->exts->config_array["order_overview_tags"] : '';
            $this->amazon_invoice_tags = isset($this->exts->config_array["amazon_invoice_tags"]) ? $this->exts->config_array["amazon_invoice_tags"] : '';
            $this->procurment_report = isset($this->exts->config_array["procurment_report"]) ? (int)$this->exts->config_array["procurment_report"] : 0;
            $this->start_page = isset($this->exts->config_array["start_page"]) ? $this->exts->config_array["start_page"] : '';
            $this->last_invoice_date = isset($this->exts->config_array["last_invoice_date"]) ? $this->exts->config_array["last_invoice_date"] : '';
            $this->start_date = (isset($this->exts->config_array["start_date"]) && !empty($this->exts->config_array["start_date"])) ? trim($this->exts->config_array["start_date"]) : "";

            if (!empty($this->start_date)) {
                $this->start_date = strtotime($this->start_date);
            }
            $this->exts->log('Download Overview - ' . $this->amazon_download_overview);
            $this->exts->log('Download Invoice from message - ' . $this->download_invoice_from_message);
            $this->exts->log('Auto Invoice Request - ' . $this->auto_request_invoice);

            $this->invalid_filename_pattern = '';
            if (!empty($this->invalid_filename_keywords)) {
                $this->invalid_filename_pattern = '';
                foreach ($this->invalid_filename_keywords as $s) {
                    if ($this->invalid_filename_pattern != '') $this->invalid_filename_pattern .= '|';
                    $this->invalid_filename_pattern .= preg_quote($s, '/');
                }
            }
        } else {
            $this->last_state = $this->current_state;
        }

        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->exts->capture("Home-page-without-cookie");

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->exts->capture("Home-page-with-cookie");

        $this->exts->openUrl($this->orderPageUrl);
        sleep(5);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            if ($this->exts->exists($this->login_button_selector)) {
                $this->exts->moveToElementAndClick($this->login_button_selector);
            } else {
                $this->exts->openUrl($this->orderPageUrl);
            }
            sleep(5);
            $this->checkFillLogin();
            sleep(5);
            $this->processImageCaptcha();
            sleep(15);
            // Check 2FA
            if ($this->exts->exists('input[name="verifyToken"]')) {
                $this->exts->moveToElementAndClick('input[name="verifyToken"] ~ div input#continue');
                sleep(15);

                if ($this->exts->exists('input[name="code"]')) {
                    $this->checkFillTwoFactor('input[name="code"]', 'form[action="verify"] span[class*="verify"] [type="submit"]', 'form[action="verify"] div.a-row.a-spacing-none');
                } else if ($this->exts->exists('#auth-mfa-otpcode')) {
                    $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'div.a-row.a-spacing-none');
                } else if ($this->exts->exists('div#tiv-message + form[action="verify"]')) {
                    $this->checkFillTwoFactorWithPushNotify('div#tiv-message');
                } else if ($this->exts->exists('input[name="transactionApprovalStatus"]')) {
                    $this->checkFillTwoFactorWithPushNotify('div.a-section.a-spacing-large span.a-text-bold, div#channelDetails');
                }
            } else if ($this->exts->exists('[action="verify"]') && $this->exts->exists('[name*="dcq_question_date_picker"]')) {
                $this->exts->log('Two factor auth required - security question');
                $this->checkFillAnswerSerQuestion('[name*="dcq_question_date_picker"]', '[value="verify"]', '[action="verify"] .a-form-label');
            } else if ($this->exts->exists('input[name="transactionApprovalStatus"]')) {
                $this->checkFillTwoFactorWithPushNotify('div.a-section.a-spacing-large span.a-text-bold, div#channelDetails');
            } else if ($this->exts->exists('div#tiv-message + form[action="verify"]')) {
                $this->checkFillTwoFactorWithPushNotify('div#tiv-message');
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]')) {
                $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]:not(:checked)');
                sleep(2);
                $this->exts->moveToElementAndClick('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]')) {
                $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]:not(:checked)');
                sleep(2);
                $this->exts->moveToElementAndClick('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]')) {
                $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]:not(:checked)');
                sleep(2);
                $this->exts->moveToElementAndClick('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            } else if ($this->exts->exists('input#auth-mfa-otpcode')) {
                $this->checkFillTwoFactor('input#auth-mfa-otpcode', 'input#auth-signin-button', 'form#auth-mfa-form h1, form#auth-mfa-form h1 ~ p');
            } else if ($this->exts->exists('input#input-box-otp')) {
                $this->checkFillTwoFactor('input#input-box-otp', 'input[aria-labelledby="cvf-submit-otp-button-announce"]', 'span.a-size-base.transaction-approval-word-break');
            }
        }

        if ($this->checkLogin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->processAfterLogin(0);

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log('::URL login failure:: ' . $this->exts->getUrl());

            $wrongEmail = strtolower($this->exts->extract('div#auth-email-invalid-claim-alert div.a-alert-content'));

            $this->exts->log('::Wrong email text' . $wrongEmail);

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
            $this->exts->log('::error_text:: ' . $error_text);

            if ($this->exts->urlContains('forgotpassword/reverification')) {
                // Password reset required
                // Please set a new password for your account that you have not used elsewhere.
                // We'll email you a One Time Password (OTP) to authenticate this change.
                $this->exts->account_not_ready();
            } elseif ($this->exts->exists('input#account-fixup-phone-number')) {
                // Add Cell Number
                $this->exts->account_not_ready();
            } elseif (
                stripos($error_text, 'la tua password non è corretta') !== false ||
                stripos($error_text, "non riusciamo a trovare un account con quell'indirizzo e-mail") !== false ||
                stripos($error_text, 'your password is incorrect') !== false
            ) {
                $this->exts->loginFailure(1);
            } else if (
                stripos($wrongEmail, strtolower('Indirizzo e-mail o numero di cellulare errato o non valido. Correggi e riprova.')) !== false ||
                stripos($wrongEmail, strtolower('Invalid or incorrect email address or mobile number. Please correct and try again.')) !== false
            ) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $this->exts->capture(__FUNCTION__);
        $isLoggedIn = false;
        sleep(5);
        if ($this->exts->exists($this->check_login_success_selector)) {
            $isLoggedIn = true;
        } elseif ($this->exts->exists("div#nav-tools a#nav-link-accountList, div#nav-tools a#nav-link-yourAccount")) {
            $href = $this->exts->getElement("div#nav-tools a#nav-link-accountList, div#nav-tools a#nav-link-yourAccount")->getAttribute("href");
            if (trim($href) != "" && stripos($href, "/gp/css/homepage.html") !== false) {
                $isLoggedIn = true;
            }
        }

        return $isLoggedIn;
    }

    private function checkFillLogin($count = 0)
    {
        sleep(3);
        $this->exts->log(__FUNCTION__);
        $this->exts->capture(__FUNCTION__);
        if ($this->exts->exists("button.a-button-close.a-declarative")) {
            $this->exts->moveToElementAndClick("button.a-button-close.a-declarative");
        }

        if ($this->exts->exists('div.cvf-account-switcher-profile-details-after-account-removed')) {
            $this->exts->log("click account-switcher");
            $this->exts->capture("account-switcher");
            $this->exts->moveToElementAndClick('div.cvf-account-switcher-profile-details-after-account-removed');
            sleep(4);
        }

        if ($this->exts->exists($this->password_selector) || $this->exts->exists($this->username_selector)) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Click Continue button");
            $this->exts->moveToElementAndClick($this->continue_button_selector);
            sleep(2);

            $this->checkImageCaptcha();

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            // $this->processImageCaptcha();
            // sleep(2);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(2);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processImageCaptcha()
    {
        $this->checkImageCaptcha();
        sleep(10);
        if (!$this->exts->exists('form[name="signIn"] input#auth-captcha-guess')) {
            $this->exts->log("No Image Captcha Found");
            $this->exts->capture('No-Image-Captcha');
            return;
        }

        $this->exts->log("Processing Image Captcha");
        $this->exts->capture(__FUNCTION__);

        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        $this->exts->processCaptcha('form[name="signIn"]', 'form[name="signIn"] input#auth-captcha-guess');
        sleep(2);

        $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(1);

        $this->exts->capture("filled-captcha");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(2);

        // image captcha fail
        if (
            $this->exts->exists('div#auth-error-message-box h4')
            && $this->exts->exists('form[name="signIn"] input#auth-captcha-guess')
        ) {
            $this->exts->log($this->exts->extract('div#auth-error-message-box h4'));
            $this->exts->capture('filled-captcha-failed');

            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->processCaptcha('form[name="signIn"]', 'form[name="signIn"] input#auth-captcha-guess');
            sleep(2);

            // $this->exts->moveToElementAndClick($this->remember_me_selector);
            // sleep(1);

            $this->exts->capture("filled-captcha");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(2);
        }
    }

    private function checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector)
    {
        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $total_2fa = count($this->exts->getElements($two_factor_message_selector));
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < $total_2fa; $i++) {
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

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function checkFillAnswerSerQuestion($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector)
    {
        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $total_2fa = count($this->exts->getElements($two_factor_message_selector));
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < $total_2fa; $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = 'Please enter answer of below question (MM/YYYY): ' . trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = 'Bitte geben Sie die Antwort der folgenden Frage ein (MM/YYYY): ' . $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $month = trim(explode('/', $two_factor_code)[0]);
                $year = trim(end(explode('/', $two_factor_code)));
                $this->exts->moveToElementAndType('[name="dcq_question_date_picker_1_1"]', $month);
                $this->exts->moveToElementAndType('[name="dcq_question_date_picker_1_2"]', $year);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function checkFillTwoFactorWithPushNotify($two_factor_message_selector)
    {
        if ($this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $total_2fa = count($this->exts->getElements($two_factor_message_selector));
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < $total_2fa; $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . 'Please input "OK" after responded email/approve notification!';
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . 'Please input "OK" after responded email/approve notification!';;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '' && strtolower($two_factor_code) == 'ok') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                sleep(15);

                if ($this->exts->getElement($two_factor_message_selector) == null && !$this->exts->exists('input[name="transactionApprovalStatus"]')) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactorWithPushNotify($two_factor_message_selector);
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function checkImageCaptcha()
    {
        if ($this->exts->exists('iframe#aa-challenge-whole-page-iframe')) {
            $this->exts->switchToFrame('iframe#aa-challenge-whole-page-iframe');
            sleep(5);
            $this->exts->processCaptcha('img[alt="captcha"]', 'input#aa_captcha_input');
            $this->exts->moveToElementAndClick('span#aa_captcha_submit_button input[name="submit_button"]');
            sleep(5);
            $this->exts->switchToDefault();
        }
    }

    // Check 2FA
    private function check2FAScreen()
    {
        if ($this->exts->exists('input[name="verifyToken"]')) {
            $this->exts->moveToElementAndClick('input[name="verifyToken"] ~ div input#continue');
            sleep(15);

            if ($this->exts->exists('input[name="code"]')) {
                $this->checkFillTwoFactor('input[name="code"]', 'form[action="verify"] span[class*="verify"] [type="submit"]', 'form[action="verify"] div.a-row.a-spacing-none');
            } else if ($this->exts->exists('#auth-mfa-otpcode')) {
                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'div.a-row.a-spacing-none');
            } else if ($this->exts->exists('div#tiv-message + form[action="verify"]')) {
                $this->checkFillTwoFactorWithPushNotify('div#tiv-message');
            } else if ($this->exts->exists('input[name="transactionApprovalStatus"]')) {
                $this->checkFillTwoFactorWithPushNotify('div.a-section.a-spacing-large span.a-text-bold, div#channelDetails');
            }
        } else if ($this->exts->exists('[action="verify"]') && $this->exts->exists('[name*="dcq_question_date_picker"]')) {
            $this->exts->log('Two factor auth required - security question');
            $this->checkFillAnswerSerQuestion('[name*="dcq_question_date_picker"]', '[value="verify"]', '[action="verify"] .a-form-label');
        } else if ($this->exts->exists('input[name="transactionApprovalStatus"]')) {
            $this->checkFillTwoFactorWithPushNotify('div.a-section.a-spacing-large span.a-text-bold, div#channelDetails');
        } else if ($this->exts->exists('div#tiv-message + form[action="verify"]')) {
            $this->checkFillTwoFactorWithPushNotify('div#tiv-message');
        } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]')) {
            $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]:not(:checked)');
            sleep(2);
            $this->exts->moveToElementAndClick('input#auth-send-code');
            sleep(15);

            $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
        } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]')) {
            $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]:not(:checked)');
            sleep(2);
            $this->exts->moveToElementAndClick('input#auth-send-code');
            sleep(15);

            $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
        } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]')) {
            $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]:not(:checked)');
            sleep(2);
            $this->exts->moveToElementAndClick('input#auth-send-code');
            sleep(15);

            $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
        } else if ($this->exts->exists('input#auth-mfa-otpcode')) {
            $this->checkFillTwoFactor('input#auth-mfa-otpcode', 'input#auth-signin-button', 'form#auth-mfa-form h1, form#auth-mfa-form h1 ~ p');
        } else if ($this->exts->exists('input#input-box-otp')) {
            $this->checkFillTwoFactor('input#input-box-otp', 'input[aria-labelledby="cvf-submit-otp-button-announce"]', 'span.a-size-base.transaction-approval-word-break');
        }
    }

    function processAfterLogin($count)
    {
        $this->exts->log("Begin processAfterLogin " . $count);
        if ($count == 0) {
            $this->exts->openUrl($this->orderPageUrl);
            sleep(2);
        }

        if (stripos($this->exts->getUrl(), "amazon.it/ap/signin") === false) {
            $isMultiAccount = count($this->exts->getElements("select[name=\"selectedB2BGroupKey\"] option")) > 1 ? true : false;
            $this->exts->log("isMultiAccount - " . $isMultiAccount);

            if (empty($this->last_state['stage']) || $this->last_state['stage'] == 'ORDER') {
                // keep current state of processing
                $this->current_state['stage'] = 'ORDER';
                $this->last_state['stage'] = '';

                if ($isMultiAccount > 0) {
                    // Get Business Accounts Filter, only first time of execution, not in restart
                    $optionAccountSelectors = [];
                    $selectAccountElements = $this->exts->getElements("select[name=\"selectedB2BGroupKey\"] option");
                    if (count($selectAccountElements) > 0) {
                        foreach ($selectAccountElements as $selectAccountElement) {
                            $elementAccountValue = trim($selectAccountElement->getAttribute('value'));
                            $optionAccountSelectors[] = $elementAccountValue;
                        }
                    }


                    if (!empty($optionAccountSelectors)) {
                        $this->exts->log("optionAccountSelectors " . count($optionAccountSelectors));
                        foreach ($optionAccountSelectors as $optionAccountSelector) {
                            // In restart mode, process only those account which is not processed yet
                            if ($this->exts->docker_restart_counter > 0 && !empty($this->last_state['accounts']) && in_array($optionAccountSelector, $this->last_state['accounts'])) {
                                $this->exts->log("Restart: Already processed earlier - Account-value  " . $optionAccountSelector);
                                continue;
                            }

                            $this->exts->log("Account-value  " . $optionAccountSelector);

                            // Fill Account Select
                            // $this->exts->getElement("select[name=\"orderFilter\"]")->selectOptionByValue($yearOrderSelection);
                            $optionSelAccEle = "select[name=\"selectedB2BGroupKey\"] option[value=\"" . $optionAccountSelector . "\"]";
                            $this->exts->log("processing account element  " . $optionSelAccEle);
                            $selectAccountElement = $this->exts->getElement($optionSelAccEle);
                            if ($selectAccountElement != null) {
                                $selectAccountElement->click();
                                sleep(5);

                                $this->exts->capture("Account-Selected-" . $optionAccountSelector);

                                //Reset date limit for each account.
                                $this->dateLimitReached = 0;

                                // Process year filters for each account
                                $this->orderYearFilters(trim($optionAccountSelector));

                                // Keep completely processed account key
                                $this->current_state['accounts'][] = $optionAccountSelector;
                            }
                        }
                        $this->last_state['accounts'] = array();
                    } else {
                        $this->orderYearFilters();
                    }
                } else {
                    $this->orderYearFilters();
                }
            }
            //open procurement analysis
            if ((empty($this->last_state['stage']) || $this->last_state['stage'] == 'PROCUREMENT_ANALYSIS') && ((int)$this->procurment_report == 1 || (int)$this->only_business_invoice == 0)) {
                // Keep current state of processing
                $this->current_state['stage'] = 'PROCUREMENT_ANALYSIS';
                $this->last_state['stage'] = '';

                if ($this->exts->exists('a[href*="/b2b/aba/"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/b2b/aba/"]');
                    sleep(10);
                    if (stripos($this->exts->getUrl(), "/ap/signin") !== false) {
                        $this->checkFillLogin(0);
                        // retry if captcha showed
                        if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                            $this->checkFillLogin(0);
                        }
                        //$this->checkFillTwoFactor();
                        $this->check2FAScreen();
                        sleep(4);
                        if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                            $this->exts->moveToElementAndClick('a#ap-account-fixup-phone-skip-link');
                            sleep(2);
                        }
                    } else {
                        // $this->checkFillTwoFactor();
                        $this->check2FAScreen();
                    }
                    if ($this->exts->exists('form[action*="cookieprefs"] #sp-cc-accept')) {
                        $this->exts->moveToElementAndClick('form[action*="cookieprefs"] #sp-cc-accept');
                        sleep(1);
                    }
                    // Click Order report
                    $this->exts->moveToElementAndClick('a[href*="/b2b/aba/reports?reportType=items_report_1"]');
                    sleep(15);
                    $this->exts->capture('procurment_report');

                    if ($this->exts->exists('#date_range_selector__range')) {
                        $this->exts->moveToElementAndClick('#date_range_selector__range');
                        sleep(1);

                        if ((int)$this->restrictPages == 0) {
                            $this->exts->moveToElementAndClick('.date-range-selector .b-dropdown-menu a[value="PAST_12_MONTHS"]');
                        } else {
                            $this->exts->moveToElementAndClick('.date-range-selector .b-dropdown-menu a[value="PAST_12_WEEKS"]');
                        }
                        sleep(15);

                        if (!$this->exts->exists('.report-table .column:nth-child(3) [class*="cell-row-"]')) {
                            sleep(25);
                        }

                        $this->isBusinessUser = true;

                        $this->dateLimitReached = 0;
                        $this->download_procurment_document(1);
                    }
                }
            }
            //Check Business Prime Account
            //https://www.amazon.it/businessprimeversand
            if (empty($this->last_state['stage']) || $this->last_state['stage'] == 'BUSINESS_PRIME') {
                // Keep current state of processing
                $this->current_state['stage'] = 'BUSINESS_PRIME';
                $this->last_state['stage'] = '';

                $this->downloadBusinessPrimeInvoices();
            }

            // Process Message Center
            if (empty($this->last_state['stage']) || $this->last_state['stage'] == 'MESSAGE') {
                // Keep current state of processing
                $this->current_state['stage'] = 'MESSAGE';
                $this->last_state['stage'] = '';

                $this->triggerMsgInvoice();
            }
        } else {
            if ($this->login_tryout == 0) {
                $this->checkFillLogin(0);
            }
        }
    }

    function orderYearFilters($selectedBusinessAccount = "")
    {
        if (trim($selectedBusinessAccount) != "") {
            $this->exts->log("selectedBusinessAccount Account-value  " . $selectedBusinessAccount);
        }

        // Get Order Filter years
        $optionSelectors = array();
        $selectElements = $this->exts->getElements("select[name=\"orderFilter\"] option");
        $this->exts->log("selectElements " . count($selectElements));
        if (count($selectElements) == 0) {
            $selectElements = $this->exts->getElements('select[name="timeFilter"] option');
            $this->exts->log("selectElements " . count($selectElements));
        }
        if (count($selectElements) > 0) {
            $restrictYears = array();
            if (!empty($this->only_years)) {
                $this->exts->log("only_years - " . $this->only_years);
                $restrictYears = explode(",", $this->only_years);
                if (!empty($restrictYears)) {
                    foreach ($restrictYears as $key => $restrictYear) {
                        $restrictYears[$key] = strtolower("year-" . $restrictYear);
                    }
                }
            }
            $this->exts->log("restrictYears " . print_r($restrictYears, true));

            if ((int)@$this->restrictPages > 0) {
                $elementValue = trim($selectElements[1]->getAttribute('value'));
                $optionSelectors[] = $elementValue;
            } else {
                foreach ($selectElements as $selectElement) {
                    $elementValue = trim($selectElement->getAttribute('value'));
                    $this->exts->log("elementValue - " . $elementValue);
                    if (!empty($this->only_years)) {
                        if (count($optionSelectors) < count($restrictYears)) {
                            if (in_array(strtolower($elementValue), $restrictYears)) {
                                $optionSelectors[] = $elementValue;
                            }
                        } else {
                            break;
                        }
                    } else {
                        if ($elementValue != "last30" && $elementValue != "months-6" && $elementValue != "months-3" && $elementValue != "archived") {
                            $optionSelectors[] = $elementValue;
                        }
                    }

                    //Added this to minimize the download for last 2 years
                    if (count($optionSelectors) > 2) break;
                }
            }
        }

        $this->exts->log("optionSelectors " . count($optionSelectors));
        $total_option_selectors = count($optionSelectors);
        if (!empty($optionSelectors)) {
            //for($i=0; $i<$total_option_selectors; $i++) {
            for ($i = 0; $i < 2; $i++) {
                $this->exts->log("year-value  " . $optionSelectors[$i]);
            }

            // Process Each Year
            $this->processYears($optionSelectors);
        }
    }
    function processYears($optionSelectors)
    {
        //Update the lock so that window is not closed by cron.
        $this->exts->update_process_lock();

        $this->exts->capture("Process-Years");

        foreach ($optionSelectors as $optionSelector) {
            $this->exts->log("processing year  " . $optionSelector);

            if ($this->dateLimitReached == 1) break;

            // In restart mode, process only those years which is not processed yet
            if ($this->exts->docker_restart_counter > 0 && !empty($this->last_state['years']) && in_array($optionSelector, $this->last_state['years'])) {
                $this->exts->log("Restart: Already processed year - " . $optionSelector);
                continue;
            }

            // Fill order Select
            if ($this->exts->exists("select[name=\"orderFilter\"]")) {
                $optionSelEle = "select[name=\"orderFilter\"] option[value=\"" . $optionSelector . "\"]";
                $selectElement = $this->exts->getElement($optionSelEle);
                $selectElement->click();
            } else {
                $this->exts->moveToElementAndClick('select#time-filter + span.a-button-dropdown');
                sleep(1);
                $this->exts->moveToElementAndClick('div.a-dropdown li a[data-value*="' . $optionSelector . '"]');
            }
            sleep(5);

            if ($this->exts->getElement($this->password_selector) != null || stripos($this->exts->getUrl(), "/ap/signin?") !== FALSE) {
                if ($this->login_tryout == 0) {
                    $this->checkFillLogin(0);
                    if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                        $this->exts->moveToElementAndClick('a#ap-account-fixup-phone-skip-link');
                        sleep(2);
                    }
                } else {
                    $this->exts->init_required();
                }
            }

            $this->exts->capture("orders-" . $optionSelector);

            if ($this->exts->getElement($this->password_selector) != null || stripos($this->exts->getUrl(), "/ap/signin?") !== false) {
                $this->checkFillLogin(0);
                sleep(4);

                //$this->checkFillTwoFactor();
                $this->check2FAScreen();
                if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                    $this->exts->moveToElementAndClick('a#ap-account-fixup-phone-skip-link');
                    sleep(2);
                }
            }

            $this->exts->waitTillPresent(".order-card, .js-order-card", 15);
            $this->exts->capture('order-page');
            for ($paging_count = 1; $paging_count < 100; $paging_count++) {
                if ($this->exts->getElement(".order-card, .js-order-card") != null) {
                    $this->exts->log("Invoice Found");

                    $invoice_data_arr = array();
                    $rows = $this->exts->getElements(".order-card, .js-order-card");
                    $this->exts->log("Invoice Rows- " . count($rows));
                    $total_rows = count($rows);
                    if (count($rows) > 0) {
                        for ($i = 0, $j = 2; $i < $total_rows; $i++, $j++) {
                            $rowItem = $rows[$i];
                            try {
                                $columns = $this->exts->getElements('div.order-info div.a-fixed-right-grid-col:nth-child(1) span.a-color-secondary.value, .order-header .a-row:nth-child(2) .a-color-secondary', $rowItem);
                                $this->exts->log("Invoice Row columns- $i - " . count($columns));
                                if (count($columns) > 0) {
                                    $invoice_date = trim($columns[0]->getAttribute('innerText'));
                                    $this->exts->log("invoice_date - " . $invoice_date);
                                    $parsed_date = $this->exts->parse_date($invoice_date);
                                    if ($this->parsed_date != "" && !empty($this->start_date)) {
                                        if ($this->start_date > strtotime($parsed_date)) {
                                            $this->dateLimitReached = 1;
                                            break;
                                        }
                                    }
                                    $invoice_amount = trim($columns[count($columns) - 1]->getAttribute('innerText'));
                                    if (stripos($invoice_amount, "EUR") !== false && stripos($invoice_amount, "EUR") <= 1) {
                                        $invoice_amount = trim(substr($invoice_amount, 3)) . " EUR";
                                    } else if (stripos($invoice_amount, "EUR") !== false && stripos($invoice_amount, "EUR") > 1) {
                                        $invoice_amount = trim(substr($invoice_amount, 3, strlen($invoice_amount) - 3)) . " EUR";
                                    } else if (stripos($invoice_amount, "USD") !== false && stripos($invoice_amount, "USD") <= 1) {
                                        $invoice_amount = trim(substr($invoice_amount, 3)) . " USD";
                                    } else if (stripos($invoice_amount, "USD") !== false && stripos($invoice_amount, "USD") > 1) {
                                        $invoice_amount = trim(substr($invoice_amount, 3, strlen($invoice_amount) - 3)) . " USD";
                                    }

                                    $invoice_number = $this->exts->extract('[class*="order-id"] .a-color-secondary:nth-child(2)', $rowItem, 'innerText');
                                    $invoice_number = trim($invoice_number);
                                    $this->exts->log("invoice_number - " . $invoice_number);


                                    $this->exts->log("starting process for invoice_number - " . $invoice_number);
                                    $sellerName = "";
                                    $sellerColumns = $this->exts->getElements("div.a-box.shipment div.a-fixed-left-grid-col.a-col-right span.a-color-secondary", $rowItem);
                                    if (count($sellerColumns) > 0) {
                                        $sellerName = trim($sellerColumns[0]->getAttribute('innerText'));
                                        if (trim($sellerName) != "" && stripos(trim($sellerName), ": Amazon EU S.a.r.L.") !== false && count($sellerColumns) > 1) {
                                            $sellerColumns1 = $this->exts->getElements("div.a-box.shipment div.a-fixed-left-grid-col.a-col-right span.a-size-small.a-color-secondary", $rowItem);
                                            if (count($sellerColumns1) > 0) {
                                                foreach ($sellerColumns1 as $sellerColumnEle) {
                                                    $sellerColumnEleText = trim($sellerColumnEle->getAttribute('innerText'));
                                                    if (trim($sellerColumnEleText) != "" && stripos(trim($sellerColumnEleText), ": Amazon EU S.a.r.L.") === false) {
                                                        $sellerName = trim($sellerColumnEleText);
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    $detailPageUrl = "";
                                    $columns = $this->exts->getElements('div.order-info div.a-fixed-right-grid-col.actions ul a.a-link-normal, a[href*="/order-details"]', $rowItem);
                                    if (count($columns) > 0) {
                                        $detailPageUrl = $columns[0]->getAttribute("href");
                                        if (stripos($detailPageUrl, "https://www.amazon.it") === false && stripos($detailPageUrl, "https://") === false) {
                                            $detailPageUrl = "https://www.amazon.it" . trim($detailPageUrl);
                                        }

                                        $filename = trim($invoice_number) . ".pdf";

                                        //Stop Downloading invoice if invoice is older than 90 days. 45*24 = 1080
                                        /*if($this->last_invoice_date != "" && !empty($this->last_invoice_date)) {
                                        $last_date_timestamp = strtotime($this->last_invoice_date);
                                        $last_date_timestamp = $last_date_timestamp-(1080*60*60);
                                        $parsed_date = $this->exts->parse_date($invoice_date);
                                        if(trim($parsed_date) != "") $invoice_date = $parsed_date;
                                        if($last_date_timestamp > strtotime($invoice_date) && trim($parsed_date) != "") {
                                            $this->exts->log("Skip invoice download as it is not newer than " . $this->last_invoice_date . " - " . $invoice_date);
                                            $this->dateLimitReached = 1;
                                            break;
                                        }
                                    }*/

                                        if (trim($detailPageUrl) != "" && $this->dateLimitReached == 0) {
                                            if ($this->last_invoice_date != "" && !empty($this->last_invoice_date)) {
                                                $last_date_timestamp = strtotime($this->last_invoice_date);
                                                $last_date_timestamp = $last_date_timestamp - (1080 * 60 * 60);
                                            }
                                            $parsed_date = $this->exts->parse_date($invoice_date);
                                            if (trim($parsed_date) != "") $invoice_date = $parsed_date;
                                            if ($last_date_timestamp > strtotime($invoice_date) && $this->last_invoice_date != "" && !empty($this->last_invoice_date) && trim($parsed_date) != "") {
                                                $this->exts->log("Skip invoice download as it is not newer than " . $this->last_invoice_date . " - " . $invoice_date);
                                                $this->dateLimitReached = 1;
                                                break;
                                            } else {
                                                $prices = array();
                                                $price_blocks = $rowItem->querySelectorAll("div.a-box.shipment div.a-fixed-left-grid-col.a-col-right span.a-color-price");
                                                if (!is_string($price_blocks) && count($price_blocks) > 0) {
                                                    foreach ($price_blocks as $price_block) {
                                                        $currentBlockPrice = $price_block->getAttribute('innerText');
                                                        $currentBlockPrice = trim($currentBlockPrice);
                                                        $currentBlockPrice = str_replace("EUR", "", $currentBlockPrice);
                                                        $currentBlockPrice = str_replace(".", "", $currentBlockPrice);
                                                        $currentBlockPrice = str_replace(",", ".", $currentBlockPrice);

                                                        $prices[] = $currentBlockPrice;
                                                    }
                                                }

                                                $isPopOver = $rowItem->querySelectorAll('a[href*="/ajax/invoice/"], [data-a-popover*="invoice"] a');

                                                $invoice_urls = array();
                                                if (count($isPopOver) > 0) {
                                                    $invoice_popover_button = $this->exts->getElement('a[href*="/ajax/invoice/"], [data-a-popover*="invoice"] a', $rowItem);
                                                    $invoice_popover_button->click();
                                                    sleep(2);
                                                    $this->exts->waitTillPresent('.a-popover[aria-hidden="false"] .invoice-list a', 10);
                                                    $links = $this->exts->getElements('.a-popover[aria-hidden="false"] .invoice-list a');

                                                    $this->exts->log("Popover Links found - " . count($links));
                                                    if (empty($links)) {
                                                        $this->exts->log("No Invoice Url found So moving to next row - " . $invoice_number);
                                                        continue;
                                                    }

                                                    // Find overview link
                                                    $overview_link = "";
                                                    foreach ($links as $link_item) {
                                                        $currItemLink = $link_item->getAttribute('href');
                                                        $currItemLink = trim($currItemLink);
                                                        if (stripos($currItemLink, "print.html") !== false) {
                                                            $overview_link = $currItemLink;
                                                            break;
                                                        }
                                                    }

                                                    // Find contact link
                                                    $contact_link = "";
                                                    foreach ($links as $link_item) {
                                                        $currItemLink = $link_item->getAttribute('href');
                                                        $currItemLink = trim($currItemLink);
                                                        if (stripos($currItemLink, "contact.html") !== false) {
                                                            $contact_link = $currItemLink;
                                                            break;
                                                        }
                                                    }

                                                    // Find invoice links
                                                    $inv_num = 1;
                                                    foreach ($links as $lkey =>  $link_item) {
                                                        $currItemLinkText = $link_item->getAttribute('innerText');
                                                        $currItemLinkText = trim($currItemLinkText);

                                                        if (stripos($currItemLinkText, "Nota di credito") === false) {
                                                            // Sometime in .de language appears as english, so alongwith Rechnung, replace Invoice
                                                            $currItemLinkText = str_replace("Fattura / Ricevuta", "", $currItemLinkText);
                                                            $currItemLinkText = str_replace("Ricevuta ", "", $currItemLinkText);
                                                            $currItemLinkText = str_replace("Fattura ", "", $currItemLinkText);
                                                            $currItemLinkText = str_replace("Rechnung ", "", $currItemLinkText);
                                                            $currItemLinkText = str_replace("Invoice ", "", $currItemLinkText);
                                                            $currItemLinkText = str_replace("/", "", $currItemLinkText);

                                                            if ((int)trim($currItemLinkText) == $inv_num) {
                                                                $currItemLink = $link_item->getAttribute('href');
                                                                $currItemLink = trim($currItemLink);

                                                                $invoice_urls[] = array(
                                                                    'link' => $currItemLink,
                                                                    'overview_link' => $overview_link,
                                                                    'contact_url' => "",
                                                                    'price' => isset($prices[$lkey]) ? $prices[$lkey] : 0,
                                                                    'is_credit_note' => 0
                                                                );
                                                                $inv_num++;
                                                            } else {
                                                                $tempArr = explode(" ", $currItemLinkText);
                                                                $tempInvNum = trim($tempArr[count($tempArr) - 1]);
                                                                if ((int)trim($tempInvNum) == $inv_num) {
                                                                    $currItemLink = $link_item->getAttribute('href');
                                                                    $currItemLink = trim($currItemLink);

                                                                    $invoice_urls[] = array(
                                                                        'link' => $currItemLink,
                                                                        'overview_link' => $overview_link,
                                                                        'contact_url' => "",
                                                                        'price' => isset($prices[$lkey]) ? $prices[$lkey] : 0,
                                                                        'is_credit_note' => 0
                                                                    );
                                                                    $inv_num++;
                                                                }
                                                            }
                                                        }
                                                    }

                                                    $inv_num = 1;
                                                    if (trim($contact_link) == "") {
                                                        // Download credit note only if no contact link is available, because if contact link is available
                                                        // system will download overview and do a invoice request
                                                        // virtually in this way, system will never download credit note and in either way we don't need it.
                                                        foreach ($links as $lkey =>  $link_item) {
                                                            $currItemLinkText = $link_item->getAttribute('innerText');
                                                            $currItemLinkText = trim($currItemLinkText);

                                                            if (stripos($currItemLinkText, "Nota di credito") !== false) {
                                                                $currItemLinkText = str_replace("Nota di credito ", "", $currItemLinkText);
                                                                if ((int)trim($currItemLinkText) == $inv_num) {
                                                                    $currItemLink = $link_item->getAttribute('href');
                                                                    $currItemLink = trim($currItemLink);

                                                                    $invoice_urls[] = array(
                                                                        'link' => $currItemLink,
                                                                        'overview_link' => $overview_link,
                                                                        'contact_url' => $contact_link,
                                                                        'price' => isset($prices[$lkey]) ? $prices[$lkey] : 0,
                                                                        'is_credit_note' => 1
                                                                    );
                                                                    $inv_num++;
                                                                }
                                                            } else {
                                                                $currItemLinkText = str_replace("regolazione della fattura ", "", $currItemLinkText);
                                                                $currItemLinkText = str_replace("adeguamento della fattura ", "", $currItemLinkText);
                                                                if ((int)trim($currItemLinkText) == $inv_num) {
                                                                    $currItemLink = $link_item->getAttribute('href');
                                                                    $currItemLink = trim($currItemLink);

                                                                    $invoice_urls[] = array(
                                                                        'link' => $currItemLink,
                                                                        'overview_link' => $overview_link,
                                                                        'contact_url' => $contact_link,
                                                                        'price' => isset($prices[$lkey]) ? $prices[$lkey] : 0,
                                                                        'is_credit_note' => 1
                                                                    );
                                                                    $inv_num++;
                                                                }
                                                            }
                                                        }
                                                    }
                                                    if (empty($invoice_urls)) {
                                                        $invoice_urls[] = array(
                                                            'link' => trim($links[0]->getAttribute('href')),
                                                            'overview_link' => $overview_link,
                                                            'contact_url' => $contact_link,
                                                            'price' => 0,
                                                            'is_credit_note' => 0
                                                        );
                                                    }

                                                    if (empty($invoice_urls)) {
                                                        $invoice_urls[] = array(
                                                            'link' => trim($links[0]->getAttribute('href')),
                                                            'overview_link' => $overview_link,
                                                            'contact_url' => $contact_link,
                                                            'price' => 0,
                                                            'is_credit_note' => 0
                                                        );
                                                    } else {
                                                        //10-06-2021- adding this because now if invoice url is there then amazon removed overview url
                                                        //Check in Invoice URL array is having overview array url or not because if not then order number will be invoice number and if user selected to download overview, invoice will not get saved because of overview
                                                        $checkOverviewArray = false;
                                                        foreach ($invoice_urls as $item_arr) {
                                                            if (stripos($item_arr['link'], "print") !== false) {
                                                                $checkOverviewArray = true;
                                                                break;
                                                            }
                                                        }

                                                        if (!$checkOverviewArray) {
                                                            $temp_invoice_urls = array();
                                                            $temp_invoice_urls[] = array(
                                                                'link' => trim($links[0]->getAttribute('href')),
                                                                'overview_link' => $overview_link,
                                                                'contact_url' => $contact_link,
                                                                'price' => 0,
                                                                'is_credit_note' => 0
                                                            );
                                                            foreach ($invoice_urls as $item_arr) {
                                                                $temp_invoice_urls[] = $item_arr;
                                                            }
                                                            $invoice_urls = $temp_invoice_urls;
                                                        }
                                                    }
                                                } else {
                                                    $links = $rowItem->querySelectorAll("div.orderSummary a.a-link-normal");
                                                    if (count($links) > 0) {
                                                        $invoice_urls[] = array(
                                                            'link' => count($links) > 0 ? trim($links[0]->getAttribute('href')) : "",
                                                            'overview_link' => "",
                                                            'contact_url' => "",
                                                            'price' => 0,
                                                            'is_credit_note' => 0
                                                        );
                                                    }
                                                }


                                                //Remove invoice triggered popups
                                                if ($this->exts->exists('[data-action="a-popover-close"]')) {
                                                    $this->exts->moveToElementAndClick('[data-action="a-popover-close"]');
                                                }

                                                if (!empty($invoice_urls)) {
                                                    $this->isNoInvoice = false;
                                                    $invoicePrefix = 0;
                                                    $invoiceSize = 0;
                                                    $savedInvoices = array();

                                                    foreach ($invoice_urls as $invoice_url_item) {
                                                        $item_invoice_number = $invoice_number;
                                                        $this->exts->log("Invoice url - " . $invoice_url_item['link']);
                                                        $this->exts->log("Invoice Overview url - " . $invoice_url_item['overview_link']);
                                                        $this->exts->log("Invoice cost - " . $invoice_url_item['price']);
                                                        $this->exts->log("Invoice is_credit_note - " . $invoice_url_item['is_credit_note']);

                                                        $contact_url = $invoice_url_item['contact_url'];
                                                        $invoice_url = $invoice_url_item['link'];
                                                        $overview_link = $invoice_url_item['overview_link'];
                                                        $orderPrice = $invoice_url_item['price'];

                                                        if ((int)$invoice_url_item['is_credit_note'] == 1) {
                                                            $item_invoice_number = $item_invoice_number . "-CN";
                                                        } else {
                                                            if (stripos($invoice_url, "print") === false) {
                                                                $contact_url = "";
                                                            }
                                                        }

                                                        if (trim($invoice_url) != "") {
                                                            if (stripos($invoice_url, "contact") !== false) {
                                                                $links = $rowItem->querySelectorAll(".a-popover-content a.a-link-normal");
                                                                $contact_url = $invoice_url;
                                                                $invoice_url = $links[1]->getAttribute('href');
                                                            }
                                                        }

                                                        if (trim($invoice_url) == "") {
                                                            $invoice_url = trim($detailPageUrl) . "&print=1";
                                                        }
                                                        $this->exts->log("invoice_url - " . $invoice_url);

                                                        // If seller is amazon, then download orders only after 2 day.
                                                        // we have noticed sometime it downloads credit note linked as invoice
                                                        if (trim($sellerName) != "" && (trim($sellerName) == "Vendido por: Amazon EU S.a.r.L." || trim($sellerName) == "Venduto da: Amazon EU S.a.r.L.")) {
                                                            $this->exts->log("invoiceDate - " . $invoice_date);
                                                            $timeDiff = strtotime("now") - strtotime($invoice_date);
                                                            $diffDays = ceil($timeDiff / (3600 * 24));
                                                            $this->exts->log("diffDays - " . $diffDays);
                                                            if ($diffDays < 2) {
                                                                $invoice_url = "";
                                                                $this->exts->log("Skipped Amazon seller invoice as it is not 2 days old");
                                                                continue;
                                                            }
                                                        }

                                                        if (stripos($invoice_url, "oh_aui_ajax_request_invoice") !== false) {
                                                            $invoice_url = "";
                                                            $this->exts->log("Skipped Business Account, No Invoice Url, No Auto Request url");
                                                        }

                                                        if (trim($invoice_url) != "") {
                                                            if ($invoicePrefix > 0) {
                                                                $item_invoice_number = $item_invoice_number . "-" . $invoicePrefix;
                                                                $filename = $item_invoice_number . ".pdf";
                                                            }

                                                            if (stripos($invoice_url, "https://www.amazon.it") === false && stripos($invoice_url, "https://") === false) {
                                                                $invoice_url = "https://www.amazon.it" . $invoice_url;
                                                            }
                                                            $this->exts->log("invoice_url - " . $invoice_url);

                                                            if (trim($overview_link) != "" && stripos($overview_link, "https://www.amazon.it") === false && stripos($overview_link, "https://") === false) {
                                                                $overview_link = "https://www.amazon.it" . $overview_link;
                                                            }

                                                            if (stripos($invoice_url, "print") !== false) {
                                                                // Check if user has opted for auto invoice request, then download overview only if amazon is not seller
                                                                $download_overview = $this->amazon_download_overview;
                                                                if ((int)$this->auto_request_invoice == 1 && $contact_url != "") {
                                                                    $download_overview = 1;
                                                                }

                                                                if ($download_overview == 1 && trim($sellerName) != "" && trim($sellerName) != "Vendido por: Amazon EU S.a.r.L." && trim($sellerName) != "Venduto da: Amazon EU S.a.r.L.") {
                                                                    $this->exts->log("Downloading overview page as invoice");

                                                                    $this->exts->log("New Overview invoiceName- " . $item_invoice_number);
                                                                    $this->exts->log("New Overview invoiceAmount- " . $invoice_amount);
                                                                    $this->exts->log("New Overview Filename- " . $filename);

                                                                    //Sometime while capturing overview page we get login form, but not in opening any other page.
                                                                    //So detect such case and process login again

                                                                    $currentUrl = $this->exts->getUrl();

                                                                    // Open New window To process Invoice
                                                                    // $this->exts->open_new_window();

                                                                    $newTab = $this->exts->openNewTab();

                                                                    // Call Processing function to process current page invoices
                                                                    $this->exts->openUrl($invoice_url);
                                                                    sleep(2);

                                                                    if (stripos($this->exts->getUrl(), "amazon.it/ap/signin") !== false) {
                                                                        $this->checkFillLogin(0);
                                                                        sleep(4);

                                                                        $downloaded_file = $this->exts->download_current($filename, 5);
                                                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                                                            $pdf_content = file_get_contents($downloaded_file);
                                                                            if (stripos($pdf_content, "%PDF") !== false) {
                                                                                $savedInvoices[] = array(
                                                                                    'invoiceName' => $item_invoice_number,
                                                                                    'invoiceAmount' => $invoice_amount,
                                                                                    'invoiceDate' => $invoice_date,
                                                                                    'filename' => $filename,
                                                                                    'invoiceSize' => filesize($downloaded_file),
                                                                                    'contact_url' => $contact_url,
                                                                                    'invoice_url' => $invoice_url,
                                                                                    'orderPrice' => $orderPrice
                                                                                );
                                                                            } else {
                                                                                $this->exts->log("Not Valid PDF - " . $filename);
                                                                            }
                                                                        }
                                                                    }

                                                                    // Close new window
                                                                    // $this->exts->close_new_window();
                                                                    $this->exts->closeTab($newTab);
                                                                } else {
                                                                    if (trim($sellerName) != "" && (trim($sellerName) == "Vendido por: Amazon EU S.a.r.L." || trim($sellerName) == "Venduto da: Amazon EU S.a.r.L.")) {
                                                                        $this->exts->log("Skip download overview for amazon");
                                                                    } else {
                                                                        $this->exts->log("Skip download overview as user has not opted for");
                                                                    }
                                                                }
                                                            } else {
                                                                $downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
                                                                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                                                    $pdf_content = file_get_contents($downloaded_file);
                                                                    if (stripos($pdf_content, "%PDF") !== false) {
                                                                        $savedInvoices[] = array(
                                                                            'invoiceName' => $item_invoice_number,
                                                                            'invoiceAmount' => $invoice_amount,
                                                                            'invoiceDate' => $invoice_date,
                                                                            'filename' => $filename,
                                                                            'invoiceSize' => filesize($downloaded_file),
                                                                            'contact_url' => $contact_url,
                                                                            'invoice_url' => $invoice_url,
                                                                            'orderPrice' => $orderPrice
                                                                        );
                                                                    } else {
                                                                        //Sometime while downloading pdf we get login form, but not in opening any other page.
                                                                        //So detect such case and process login again

                                                                        $currentUrl = $this->exts->getUrl();

                                                                        // Open New window To process Invoice
                                                                        // $this->exts->open_new_window();
                                                                        $newTab  =  $this->exts->openNewTab();

                                                                        // Call Processing function to process current page invoices
                                                                        $this->exts->openUrl($invoice_url);
                                                                        sleep(2);

                                                                        if (stripos($this->exts->getUrl(), "amazon.it/ap/signin") !== false) {
                                                                            $this->checkFillLogin(0);
                                                                            sleep(4);

                                                                            unlink($downloaded_file);
                                                                            $downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
                                                                            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                                                                $pdf_content = file_get_contents($downloaded_file);
                                                                                if (stripos($pdf_content, "%PDF") !== false) {
                                                                                    $savedInvoices[] = array(
                                                                                        'invoiceName' => $item_invoice_number,
                                                                                        'invoiceAmount' => $invoice_amount,
                                                                                        'invoiceDate' => $invoice_date,
                                                                                        'filename'      => $filename,
                                                                                        'invoiceSize'   => filesize($downloaded_file),
                                                                                        'contact_url'   => $contact_url,
                                                                                        'invoice_url'   => $invoice_url,
                                                                                        'orderPrice'    => $orderPrice
                                                                                    );
                                                                                }
                                                                            }
                                                                        }

                                                                        // Close new window
                                                                        // $this->exts->close_new_window();
                                                                        $this->exts->closeTab($newTab);
                                                                    }
                                                                }
                                                            }
                                                            $invoicePrefix++;
                                                        }
                                                    }
                                                }

                                                if (!empty($savedInvoices)) {
                                                    foreach ($savedInvoices as $sikey => $savedInvoice) {
                                                        $this->exts->log("Invoice Name - " . $savedInvoice['invoiceName']);
                                                        $this->exts->log("Invoice Date - " . $savedInvoice['invoiceDate']);
                                                        $this->exts->log("Invoice Amount - " . $savedInvoice['invoiceAmount']);
                                                        $this->exts->log("Invoice filename - " . $savedInvoice['filename']);
                                                        $this->exts->log("Invoice invoiceSize - " . $savedInvoice['invoiceSize']);
                                                        $this->exts->log("Invoice contact_url - " . $savedInvoice['contact_url']);
                                                        $this->exts->log("Invoice invoice_url - " . $savedInvoice['invoice_url']);
                                                        $this->exts->log("Invoice orderPrice - " . $savedInvoice['orderPrice']);

                                                        $useOrderPrice = 0;
                                                        $inv_size = 0;
                                                        if ($sikey == 0) {
                                                            $inv_size = $savedInvoice['invoiceSize'];
                                                        } else {
                                                            if ($inv_size == $savedInvoice['invoiceSize'] && $sikey == 1) {
                                                                $useOrderPrice = 1;
                                                            }
                                                        }
                                                    }
                                                    $this->exts->log("Use order price -  -" . $useOrderPrice);

                                                    if (count($savedInvoices) > 1) {
                                                        foreach ($savedInvoices as $sikey => $savedInvoice) {
                                                            $savedInvoices[$sikey]['invoiceAmount'] = ($useOrderPrice == 1) ? $savedInvoices[$sikey]['orderPrice'] : 0;
                                                        }
                                                    }

                                                    foreach ($savedInvoices as $savedInvoice) {
                                                        if (stripos($savedInvoice['invoice_url'], "print") !== false) {
                                                            $this->exts->new_invoice($savedInvoice['invoiceName'], $savedInvoice['invoiceDate'], $savedInvoice['invoiceAmount'], $savedInvoice['filename']);

                                                            $contact_url = $savedInvoice['contact_url'];
                                                            if (trim($contact_url) != "") {
                                                                if (stripos($contact_url, "https://www.amazon.it") === false) {
                                                                    $contact_url = "https://www.amazon.it" . $contact_url;
                                                                }
                                                                $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::" . $contact_url, "===EXTRA-DATA===");
                                                            } else {
                                                                $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::AMAZON_NO_DOWNLOAD", "===EXTRA-DATA===");
                                                            }
                                                            $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::Order Overview - " . $savedInvoice['invoiceName'], "===NOTE-DATA===");

                                                            if ((int)@$this->auto_tagging == 1 && !empty($this->order_overview_tags)) {
                                                                $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::" . $this->order_overview_tags, "===INVOICE-TAGS===");
                                                            }
                                                        } else {
                                                            $this->exts->new_invoice($savedInvoice['invoiceName'], $savedInvoice['invoiceDate'], $savedInvoice['invoiceAmount'], $savedInvoice['filename']);

                                                            $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::Amazon Direct - " . $savedInvoice['invoiceName'], "===NOTE-DATA===");
                                                            if ((int)@$this->auto_tagging == 1 && !empty($this->amazon_invoice_tags)) {
                                                                $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::" . $this->amazon_invoice_tags, "===INVOICE-TAGS===");
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            } catch (\Exception $exception) {
                                $this->exts->log("Exception finding columns element " . $exception->getMessage());
                            }
                        }
                        sleep(2);
                    }
                }

                if ($this->exts->exists('.a-pagination li.a-selected + li:not(.a-disabled) a')) {
                    $this->exts->log('NEXT page ' . $paging_count);
                    $this->exts->moveToElementAndClick('.a-pagination li.a-selected + li:not(.a-disabled) a');
                    sleep(10);
                } else {
                    break;
                }
            }
            // Keep processed years
            $this->last_state['years'] = array();
            $this->current_state['years'][] = $optionSelector;
        }
    }
    function getTotalYearPages($reloadCount)
    {
        $pages = 0;
        if ($this->exts->getElement("span.num-orders-for-orders-by-date span.num-orders") != null) {
            $total_data = $this->exts->getElement("span.num-orders-for-orders-by-date span.num-orders")->getAttribute('innerText');
            $this->exts->log("total_data -" . $total_data);
            $tempArr = explode(" ", $total_data);
            if (count($tempArr)) {
                $total_data = trim($tempArr[0]);
            }
            $this->exts->log("total_data -" . $total_data);
            $pages = round($total_data / 10);
            $this->exts->log("total_data -" . $pages);

            if ($pages < 0) {
                $pageEle = $this->exts->getElements("div.pagination-full a");
                $liCount = count($pageEle);
                if ($liCount > 2) {
                    $pages = (int)trim($pageEle[$liCount - 2]->getAttribute('innerText'));
                }
            }
        } else if ($this->exts->getElement("span.num-orders") != null) {
            $total_data = $this->exts->getElement("span.num-orders")->getAttribute('innerText');
            $this->exts->log("total_data -" . $total_data);
            $tempArr = explode(" ", $total_data);
            if (count($tempArr)) {
                $total_data = trim($tempArr[0]);
            }
            $this->exts->log("total_data -" . $total_data);
            $pages = round($total_data / 10);
            $this->exts->log("total_data -" . $pages);

            if ($pages < 0) {
                $pageEle = $this->exts->getElements("div.pagination-full a");
                $liCount = count($pageEle);
                if ($liCount > 2) {
                    $pages = (int)trim($pageEle[$liCount - 2]->getAttribute('innerText'));
                }
            }
        } else {
            $pageEle = $this->exts->getElements("div.pagination-full a");
            if (count($pageEle)) {
                $liCount = count($pageEle);
                if ($liCount > 2) {
                    $pages = (int)trim($pageEle[$liCount - 2]->getAttribute('innerText'));
                }
            }
        }

        //$this->exts->getElement("div#partial-order-fail-alert")->isDisplayed();
        if ((int)@$pages == 0 && (int)@$reloadCount == 0) {
            $reloadCount++;
            $currentUrl = $this->exts->getUrl();
            $this->exts->openUrl($currentUrl);
            sleep(5);

            $pages = $this->getTotalYearPages($reloadCount);
        }

        return $pages;
    }

    function downloadBusinessPrimeInvoices()
    {
        $this->exts->openUrl($this->businessPrimeUrl);
        sleep(5);

        if (stripos($this->exts->getUrl(), "amazon.it/ap/signin") !== false) {
            $this->checkFillLogin(0);
            sleep(4);
        }

        if ($this->exts->getElement("a[href*=\"/bb/benefits/bps/landing/?ref_=ab_bps_engmt_mgmtbox\"]") != null) {
            $this->exts->getElement("a[href*=\"/bb/benefits/bps/landing/?ref_=ab_bps_engmt_mgmtbox\"]")->click();
            sleep(10);

            $invoice_url = "";
            if ($this->exts->getElement("a#business-prime-shipping-view-last-invoice") != null) {
                try {
                    $invoice_url = $this->exts->getElement("a#business-prime-shipping-view-last-invoice")->getAttribute("href");
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                    if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.it") === false && stripos($invoice_url, "https://") === false) {
                        $invoice_url = "https://www.amazon.it" . $invoice_url;
                    }
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                } catch (\Exception $exception) {
                    $this->exts->log("Getting business prime invoice 1st option - " . $exception->getMessage());
                }
            } else if ($this->exts->getElement("a[href*=\"/documents/download/\"]") != null) {
                try {
                    $invoice_url = $this->exts->getElement("a[href*=\"/documents/download/\"]")->getAttribute("href");
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                    if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.it") === false && stripos($invoice_url, "https://") === false) {
                        $invoice_url = "https://www.amazon.it" . $invoice_url;
                    }
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                } catch (\Exception $exception) {
                    $this->exts->log("Getting business prime invoice 2nd option - " . $exception->getMessage());
                }
            }

            if (trim($invoice_url) != "" && !empty($invoice_url)) {
                try {
                    $invoiceDate = "";
                    if ($this->exts->getElement("div#bps-mgmt-payment-history-last-invoice span > font") != null) {
                        $tempInvoiceDate = trim($this->exts->getElement("div#bps-mgmt-payment-history-last-invoice span > font")->getAttribute('innerText'));
                        $tempArr = explode(":", $tempInvoiceDate);
                        $invoiceDate = trim($tempArr[count($tempArr) - 1]);
                    } else if ($this->exts->getElement("div#bps-mgmt-payment-history-last-invoice") != null) {
                        $tempInvoiceDate = trim($this->exts->getElement("div#bps-mgmt-payment-history-last-invoice")->getAttribute('innerText'));
                        $tempArr = explode(":", $tempInvoiceDate);
                        $invoiceDate = trim($tempArr[count($tempArr) - 1]);
                        $this->exts->log("prime invoice date - " . $invoiceDate);

                        $tempArr = explode(" ", $invoiceDate);
                        $invoiceDate = trim($tempArr[0]) . " " . trim($tempArr[1]) . " " . trim($tempArr[2]);
                        $this->exts->log("prime invoice date - " . $invoiceDate);
                    }
                    $this->exts->log("prime invoice date - " . $invoiceDate);

                    if (trim($invoiceDate) != "") {
                        $parsed_invoice_date = $this->exts->parse_date($invoiceDate);
                        $invoiceDate = $parsed_invoice_date;
                    }
                    $this->exts->log("prime invoice date - " . $invoiceDate);
                    $this->isNoInvoice = false;
                    $filename = "";
                    if (trim($invoiceDate) != "") $filename = trim($invoiceDate) . ".pdf";
                    $downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice("", $invoiceDate, "", $downloaded_file);
                    }
                } catch (\Exception $exception) {
                    $this->exts->log("Downloading prime invoice - " . $exception->getMessage());
                }
            } else {
                $this->exts->log("No Business Prime Invoices");
                $this->exts->success();
            }
        } else {
            $this->exts->log("No Business Prime Invoices");
            $this->exts->success();
        }
    }

    function triggerMsgInvoice()
    {
        if ((int)@$this->msg_invoice_triggerd == 0) {
            $this->msg_invoice_triggerd = 1;
            if ((int)@$this->download_invoice_from_message == 1 && empty($this->only_years)) {
                $this->msgTimeLimitReached = 0;
                $this->processMSInvoice(0);
            }
        }
    }
    function processMSInvoice($currentMessagePage)
    {
        $this->exts->openUrl($this->messagePageUrl);
        sleep(5);

        if (stripos($this->exts->getUrl(), "amazon.it/ap/signin") !== false) {
            $this->checkFillLogin();
            sleep(5);
            $this->processImageCaptcha();
            sleep(15);
            // Check 2FA
            if ($this->exts->exists('input[name="verifyToken"]')) {
                $this->exts->moveToElementAndClick('input[name="verifyToken"] ~ div input#continue');
                sleep(15);

                if ($this->exts->exists('input[name="code"]')) {
                    $this->checkFillTwoFactor('input[name="code"]', 'form[action="verify"] span[class*="verify"] [type="submit"]', 'form[action="verify"] div.a-row.a-spacing-none');
                } else if ($this->exts->exists('#auth-mfa-otpcode')) {
                    $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'div.a-row.a-spacing-none');
                } else if ($this->exts->exists('div#tiv-message + form[action="verify"]')) {
                    $this->checkFillTwoFactorWithPushNotify('div#tiv-message');
                } else if ($this->exts->exists('input[name="transactionApprovalStatus"]')) {
                    $this->checkFillTwoFactorWithPushNotify('div.a-section.a-spacing-large span.a-text-bold, div#channelDetails');
                }
            } else if ($this->exts->exists('[action="verify"]') && $this->exts->exists('[name*="dcq_question_date_picker"]')) {
                $this->exts->log('Two factor auth required - security question');
                $this->checkFillAnswerSerQuestion('[name*="dcq_question_date_picker"]', '[value="verify"]', '[action="verify"] .a-form-label');
            } else if ($this->exts->exists('input[name="transactionApprovalStatus"]')) {
                $this->checkFillTwoFactorWithPushNotify('div.a-section.a-spacing-large span.a-text-bold, div#channelDetails');
            } else if ($this->exts->exists('div#tiv-message + form[action="verify"]')) {
                $this->checkFillTwoFactorWithPushNotify('div#tiv-message');
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]')) {
                $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]:not(:checked)');
                sleep(2);
                $this->exts->moveToElementAndClick('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]')) {
                $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]:not(:checked)');
                sleep(2);
                $this->exts->moveToElementAndClick('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]')) {
                $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]:not(:checked)');
                sleep(2);
                $this->exts->moveToElementAndClick('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            } else if ($this->exts->exists('input#auth-mfa-otpcode')) {
                $this->checkFillTwoFactor('input#auth-mfa-otpcode', 'input#auth-signin-button', 'form#auth-mfa-form h1, form#auth-mfa-form h1 ~ p');
            }
        }

        $inv_msgs = array();
        $invMsgRows = $this->exts->getElements("//*[@id=\"inbox_bsm_tab_content\"]/tbody/tr");
        $this->exts->log("Message Rows on page - " . $currentMessagePage . " - " . count($invMsgRows));
        if (count($invMsgRows) == 0) {
            if ((int)@$currentMessagePage == 0) {
                $this->exts->openUrl($this->alt_messagePageUrl);
                sleep(5);
            }

            if (stripos($this->exts->getUrl(), "amazon.it/ap/signin") !== false) {
                $this->checkFillLogin();
                sleep(5);
                $this->processImageCaptcha();
                sleep(15);
                // Check 2FA
                if ($this->exts->exists('input[name="verifyToken"]')) {
                    $this->exts->moveToElementAndClick('input[name="verifyToken"] ~ div input#continue');
                    sleep(15);

                    if ($this->exts->exists('input[name="code"]')) {
                        $this->checkFillTwoFactor('input[name="code"]', 'form[action="verify"] span[class*="verify"] [type="submit"]', 'form[action="verify"] div.a-row.a-spacing-none');
                    } else if ($this->exts->exists('#auth-mfa-otpcode')) {
                        $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'div.a-row.a-spacing-none');
                    } else if ($this->exts->exists('div#tiv-message + form[action="verify"]')) {
                        $this->checkFillTwoFactorWithPushNotify('div#tiv-message');
                    } else if ($this->exts->exists('input[name="transactionApprovalStatus"]')) {
                        $this->checkFillTwoFactorWithPushNotify('div.a-section.a-spacing-large span.a-text-bold, div#channelDetails');
                    }
                } else if ($this->exts->exists('[action="verify"]') && $this->exts->exists('[name*="dcq_question_date_picker"]')) {
                    $this->exts->log('Two factor auth required - security question');
                    $this->checkFillAnswerSerQuestion('[name*="dcq_question_date_picker"]', '[value="verify"]', '[action="verify"] .a-form-label');
                } else if ($this->exts->exists('input[name="transactionApprovalStatus"]')) {
                    $this->checkFillTwoFactorWithPushNotify('div.a-section.a-spacing-large span.a-text-bold, div#channelDetails');
                } else if ($this->exts->exists('div#tiv-message + form[action="verify"]')) {
                    $this->checkFillTwoFactorWithPushNotify('div#tiv-message');
                } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]')) {
                    $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]:not(:checked)');
                    sleep(2);
                    $this->exts->moveToElementAndClick('input#auth-send-code');
                    sleep(15);

                    $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
                } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]')) {
                    $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]:not(:checked)');
                    sleep(2);
                    $this->exts->moveToElementAndClick('input#auth-send-code');
                    sleep(15);

                    $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
                } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]')) {
                    $this->exts->moveToElementAndClick('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]:not(:checked)');
                    sleep(2);
                    $this->exts->moveToElementAndClick('input#auth-send-code');
                    sleep(15);

                    $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
                } else if ($this->exts->exists('input#auth-mfa-otpcode')) {
                    $this->checkFillTwoFactor('input#auth-mfa-otpcode', 'input#auth-signin-button', 'form#auth-mfa-form h1, form#auth-mfa-form h1 ~ p');
                }
            }

            if ((int)@$currentMessagePage == 0 && $this->exts->getElement("div#inbox-page ul.a-tabs.a-declarative li[data-a-tab-name=\"inbox_bsm_tab\"] a") != null && $this->exts->getElement("div#inbox-page ul.a-tabs.a-declarative li[data-a-tab-name=\"inbox_bsm_tab\"] a")->isDisplayed()) {
                $this->exts->moveToElementAndClick("div#inbox-page ul.a-tabs.a-declarative li[data-a-tab-name=\"inbox_bsm_tab\"] a");
            }
            sleep(10);

            $invMsgRows = $this->exts->getElements("//*[@id=\"inbox_bsm_tab_content\"]/tbody/tr");
            $this->exts->log("Message Rows on page - " . $currentMessagePage . " - " . count($invMsgRows));
        }
        if (count($invMsgRows) > 0) {
            $msgTimeDiff = time() - (60 * 24 * 60 * 60);
            foreach ($invMsgRows as $key => $invMsgRow) {
                $mj = $key + 1;
                $invMsgCols = $this->exts->getElements("//*[@id=\"inbox_bsm_tab_content\"]/tbody/tr[$mj]/td");
                $invMsgColImg = $this->exts->getElements("//*[@id=\"inbox_bsm_tab_content\"]/tbody/tr[$mj]//span/img");

                $this->exts->log("Message cols and imgcols  - " . $key . " - " . count($invMsgCols) . " - " . count($invMsgColImg));
                if (count($invMsgColImg) > 0 && count($invMsgCols) > 0) {
                    $invMsgColImgTitle = $invMsgColImg[0]->getAttribute("Title");
                    $invMsgColImgTitle = trim($invMsgColImgTitle);
                    if (empty($invMsgColImgTitle)) $invMsgColImgTitle = trim($invMsgColImg[0]->getAttribute("title"));
                    $this->exts->log("message img title - " . $key . " - " . $invMsgColImgTitle);
                    if ($invMsgColImgTitle == "Attachment") {
                        $msgTime = ($invMsgCols[0]->getAttribute("messagesenttime") / 1000);
                        if ($msgTime > $msgTimeDiff) {
                            $inv_msgs[] = array(
                                'msg_time' => $invMsgCols[0]->getAttribute("messagesenttime"),
                                'msg_id' => $invMsgCols[0]->getAttribute("messageid")
                            );
                        } else {
                            $this->msgTimeLimitReached = 1;
                            $this->exts->log("Skipping message older than 60 days - " . date("Y-m-d H:i:s", $msgTime));
                            break;
                        }
                    }
                }
            }
        }

        $this->exts->log("Found message on page - " . $currentMessagePage . " - " . count($inv_msgs));
        if (!empty($inv_msgs)) {
            // close all new tab too avoid too much tabs before starting message processing
            // $handles = $this->exts->webdriver->getWindowHandles();
            // if(count($handles) > 1){
            //     $this->exts->webdriver->switchTo()->window(end($handles));
            //     $this->exts->webdriver->close();
            //     $handles = $this->exts->webdriver->getWindowHandles();
            //     $this->exts->webdriver->switchTo()->window($handles[0]);
            // }
            $this->exts->closeTab();

            // Open New window To process Invoice
            //$this->exts->open_new_window();

            //Call Processing function to process current page invoices
            $this->startCurrentPageMessageDownload($inv_msgs);
            sleep(2);

            // close new tab too avoid too much tabs
            // $handles = $this->exts->webdriver->getWindowHandles();
            // if(count($handles) > 1){
            //     $this->exts->webdriver->switchTo()->window(end($handles));
            //     $this->exts->webdriver->close();
            //     $handles = $this->exts->webdriver->getWindowHandles();
            //     $this->exts->webdriver->switchTo()->window($handles[0]);
            // }
            $this->exts->closeTab();
        }

        if (count($invMsgRows) > 0) {
            if ($this->exts->getElement("#inbox_button_next_page input[type=\"submit\"]") != null && $this->exts->getElement("#inbox_button_next_page input[type=\"submit\"]")->isDisplayed() && (int)@$this->msgTimeLimitReached == 0) {
                if ($this->exts->getElement(".a-button-disabled#inbox_button_next_page input[type=\"submit\"]") == null && $this->exts->getElement("#inbox_button_next_page input[type=\"submit\"]")->isDisplayed()) {
                    $this->exts->getElement("#inbox_button_next_page input[type=\"submit\"]")->click();
                    $currentMessagePage++;
                    sleep(10);

                    $this->processMSInvoice($currentMessagePage);
                }
            }
        }
    }
    function startCurrentPageMessageDownload($inv_msgs)
    {
        foreach ($inv_msgs as $inv_msg) {
            $this->exts->log("Message ID - " . $inv_msg['msg_id']);
            $this->exts->log("Message Timestamp - " . $inv_msg['msg_time']);

            $msgUrl = "https://www.amazon.it/gp/message?ie=UTF8&cl=4&ref_=ya_mc_bsm&#!/detail/" . $inv_msg['msg_id'] . "/bsm/" . $inv_msg['msg_time'] . "/inbox";
            $this->exts->log("Message URL - " . $msgUrl);

            $this->exts->openUrl($msgUrl);
            sleep(5);

            if ($this->exts->getElement('.a-ordered-list a.a-link-normal') != null) {
                $links = $this->exts->getElements(".a-ordered-list a.a-link-normal");
                foreach ($links as $link_item) {
                    $invoice_data = array();
                    $invoice_name = trim($link_item->getAttribute('innerText'));

                    if (stripos($invoice_name, ".pdf") !== false) {
                        $order_number = "";

                        $ordItems = $this->exts->getElements("div#detail-page .a-box-inner a[href*=\"summary/edit.html\"]");
                        if (count($ordItems) > 0) {
                            $order_number = trim($ordItems[0]->getAttribute('innerText'));
                        }

                        $invoice_data = array(
                            'invoice_name' => $invoice_name,
                            'invoice_url' => $link_item->getAttribute("href"),
                            'order_number' => $order_number
                        );
                        $this->isNoInvoice = false;
                        $this->exts->log("Invoice data found - " . count($invoice_data));

                        if (!empty($invoice_data)) {
                            $this->exts->log("Invoice Name - " . $invoice_data['invoice_name']);
                            $this->exts->log("Invoice Order Number - " . $invoice_data['order_number']);
                            $this->exts->log("Invoice Url - " . $invoice_data['invoice_url']);

                            if (trim($invoice_data['invoice_url']) != "" && !$this->exts->invoice_exists($invoice_name)) {
                                $file_ext = $this->exts->get_file_extension($invoice_name);
                                if ($file_ext == 'pdf') {
                                    $invoice_name = basename($invoice_name, '.' . $file_ext);
                                    if (trim($invoice_name) != "" && !empty($this->invalid_filename_pattern) && preg_match("/" . $this->invalid_filename_pattern . "/i", $invoice_name)) {
                                        $this->exts->log("Skipping as file name is in blacklist - " . $invoice_name);
                                    } else {
                                        preg_replace('/^\D+/i', '', $invoice_name);
                                        if (trim($invoice_name) == "") {
                                            $invoice_name = $inv_msg['msg_id'];
                                        }
                                        $filename = $invoice_name . ".pdf";

                                        $invoice_url = $invoice_data['invoice_url'];
                                        if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.it") === false && stripos($invoice_url, "https://") === false) {
                                            $invoice_url = "https://www.amazon.it" . $invoice_url;
                                        }

                                        $downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {

                                            $this->exts->new_invoice($invoice_name, "", "", $filename);

                                            $this->exts->sendRequestEx($invoice_name . ":::Marketplace Seller - " . $invoice_data['order_number'], "===NOTE-DATA===");
                                            if ((int)@$this->auto_tagging == 1 && !empty($this->marketplace_invoice_tags)) {
                                                $this->exts->sendRequestEx($invoice_name . ":::" . $this->marketplace_invoice_tags, "===INVOICE-TAGS===");
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $this->exts->log("Unable to load message detail page");
            }
        }
    }

    public function download_procurment_document($pageNum = 1)
    {
        //Added do /while and remove calling recursive function because after 256 php stop recurssion.
        do {
            if ($pageNum > 1) {
                if ((int)$this->restrictPages == 0) {
                    if ($this->exts->exists('.report-table-footer button[data-testid="next-button"]') && !$this->exts->exists('.report-table-footer button[data-testid="next-button"][disabled]')) {
                        $this->exts->moveToElementAndClick('.report-table-footer button[data-testid="next-button"]');
                        sleep(15);
                        $pageNum++;
                    } else {
                        break;
                    }
                } else {
                    if ($this->exts->exists('.report-table-footer button[data-testid="next-button"]') && !$this->exts->exists('.report-table-footer button[data-testid="next-button"][disabled]') && $pageNum < 50) {
                        $this->exts->moveToElementAndClick('.report-table-footer button[data-testid="next-button"]');
                        sleep(15);
                        $pageNum++;
                    } else {
                        break;
                    }
                }
            } else {
                $pageNum++;
            }
            sleep(15);
            if ($this->dateLimitReached == 1) break;
            $rows = $this->exts->getElements('.report-table .column:nth-child(3) [class*="cell-row-"]');
            foreach ($rows as $key => $row) {
                $row =  $this->exts->getElements('.report-table .column:nth-child(3) [class*="cell-row-"]')[$key];
                $linkBtn = $this->exts->getElement('[data-action="a-popover"] a', $row);
                $datecell = $this->exts->getElements('.report-table .column:nth-child(2) [class*="cell-row-"]')[$key];
                if ($linkBtn != null) {
                    $orderNum = trim($linkBtn->getAttribute('innerText'));
                    $this->exts->log('Order - ' . $orderNum);
                    try {
                        $linkBtn->click();
                    } catch (\Exception $exception) {
                        $this->exts->executeSafeScript('arguments[0].click();', [$linkBtn]);
                    }
                    sleep(1);


                    if ($this->exts->exists('.a-popover .a-popover-content a')) {
                        $linkElements = $this->exts->getElements('.a-popover .a-popover-content a');
                        $this->exts->log('Total Link - ' . count($linkElements));
                        $downloadLinks = array();
                        foreach ($linkElements as $linkElement) {
                            $url = $linkElement->getAttribute('href');
                            $this->exts->log('URL - ' . $url);
                            if ((int)@$this->amazon_download_overview == 1) {
                                $downloadLinks[] = $url;
                            } else if (stripos($url, '/b2b/aba/order-summary/') === false) {
                                $downloadLinks[] = $url;
                            }
                        }

                        try {
                            $invoice_date = $datecell->getAttribute('innerText');
                            $this->exts->log('Invoice Date - ' . $invoice_date);
                            $parse_date = $this->exts->parse_date($invoice_date);
                            $this->exts->log('Parsed Invoice Date - ' . $parse_date);
                            if (!empty($parse_date) && $parse_date != null) $invoice_date = $parse_date;
                            if ($this->start_date != "" && !empty($this->start_date) && !empty($invoice_date)) {
                                if ($this->start_date > strtotime($invoice_date)) {
                                    $this->dateLimitReached = 1;
                                }
                            }
                        } catch (\Exception $exceptiondt) {
                            $this->exts->log("Exception getting date " . $exceptiondt);
                        }

                        $currentUrl = $this->exts->getUrl();

                        foreach ($downloadLinks as $downloadLink) {
                            if (stripos($downloadLink, '/b2b/aba/order-summary/') !== false) {
                                if (trim($orderNum) !== '' && !$this->exts->invoice_exists($orderNum)) {
                                    $invoice_name = $orderNum;
                                    $fileName = $orderNum . '.pdf';

                                    // Open New window To process Invoice

                                    // $this->exts->open_new_window();
                                    $newTab = $this->exts->openNewTab();

                                    // Call Processing function to process current page invoices
                                    $this->exts->openUrl($downloadLink);
                                    sleep(2);

                                    if (stripos($this->exts->getUrl(), "amazon.it/ap/signin") !== false) {
                                        $this->checkFillLogin(0);
                                        sleep(4);
                                    }
                                    sleep(2);

                                    if (stripos($this->exts->getUrl(), "amazon.it/ap/signin") === false && stripos($this->exts->getUrl(), "/print.html") !== false) {
                                        $downloaded_file = $this->exts->download_current($fileName, 10);
                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                            $invoice_note = "Order Overview - " . $invoice_name;
                                            $this->exts->new_invoice($invoice_name, '', '', $downloaded_file, 1, $invoice_note, 0, '', array(
                                                'extra_data' => "AMAZON_NO_DOWNLOAD",
                                                'tags' => (int)@$this->auto_tagging == 1 && !empty($this->order_overview_tags) ? $this->order_overview_tags : ''
                                            ));

                                            $invoice_note = ":::" . $invoice_note;
                                            $this->exts->sendRequestEx($invoice_name . ":::AMAZON_NO_DOWNLOAD", "===EXTRA-DATA===");
                                            $this->exts->sendRequestEx($invoice_name . $invoice_note, "===NOTE-DATA===");

                                            if ((int)@$this->auto_tagging == 1 && !empty($this->order_overview_tags)) {
                                                $this->exts->sendRequestEx($invoice_name . ":::" . $this->order_overview_tags, "===INVOICE-TAGS===");
                                            }
                                        }
                                    } else if (stripos($this->exts->getUrl(), "amazon.it/ap/signin") === false && (stripos($this->exts->getUrl(), "order-document.pdf") !== false || stripos($this->exts->getUrl(), ".pdf") !== false)) {
                                        // Wait for completion of file download
                                        $this->exts->wait_and_check_download('pdf');

                                        // find new saved file and return its path
                                        $downloaded_file = $this->exts->find_saved_file('pdf', $fileName);
                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                            $invoice_note = "Order Overview - " . $invoice_name;
                                            $this->exts->new_invoice($invoice_name, '', '', $downloaded_file, 1, $invoice_note, 0, '', array(
                                                'extra_data' => "AMAZON_NO_DOWNLOAD",
                                                'tags' => (int)@$this->auto_tagging == 1 && !empty($this->order_overview_tags) ? $this->order_overview_tags : ''
                                            ));

                                            $invoice_note = ":::" . $invoice_note;
                                            $this->exts->sendRequestEx($invoice_name . ":::AMAZON_NO_DOWNLOAD", "===EXTRA-DATA===");
                                            $this->exts->sendRequestEx($invoice_name . $invoice_note, "===NOTE-DATA===");

                                            if ((int)@$this->auto_tagging == 1 && !empty($this->order_overview_tags)) {
                                                $this->exts->sendRequestEx($invoice_name . ":::" . $this->order_overview_tags, "===INVOICE-TAGS===");
                                            }
                                        }
                                    } else {
                                        // Wait for completion of file download
                                        $this->exts->wait_and_check_download('pdf');

                                        // find new saved file and return its path
                                        $downloaded_file = $this->exts->find_saved_file('pdf', $fileName);
                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                            $invoice_note = "Order Overview - " . $invoice_name;
                                            $this->exts->new_invoice($invoice_name, '', '', $downloaded_file, 1, $invoice_note, 0, '', array(
                                                'extra_data' => "AMAZON_NO_DOWNLOAD",
                                                'tags' => (int)@$this->auto_tagging == 1 && !empty($this->order_overview_tags) ? $this->order_overview_tags : ''
                                            ));

                                            $invoice_note = ":::" . $invoice_note;
                                            $this->exts->sendRequestEx($invoice_name . ":::AMAZON_NO_DOWNLOAD", "===EXTRA-DATA===");
                                            $this->exts->sendRequestEx($invoice_name . $invoice_note, "===NOTE-DATA===");

                                            if ((int)@$this->auto_tagging == 1 && !empty($this->order_overview_tags)) {
                                                $this->exts->sendRequestEx($invoice_name . ":::" . $this->order_overview_tags, "===INVOICE-TAGS===");
                                            }
                                        }
                                    }

                                    //This is needed if URL shows print.html
                                    $this->exts->openUrl($this->baseUrl);
                                    sleep(2);
                                    if ($this->exts->exists('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]')) {
                                        $this->exts->moveToElementAndClick('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]');
                                        sleep(2);
                                    }

                                    // Close new window
                                    $this->exts->closeTab($newTab);
                                } else {
                                    $this->exts->log('Already Invoice Exists - ' . $orderNum);
                                }
                            } else {
                                //I am opening this becasue sometime download link gives technical error.
                                // $this->exts->open_new_window();
                                $newTab = $this->exts->openNewTab();

                                $this->exts->openUrl($this->baseUrl);
                                sleep(2);
                                if ($this->exts->exists('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]')) {
                                    $this->exts->moveToElementAndClick('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]');
                                    sleep(2);
                                }

                                $downloaded_file = $this->exts->direct_download($downloadLink, "pdf", '');
                                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                    $invoice_name = basename($downloaded_file, '.pdf');
                                    $this->exts->new_invoice($invoice_name, '', '', $downloaded_file);
                                } else {
                                    $downloadLink = urldecode($downloadLink);
                                    $downloadLink = preg_replace('/\s+/', '', $downloadLink);
                                    $downloaded_file = $this->exts->direct_download($downloadLink, "pdf", '');
                                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                        $invoice_name = basename($downloaded_file, '.pdf');
                                        $this->exts->new_invoice($invoice_name, '', '', $downloaded_file);
                                    }
                                }

                                // $this->exts->close_new_window();
                                $this->exts->closeTab($newTab);
                            }
                        }
                    } else {
                        $this->exts->log('No Invoice - ' . $orderNum);
                    }
                }

                $popups = $this->exts->getElements('.a-popover.a-popover-no-header.a-arrow-right');
                if (count($popups) > 0) {
                    $this->exts->execute_javascript("
                var popups = document.querySelectorAll(\".a-popover.a-popover-no-header.a-arrow-right\");
                for(var i=0; i<popups.length; i++) {
                    popups[i].remove();
                }");
                }
            }
        } while ($this->exts->exists('.report-table-footer button[data-testid="next-button"]') && !$this->exts->exists('.report-table-footer button[data-testid="next-button"][disabled]') && $pageNum > 1);
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
