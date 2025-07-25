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

    // Server-Portal-ID: 10250 - Last modified: 16.04.2025 14:45:36 UTC - User: 1

    public $baseUrl = "https://www.amazon.fr";
    public $orderPageUrl = "https://www.amazon.fr/gp/css/order-history/ref=nav_youraccount_orders";
    public $messagePageUrl = "https://www.amazon.fr/gp/message?ref_=ya_d_l_msg_center#!/inbox";
    public $alt_messagePageUrl = "https://www.amazon.fr/gp/message?ie=UTF8&ref_=ya_mc_bsm&#!/inbox";
    public $businessPrimeUrl = "https://www.amazon.fr/businessprime";
    public $loginLinkPrim = "div[id=\"nav-flyout-ya-signin\"] a";
    public $loginLinkSec = "div[id=\"nav-signin-tooltip\"] a";
    public $loginLinkThr = "div#nav-tools a#nav-link-yourAccount";
    public $username_selector = 'input[autocomplete="username"]';
    public $password_selector = "#ap_password";
    public $submit_button_selector = "#signInSubmit, input[type='submit']";
    public $continue_button_selector = "#continue, #continue.a-button";
    public $logout_link = "a#nav-item-signout";
    public $remember_me = "input[name=\"rememberMe\"]";
    public $login_tryout = 0;
    public $msg_invoice_triggerd = 0;
    public $restrictPages = 3;
    public $all_processed_orders = array();
    public $amazon_download_overview;
    public $download_invoice_from_message;
    public $auto_request_invoice;
    public $procurment_report = 0;
    public $only_years;
    public $auto_tagging;
    public $marketplace_invoice_tags;
    public $order_overview_tags;
    public $amazon_invoice_tags;
    public $start_page = 0;
    public $dateLimitReached = 0;
    public $msgTimeLimitReached = 0;
    public $last_invoice_date = "";
    public $last_state = array();
    public $current_state = array();
    public $isBusinessUser = false;
    public $invalid_filename_keywords = array('agb', 'terms', 'datenschutz', 'privacy', 'rechnungsbeilage', 'informationsblatt', 'gesetzliche', 'retouren', 'widerruf', 'allgemeine gesch', 'mfb-buchung', 'informationen zu zahlung', 'nachvertragliche', 'retourenschein', 'allgemeine_gesch', 'rcklieferschein');

    public $invalid_filename_pattern = '';
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
            $this->procurment_report = isset($this->exts->config_array["procurment_report"]) ? (int)$this->exts->config_array["procurment_report"] : 0;
            $this->only_years = isset($this->exts->config_array["only_years"]) ? $this->exts->config_array["only_years"] : '';
            $this->auto_tagging = isset($this->exts->config_array["auto_tagging"]) ? $this->exts->config_array["auto_tagging"] : '';
            $this->marketplace_invoice_tags = isset($this->exts->config_array["marketplace_invoice_tags"]) ? $this->exts->config_array["marketplace_invoice_tags"] : '';
            $this->order_overview_tags = isset($this->exts->config_array["order_overview_tags"]) ? $this->exts->config_array["order_overview_tags"] : '';
            $this->amazon_invoice_tags = isset($this->exts->config_array["amazon_invoice_tags"]) ? $this->exts->config_array["amazon_invoice_tags"] : '';
            $this->start_page = isset($this->exts->config_array["start_page"]) ? $this->exts->config_array["start_page"] : '';
            $this->last_invoice_date = isset($this->exts->config_array["last_invoice_date"]) ? $this->exts->config_array["last_invoice_date"] : '';
            $this->start_date = (isset($this->exts->config_array["start_date"]) && !empty($this->exts->config_array["start_date"])) ? trim($this->exts->config_array["start_date"]) : "";

            if (!empty($this->start_date)) {
                $this->start_date = strtotime($this->start_date);
            }
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
        $this->check_solve_captcha_page();
        $this->exts->capture("Home-page-without-cookie");
        if ($this->exts->exists('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]')) {
            $this->exts->click_by_xdotool('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]');
            sleep(2);
        }

        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->orderPageUrl);
        sleep(5);
        $this->check_solve_captcha_page();

        if ($this->checkLogin()) {
            // 2024-Jan-15 Huy added this because a bug, 
            // with cookie, user maybe logged in, order page loaded successfully, BUT when click "invoice" dropdown, it displays "Session is expired"
            // So do this as double check for cokkie and login status
            $this->exts->openUrl('https://www.amazon.fr/gp/message');
            sleep(5);
            $this->check_solve_captcha_page();
            $this->exts->capture("login-cookie-double-check");
        }

        if (!$this->checkLogin()) {
            // $this->exts->openUrl('https://www.amazon.fr/gp/message');
            $this->exts->openUrl($this->orderPageUrl);
            sleep(5);

            if (stripos($this->exts->getUrl(), "/gp/css/homepage.html?") !== false) {
                $this->exts->click_by_xdotool('a[href*="/gp/your-account/order-history?"]');
                sleep(5);
            }

            $this->check_solve_captcha_page();
            $this->fillForm(0);
            // retry if captcha showed
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                $this->fillForm(0);
            }
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                $this->fillForm(0);
            }
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                $this->fillForm(0);
                sleep(5);
                if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                    $this->fillForm(0);
                    sleep(5);
                }
                if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                    $this->fillForm(0);
                    sleep(5);
                }
                if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                    $this->fillForm(0);
                    sleep(5);
                }
            }
            $this->check_solve_captcha_page();
            $this->checkFillExternalLogin();
            // Captcha and Two Factor Check
            $this->checkFillTwoFactor();
            sleep(5);

            // retry otp in code is invalid
            if (
                stripos($this->exts->extract('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', null, 'innerText'), "Le code que vous avez saisi n'est pas valide. Veuillez réessayer.") !== false ||
                stripos($this->exts->extract('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', null, 'innerText'), "The code you entered is invalid. Please try again.") !== false ||
                stripos($this->exts->extract('div#invalid-otp-code-message', null, 'innerText'), "Le code que vous avez saisi n'est pas valide. Veuillez vérifiez le code et réessayez.") !== false
            ) {
                if ($this->exts->exists('a#auth-get-new-otp-link')) {
                    $this->exts->moveToElementAndClick('a#auth-get-new-otp-link');
                    sleep(5);
                }

                if ($this->exts->exists('div#invalid-otp-code-message')) {
                    sleep(5);
                    $this->exts->waitTillPresent('a[class="a-link-normal"]#resend-approval-link', 25);
                    $this->exts->moveToElementAndClick('a[class="a-link-normal"]#resend-approval-link');
                    sleep(5);
                }
                $this->checkFillTwoFactor();
            }

            $this->check_solve_captcha_page();
            if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                sleep(2);
            }
            $this->check_solve_captcha_page();
        }

        if ($this->checkLogin()) {
            $this->exts->waitTillPresent('#nav-flyout-anchor a[href*="/order-history"]');
            if ($this->exts->exists('#nav-flyout-anchor a[href*="/order-history"]')) {
                $this->exts->click_element('#nav-flyout-anchor a[href*="/order-history"]');
            } else if ($this->exts->exists('a[href*="/order-history"]')) {
                $this->exts->click_element('a[href*="/order-history"]');
            } else {
                $this->exts->openUrl($this->orderPageUrl);
            }
            sleep(5);
            $this->exts->capture("LoginSuccess");

            $this->processAfterLogin(0);
            $this->exts->success();
        } else {
            if ($this->isIncorrectCredential() || $this->exts->exists('div#auth-email-invalid-claim-alert')) {
                $this->exts->loginFailure(1);
            } else if (
                stripos($this->exts->extract('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', null, 'innerText'), "Le code que vous avez saisi n'est pas valide. Veuillez réessayer.") !== false ||
                stripos($this->exts->extract('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', null, 'innerText'), "The code you entered is invalid. Please try again.") !== false ||
                stripos($this->exts->extract('div#invalid-otp-code-message', null, 'innerText'), "Le code que vous avez saisi n'est pas valide. Veuillez vérifiez le code et réessayez.") !== false
            ) {
                $this->exts->loginFailure(1);
            }
            $this->exts->loginFailure();
        }
    }
    private function fillForm($count)
    {
        $this->check_solve_captcha_page();
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->log("Begin fillForm URL - " . $this->exts->getUrl());

        if ($this->exts->querySelector("button.a-button-close.a-declarative") != null) {
            $this->exts->click_element("button.a-button-close.a-declarative");
        }

        $this->exts->capture("account-switcher");
        $account_switcher_elements = $this->exts->querySelectorAll("div.cvf-account-switcher-profile-details-after-account-removed");
        if (count($account_switcher_elements) > 0) {
            $this->exts->log("click account-switcher");
            $this->exts->click_element($account_switcher_elements[0]);
            sleep(4);
        }
        sleep(10);
        if ($this->exts->urlContains('google.com/')) {
            $this->loginGoogleIfRequired();
        } else if ($this->exts->exists('input#okta-signin-username') || $this->exts->exists('input#okta-signin-password') || $this->exts->urlContains('okta.com')) {
            $this->checkFillExternalLogin();
        } else if ($this->exts->urlContains('microsoft') || $this->exts->urlContains('live.')) {
            $this->loginMicrosoftIfRequired();
        } else {
            if ($this->exts->querySelector($this->password_selector) != null || $this->exts->querySelector($this->username_selector) != null) {
                $this->exts->capture("1-pre-login");
                $formType = $this->exts->querySelector($this->password_selector);
                if ($formType == null) {
                    $this->exts->log("Form with Username Only");
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(2);
                    if ($this->exts->exists('input#auth-captcha-guess')) {
                        $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
                    }
                    $this->exts->log("Username form button click");
                    $this->exts->click_by_xdotool($this->continue_button_selector);
                    sleep(5);
                    $this->exts->capture("1.1-pre-login");
                    sleep(10);
                    if ($this->exts->urlContains('google.com/')) {
                        $this->loginGoogleIfRequired();
                    } else if ($this->exts->exists('input#okta-signin-username') || $this->exts->exists('input#okta-signin-password') || $this->exts->urlContains('okta.com')) {
                        $this->checkFillExternalLogin();
                    } else if ($this->exts->urlContains('microsoft') || $this->exts->urlContains('live.')) {
                        $this->loginMicrosoftIfRequired();
                    } else {
                        if ($this->exts->exists($this->username_selector)) {
                            $this->exts->moveToElementAndType($this->username_selector, $this->username);
                            sleep(2);
                            if ($this->exts->exists('input#auth-captcha-guess')) {
                                $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
                            }
                            $this->exts->type_key_by_xdotool('Return');
                            sleep(5);
                        }
                        sleep(5);
                        if ($this->exts->exists('form input[id="continue"]')) {
                            $this->exts->click_element('form input[id="continue"]');
                            sleep(15);
                            $this->checkFillTwoFactor();
                        } else {
                            $this->exts->click_element('Not Found button');
                        }
                        $this->check_solve_captcha_page();
                        if ($this->exts->exists($this->password_selector)) {
                            $this->exts->log("Enter Password");
                            $this->exts->moveToElementAndType($this->password_selector, $this->password);
                            sleep(1);
                            if ($this->exts->exists('input[name="rememberMe"]:not(:checked)')) {
                                $this->exts->click_by_xdotool('input[name="rememberMe"]:not(:checked)');
                            }
                            if ($this->exts->exists('input#auth-captcha-guess')) {
                                $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
                            }
                            $this->exts->capture("1-filled-login");
                            $this->exts->click_by_xdotool($this->submit_button_selector);
                            sleep(5);
                            if ($this->exts->exists($this->submit_button_selector)) {
                                $this->exts->click_element($this->submit_button_selector);
                            }
                            $this->loginGoogleIfRequired();
                        }
                    }
                } else {
                    if ($this->exts->querySelector($this->username_selector) != null && $this->exts->querySelector("input#ap_email[type=\"hidden\"]") == null) {
                        $this->exts->log("Enter Username");
                        $this->exts->moveToElementAndType($this->username_selector, $this->username);
                        sleep(1);
                    }

                    if ($this->exts->querySelector($this->password_selector) != null) {
                        $this->exts->log("Enter Password");
                        $this->exts->moveToElementAndType($this->password_selector, $this->password);
                        sleep(1);
                    }
                    if ($this->exts->exists('input#auth-captcha-guess')) {
                        $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
                    }
                    if ($this->exts->exists('input[name="rememberMe"]:not(:checked)')) {
                        $this->exts->click_by_xdotool('input[name="rememberMe"]:not(:checked)');
                    }
                    $this->exts->capture("2-filled-login");
                    $this->exts->click_by_xdotool($this->submit_button_selector);
                    sleep(5);
                    if ($this->exts->exists($this->submit_button_selector)) {
                        $this->exts->click_element($this->submit_button_selector);
                    }
                }
                sleep(11);
            }
            $this->exts->log("END fillForm URL - " . $this->exts->getUrl());
        }
    }
    private function checkFillTwoFactor()
    {
        $this->exts->capture("2.0-two-factor-checking");
        if ($this->exts->exists('div.auth-SMS input[type="radio"], input[type="radio"][value="mobile"]')) {
            $this->exts->click_by_xdotool('div.auth-SMS input[type="radio"]:not(:checked), input[type="radio"][value="mobile"]:not(:checked)');
            sleep(2);
            $this->exts->click_by_xdotool('input#auth-send-code, input#continue');
            sleep(5);
        } else if ($this->exts->exists('div.auth-TOTP input[type="radio"], input[type="radio"][value="email"]')) {
            $this->exts->click_by_xdotool('div.auth-TOTP input[type="radio"]:not(:checked), input[type="radio"][value="email"]:not(:checked)');
            sleep(2);
            $this->exts->click_by_xdotool('input#auth-send-code, input#continue');
            sleep(5);
        } else if ($this->exts->allExists(['input[type="radio"]', 'input#auth-send-code']) || $this->exts->exists('input[name="OTPChallengeOptions"]')) {
            $this->exts->click_by_xdotool('input[type="radio"]');
            sleep(2);
            $this->exts->click_by_xdotool('input#auth-send-code, input#continue');
            sleep(5);
        }

        if ($this->exts->exists('#verification-code-form input[name="code"] , input[name="otpCode"]:not([type="hidden"]), input[name="code"], input#input-box-otp')) {
            $two_factor_selector = '#verification-code-form input[name="code"] , input[name="otpCode"]:not([type="hidden"]), input[name="code"], input#input-box-otp';
            $two_factor_message_selector = '#verification-code-form h1 , #auth-mfa-form h1 + p, #verification-code-form > .a-spacing-small > .a-spacing-none, #channelDetailsForOtp';
            $two_factor_submit_selector = '#cvf-submit-otp-button input[class="a-button-input"] , #auth-signin-button, #verification-code-form input[type="submit"]';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code)) {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code: " . $two_factor_code);
                if (!$this->exts->exists($two_factor_selector)) { // by some javascript reason, sometime selenium can not find the input
                    $this->exts->refresh();
                    sleep(5);
                    $this->exts->capture("2.1-two-factor-refreshed." . $this->exts->two_factor_attempts);
                }
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                if ($this->exts->exists('label[for="auth-mfa-remember-device"] input[name="rememberDevice"]:not(:checked)')) {
                    $this->exts->click_by_xdotool('label[for="auth-mfa-remember-device"]');
                }
                sleep(1);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                if ($this->exts->exists('#cvf-submit-otp-button input[type="submit"]')) {
                    $this->exts->click_by_xdotool('#cvf-submit-otp-button input[type="submit"]');
                } else {
                    $this->exts->click_by_xdotool($two_factor_submit_selector);
                }

                sleep(5);
                $this->exts->waitTillPresent('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', 7);
                $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
            } else {
                $this->exts->log("Not received two factor code");
            }

            // Huy added this 2022-12 Retry if incorrect code inputted
            if ($this->exts->exists($two_factor_selector)) {
                if (
                    stripos($this->exts->extract('#auth-error-message-box .a-alert-content', null, 'innerText'), 'Der eingegebene Code ist ung') !== false ||
                    stripos($this->exts->extract('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', null, 'innerText'), 'you entered is not valid') !== false ||
                    stripos($this->exts->extract('#invalid-otp-code-message', null, 'innerText'), 'Code ist ung') !== false
                ) {
                    $temp_text = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
                    if (!empty($temp_text)) {
                        $this->exts->two_factor_notif_msg_en = $temp_text;
                    }
                    $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                    $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
                    for ($t = 2; $t <= 3; $t++) {
                        $this->exts->log("Retry 2FA Message:\n" . $this->exts->two_factor_notif_msg_en);
                        $this->exts->notification_uid = "";
                        $this->exts->two_factor_attempts++;
                        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
                        if (!empty($two_factor_code)) {
                            $this->exts->log("Retry 2FA: Entering two_factor_code: " . $two_factor_code);
                            if (!$this->exts->exists($two_factor_selector)) { // by some javascript reason, sometime selenium can not find the input
                                $this->exts->refresh();
                                sleep(5);
                                $this->exts->capture("2.1-two-factor-refreshed." . $this->exts->two_factor_attempts);
                            }

                            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                            if ($this->exts->exists('label[for="auth-mfa-remember-device"] input[name="rememberDevice"]:not(:checked)')) {
                                $this->exts->click_by_xdotool('label[for="auth-mfa-remember-device"]');
                            }
                            sleep(1);
                            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                            if ($this->exts->exists('#cvf-submit-otp-button input[type="submit"]')) {
                                $this->exts->click_by_xdotool('#cvf-submit-otp-button input[type="submit"]');
                            } else {
                                $this->exts->click_by_xdotool($two_factor_submit_selector);
                            }
                            sleep(10);
                            $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
                            if (!$this->exts->exists($two_factor_selector)) {
                                break;
                            }
                        } else {
                            $this->exts->log("Not received Retry two factor code");
                        }
                    }
                }
            }
        } else if ($this->exts->exists('div.otp-input-box-container input[name*="otc"]')) {
            $two_factor_selector = 'div.otp-input-box-container input[name*="otc"]';
            $two_factor_message_selector = 'div#channelDetailsForOtp';
            $two_factor_submit_selector = 'form#verification-code-form input[type="submit"][aria-labelledby="cvf-submit-otp-button-announce"]';

            if ($this->exts->querySelector($two_factor_selector) != null) {
                $this->exts->log("Two factor page found.");
                $this->exts->capture("2.1-two-factor");

                if ($this->exts->querySelector($two_factor_message_selector) != null) {
                    $this->exts->two_factor_notif_msg_en = "";
                    for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
                    }
                    $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                    $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                    $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
                }

                $two_factor_code = trim($this->exts->fetchTwoFactorCode());
                if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                    $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);

                    $resultCodes = str_split($two_factor_code);
                    $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
                    foreach ($code_inputs as $key => $code_input) {
                        if (array_key_exists($key, $resultCodes)) {
                            $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                            $this->exts->moveToElementAndType('div.otp-input-box-container input[name*="otc"]:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                            // $code_input->sendKeys($resultCodes[$key]);
                        } else {
                            $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                        }
                    }

                    $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                    sleep(3);
                    $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                    $this->exts->click_by_xdotool($two_factor_submit_selector);
                    sleep(15);

                    if ($this->exts->exists($two_factor_selector)) {
                        $temp_text = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
                        if (!empty($temp_text)) {
                            $this->exts->two_factor_notif_msg_en = $temp_text;
                        }
                        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
                        for ($t = 2; $t <= 3; $t++) {
                            $this->exts->log("Retry 2FA Message:\n" . $this->exts->two_factor_notif_msg_en);
                            $this->exts->notification_uid = "";
                            $this->exts->two_factor_attempts++;
                            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
                            if (!empty($two_factor_code)) {
                                $this->exts->log("Retry 2FA: Entering two_factor_code: " . $two_factor_code);
                                $resultCodes = str_split($two_factor_code);
                                $code_inputs = $this->exts->getElements($two_factor_selector);
                                $resultCodes = str_split($two_factor_code);
                                $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
                                foreach ($code_inputs as $key => $code_input) {
                                    if (array_key_exists($key, $resultCodes)) {
                                        $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                                        $this->exts->moveToElementAndType('div.otp-input-box-container input[name*="otc"]:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                                    } else {
                                        $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                                    }
                                }
                                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                                $this->exts->click_by_xdotool($two_factor_submit_selector);
                                sleep(10);
                                $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
                                if (!$this->exts->exists($two_factor_selector)) {
                                    break;
                                }
                            } else {
                                $this->exts->log("Not received Retry two factor code");
                            }
                        }
                    }
                } else {
                    $this->exts->log("Not received two factor code");
                }
            }
        } else if ($this->exts->exists('[name="transactionApprovalStatus"], form[action*="/approval/poll"]')) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            $message_selector = '.transaction-approval-word-break, #channelDetails, #channelDetailsWithImprovedLayout';
            $this->exts->two_factor_notif_msg_en = join(' ', $this->exts->getElementsAttribute($message_selector, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirmation";
            $this->exts->log($this->exts->two_factor_notif_msg_en);

            $this->exts->notification_uid = "";
            $this->exts->two_factor_attempts++;
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            // Huy added this 2023-02
            if (!empty($two_factor_code) && stripos($two_factor_code, 'OK') !== false) {
                sleep(7);
            } else {
                sleep(5 * 60);
                $this->exts->update_process_lock();
                if ($this->exts->exists('[name="transactionApprovalStatus"], form[action*="/approval/poll"]')) {
                    $this->exts->two_factor_expired();
                }
            }
        }
    }
    private function isIncorrectCredential()
    {
        $incorrect_credential_keys = [
            'Es konnte kein Konto mit dieser',
            'dass die eingegebene Nummer korrekt ist oder melde dich',
            't find an account with that',
            'Falsches Passwort',
            'password is incorrect',
            'password was incorrect',
            'Passwort war nicht korrekt',
            'Impossible de trouver un compte correspondant',
            'Votre mot de passe est incorrect',
            'Je wachtwoord is onjuist',
            'La tua password non',
            'a no es correcta'
        ];
        $error_message = $this->exts->extract('#auth-error-message-box');
        foreach ($incorrect_credential_keys as $incorrect_credential_key) {
            if (strpos(strtolower($error_message), strtolower($incorrect_credential_key)) !== false) {
                return true;
            }
        }
        return false;
    }
    private function processCaptcha($captcha_image_selector, $captcha_input_selector)
    {
        $this->exts->log("--IMAGE CAPTCHA--");
        if ($this->exts->exists($captcha_image_selector)) {
            $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
            $source_image = imagecreatefrompng($image_path);
            imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', 90);

            if (!empty($this->exts->config_array['captcha_shell_script'])) {
                $cmd = $this->exts->config_array['captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid;
                $this->exts->log('Executing command : ' . $cmd);
                exec($cmd, $output, $return_var);
                $this->exts->log('Command Result : ' . print_r($output, true));
                $captcha_code = '';
                if (!empty($output)) {
                    $output_text = $output[0];
                    if (stripos($output_text, 'OK|') !== false) {
                        $captcha_code = trim(end(explode("OK|", $output_text)));
                    } else if (stripos($output_text, ':') !== false) {
                        $captcha_code = trim(end(explode(":", $output_text)));
                    } else {
                        $captcha_code = trim(end(explode(":", $output_text)));
                    }
                }
                if ($captcha_code == '') {
                    $this->exts->log("Can not get result from API");
                } else {
                    $this->exts->moveToElementAndType($captcha_input_selector, $captcha_code);
                    return true;
                }
            }
        } else {
            $this->exts->log("Image does not found!");
        }

        return false;
    }
    private function check_solve_captcha_page()
    {
        $captcha_iframe_selector = '#aa-challenge-whole-page-iframe';
        $image_selector = 'img[src*="captcha"]';
        $captcha_input_selector = 'input#captchacharacters, input#aa_captcha_input, input[name="cvf_captcha_input"]';
        $captcha_submit_button = 'button[type="submit"], [name="submit_button"], [type="submit"][value="verifyCaptcha"]';
        if ($this->exts->exists($captcha_iframe_selector)) {
            $this->switchToFrame($captcha_iframe_selector);
        }
        if ($this->exts->allExists([$image_selector, $captcha_input_selector, $captcha_submit_button])) {
            $this->processCaptcha($image_selector, $captcha_input_selector);
            $this->exts->click_by_xdotool($captcha_submit_button);
            sleep(5);
            $this->exts->switchToDefault();
            if ($this->exts->exists($captcha_iframe_selector)) {
                $this->switchToFrame($captcha_iframe_selector);
            }
            if ($this->exts->allExists([$image_selector, $captcha_input_selector, $captcha_submit_button])) {
                $this->processCaptcha($image_selector, $captcha_input_selector);
                $this->exts->click_by_xdotool($captcha_submit_button);
                sleep(5);
            }
            $this->exts->switchToDefault();
            if ($this->exts->exists($captcha_iframe_selector)) {
                $this->switchToFrame($captcha_iframe_selector);
            }
            if ($this->exts->allExists([$image_selector, $captcha_input_selector, $captcha_submit_button])) {
                $this->processCaptcha($image_selector, $captcha_input_selector);
                $this->exts->click_by_xdotool($captcha_submit_button);
                sleep(5);
            }
        }
        $this->exts->switchToDefault();
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
    private function captcha_required()
    {
        // Supporting de, fr, en, es, it, nl language
        $captcha_required_keys = [
            'wie sie auf dem Bild erscheinen',
            'die in der Abbildung unten gezeigt werden',
            'Geben Sie die Zeichen so ein, wie sie auf dem Bild erscheinen',
            'the characters as they are shown in the image',
            'Enter the characters as they are given',
            'luego introduzca los caracteres que aparecen en la imagen',
            'Introduce los caracteres tal y como aparecen en la imagen',
            "dans l'image ci-dessous",
            "apparaissent sur l'image",
            'quindi digita i caratteri cos',
            'Inserire i caratteri cos',
            'en voer de tekens in zoals deze worden weergegeven in de afbeelding hieronder om je account',
            'Voer de tekens in die je uit veiligheidsoverwegingen moet'
        ];
        $error_message = $this->exts->extract('#auth-error-message-box, #auth-warning-message-box');
        foreach ($captcha_required_keys as $captcha_required_key) {
            if (strpos(strtolower($error_message), strtolower($captcha_required_key)) !== false) {
                return true;
            }
        }
        return false;
    }
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->querySelector($this->logout_link) != null) {
                $isLoggedIn = true;
            } else {
                if ($this->exts->querySelector("a[id='nav-link-yourAccount'],div#nav-tools a#nav-link-accountList") != null) {
                    $href = $this->exts->querySelector("a[id='nav-link-yourAccount'],div#nav-tools a#nav-link-accountList")->getAttribute("href");
                    if (trim($href) != "" && stripos($href, "/gp/css/homepage.html") !== false) {
                        $isLoggedIn = true;
                    }
                } else if ($this->exts->querySelector("a#nav-item-signout-sa") != null) {
                    $isLoggedIn = true;
                } else if ($this->exts->querySelector("div#nav-tools a#nav-link-yourAccount") != null) {
                    $href = $this->exts->querySelector("div#nav-tools a#nav-link-yourAccount")->getAttribute("href");
                    if (trim($href) != "" && stripos($href, "/gp/css/homepage.html") !== false) {
                        $isLoggedIn = true;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception);
            if ($this->exts->querySelector("div#nav-tools a#nav-link-accountList") != null) {
                $href = $this->exts->querySelector("div#nav-tools a#nav-link-accountList")->getAttribute("href");
                if (trim($href) != "" && stripos($href, "/gp/css/homepage.html") !== false) {
                    $isLoggedIn = true;
                }
            } else if ($this->exts->querySelector("a#nav-item-signout-sa") != null) {
                $isLoggedIn = true;
            } else if ($this->exts->querySelector("div#nav-tools a#nav-link-yourAccount") != null) {
                $href = $this->exts->querySelector("div#nav-tools a#nav-link-yourAccount")->getAttribute("href");
                if (trim($href) != "" && stripos($href, "/gp/css/homepage.html") !== false) {
                    $isLoggedIn = true;
                }
            }
        }

        return $isLoggedIn;
    }
    private function accept_cookies()
    {
        if ($this->exts->exists('#sp-cc-accept')) {
            $this->exts->click_by_xdotool('#sp-cc-accept');
            sleep(1);
        }
    }


    //*********** Microsoft Login
    public $microsoft_username_selector = 'input[name="loginfmt"]';
    public $microsoft_password_selector = 'input[name="passwd"]';
    public $microsoft_remember_me_selector = 'input[name="KMSI"] + span';
    public $microsoft_submit_login_selector = 'input[type="submit"]#idSIButton9';

    public $microsoft_account_type = 0;
    public $microsoft_phone_number = '';
    public $microsoft_recovery_email = '';
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function loginMicrosoftIfRequired($count = 0)
    {
        $this->microsoft_phone_number = isset($this->exts->config_array["phone_number"]) ? $this->exts->config_array["phone_number"] : '';
        $this->microsoft_recovery_email = isset($this->exts->config_array["recovery_email"]) ? $this->exts->config_array["recovery_email"] : '';
        $this->microsoft_account_type = isset($this->exts->config_array["account_type"]) ? (int)@$this->exts->config_array["account_type"] : 0;

        if ($this->exts->urlContains('microsoft') || $this->exts->urlContains('live.')) {
            $this->checkFillMicrosoftLogin();
            sleep(10);
            $this->checkMicrosoftTwoFactorMethod();

            if ($this->exts->exists('input#newPassword')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->querySelector('#passwordError a[href*="ResetPassword"], #passwordError a[href*="passwordreset"]') != null) {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required microsoft login.');
            $this->exts->capture("3-no-microsoft-required");
        }
    }

    private function checkFillMicrosoftLogin()
    {
        $this->exts->log(__FUNCTION__);
        // When open login page, sometime it show previous logged user, select login with other user.
        $this->exts->waitTillPresent('[role="listbox"] .row #otherTile[role="option"], div#otherTile', 20);
        if ($this->exts->exists('[role="listbox"] .row #otherTile[role="option"], div#otherTile')) {
            $this->exts->click_by_xdotool('[role="listbox"] .row #otherTile[role="option"], div#otherTile');
            sleep(10);
        }

        $this->exts->capture("2-microsoft-login-page");
        if ($this->exts->querySelector($this->microsoft_username_selector) != null) {
            sleep(3);
            $this->exts->log("Enter microsoft Username");
            $this->exts->moveToElementAndType($this->microsoft_username_selector, $this->username);
            sleep(1);
            $this->exts->click_by_xdotool($this->microsoft_submit_login_selector);
            sleep(10);
        }

        //Some user need to approve login after entering username on the app
        if ($this->exts->exists('div#idDiv_RemoteNGC_PollingDescription')) {
            $this->exts->two_factor_timeout = 5;
            $polling_message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
            $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->extract($polling_message_selector)));
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                }
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                $this->exts->two_factor_timeout = 15;
            } else {
                if ($this->exts->exists('a#idA_PWD_SwitchToPassword')) {
                    $this->exts->click_by_xdotool('a#idA_PWD_SwitchToPassword');
                    sleep(5);
                } else {
                    $this->exts->log("Not received two factor code");
                }
            }
        }
        if ($this->exts->exists('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]')) {
            // if site show: Already login with .. account, click logout and login with other account
            $this->exts->click_by_xdotool('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]');
            sleep(10);
        }
        if ($this->exts->exists('a#mso_account_tile_link, #aadTile, #msaTile')) {
            // if site show: This Email is being used with multiple Microsoft accounts, Select depending on account_type variable
            //if account type is 1 then only personal account will be selected otherwise business account.
            if ($this->microsoft_account_type == 1) {
                $this->exts->click_by_xdotool('#msaTile');
            } else {
                $this->exts->click_by_xdotool('a#mso_account_tile_link, #aadTile');
            }
            sleep(10);
        }
        if ($this->exts->exists('form #idA_PWD_SwitchToPassword')) {
            $this->exts->click_by_xdotool('form #idA_PWD_SwitchToPassword');
            sleep(5);
        } else if ($this->exts->exists('#idA_PWD_SwitchToCredPicker')) {
            $this->exts->moveToElementAndClick('#idA_PWD_SwitchToCredPicker');
            sleep(5);
            $this->exts->moveToElementAndClick('[role="listitem"] img[src*="password"]');
            sleep(3);
        }


        if ($this->exts->querySelector($this->microsoft_password_selector) != null) {
            $this->exts->log("Enter microsoft Password");
            $this->exts->moveToElementAndType($this->microsoft_password_selector, $this->password);
            sleep(1);
            $this->exts->click_by_xdotool($this->microsoft_remember_me_selector);
            sleep(2);
            $this->exts->capture("2-microsoft-password-page-filled");
            $this->exts->click_by_xdotool($this->microsoft_submit_login_selector);
            sleep(10);
            $this->exts->capture("2-microsoft-after-submit-password");
        } else {
            $this->exts->log(__FUNCTION__ . '::microsoft Password page not found');
        }

        $this->checkConfirmMicrosoftButton();
    }

    private function checkFillExternalLogin()
    {
        $this->exts->log(__FUNCTION__);
        if ($this->exts->urlContains('idaptive.app/login')) {
            $this->exts->capture("2-login-external-page");
            if ($this->exts->querySelector('#usernameForm:not(.hidden) input[name="username"]') != null) {
                sleep(3);
                $this->exts->log("Enter idaptive Username");
                $this->exts->moveToElementAndType('#usernameForm:not(.hidden) input[name="username"]', $this->username);
                sleep(1);
                $this->exts->click_by_xdotool('#usernameForm:not(.hidden) [type="submit"]');
                sleep(5);
            }
            if ($this->exts->querySelector('.login-form:not(.hidden) input[name="answer"][type="password"]') != null) {
                $this->exts->log("Enter idaptive Password");
                $this->exts->moveToElementAndType('.login-form:not(.hidden) input[name="answer"][type="password"]', $this->password);
                sleep(1);
                $this->exts->click_by_xdotool('.login-form:not(.hidden) [name="rememberMe"]');
                $this->exts->capture("2-login-external-filled");
                $this->exts->click_by_xdotool('.login-form:not(.hidden) [type="submit"]');
                sleep(5);
            }

            if ($this->exts->extract('#errorForm:not(.hidden) .error-message, #usernameForm:not(.hidden ) .error-message:not(.hidden )') != '') {
                $this->exts->loginFailure(1);
            }
            sleep(10);

            if ($this->exts->exists('#answerInputForm:not(.hidden) [type="submit"]')) {
                $this->exts->capture("2-login-idaptive-2fa");
                $input_selector = '#answerInputForm:not(.hidden) [type="submit"]';
                $message_selector = '#answerInputForm:not(.hidden) label';
                $remember_selector = '';
                $submit_selector = '#answerInputForm:not(.hidden) [type="submit"]';
                $this->exts->two_factor_attempts = 0;
                $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            }
        } else if ($this->exts->urlContains('okta.com')) {
            $this->exts->capture("2-login-external-page");
            if ($this->exts->querySelector('input[name="username"],  input[name="identifier"]') != null) {
                sleep(3);
                $this->exts->log("Enter okta Username");
                $this->exts->moveToElementAndType('input[name="username"],  input[name="identifier"]', $this->username);
                sleep(1);
                $this->exts->click_by_xdotool('input[type="submit"]');
                sleep(7);
                $is_captcha = $this->solve_captcha_by_clicking(0);
                if ($is_captcha) {
                    for ($i = 1; $i < 15; $i++) {
                        if ($is_captcha == false || stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                            break;
                        }
                        $is_captcha = $this->solve_captcha_by_clicking($i);
                    }
                }
                if ($this->exts->exists('input[type="submit"]')) {
                    $this->exts->click_by_xdotool('input[type="submit"]');
                }
                sleep(7);
            }
            if ($this->exts->querySelector('input[name="password"], input[type="password"]') != null) {
                $this->exts->log("Enter okta Password");
                $this->exts->moveToElementAndType('input[name="password"], input[type="password"]', $this->password);
                sleep(1);
                $this->exts->capture("2-login-external-filled");
                $this->exts->click_by_xdotool('form input[type="submit"]');
                sleep(5);
            }

            if ($this->exts->extract('[data-se="factor-password"] .infobox-error p') != '' || $this->exts->exists('div.infobox-error')) {
                $this->exts->loginFailure(1);
            }
            sleep(10);
            if ($this->exts->exists('[data-se="factor-totp"] input[name="answer"]')) {
                $this->exts->capture("2-login-okta-2fa");
                $input_selector = '[data-se="factor-totp"] input[name="answer"]';
                $message_selector = '[data-se="factor-totp"] p.okta-form-subtitle';
                $remember_selector = '[data-se="factor-totp"] [data-se-for-name="rememberDevice"]';
                $submit_selector = '[data-se="factor-totp"] [type="submit"]';
                $this->exts->two_factor_attempts = 0;
                $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            }
        } else if ($this->exts->urlContains('onelogin.com')) {
            $this->exts->capture("2-login-external-page");
            if ($this->exts->querySelector('input[name="username"]') != null) {
                sleep(3);
                $this->exts->log("Enter onelogin Username");
                $this->exts->moveToElementAndType('input[name="username"]', $this->username);
                sleep(1);
                $this->exts->click_by_xdotool('form button[type="submit"]');
                sleep(7);
            }
            if ($this->exts->querySelector('input[name="password"]') != null) {
                $this->exts->log("Enter onelogin Password");
                $this->exts->moveToElementAndType('input[name="password"]', $this->password);
                sleep(1);
                $this->exts->capture("2-login-external-filled");
                $this->exts->click_by_xdotool('form button[type="submit"]');
                // sleep(5);
            }

            $this->exts->waitTillPresent('[type="error"]');
            sleep(1);
            if ($this->exts->extract('[type="error"]') != '') {
                $this->exts->loginFailure(1);
            }
            sleep(5);
        } else if ($this->exts->urlContains('remerge.io')) {
            $this->exts->capture("2-login-external-page");
            if ($this->exts->querySelector('span[class*="okta-form-input-field"] input[name="identifier"]') != null) {
                sleep(3);
                $this->exts->log("Enter onelogin Username");
                $this->exts->moveToElementAndType('span[class*="okta-form-input-field"] input[name="identifier"]', $this->username);
                sleep(1);
                $this->exts->click_by_xdotool('main#okta-sign-in form input[type="submit"]');
                sleep(7);
            }

            if ($this->exts->querySelector('span[class*="okta-form-input-field"] input[name="credentials.passcode"]') != null) {
                $this->exts->log("Enter onelogin Password");
                $this->exts->moveToElementAndType('span[class*="okta-form-input-field"] input[name="credentials.passcode"]', $this->password);
                sleep(1);
                $this->exts->capture("2-login-external-filled");
                $this->exts->click_by_xdotool('main#okta-sign-in form input[type="submit"]');
                // sleep(5);
            }

            $this->exts->waitTillPresent('.okta-form-infobox-error.infobox.infobox-error p');
            sleep(1);
            if ($this->exts->extract('.okta-form-infobox-error.infobox.infobox-error p') != '') {
                $this->exts->loginFailure(1);
            }
            sleep(5);
        }
    }

    private function solve_captcha_by_clicking($count = 1)
    {
        $this->exts->log("Checking captcha");
        $language_code = '';
        $this->exts->waitTillPresent('div[style*="visibility: visible;"] iframe[title="recaptcha challenge expires in two minutes"]', 20);
        $recaptcha_challenger_wraper_selector = 'div[style*="visibility: visible;"] iframe[title="recaptcha challenge expires in two minutes"]';
        if ($this->exts->exists($recaptcha_challenger_wraper_selector)) {
            // Check if challenge images hasn't showed yet, Click checkbox to show images challenge

            $this->exts->capture("tesla-captcha");

            $captcha_instruction = '';

            //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
            sleep(5);
            $captcha_wraper_selector = 'div[style*="visibility: visible;"] iframe[title="recaptcha challenge expires in two minutes"]';

            if ($this->exts->exists($captcha_wraper_selector)) {
                $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);


                // if($coordinates == '' || count($coordinates) < 2){
                //  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
                // }
                if ($coordinates != '') {
                    // $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                    foreach ($coordinates as $coordinate) {
                        $this->click_hcaptcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                    }

                    $this->exts->capture("tesla-captcha-selected " . $count);
                    $this->exts->makeFrameExecutable('iframe[title="recaptcha challenge expires in two minutes"]')->click_element('button[id="recaptcha-verify-button"]');
                    sleep(10);
                    return true;
                }
            }

            return false;
        }
    }
    private function getCoordinates(
        $captcha_image_selector,
        $instruction = '',
        $lang_code = '',
        $json_result = false,
        $image_dpi = 75
    ) {
        $this->exts->log("--GET Coordinates By 2CAPTCHA--");
        $response = '';
        $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
        $source_image = imagecreatefrompng($image_path);
        imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', $image_dpi);

        $cmd = $this->exts->config_array['click_captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --CAPTCHA_INSTRUCTION::" . urlencode($instruction) . " --LANG_CODE::" . urlencode($lang_code) . " --JSON_RESULT::" . urlencode($json_result);
        $this->exts->log('Executing command : ' . $cmd);
        exec($cmd, $output, $return_var);
        $this->exts->log('Command Result : ' . print_r($output, true));

        if (!empty($output)) {
            $output = trim($output[0]);
            if ($json_result) {
                if (strpos($output, '"status":1') !== false) {
                    $response = json_decode($output, true);
                    $response = $response['request'];
                }
            } else {
                if (strpos($output, 'coordinates:') !== false) {
                    $array = explode("coordinates:", $output);
                    $response = trim(end($array));
                    $coordinates = [];
                    $pairs = explode(';', $response);
                    foreach ($pairs as $pair) {
                        preg_match('/x=(\d+),y=(\d+)/', $pair, $matches);
                        if (!empty($matches)) {
                            $coordinates[] = ['x' => (int)$matches[1], 'y' => (int)$matches[2]];
                        }
                    }
                    $this->exts->log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
                    $this->exts->log(print_r($coordinates, true));
                    return $coordinates;
                }
            }
        }

        if ($response == '') {
            $this->exts->log("Can not get result from API");
        }
        return $response;
    }


    private function fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector)
    {
        $two_factor_selector = $input_selector;
        $two_factor_message_selector =  $message_selector;
        $two_factor_remember_selector_selector = $remember_selector;
        $two_factor_submit_selector = $submit_selector;
        $this->exts->waitTillPresent($two_factor_selector, 10);
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
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
                $this->exts->click_by_xdotool($two_factor_selector);
                sleep(2);
                $this->exts->type_text_by_xdotool($two_factor_code);


                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($two_factor_remember_selector_selector)) {
                    $this->exts->click_by_xdotool($two_factor_remember_selector_selector);
                    sleep(1);
                }

                $this->exts->click_by_xdotool($two_factor_submit_selector);
                sleep(15);
                if ($this->exts->querySelector($two_factor_selector) == null) {
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

    private function checkConfirmMicrosoftButton()
    {
        // After submit password, It have many button can be showed, check and click it
        if ($this->exts->exists('form[action*="/kmsi"] input[name="DontShowAgain"], input#idSIButton9[aria-describedby="KmsiDescription"]')) {
            // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
            $this->exts->click_by_xdotool('form input[name="DontShowAgain"] + span');
            sleep(3);
            $this->exts->click_by_xdotool('form[action*="/kmsi"] input#idSIButton9, input#idSIButton9[aria-describedby="KmsiDescription"]');
            sleep(10);
        }
        if ($this->exts->exists('input#btnAskLater')) {
            $this->exts->click_by_xdotool('input#btnAskLater');
            sleep(10);
        }
        if ($this->exts->exists('a[data-bind*=SkipMfaRegistration]')) {
            $this->exts->click_by_xdotool('a[data-bind*=SkipMfaRegistration]');
            sleep(10);
        }
        if ($this->exts->exists('input#idSIButton9[aria-describedby="KmsiDescription"]')) {
            $this->exts->click_by_xdotool('input#idSIButton9[aria-describedby="KmsiDescription"]');
            sleep(10);
        }
        if ($this->exts->exists('input#idSIButton9[aria-describedby*="landingDescription"]')) {
            $this->exts->click_by_xdotool('input#idSIButton9[aria-describedby*="landingDescription"]');
            sleep(3);
        }
        if ($this->exts->querySelector("#verifySetup a#verifySetupCancel") != null) {
            $this->exts->click_by_xdotool("#verifySetup a#verifySetupCancel");
            sleep(10);
        }
        if ($this->exts->querySelector('#authenticatorIntro a#iCancel') != null) {
            $this->exts->click_by_xdotool('#authenticatorIntro a#iCancel');
            sleep(10);
        }
        if ($this->exts->querySelector("input#iLooksGood") != null) {
            $this->exts->click_by_xdotool("input#iLooksGood");
            sleep(10);
        }
        if ($this->exts->exists("input#StartAction") && !$this->exts->urlContains('/Abuse?')) {
            $this->exts->click_by_xdotool("input#StartAction");
            sleep(10);
        }
        if ($this->exts->querySelector(".recoveryCancelPageContainer input#iLandingViewAction") != null) {
            $this->exts->click_by_xdotool(".recoveryCancelPageContainer input#iLandingViewAction");
            sleep(10);
        }
        if ($this->exts->querySelector("input#idSubmit_ProofUp_Redirect") != null) {
            $this->exts->click_by_xdotool("input#idSubmit_ProofUp_Redirect");
            sleep(10);
        }
        if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__11')) {
            // Great job! Your security information has been successfully set up. Click "Done" to continue login.
            $this->exts->click_by_xdotool(' #id__11');
            sleep(10);
        }
        if ($this->exts->querySelector('div input#iNext') != null) {
            $this->exts->click_by_xdotool('div input#iNext');
            sleep(10);
        }
        if ($this->exts->querySelector('input[value="Continue"]') != null) {
            $this->exts->click_by_xdotool('input[value="Continue"]');
            sleep(10);
        }
        if ($this->exts->querySelector('form[action="/kmsi"] input#idSIButton9') != null) {
            $this->exts->click_by_xdotool('form[action="/kmsi"] input#idSIButton9');
            sleep(10);
        }
        if ($this->exts->querySelector('a#CancelLinkButton') != null) {
            $this->exts->click_by_xdotool('a#CancelLinkButton');
            sleep(10);
        }
        if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__7')) {
            // Confirm your info.
            $this->exts->click_by_xdotool(' #id__7');
            sleep(10);
        }
        if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__11')) {
            // Great job! Your security information has been successfully set up. Click "Done" to continue login.
            $this->exts->click_by_xdotool(' #id__11');
            sleep(10);
        }
        if ($this->exts->exists('form[action*="/kmsi"] input[name="DontShowAgain"]')) {
            // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
            $this->exts->click_by_xdotool('form[action*="/kmsi"] input[name="DontShowAgain"] + span');
            sleep(3);
            $this->exts->click_by_xdotool('form[action*="/kmsi"] input#idSIButton9');
            sleep(10);
        }
    }

    private function checkMicrosoftTwoFactorMethod()
    {
        // Currently we met 4 two factor methods
        // - Email
        // - Text Message
        // - Approve request in Microsoft Authenticator app
        // - Use verification code from mobile app
        $this->exts->log(__FUNCTION__);
        sleep(5);
        $this->exts->capture("2.0-microsoft-two-factor-checking");
        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->exts->exists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]')) {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]');
            sleep(10);
        } else if ($this->exts->exists('#iProofList input[name="proof"]')) {
            $this->exts->click_by_xdotool('#iProofList input[name="proof"]');
            sleep(10);
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"]')) {
            // Updated 11-2020
            if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]')) { // phone SMS
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]');
            } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]')) { // phone SMS
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]');
            } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]')) { // Email 
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]');
            } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]')) {
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]');
            } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]')) {
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]');
            } else {
                $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"]');
            }
            sleep(5);
        }

        // STEP 2: (Optional)
        if ($this->exts->exists('#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc')) {
            // If method is click some number on Microsoft Authenticator app, send 2FA to ask user to do click it
            $message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
            $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->extract($message_selector)));
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

            $this->exts->two_factor_attempts = 2;
            $this->fillMicrosoftTwoFactor('', '', '', '');
        } else if ($this->exts->exists('[data-bind*="Type.TOTPAuthenticatorV2"]')) {
            // If method is Microsoft Authenticator app: send 2FA to ask user approve on Microsoft app.
            // Then wait. If not success, click to select two factor by code from mobile app
            $input_selector = '';
            $message_selector = 'div#idDiv_SAOTCAS_Description';
            $remember_selector = 'label#idLbl_SAOTCAS_TD_Cb';
            $submit_selector = '';
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($message_selector, null, 'innerText');
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->exts->two_factor_attempts = 2;
            $this->exts->two_factor_timeout = 5;
            $this->fillMicrosoftTwoFactor('', '', $remember_selector, $submit_selector);
            // sleep(30);

            if ($this->exts->exists('a#idA_SAASTO_TOTP')) {
                $this->exts->click_by_xdotool('a#idA_SAASTO_TOTP');
                sleep(5);
            }
        } else if ($this->exts->exists('input[value="TwoWayVoiceOffice"]') && $this->exts->exists('div#idDiv_SAOTCC_Description')) {
            // If method is Microsoft Authenticator app: send 2FA to ask user approve on Microsoft app.
            // Then wait. If not success, click to select two factor by code from mobile app
            $input_selector = '';
            $message_selector = 'div#idDiv_SAOTCC_Description';
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($message_selector, null, 'innerText');
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->exts->two_factor_attempts = 2;
            $this->exts->two_factor_timeout = 5;
            $this->fillMicrosoftTwoFactor('', '', '', '');
        } else if ($this->exts->exists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"])')) {
            // If method is email code or phone code, This site may be ask for confirm phone/email first, So send 2FA to ask user phone/email
            $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"])';
            $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
            $remember_selector = '';
            $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"], input[type="submit"]';
            $this->exts->two_factor_attempts = 1;
            if ($this->microsoft_recovery_email != '' && filter_var($this->recovery_email, FILTER_VALIDATE_EMAIL) !== false) {
                $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
                sleep(1);
                $this->exts->click_by_xdotool($submit_selector);
                sleep(10);
            } else {
                $this->exts->two_factor_attempts = 1;
                $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            }
        } else if ($this->exts->exists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])')) {
            // If method is phone code, This site may be ask for confirm phone first, So send 2FA to ask user phone
            $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])';
            $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
            $remember_selector = '';
            $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"], input[type="submit"]';
            $this->exts->two_factor_attempts = 1;
            if ($this->phone_number != '' && is_numeric(trim(substr($this->phone_number,  -1, 4)))) {
                $last4digit = substr($this->phone_number, -1, 4);
                $this->exts->moveToElementAndType($input_selector, $last4digit);
                sleep(3);
                $this->exts->click_by_xdotool($submit_selector);
                sleep(10);
            } else {
                $this->exts->two_factor_attempts = 1;
                $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            }
        }

        // STEP 3: input code
        if ($this->exts->exists('input[name="otc"], input[name="iOttText"]')) {
            $input_selector = 'input[name="otc"], input[name="iOttText"]';
            $message_selector = 'div#idDiv_SAOTCC_Description, .OTTLabel, #idDiv_SAOTCC_Description, span#otcDesc';
            $remember_selector = 'label#idLbl_SAOTCC_TD_Cb';
            $submit_selector = 'input#idSubmit_SAOTCC_Continue, input#iVerifyCodeAction, input[type="submit"]';
            $this->exts->two_factor_attempts = 0;
            $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }
    }

    private function fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("microsoft Two factor page found.");
        $this->exts->capture("2.1-microsoft-two-factor-page");
        $this->exts->log($message_selector);
        if ($this->exts->querySelector($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->extract($message_selector));
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->querySelector($input_selector) != null) {
                $this->exts->log("microsoftfillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(2);
                if ($this->exts->exists($remember_selector)) {
                    $this->exts->click_by_xdotool($remember_selector);
                }
                $this->exts->capture("2.2-microsoft-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($submit_selector)) {
                    $this->exts->log("microsoftfillTwoFactor: Clicking submit button.");
                    $this->exts->click_by_xdotool($submit_selector);
                }
                sleep(15);

                if ($this->exts->querySelector($input_selector) == null) {
                    $this->exts->log("microsoftTwo factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
                } else {
                    $this->exts->log("microsoft Two factor can not solved");
                }
            } else {
                $this->exts->log("Not found microsoft two factor input");
            }
        } else {
            $this->exts->log("Not received microsoft two factor code");
        }
    }
    //*********** END Microsoft Login

    // -------------------- GOOGLE login
    public $google_username_selector = 'input[name="identifier"]';
    public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
    public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
    public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
    public $google_solved_rejected_browser = false;
    private function loginGoogleIfRequired()
    {
        if ($this->exts->urlContains('google.')) {
            $this->checkFillGoogleLogin();
            sleep(10);
            $this->check_solve_rejected_browser();

            if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null) {
                $this->exts->loginFailure(1);
            }

            if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
                // An application is requesting permission to access your Google Account.
                // Click allow
                $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
                sleep(10);
            }
            // Click next if confirm form showed
            $this->exts->click_by_xdotool('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
            $this->checkGoogleTwoFactorMethod();
            sleep(10);
            if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
                $this->exts->click_by_xdotool('#smsauth-interstitial-remindbutton');
                sleep(10);
            }
            if ($this->exts->exists('#tos_form input#accept')) {
                $this->exts->click_by_xdotool('#tos_form input#accept');
                sleep(10);
            }
            if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
                $this->exts->click_by_xdotool('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
                // SKIP setup 2FA
                $this->exts->click_by_xdotool('.action-button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
                $this->exts->click_by_xdotool('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
                $this->exts->click_by_xdotool('input[name="later"]');
                sleep(7);
            }
            if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
                $this->exts->click_by_xdotool('#editLanguageAndContactForm a[href*="/adsense/app"]');
                sleep(7);
            }
            if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
                $this->exts->click_by_xdotool('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
                sleep(10);
            }
            if ($this->exts->urlContains('gds.google.com/web/chip')) {
                $this->exts->click_by_xdotool('[role="button"]:first-child [jsslot]');
                sleep(10);
            }

            if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
                // An application is requesting permission to access your Google Account.
                // Click allow
                $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
                sleep(10);
            }


            $this->exts->log('URL before back to main tab: ' . $this->exts->getUrl());
            $this->exts->capture("google-login-before-back-maintab");
            if (
                $this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null
            ) {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required google login.');
            $this->exts->capture("3-no-google-required");
        }
    }
    private function checkFillGoogleLogin()
    {
        if ($this->exts->exists('[data-view-id*="signInChooserView"] li [data-identifier]')) {
            $this->exts->click_by_xdotool('[data-view-id*="signInChooserView"] li [data-identifier]');
            sleep(10);
        } else if ($this->exts->exists('form li [role="link"][data-identifier]')) {
            $this->exts->click_by_xdotool('form li [role="link"][data-identifier]');
            sleep(10);
        }
        if ($this->exts->exists('form [data-profileindex]')) {
            $this->exts->click_by_xdotool('form [data-profileindex]');
            sleep(5);
        }
        $this->exts->capture("2-google-login-page");
        if ($this->exts->querySelector($this->google_username_selector) != null) {
            // $this->fake_user_agent();
            // $this->exts->refresh();
            // sleep(5);

            $this->exts->log("Enter Google Username");
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(7);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
            }

            // Which account do you want to use?
            if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->exts->querySelector($this->google_password_selector) != null) {
            $this->exts->log("Enter Google Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            }

            $this->exts->capture("2-google-password-filled");
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                $this->exts->capture("2-login-google-pageandcaptcha-filled");
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
                sleep(10);
                if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                    $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                    sleep(1);
                    $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                    $this->exts->capture("2-login-google-pageandcaptcha-filled");
                    $this->exts->click_by_xdotool($this->google_submit_password_selector);
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::google Password page not found');
            $this->exts->capture("2-google-password-page-not-found");
        }
    }
    private function check_solve_rejected_browser()
    {
        $this->exts->log(__FUNCTION__);
        $root_user_agent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:105.0) Gecko/20100101 Firefox/105.0');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:105.0) Gecko/20100101 Firefox/105.0');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12.6; rv:105.0) Gecko/20100101 Firefox/105.0');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Safari/605.1.15');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
            $this->overwrite_user_agent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->exts->capture("2-login-alternative-page");
            $this->checkFillLogin_undetected_mode();
        }

        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            $this->overwrite_user_agent($root_user_agent);
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            $this->overwrite_user_agent($root_user_agent);
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
        if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
            if ($this->exts->urlContains('/v3/')) {
                $this->exts->click_by_xdotool('a[href*="/restart"]');
            } else {
                $this->exts->refresh();
            }
            sleep(7);
            $this->checkFillLogin_undetected_mode($root_user_agent);
        }
    }
    private function overwrite_user_agent($user_agent_string = 'DN')
    {
        $userAgentScript = "
(function() {
if ('userAgentData' in navigator) {
   navigator.userAgentData.getHighEntropyValues({}).then(() => {
       Object.defineProperty(navigator, 'userAgent', { 
           value: '{$user_agent_string}', 
           configurable: true 
       });
   });
} else {
   Object.defineProperty(navigator, 'userAgent', { 
       value: '{$user_agent_string}', 
       configurable: true 
   });
}
})();
";
        $this->exts->execute_javascript($userAgentScript);
    }

    private function checkFillLogin_undetected_mode($root_user_agent = '')
    {
        if ($this->exts->exists('form [data-profileindex]')) {
            $this->exts->click_by_xdotool('form [data-profileindex]');
            sleep(5);
        } else if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
            $this->exts->capture("2-google-verify-it-you");
            // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(5);
        }

        $this->exts->capture("2-google-login-page");
        if ($this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
            if (!empty($root_user_agent)) {
                $this->overwrite_user_agent('DN'); // using DN (DONT KNOW) user agent, last solution
            }
            $this->exts->type_key_by_xdotool("F5");
            sleep(5);
            $current_useragent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

            $this->exts->log('current_useragent: ' . $current_useragent);
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->google_username_selector);
            $this->exts->click_by_xdotool($this->google_username_selector);
            $this->exts->type_key_by_xdotool("ctrl+a");
            $this->exts->type_key_by_xdotool("Delete");
            $this->exts->type_text_by_xdotool($this->username);
            sleep(1);
            $this->exts->capture_by_chromedevtool("2-google-username-filled");
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(7);
            if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
                if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->click_by_xdotool($this->google_submit_username_selector);
                    sleep(5);
                }
                if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->click_by_xdotool($this->google_submit_username_selector);
                    sleep(5);
                }
            }

            if (!empty($root_user_agent)) { // If using DN user agent, we must revert back to root user agent before continue
                $this->overwrite_user_agent($root_user_agent);
                if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
                    $this->exts->type_key_by_xdotool("F5");
                    sleep(3);
                    $this->exts->type_key_by_xdotool("F5");
                    sleep(3);
                    $this->exts->type_key_by_xdotool("F5");
                    sleep(6);
                    $this->exts->capture_by_chromedevtool("2-google-login-reverted-UA");
                }
            }

            // Which account do you want to use?
            if ($this->exts->check_exist_by_chromedevtool('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->exts->check_exist_by_chromedevtool('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            }

            $this->exts->capture("2-google-password-filled");
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                if ($this->exts->exists('#captchaimg[src]')) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                }
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                    $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                    sleep(1);
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->capture("2-lgoogle-ogin-pageandcaptcha-filled");
                    $this->exts->click_by_xdotool($this->google_submit_password_selector);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Password page not found');
            $this->exts->capture("2-google-password-page-not-found");
        }
    }
    private function checkGoogleTwoFactorMethod()
    {
        // Currently we met many two factor methods
        // - Confirm email account for account recovery
        // - Confirm telephone number for account recovery
        // - Call to your assigned phone number
        // - confirm sms code
        // - Solve the notification has sent to smart phone
        // - Use security key usb
        // - Use your phone or tablet to get a security code (EVEN IF IT'S OFFLINE)
        $this->exts->log(__FUNCTION__);
        sleep(5);
        $this->exts->capture("2.0-before-check-two-factor");
        // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
        if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
            $this->exts->click_by_xdotool('#assistActionId');
            sleep(5);
        } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list");
            if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
                $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
                sleep(5);
            }
        } else if ($this->exts->urlContains('/sk/webauthn') || $this->exts->urlContains('/challenge/pk')) {
            // CURRENTLY THIS CASE CAN NOT BE SOLVED
            $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
            exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get clean'");
            exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get -y update'");
            exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get install -y xdotool'");
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
            sleep(3);
            $this->exts->capture("2.0-cancel-security-usb");
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list");
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->click_by_xdotool('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
            sleep(7);
        } else if ($this->exts->exists('input[name="ootpPin"]')) {
            // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(7);
        } else if ($this->exts->urlContains('/challenge/') && !$this->exts->urlContains('/challenge/pwd') && !$this->exts->urlContains('/challenge/totp')) { // totp is authenticator app code method
            // if this is not password form AND this is two factor form BUT it is not Authenticator app code method, back to selection list anyway in order to choose Authenticator app method if available
            $supporting_languages = [
                "Try another way",
                "Andere Option w",
                "Essayer une autre m",
                "Probeer het op een andere manier",
                "Probar otra manera",
                "Prova un altro metodo"
            ];
            $back_button_xpath = '//*[contains(text(), "Try another way") or contains(text(), "Andere Option w") or contains(text(), "Essayer une autre m")';
            $back_button_xpath = $back_button_xpath . ' or contains(text(), "Probeer het op een andere manier") or contains(text(), "Probar otra manera") or contains(text(), "Prova un altro metodo")';
            $back_button_xpath = $back_button_xpath . ']/..';
            $back_button = $this->exts->getElement($back_button_xpath, null, 'xpath');
            if ($back_button != null) {
                try {
                    $this->exts->log(__FUNCTION__ . ' back to method list to find Authenticator app.');
                    $this->exts->execute_javascript("arguments[0].click();", [$back_button]);
                } catch (\Exception $exception) {
                    $this->exts->execute_javascript("arguments[0].click()", [$back_button]);
                }
            }
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list");
        }

        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
            $this->exts->capture("2.1-2FA-method-list");
            // Updated 03-2023 since we setup sub-system to get authenticator code without request to end-user. So from now, We priority for code from Authenticator app top 1, sms code or email code 2st, then other methods
            if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND TOP 1 method type = 6 is get code from Google Authenticator
                $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->click_by_xdotool('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->click_by_xdotool('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="1"]:not([data-challengeunavailable="true"])')) {
                // Select enter your passowrd, if only option is passkey
                $this->exts->click_by_xdotool('li [data-challengetype="1"]:not([data-challengeunavailable="true"])');
                sleep(3);
                $this->checkFillGoogleLogin();
                sleep(3);
                $this->checkGoogleTwoFactorMethod();
            } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND method type = 6 is get code from Google Authenticator
                $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])') && (isset($this->security_phone_number) && $this->security_phone_number != '')) {
                // We second RECOMMEND method type = 9 is get code from SMS
                $this->exts->click_by_xdotool('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="12"]:not([data-challengeunavailable="true"])')) {
                // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
                $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
                // Use a smartphone or tablet to receive a security code (even when offline)
                $this->exts->click_by_xdotool('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
                // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
                $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
            } else {
                $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"])');
            }
            sleep(10);
        } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
            sleep(10);
        }

        // STEP 2: (Optional)
        if ($this->exts->exists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')) {
            // If methos is recovery email, send 2FA to ask for email
            $this->exts->two_factor_attempts = 2;
            $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
                $this->exts->type_key_by_xdotool('Return');
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')) {
            // If methos confirm recovery phone number, send 2FA to ask
            $this->exts->two_factor_attempts = 3;
            $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool('Return');
                sleep(5);
            }
            if ($this->exts->exists($input_selector)) {
                $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('input#phoneNumberId')) {
            // Enter a phone number to receive an SMS with a confirmation code.
            $this->exts->two_factor_attempts = 3;
            $input_selector = 'input#phoneNumberId';
            $message_selector = '[data-view-id] form section > div > div > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool('Return');
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
            }
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionId
        }

        // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
        if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
            sleep(10);
        } else if (count($this->exts->querySelectorAll('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
            $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
            sleep(7);
        }


        // STEP 4: input code
        if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId')) {
            $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext';
            $this->exts->two_factor_attempts = 3;
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
            $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->fillGoogleTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionIdk
        } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
            $input_selector = 'input[name="secretQuestionResponse"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
            $this->exts->two_factor_attempts = 0;
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
        }
    }
    private function fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            if ($this->exts->two_factor_attempts > 1) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
        }

        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->querySelector($input_selector) != null) {
                if (substr(trim($two_factor_code), 0, 2) === 'G-') {
                    $two_factor_code = end(explode('G-', $two_factor_code));
                }
                if (substr(trim($two_factor_code), 0, 2) === 'g-') {
                    $two_factor_code = end(explode('g-', $two_factor_code));
                }
                $this->exts->log("fillTwoFactor: Entering two_factor_code: " . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, '');
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(2);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($submit_selector)) {
                    $this->exts->log("fillTwoFactor: Clicking submit button.");
                    $this->exts->click_by_xdotool($submit_selector);
                } else if ($submit_by_enter) {
                    $this->exts->type_key_by_xdotool('Return');
                }
                sleep(10);
                $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
                if ($this->exts->querySelector($input_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else {
                    if ($this->exts->two_factor_attempts < 3) {
                        $this->exts->notification_uid = '';
                        $this->exts->two_factor_attempts++;
                        if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
                            // if(strpos(strtoupper($this->exts->extract('div:last-child[style*="visibility: visible;"] [role="button"]')), 'CODE') !== false){
                            $this->exts->click_by_xdotool('[aria-relevant="additions"] + [style*="visibility: visible;"] [role="button"]');
                            sleep(2);
                            $this->exts->capture("2.2-two-factor-resend-code-" . $this->exts->two_factor_attempts);
                            // }
                        }

                        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
                    } else {
                        $this->exts->log("Two factor can not solved");
                    }
                }
            } else {
                $this->exts->log("Not found two factor input");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
    // -------------------- GOOGLE login END


    function processAfterLogin($count)
    {
        $this->exts->log("Begin processAfterLogin " . $count);
        if ($count == 0) {
            if ($this->exts->exists('#nav-flyout-anchor a[href*="/order-history"]')) {
                $this->exts->click_element('#nav-flyout-anchor a[href*="/order-history"]');
            } else if ($this->exts->exists('a[href*="/order-history"]')) {
                $this->exts->click_element('a[href*="/order-history"]');
            } else {
                $this->exts->openUrl($this->orderPageUrl);
            }
            sleep(2);

            if (stripos($this->exts->getUrl(), "amazon.fr/ap/signin") !== false) {
                if ($this->login_tryout == 0) {
                    $this->fillForm(0);

                    $this->checkFillTwoFactor();
                    sleep(5);
                } else {
                    $this->exts->init_required();
                }
            }
        }

        if ($this->checkLogin() && stripos($this->exts->getUrl(), "amazon.fr/ap/signin") === false) {
            $isMultiAccount = count($this->exts->querySelectorAll("select[name=\"selectedB2BGroupKey\"] option")) > 1 ? true : false;
            $this->exts->log("isMultiAccount - " . $isMultiAccount);

            if ($this->exts->exists('form[action*="cookieprefs"] #sp-cc-accept')) {
                $this->exts->click_by_xdotool('form[action*="cookieprefs"] #sp-cc-accept');
                sleep(1);
            }

            if (empty($this->last_state['stage']) || $this->last_state['stage'] == 'ORDER') {
                // keep current state of processing
                $this->current_state['stage'] = 'ORDER';
                $this->last_state['stage'] = '';

                if ($isMultiAccount > 0) {
                    $this->isBusinessUser = true;
                    // Get Business Accounts Filter, only first time of execution, not in restart
                    $optionAccountSelectors = array();
                    $selectAccountElements = $this->exts->querySelectorAll("select[name=\"selectedB2BGroupKey\"] option");
                    if (count($selectAccountElements) > 0) {
                        foreach ($selectAccountElements as $selectAccountElement) {

                            $elementAccountValue = trim($selectAccountElement->getAttribute('value'));
                            if (strtolower($elementAccountValue) == 'yourself' || stripos($elementAccountValue, 'B2B') !== false) {
                                $optionAccountSelectors[] = $elementAccountValue;
                            }
                        }
                    }

                    $this->exts->log("optionAccountSelectors " . count($optionAccountSelectors));
                    if (!empty($optionAccountSelectors)) {
                        foreach ($optionAccountSelectors as $optionAccountSelector) {
                            // In restart mode, process only those account which is not processed yet
                            if ($this->exts->docker_restart_counter > 0 && !empty($this->last_state['accounts']) && in_array($optionAccountSelector, $this->last_state['accounts'])) {
                                $this->exts->log("Restart: Already processed earlier - Account-value  " . $optionAccountSelector);
                                continue;
                            }

                            $this->exts->log("Account-value  " . $optionAccountSelector);

                            // Fill Account Select
                            // $this->exts->querySelector("select[name=\"orderFilter\"]")->selectOptionByValue($yearOrderSelection);
                            // $optionSelAccEle = "select[name=\"selectedB2BGroupKey\"] option[value=\"" . $optionAccountSelector . "\"]";
                            // $this->exts->log("processing account element  " . $optionSelAccEle);
                            // $selectAccountElement = $this->exts->querySelector($optionSelAccEle);
                            // $this->exts->click_element($selectAccountElement);

                            // Selecto box is hiddedn user see custom sropdown
                            $this->exts->waitTillPresent('span[data-action="b2b-account-change"] span[role="button"]');
                            $this->exts->click_element('span[data-action="b2b-account-change"] span[role="button"]');
                            $optionSelAccEle = 'a[id*="b2bDropdown"][data-value*="' . $optionAccountSelector . '"]';
                            $this->exts->log("processing account element  " . $optionSelAccEle);
                            $selectAccountElement = $this->exts->querySelector($optionSelAccEle);
                            $this->exts->click_element($selectAccountElement);
                            sleep(5);

                            $this->exts->capture("Account-Selected-" . $optionAccountSelector);

                            //Reset date limit for each account.
                            $this->dateLimitReached = 0;

                            // Process year filters for each account
                            $this->orderYearFilters(trim($optionAccountSelector));

                            // Keep completely processed account key
                            $this->current_state['accounts'][] = $optionAccountSelector;
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
            if ((empty($this->last_state['stage']) || $this->last_state['stage'] == 'PROCUREMENT_ANALYSIS') && ((int)$this->procurment_report == 1)) {
                // Keep current state of processing
                $this->current_state['stage'] = 'PROCUREMENT_ANALYSIS';
                $this->last_state['stage'] = '';

                if ($this->exts->exists('a.nav-link[href*="/b2b/aba/"]')) {

                    $url = $this->exts->querySelector('a.nav-link[href*="/b2b/aba/"]')->getAttribute('href');
                    if ($url != '' || $url != null) {
                        $this->exts->openUrl($url);
                    } else {
                        $this->exts->click_element('a.nav-link[href*="/b2b/aba/"]');
                    }



                    sleep(10);
                    if (stripos($this->exts->getUrl(), "/ap/signin") !== false) {
                        $this->fillForm(0);
                        // retry if captcha showed
                        if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                            $this->fillForm(0);
                        }
                        $this->checkFillTwoFactor();
                        sleep(4);
                        if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                            $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                            sleep(2);
                        }
                    } else {
                        $this->checkFillTwoFactor();
                    }
                    if ($this->exts->exists('form[action*="cookieprefs"] #sp-cc-accept')) {
                        $this->exts->click_by_xdotool('form[action*="cookieprefs"] #sp-cc-accept');
                        sleep(1);
                    }


                    // Click Order report
                    if ($this->exts->exists('a.nav-link[href*="/b2b/aba/"]')) {
                        $url = $this->exts->querySelector('li.report-nav-item a[href*="/b2b/aba/reports?reportType=items_report_1"]')->getAttribute('href');
                        if ($url != '' || $url != null) {
                            $this->exts->openUrl($url);
                        } else {
                            $this->exts->click_element('li.report-nav-item a[href*="/b2b/aba/reports?reportType=items_report_1"]');
                        }
                    } else {
                        $this->exts->log('PROCUREMENT_ANALYSIS REPORT ELEMENT NOT FOUND');
                    }


                    if ($this->exts->exists('#date_range_selector__range')) {
                        $this->exts->click_element('#date_range_selector__range');
                        sleep(3);

                        if ((int)$this->restrictPages == 0) {
                            $this->exts->click_element('.date-range-selector .b-dropdown-menu a[value="PAST_12_MONTHS"]');
                        } else {
                            $this->exts->click_element('.date-range-selector .b-dropdown-menu a[value="PAST_12_WEEKS"]');
                        }
                        sleep(15);

                        if (!$this->exts->exists('.report-table .column:nth-child(3) [class*="cell-row-"]')) {
                            sleep(25);
                        }

                        $this->isBusinessUser = true;
                        $this->exts->capture('procurment_report');


                        $this->dateLimitReached = 0;
                        $this->download_procurment_document(1);
                        //This only needed if we need to select custom date
                        /*if($this->exts->exists('.react-datepicker__input-container input')) {
                        $this->exts->querySelectorAll('.react-datepicker__input-container input')[0]->click();
                        sleep(1);

                        $this->exts->click_by_xdotool('.react-datepicker-popper a.react-datepicker__navigation--previous');
                        sleep(1);

                        $today_day = (int)date('d');

                        $this->exts->click_by_xdotool('.react-datepicker-popper .react-datepicker__month .react-datepicker__week [aria-label="day-'.$today_day.'"]:not(.react-datepicker__day--outside-month)');
                        sleep(1);

                        $this->exts->click_by_xdotool('.submit-button');
                        sleep(15);

                        if($this->exts->exists('.report-table .column:nth-child(3) [class*="cell-row-"]')) {
                            $this->download_procurment_document();
                        }
                    }*/
                    }
                } else {
                    $this->exts->log('PROCUREMENT_ANALYSIS URL ELEMENT NOT FOUND');
                }
            }



            //Check Business Prime Account
            //https://www.amazon.fr/businessprimeversand
            if (empty($this->last_state['stage']) || $this->last_state['stage'] == 'BUSINESS_PRIME') {
                // Keep current state of processing
                $this->current_state['stage'] = 'BUSINESS_PRIME';
                $this->last_state['stage'] = '';

                if ($this->isBusinessUser) {
                    $this->dateLimitReached = 0;
                    $this->downloadBusinessPrimeInvoices();
                } else {
                    $this->downloadPrimeInvoice();
                }
            }

            // Process Message Center
            if (empty($this->last_state['stage']) || $this->last_state['stage'] == 'MESSAGE') {
                // Keep current state of processing
                $this->current_state['stage'] = 'MESSAGE';
                $this->last_state['stage'] = '';

                $this->triggerMsgInvoice();
            }

            if ($this->exts->document_counter == 0) {
                $this->exts->no_invoice();
            }
        }
    }

    private function orderYearFilters($selectedBusinessAccount = "")
    {
        if (trim($selectedBusinessAccount) != "") {
            $this->exts->log("selectedBusinessAccount Account-value  " . $selectedBusinessAccount);
        }

        // Get Order Filter years
        $optionSelectors = array();
        $selectElements = $this->exts->querySelectorAll("select[name='orderFilter'] option");
        $this->exts->log("selectElements " . count($selectElements));
        if (count($selectElements) == 0) {
            $selectElements = $this->exts->querySelectorAll('select[name="timeFilter"] option');
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
                for ($i = 0; $i <= $this->restrictPages; $i++) {
                    $elementValue = trim($selectElements[$i]->getAttribute('value'));
                    $optionSelectors[] = $elementValue;
                }
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
            for ($i = 0; $i <= min(2, count($optionSelectors)); $i++) {
                $this->exts->log("year-value  " . $optionSelectors[$i]);
            }
            // Process Each Year
            $this->processYears($optionSelectors);
        }
    }

    private function changeSelectbox($select_box = '', $option_value = '')
    {
        $this->exts->waitTillPresent($select_box, 10);
        if ($this->exts->exists($select_box)) {
            $option = $option_value;
            $this->exts->click_by_xdotool($select_box);
            sleep(2);
            $optionIndex = $this->exts->executeSafeScript('
		const selectBox = document.querySelector("' . $select_box . '");
		const targetValue = "' . $option_value . '";
		const optionIndex = [...selectBox.options].findIndex(option => option.value === targetValue);
		return optionIndex;
	');
            $this->exts->log($optionIndex);
            sleep(1);
            for ($i = 0; $i < $optionIndex; $i++) {
                $this->exts->log('>>>>>>>>>>>>>>>>>> Down');
                // Simulate pressing the down arrow key
                $this->exts->type_key_by_xdotool('Down');
                sleep(1);
            }
            $this->exts->type_key_by_xdotool('Return');
        } else {
            $this->exts->log('Select box does not exist');
        }
    }

    private function processYears($optionSelectors)
    {
        //Update the lock so that window is not closed by cron.
        $this->exts->update_process_lock();

        $this->exts->capture("Process-Years");

        foreach ($optionSelectors as $optionSelector) {
            $this->exts->log("processing year  " . $optionSelector);

            if ($this->dateLimitReached == 1) {
                break;
            }

            // In restart mode, process only those years which is not processed yet
            if ($this->exts->docker_restart_counter > 0 && !empty($this->last_state['years']) && in_array($optionSelector, $this->last_state['years'])) {
                $this->exts->log("Restart: Already processed year - " . $optionSelector);
                continue;
            }

            $this->exts->log("processing year element  " . $optionSelector);
            if ($this->exts->exists('span[data-a-class="order-filter-dropdown"] span[role="button"]')) {
                $this->exts->click_element('span[data-a-class="order-filter-dropdown"] span[role="button"]');
                sleep(3);

                $optionSelEle = 'a[id*="orderFilter"][data-value*="' . $optionSelector . '"]';
                $selectElement = $this->exts->querySelector($optionSelEle);
                $this->exts->click_element($selectElement);
            } else {
                $this->changeSelectbox("select[name='timeFilter']", $optionSelector);
            }

            sleep(2);

            if ($this->exts->querySelector($this->password_selector) != null || stripos($this->exts->getUrl(), "/ap/signin?") !== FALSE) {
                if ($this->login_tryout == 0) {
                    $this->fillForm(0);
                    if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                        $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                        sleep(2);
                    }
                } else {
                    $this->exts->init_required();
                }
            }

            $this->exts->capture("orders-" . $optionSelector);

            if ($this->exts->querySelector($this->password_selector) != null || stripos($this->exts->getUrl(), "/ap/signin?") !== false) {
                $this->fillForm(0);
                sleep(4);

                $this->checkFillTwoFactor();
                if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                    $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                    sleep(2);
                }
            }

            $this->exts->waitTillPresent("div.a-box-group.a-spacing-base.order, .order-card", 30);

            if (!$this->exts->exists("div.a-box-group.a-spacing-base.order, .order-card")) {
                sleep(15);
            }
            $totalPages = 100;
            if ($this->exts->exists('.a-pagination li#paginationButton')) {
                $totalPages = count($this->exts->getElements('.a-pagination li#paginationButton'));
            }
            $this->exts->log('Total-order-pages:: ' . $totalPages);

            $this->exts->capture('order-page');
            for ($paging_count = 1; $paging_count < $totalPages; $paging_count++) {
                sleep(5);
                if ($this->exts->exists('.order-card a[href*="/ajax/invoice/"]')) {

                    // Huy added this 2023-01
                    $count_order_card = count($this->exts->querySelectorAll('.order-card a[href*="/ajax/invoice/"]'));
                    $order_invoices = [];
                    for ($i = 0; $i < $count_order_card; $i++) {
                        $order_card_invoice_dropdown = $this->exts->querySelectorAll('.order-card a[href*="/ajax/invoice/"]')[$i];
                        $temp_url = $order_card_invoice_dropdown->getAttribute('href');
                        $temp_array = explode('orderId=', $temp_url);
                        $order_invoice_name = end($temp_array);
                        $temp_array = explode('&', $order_invoice_name);
                        $order_invoice_name = reset($temp_array);

                        try {
                            $this->exts->click_element($order_card_invoice_dropdown);
                        } catch (Exception $e) {
                            $this->exts->execute_javascript('arguments[0].click()', [$order_card_invoice_dropdown]);
                        }
                        sleep(5);
                        if ($this->exts->exists('.a-popover[style*="visibility: visible"]:not([aria-hidden="true"]) a[href*="/invoice.pdf"]')) {
                            $order_invoice_url = $this->exts->querySelector('.a-popover[style*="visibility: visible"]:not([aria-hidden="true"]) a[href*="/invoice.pdf"]')->getAttribute('href');
                            $this->exts->log('--------------------------');
                            $this->exts->log('order_invoice_name: ' . $order_invoice_name);
                            $this->exts->log('order_invoice_url: ' . $order_invoice_url);

                            $invoiceFileName = !empty($order_invoice_name) ? $order_invoice_name . '.pdf' : '';

                            $downloaded_file = $this->exts->direct_download($order_invoice_url, 'pdf', $invoiceFileName);
                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                //This is needed by system to not check overview as invoice and bypass overview number in system
                                $invoice_note = "Amazon Direct - " . $order_invoice_name;
                                $this->exts->new_invoice($order_invoice_name, '', '', $downloaded_file, 0, $invoice_note, 0, '', array(
                                    'tags' => (int)@$this->auto_tagging == 1 && !empty($this->amazon_invoice_tags) ? $this->amazon_invoice_tags : ''
                                ));

                                $this->exts->sendRequestEx($order_invoice_name . ":::" . $invoice_note, "===NOTE-DATA===");
                                if ((int)@$this->auto_tagging == 1 && !empty($this->amazon_invoice_tags)) {
                                    $this->exts->sendRequestEx($order_invoice_name . ":::" . $this->amazon_invoice_tags, "===INVOICE-TAGS===");
                                }
                            } else {
                                $this->exts->log(__FUNCTION__ . '::No download ' . $order_invoice_name);
                            }
                        }
                        $this->exts->click_by_xdotool('[data-action="a-popover-close"]');
                        sleep(2);
                    }
                } else if ($this->exts->querySelector(".order-card, .js-order-card") != null) {
                    $this->exts->log("Invoice Found");

                    $invoice_data_arr = array();
                    $rows = $this->exts->querySelectorAll(".order-card, .js-order-card");
                    $this->exts->log("Invoice Rows- " . count($rows));
                    $total_rows = count($rows);
                    if (count($rows) > 0) {
                        for ($i = 0, $j = 2; $i < $total_rows; $i++, $j++) {
                            $rowItem = $rows[$i];
                            try {
                                $columns = $rowItem->querySelectorAll('div.order-info div.a-fixed-right-grid-col:nth-child(1) span.a-color-secondary.value, .order-header .a-row:nth-child(2) .a-color-secondary');
                                $this->exts->log("Invoice Row columns- $i - " . count($columns));
                                if (count($columns) > 0) {
                                    $invoice_date = trim($columns[0]->getText());
                                    $this->exts->log("invoice_date - " . $invoice_date);

                                    if ($this->start_date != "" && !empty($this->start_date)) {
                                        try {
                                            $parsed_date = $this->exts->parse_date($invoice_date);
                                        } catch (\Exception $exception) {
                                            $this->exts->log('ERROR in parsing Date - ' . $invoice_date);
                                            $parsed_date = '';
                                        }

                                        if (!empty($parsed_date) && $this->start_date > strtotime($parsed_date)) {
                                            $this->dateLimitReached = 1;
                                            break;
                                        }
                                    }
                                    $invoice_amount = trim($columns[count($columns) - 1]->getText());
                                    if (stripos($invoice_amount, "EUR") !== false && stripos($invoice_amount, "EUR") <= 1) {
                                        $invoice_amount = trim(substr($invoice_amount, 3)) . " EUR";
                                    } else if (stripos($invoice_amount, "EUR") !== false && stripos($invoice_amount, "EUR") > 1) {
                                        $invoice_amount = trim(substr($invoice_amount, 3, strlen($invoice_amount) - 3)) . " EUR";
                                    } else if (stripos($invoice_amount, "USD") !== false && stripos($invoice_amount, "USD") <= 1) {
                                        $invoice_amount = trim(substr($invoice_amount, 3)) . " USD";
                                    } else if (stripos($invoice_amount, "USD") !== false && stripos($invoice_amount, "USD") > 1) {
                                        $invoice_amount = trim(substr($invoice_amount, 3, strlen($invoice_amount) - 3)) . " USD";
                                    }

                                    $columns = $rowItem->querySelectorAll('div.order-info div.a-fixed-right-grid-col:nth-child(1) span.a-color-secondary.value, .order-header .a-row:nth-child(2) .a-color-secondary');
                                    $invoice_number = $this->exts->extract('[class*="order-id"] .a-color-secondary:nth-child(2)', $rowItem, 'innerText');
                                    $invoice_number = trim($invoice_number);
                                    $this->exts->log("invoice_number - " . $invoice_number);

                                    $orderItems = $this->exts->querySelectorAll("div.a-box.shipment div.a-fixed-right-grid-col div.a-fixed-left-grid", $rowItem);
                                    $orderItemCount = count($orderItems);
                                    $this->exts->log("Order Item count - " . $orderItemCount);

                                    $this->exts->log("starting process for invoice_number - " . $invoice_number);
                                    $sellerName = "";
                                    $sellerColumns = $rowItem->querySelectorAll("div.a-box.shipment div.a-fixed-left-grid-col.a-col-right span.a-color-secondary");
                                    if (count($sellerColumns) > 0) {
                                        $sellerName = trim($sellerColumns[0]->getText());
                                        if (trim($sellerName) != "" && stripos(trim($sellerName), ": Amazon EU S.a.r.L.") !== false && count($sellerColumns) > 1) {
                                            $sellerColumns1 = $rowItem->querySelectorAll("div.a-box.shipment div.a-fixed-left-grid-col.a-col-right span.a-size-small.a-color-secondary");
                                            if (count($sellerColumns1) > 0) {
                                                foreach ($sellerColumns1 as $sellerColumnEle) {
                                                    $sellerColumnEleText = trim($sellerColumnEle->getText());
                                                    if (trim($sellerColumnEleText) != "" && stripos(trim($sellerColumnEleText), ": Amazon EU S.a.r.L.") === false) {
                                                        $sellerName = trim($sellerColumnEleText);
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    $detailPageUrl = "";
                                    $columns = $rowItem->querySelectorAll('div.order-info div.a-fixed-right-grid-col.actions ul a.a-link-normal, a[href*="/order-details"]');
                                    if (count($columns) > 0) {
                                        $detailPageUrl = $columns[0]->getAttribute("href");
                                        if (stripos($detailPageUrl, "https://www.amazon.fr") === false && stripos($detailPageUrl, "https://") === false) {
                                            $detailPageUrl = "https://www.amazon.fr" . trim($detailPageUrl);
                                        }

                                        $filename = !empty($invoice_number) ? trim($invoice_number) . ".pdf" : '';

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
                                            try {
                                                $parsed_date = $this->exts->parse_date($invoice_date);
                                            } catch (\Exception $exception) {
                                                $this->exts->log('ERROR in parsing Date - ' . $invoice_date);
                                                $parsed_date = '';
                                            }
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
                                                        $currentBlockPrice = $price_block->getText();
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
                                                    $this->exts->click_element($invoice_popover_button);
                                                    sleep(2);
                                                    $this->exts->waitTillPresent(' .a-popover[aria-hidden="false"] .invoice-list a[href*=download]', 10);
                                                    $links = $this->exts->querySelector(' .a-popover[aria-hidden="false"] .invoice-list a[href*=download]');

                                                    $this->exts->log("Popover Links found - " . count($links));
                                                    if (empty($links)) {
                                                        $this->exts->log("No Invoice Url found So moving to next row - " . $invoice_number);
                                                        continue;
                                                    }

                                                    // Find overview link
                                                    $overview_link = "";

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
                                                    $tempInvUrls = array();
                                                    foreach ($links as $lkey =>  $link_item) {
                                                        $currItemLinkText = $link_item->getText();
                                                        $currItemLinkText = trim($currItemLinkText);

                                                        if (stripos($currItemLinkText, "Rechnung oder Gutschrift") === false) {
                                                            // Sometime in .de language appears as english, so along with Rechnung, replace Invoice
                                                            $currItemLinkText = str_replace("Rechnung ", "", $currItemLinkText);
                                                            $currItemLinkText = str_replace("Invoice ", "", $currItemLinkText);

                                                            if ((int)trim($currItemLinkText) == $inv_num) {
                                                                $currItemLink = $link_item->getAttribute('href');
                                                                $currItemLink = trim($currItemLink);

                                                                $tempInvUrls[] = array(
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

                                                    // Check if invoice request link is available and no invoice link is present, then request invoice
                                                    $this->exts->log("Checking here that Invoice Request is needed or not. Total Invoice URLS - " . count($tempInvUrls));
                                                    if (trim($contact_link) != "" && (empty($tempInvUrls) || count($tempInvUrls) < $orderItemCount)) {
                                                        $invoice_urls[] = array(
                                                            'link' => $overview_link,
                                                            'overview_link' => $overview_link,
                                                            'contact_url' => $contact_link,
                                                            'price' => 0,
                                                            'is_credit_note' => 0
                                                        );
                                                    }
                                                    if (count($tempInvUrls) > 0) {
                                                        foreach ($tempInvUrls as $tempInvUrl) {
                                                            $invoice_urls[] = $tempInvUrl;
                                                        }
                                                    }
                                                    $this->exts->log("Total Invoice URLS After Invoice Request Check - " . count($invoice_urls));

                                                    $inv_num = 1;
                                                    //if(trim($contact_link) == "") {
                                                    // Download credit note only if no contact link is available, because if contact link is available
                                                    // system will download overview and do a invoice request
                                                    // virtually in this way, system will never download credit note and in either way we don't need it.
                                                    //07-02-2019 - This restriction is removed because now OCR remove delivery note and need to download adjustment
                                                    foreach ($links as $lkey =>  $link_item) {
                                                        $currItemLinkText = $link_item->getText();
                                                        $currItemLinkText = trim($currItemLinkText);

                                                        if (stripos($currItemLinkText, "Rechnung oder Gutschrift") !== false) {
                                                            $currItemLinkText = str_replace("Rechnung oder Gutschrift ", "", $currItemLinkText);
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
                                                            $currItemLinkText = str_replace("Rechnungskorrektur ", "", $currItemLinkText);
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
                                                    //}
                                                    $this->exts->log("Total Invoice URLS After Invoice Request & Credit Note Check - " . count($invoice_urls));

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
                                                    $this->exts->click_by_xdotool('[data-action="a-popover-close"]');
                                                }

                                                if (!empty($invoice_urls)) {
                                                    $invoicePrefix = 0;
                                                    $invoiceSize = 0;
                                                    $savedInvoices = array();
                                                    $org_inv_number = '';

                                                    $total_dinv = 0;
                                                    if (count($invoice_urls) > 1 && $this->exts->invoice_exists($invoice_number)) {
                                                        $total_invUrl = count($invoice_urls);
                                                        for ($mki = 0; $mki < $total_invUrl; $mki++) {
                                                            if (!empty($invoice_urls[$mki]['link'])) {
                                                                if ($mki == 0) {
                                                                    $tempInvNum = $invoice_number;
                                                                } else {
                                                                    $tempInvNum = $invoice_number . '-' . $mki;
                                                                }
                                                                $this->exts->log('Link check - ' . $invoice_urls[$mki]['link']);
                                                                $this->exts->log('Number Check - ' . $tempInvNum);
                                                                if ((stripos(trim($invoice_urls[$mki]['link']), '/download.html') !== false || stripos(trim($invoice_urls[$mki]['link']), '/documents/download/') !== false) && !empty($this->exts->config_array['download_invoices']) && !empty($tempInvNum) && in_array($tempInvNum, $this->exts->config_array['download_invoices'])) {
                                                                    $total_dinv++;
                                                                }
                                                            }
                                                        }
                                                        if ($total_dinv < ($total_invUrl - 1)) {
                                                            $invoicePrefix = $total_invUrl;
                                                        }
                                                    }
                                                    $this->exts->log('Total already downloaded documents for this number - ' . $invoice_number . ' - ' . count($invoice_urls) . ' - ' . $total_dinv);

                                                    foreach ($invoice_urls as $invoice_url_item) {
                                                        $item_invoice_number = $invoice_number;
                                                        $this->exts->log("sellerName - " . $sellerName);
                                                        $this->exts->log("Invoice url - " . $invoice_url_item['link']);
                                                        $this->exts->log("Invoice Contact url - " . $invoice_url_item['contact_url']);
                                                        $this->exts->log("Invoice Overview url - " . $invoice_url_item['overview_link']);
                                                        $this->exts->log("Invoice cost - " . $invoice_url_item['price']);
                                                        $this->exts->log("Invoice is_credit_note - " . $invoice_url_item['is_credit_note']);

                                                        $contact_url = $invoice_url_item['contact_url'];
                                                        $invoice_url = $invoice_url_item['link'];
                                                        $overview_link = $invoice_url_item['overview_link'];
                                                        $orderPrice = $invoice_url_item['price'];
                                                        $org_inv_number = $item_invoice_number;

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
                                                        // we have noticed sometime it downloads credit note linked as invoice 2*24*60*60 = 86400
                                                        if (trim($sellerName) != "" && stripos(trim($sellerName), ": Amazon EU S.a.r.L.") !== false) {
                                                            $this->exts->log("invoiceDate - " . $invoice_date);
                                                            $timeDiff = strtotime("now") - strtotime($invoice_date);
                                                            $diffDays = ceil($timeDiff / (172800));
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
                                                                $filename = !empty($item_invoice_number) ?  $item_invoice_number . ".pdf" : '';
                                                            }

                                                            if (stripos($invoice_url, "https://www.amazon.fr") === false && stripos($invoice_url, "https://") === false) {
                                                                $invoice_url = "https://www.amazon.fr" . $invoice_url;
                                                            }
                                                            $this->exts->log("invoice_url - " . $invoice_url);

                                                            if (trim($overview_link) != "" && stripos($overview_link, "https://www.amazon.fr") === false && stripos($overview_link, "https://") === false) {
                                                                $overview_link = "https://www.amazon.fr" . $overview_link;
                                                            }

                                                            if (stripos($invoice_url, "print") !== false) {
                                                                // Check if user has opted for auto invoice request, then download overview only if amazon is not seller
                                                                $download_overview = (int)@$this->amazon_download_overview;
                                                                if ((int)$this->auto_request_invoice == 1 && $contact_url != "") {
                                                                    $download_overview = 1;
                                                                }

                                                                if ($download_overview == 1 && (trim($sellerName) == "" || (trim($sellerName) != "" && stripos(trim($sellerName), ": Amazon EU S.a.r.L.") === false))) {
                                                                    $this->exts->log("New Overview invoiceName- " . $item_invoice_number . ' Original Invoice Number - ' . $org_inv_number);
                                                                    if (!$this->exts->invoice_exists($item_invoice_number) && !$this->invoice_overview_exists($item_invoice_number) && !$this->exts->invoice_exists($org_inv_number) && !$this->invoice_overview_exists($org_inv_number)) {
                                                                        $this->exts->log("Downloading overview page as invoice");

                                                                        $this->exts->log("New Overview invoiceName- " . $item_invoice_number);
                                                                        $this->exts->log("New Overview invoiceAmount- " . $invoice_amount);
                                                                        $this->exts->log("New Overview Filename- " . $filename);

                                                                        //Sometime while capturing overview page we get login form, but not in opening any other page.
                                                                        //So detect such case and process login again

                                                                        $currentUrl = $this->exts->getUrl();

                                                                        // Open New window To process Invoice

                                                                        $this->exts->openNewTab($invoice_url);
                                                                        // Call Processing function to process current page invoices

                                                                        sleep(2);

                                                                        if (stripos($this->exts->getUrl(), "amazon.fr/ap/signin") !== false) {
                                                                            $this->fillForm(0);
                                                                            sleep(4);
                                                                            if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                                                                                $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                                                                                sleep(2);
                                                                            }
                                                                        }

                                                                        if (($this->exts->urlContains('/print.html') || $this->exts->urlContains('print=1')) && !$this->exts->urlContains('/ap/signin')) {
                                                                            $downloaded_file = $this->exts->download_current($filename, 5);
                                                                            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
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
                                                                            }
                                                                        } else if (stripos($this->exts->getUrl(), "amazon.fr/ap/signin") === false && (stripos($this->exts->getUrl(), "order-document.pdf") !== false || stripos($this->exts->getUrl(), ".pdf") !== false)) {
                                                                            // Wait for completion of file download
                                                                            $this->exts->wait_and_check_download('pdf');

                                                                            // find new saved file and return its path
                                                                            $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                                                                            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
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
                                                                            }
                                                                        } else {
                                                                            $this->exts->log('Current URL - ' . $this->exts->getUrl());
                                                                            // Wait for completion of file download
                                                                            $this->exts->wait_and_check_download('pdf');

                                                                            // find new saved file and return its path
                                                                            $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                                                                            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
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
                                                                            }
                                                                        }

                                                                        // Close new window
                                                                        $this->exts->switchToInitTab();
                                                                        $this->exts->closeAllTabsButThis();
                                                                    } else {
                                                                        $this->exts->log("Invoice Overview is already downloaded");
                                                                    }
                                                                } else {
                                                                    if (trim($sellerName) != "" && stripos(trim($sellerName), ": Amazon EU S.a.r.L.") !== false) {
                                                                        $this->exts->log("Skip download overview for amazon");
                                                                    } else {
                                                                        $this->exts->log("Skip download overview as user has not opted for");
                                                                    }
                                                                }
                                                            } else {
                                                                $currentUrl = $this->exts->getUrl();
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

                                                                        // Open New window To process Invoice
                                                                        $this->exts->openNewTab($invoice_url);

                                                                        // Call Processing function to process current page invoices
                                                                        // $this->exts->openUrl($invoice_url);
                                                                        sleep(2);

                                                                        if (stripos($this->exts->getUrl(), "amazon.fr/ap/signin") !== false) {
                                                                            $this->fillForm(0);
                                                                            sleep(4);
                                                                            if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                                                                                $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                                                                                sleep(2);
                                                                            }
                                                                        }

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

                                                                        // Close new window
                                                                        $this->exts->switchToInitTab();
                                                                        sleep(2);
                                                                        $this->exts->closeAllTabsButThis();

                                                                        if (stripos($this->exts->getUrl(), "/your-account/order-history") === false) {
                                                                            $this->exts->openUrl($currentUrl);
                                                                            sleep(2);
                                                                        }

                                                                        $rows = $this->exts->querySelectorAll("div.a-box-group.a-spacing-base.order, .order-card");
                                                                        $this->exts->log("Invoice Rows- " . count($rows));
                                                                        $total_rows = count($rows);
                                                                        if ($total_rows == 0) {
                                                                            $this->exts->openUrl($currentUrl);
                                                                            sleep(10);
                                                                            $this->exts->log("Invoice Rows- " . count($rows));
                                                                        }
                                                                    }
                                                                } else {
                                                                    //Sometime while downloading pdf we get login form, but not in opening any other page.
                                                                    //So detect such case and process login again
                                                                    if (stripos($this->exts->getUrl(), "amazon.fr/ap/signin") !== false) {
                                                                        $this->fillForm(0);
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

                                                                    //Sometime download URL give 404 page.
                                                                    //Even if login page comes direct download open URL in current tab only so login form will destroy the previous collected elements.
                                                                    if (stripos($this->exts->getUrl(), "/your-account/order-history") === false) {
                                                                        $this->exts->openUrl($currentUrl);
                                                                        sleep(2);
                                                                    }

                                                                    $rows = $this->exts->querySelectorAll("div.a-box-group.a-spacing-base.order, .order-card");
                                                                    $this->exts->log("Invoice Rows- " . count($rows));
                                                                    $total_rows = count($rows);
                                                                    if ($total_rows == 0) {
                                                                        $this->exts->openUrl($currentUrl);
                                                                        sleep(10);
                                                                        $this->exts->log("Invoice Rows- " . count($rows));
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
                                                            $contact_url = $savedInvoice['contact_url'];
                                                            if (trim($contact_url) != "" && stripos($contact_url, "https://www.amazon.fr") === false) {
                                                                $contact_url = "https://www.amazon.fr" . $contact_url;
                                                            }

                                                            $invoice_note = "Order Overview - " . $savedInvoice['invoiceName'];
                                                            $this->exts->new_invoice($savedInvoice['invoiceName'], $savedInvoice['invoiceDate'], $savedInvoice['invoiceAmount'], $savedInvoice['filename'], 1, $invoice_note, 0, '', array(
                                                                'extra_data' => !empty($contact_url) ? $contact_url : "AMAZON_NO_DOWNLOAD",
                                                                'tags' => (int)@$this->auto_tagging == 1 && !empty($this->order_overview_tags) ? $this->order_overview_tags : ''
                                                            ));
                                                            $invoice_note = ":::" . $invoice_note;
                                                            if (trim($contact_url) != "") {
                                                                $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::" . $contact_url, "===EXTRA-DATA===");
                                                            } else {
                                                                $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::AMAZON_NO_DOWNLOAD", "===EXTRA-DATA===");
                                                            }
                                                            $this->exts->sendRequestEx($savedInvoice['invoiceName'] . $invoice_note, "===NOTE-DATA===");

                                                            if ((int)@$this->auto_tagging == 1 && !empty($this->order_overview_tags)) {
                                                                $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::" . $this->order_overview_tags, "===INVOICE-TAGS===");
                                                            }
                                                        } else {
                                                            $invoice_note = "Amazon Direct - " . $savedInvoice['invoiceName'];
                                                            $this->exts->new_invoice($savedInvoice['invoiceName'], $savedInvoice['invoiceDate'], $savedInvoice['invoiceAmount'], $savedInvoice['filename'], 0, $invoice_note, 0, '', array(
                                                                'tags' => (int)@$this->auto_tagging == 1 && !empty($this->amazon_invoice_tags) ? $this->amazon_invoice_tags : ''
                                                            ));

                                                            $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::" . $invoice_note, "===NOTE-DATA===");
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
                } else if ($this->exts->querySelector('a[href*="/vps/pslip/ref"]') != null) {
                    $this->exts->capture('print-orders');
                    sleep(5);
                    $invoices = [];
                    $rows = $this->exts->getElements('div#yourOrderHistorySection div#orderCard');
                    foreach ($rows as $key => $row) {
                        $invoiceLink = $this->exts->getElement('a[href*="/vps/pslip/ref"]', $row);
                        if ($invoiceLink != null) {
                            $invoiceUrl = $invoiceLink->getAttribute("href");
                            preg_match('/orderId=([0-9\-]+)/', $invoiceUrl, $matches);
                            $invoiceName = isset($matches[1]) ?  $matches[1] : '';
                            $invoiceDate = '';
                            $invoiceAmount = $this->exts->extract('span[class*="price"]', $row);

                            array_push($invoices, array(
                                'invoiceName' => $invoiceName,
                                'invoiceDate' => $invoiceDate,
                                'invoiceAmount' => $invoiceAmount,
                                'invoiceUrl' => $invoiceUrl,
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

                        $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
                        $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
                        $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                        $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                            sleep(1);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                }

                if ($this->exts->exists('.a-pagination a[href="#pagination/next/"]')) {
                    $this->exts->log('NEXT page ' . $paging_count);
                    $this->exts->click_by_xdotool('.a-pagination a[href="#pagination/next/"]');
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
        if ($this->exts->querySelector("span.num-orders-for-orders-by-date span.num-orders") != null) {
            $total_data = $this->exts->querySelector("span.num-orders-for-orders-by-date span.num-orders")->getText();
            $this->exts->log("total_data -" . $total_data);
            if (stripos($total_data, "Keine") === false) {
                $tempArr = explode(" ", $total_data);
                if (count($tempArr)) {
                    $total_data = trim($tempArr[0]);
                }
                $this->exts->log("total_data -" . $total_data);
                $pages = round($total_data / 10);
                $this->exts->log("total_data -" . $pages);
            }
            if ($pages < 2) {
                $pageEle = $this->exts->querySelectorAll("div.pagination-full a");
                $liCount = count($pageEle);
                if ($liCount > 2) {
                    $pages = (int)trim($pageEle[$liCount - 2]->getText());
                }
            }
        } else if ($this->exts->querySelector("span.num-orders") != null) {
            $total_data = $this->exts->querySelector("span.num-orders")->getText();
            $this->exts->log("total_data -" . $total_data);
            if (stripos($total_data, "Keine") === false) {
                $tempArr = explode(" ", $total_data);
                if (count($tempArr)) {
                    $total_data = trim($tempArr[0]);
                }
                $this->exts->log("total_data -" . $total_data);
                $pages = round($total_data / 10);
                $this->exts->log("total_data -" . $pages);
            }
            if ($pages < 2) {
                $pageEle = $this->exts->querySelectorAll("div.pagination-full a");
                $liCount = count($pageEle);
                if ($liCount > 2) {
                    $pages = (int)trim($pageEle[$liCount - 2]->getText());
                }
            }
        } else {
            $pageEle = $this->exts->querySelectorAll("div.pagination-full a");
            if (count($pageEle)) {
                $liCount = count($pageEle);
                if ($liCount > 2) {
                    $pages = (int)trim($pageEle[$liCount - 2]->getText());
                }
            }
        }

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
        $this->exts->update_process_lock();
        $this->exts->openUrl($this->businessPrimeUrl);
        sleep(5);

        if (stripos($this->exts->getUrl(), "/ap/signin") !== false) {
            $this->fillForm(0);
            // retry if captcha showed
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                $this->fillForm(0);
            }
            $this->checkFillTwoFactor();
            sleep(4);
            if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                sleep(2);
            }
        } else {
            $this->checkFillTwoFactor();
        }

        if ($this->exts->exists('form[action*="cookieprefs"] #sp-cc-accept')) {
            $this->exts->click_by_xdotool('form[action*="cookieprefs"] #sp-cc-accept');
            sleep(1);
        }

        $this->exts->capture("business-prime");

        if ($this->exts->querySelector("a[href*=\"/bb/benefits/bps/landing/?ref_=ab_bps_engmt_mgmtbox\"]") != null) {
            $this->exts->click_element("a[href*=\"/bb/benefits/bps/landing/?ref_=ab_bps_engmt_mgmtbox\"]");
            sleep(10);

            $invoice_url = "";
            if ($this->exts->querySelector("a#business-prime-shipping-view-last-invoice") != null) {
                try {
                    $invoice_url = $this->exts->querySelector("a#business-prime-shipping-view-last-invoice")->getAttribute("href");
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                    if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.fr") === false && stripos($invoice_url, "https://") === false) {
                        $invoice_url = "https://www.amazon.fr" . $invoice_url;
                    }
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                } catch (\Exception $exception) {
                    $this->exts->log("Getting business prime invoice 1st option - " . $exception->getMessage());
                }
            } else {
                if ($this->exts->querySelector("a[href*=\"/documents/download/\"]") != null) {
                    try {
                        $invoice_url = $this->exts->querySelector("a[href*=\"/documents/download/\"]")->getAttribute("href");
                        $this->exts->log("prime invoice URL - " . $invoice_url);
                        if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.fr") === false && stripos($invoice_url, "https://") === false) {
                            $invoice_url = "https://www.amazon.fr" . $invoice_url;
                        }
                        $this->exts->log("prime invoice URL - " . $invoice_url);
                    } catch (\Exception $exception) {
                        $this->exts->log("Getting business prime invoice 2nd option - " . $exception->getMessage());
                    }
                }
            }

            if (trim($invoice_url) != "" && !empty($invoice_url)) {
                try {
                    $invoiceDate = "";
                    if ($this->exts->querySelector("div#bps-mgmt-payment-history-last-invoice span > font") != null) {
                        $tempInvoiceDate = trim($this->exts->querySelector("div#bps-mgmt-payment-history-last-invoice span > font")->getText());
                        $tempArr = explode(":", $tempInvoiceDate);
                        $invoiceDate = trim($tempArr[count($tempArr) - 1]);
                    } else {
                        if ($this->exts->querySelector("div#bps-mgmt-payment-history-last-invoice") != null) {
                            $tempInvoiceDate = trim($this->exts->querySelector("div#bps-mgmt-payment-history-last-invoice")->getText());
                            $tempArr = explode(":", $tempInvoiceDate);
                            $invoiceDate = trim($tempArr[count($tempArr) - 1]);
                            $this->exts->log("prime invoice date - " . $invoiceDate);

                            $tempArr = explode(" ", $invoiceDate);
                            $invoiceDate = trim($tempArr[0]) . " " . trim($tempArr[1]) . " " . trim($tempArr[2]);
                            $this->exts->log("prime invoice date - " . $invoiceDate);
                        }
                    }
                    $this->exts->log("prime invoice date - " . $invoiceDate);

                    if (trim($invoiceDate) != "") {
                        try {
                            $parsed_invoice_date = $this->exts->parse_date($invoiceDate);
                            $invoiceDate = $parsed_invoice_date;
                        } catch (\Exception $exception) {
                            $this->exts->log('ERROR in parsing Date - ' . $invoiceDate);
                            $parsed_invoice_date = '';
                        }
                        if (!empty($parsed_invoice_date) && $this->start_date != "" && !empty($this->start_date)) {
                            if ($this->start_date > strtotime($parsed_invoice_date)) {
                                return;
                            }
                        }
                    }
                    $this->exts->log("prime invoice date - " . $invoiceDate);

                    $filename = "";
                    if (trim($invoiceDate) != "") {
                        $filename = !empty($invoiceDate) ? trim($invoiceDate) . ".pdf" : '';
                    }
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
        } else if ($this->exts->querySelector("a[href*=\"/gp/primecentral?ref_=ab_bps_acq_pip\"]") != null) {
            $this->exts->click_element("a[href*=\"/gp/primecentral?ref_=ab_bps_acq_pip\"]");
            sleep(10);

            $invoice_url = "";
            if ($this->exts->querySelector("a#viewPaymentHistoryLink") != null) {
                try {
                    $invoice_url = $this->exts->querySelector("a#viewPaymentHistoryLink")->getAttribute("href");
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                    if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.fr") === false && stripos($invoice_url, "https://") === false) {
                        $invoice_url = "https://www.amazon.fr" . $invoice_url;
                    }
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                } catch (\Exception $exception) {
                    $this->exts->log("Getting business prime invoice 1st option - " . $exception->getMessage());
                }
            } else {
                if ($this->exts->querySelector("a[href*=\"/your-account/order-summary.html/ref=primecentral\"]") != null) {
                    try {
                        $invoice_url = $this->exts->querySelector("a[href*=\"/your-account/order-summary.html/ref=primecentral\"]")->getAttribute("href");
                        $this->exts->log("prime invoice URL - " . $invoice_url);
                        if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.fr") === false && stripos($invoice_url, "https://") === false) {
                            $invoice_url = "https://www.amazon.fr" . $invoice_url;
                        }
                        $this->exts->log("prime invoice URL - " . $invoice_url);
                    } catch (\Exception $exception) {
                        $this->exts->log("Getting business prime invoice 2nd option - " . $exception->getMessage());
                    }
                } else {
                    if ($this->exts->exists('a[href*="/mc/receipt?transaction="]')) {
                        try {
                            $invoice_url = $this->exts->querySelector('a[href*="/mc/receipt?transaction="]')->getAttribute("href");
                            $this->exts->log("prime invoice URL - " . $invoice_url);
                            if (trim($invoice_url) == '') {
                                $this->exts->click_by_xdotool('.mcx-nav__menu > .mcx-menu > .mcx-menu__list .mcx-menu__list-item:nth-child(2) a.mcx-menu__content');
                                sleep(1);

                                $invoice_url = $this->exts->querySelector('a[href*="/mc/receipt?transaction="]')->getAttribute("href");
                                $this->exts->log("prime invoice URL - " . $invoice_url);
                            }
                            if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.fr") === false && stripos($invoice_url, "https://") === false) {
                                $invoice_url = "https://www.amazon.fr" . $invoice_url;
                            }
                            $this->exts->log("prime invoice URL - " . $invoice_url);
                        } catch (\Exception $exception) {
                            $this->exts->log("Getting business prime invoice 2nd option - " . $exception->getMessage());
                        }
                    }
                }
            }

            if (trim($invoice_url) != "" && !empty($invoice_url)) {
                try {
                    $invoiceDate = "";
                    if ($this->exts->querySelector("div#paymentHistorySettingsDiv div.membershipSettingsInfoItemDiv:nth-child(2)") != null) {
                        $tempInvoiceDate = trim($this->exts->querySelector("div#paymentHistorySettingsDiv div.membershipSettingsInfoItemDiv:nth-child(2)")->getText());
                        $tempArr = explode(":", $tempInvoiceDate);
                        $invoiceDate = trim($tempArr[count($tempArr) - 1]);
                    } else {
                        if ($this->exts->exists('.mcx-nav__menu > .mcx-menu > .mcx-menu__list .mcx-menu__list-item:nth-child(2) a.mcx-menu__content .mcx-menu-item__heading')) {
                            $invoiceDate = trim($this->exts->querySelectorAll('.mcx-nav__menu > .mcx-menu > .mcx-menu__list .mcx-menu__list-item:nth-child(2) a.mcx-menu__content .mcx-menu-item__heading')[0]->getText());
                        }
                    }
                    $this->exts->log("prime invoice date - " . $invoiceDate);

                    if (trim($invoiceDate) != "") {
                        try {
                            $parsed_invoice_date = $this->exts->parse_date($invoiceDate);
                            $invoiceDate = $parsed_invoice_date;
                        } catch (\Exception $exception) {
                            $this->exts->log('ERROR in parsing Date - ' . $invoiceDate);
                            $parsed_invoice_date = '';
                        }
                        if (!empty($parsed_invoice_date) && $this->start_date != "" && !empty($this->start_date)) {
                            if ($this->start_date > strtotime($parsed_invoice_date)) {
                                return;
                            }
                        }
                    }
                    $this->exts->log("prime invoice date - " . $invoiceDate);

                    $filename = "";
                    if (trim($invoiceDate) != "") {
                        $filename = !empty($invoiceDate) ? trim($invoiceDate) . ".pdf" : '';
                    }

                    $currentUrl = $this->exts->getUrl();
                    $this->exts->openUrl($invoice_url);
                    sleep(5);

                    if (stripos($this->exts->getUrl(), "amazon.fr/ap/signin") !== false) {
                        $this->fillForm(0);
                        sleep(4);

                        $this->checkFillTwoFactor();
                        sleep(4);
                        if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                            $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                            sleep(2);
                        }
                        if ($this->checkLogin()) {
                            $this->exts->openUrl($invoice_url);
                            sleep(5);
                        }
                    }

                    if ($this->checkLogin()) {
                        $downloaded_file = $this->exts->download_current($filename, 5);
                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->new_invoice("", $invoiceDate, "", $downloaded_file);
                        }
                    }
                    $this->exts->openUrl($currentUrl);
                    sleep(5);
                } catch (\Exception $exception) {
                    $this->exts->log("Downloading prime invoice - " . $exception->getMessage());
                }
            } else {
                $this->exts->log("No Business Prime Invoices");
                $this->exts->success();
            }
        } else if ($this->exts->exists('a[href*="/businessprime/manage/"], .membership-settings-cta a[href*="/businessprime/manage"], section.management a[href*="/businessprime/manage"]')) {
            $this->exts->click_by_xdotool('a[href*="/businessprime/manage/"], .membership-settings-cta a[href*="/businessprime/manage"], section.management a[href*="/businessprime/manage"]');
            sleep(10);

            if ($this->exts->exists('a[href*="/bb/benefits/all-invoices?ref_"]')) {
                $this->exts->click_by_xdotool('a[href*="/bb/benefits/all-invoices?ref_"]');
            } else if ($this->exts->exists('a#bps-mgmt-payment-history-all-invoices-link')) {
                $this->exts->click_by_xdotool('a#bps-mgmt-payment-history-all-invoices-link');
            } else if ($this->exts->exists('a[href="/businessprime/manage/paymenthistory"]')) {
                $this->exts->click_element('a[href="/businessprime/manage/paymenthistory"]');
            } else {
                $this->exts->click_by_xdotool('#bps-mgmt-payment-history a');
            }
            sleep(10);

            $bbstrar_invoices = array();
            $rows = $this->exts->querySelectorAll('.a-box .a-row, table tbody tr');
            if (count($rows) > 1) {
                foreach ($rows as $row) {
                    if ($this->exts->querySelector('a[href*="/documents/download/"][href*="/Invoice.pdf"]', $row) != null) {
                        $cols = $this->exts->querySelectorAll('.a-column', $row);
                        if (count($cols) > 4) {
                            $invoice_date = trim($cols[2]->getText());
                            $this->exts->log('Invoice Date - ' . $invoice_date);

                            try {
                                $parse_date = $this->exts->parse_date($invoice_date);
                            } catch (\Exception $exception) {
                                $this->exts->log('ERROR in parsing Date - ' . $invoice_date);
                                $parsed_date = '';
                            }
                            $this->exts->log('Parsed Invoice Date - ' . $parse_date);
                            if (!empty($parse_date) && $parse_date != null) $invoice_date = $parse_date;

                            if ($this->start_date != "" && !empty($this->start_date) && !empty($invoice_date)) {
                                if ($this->start_date > strtotime($invoice_date)) {
                                    break;
                                }
                            }

                            $invoice_amount = trim($cols[3]->getText());
                            $this->exts->log('Invoice Amount - ' . $invoice_amount);

                            $invoice_url = $this->exts->extract('a[href*="/documents/download/"][href*="/Invoice.pdf"]', $row, 'href');
                            $this->exts->log('URL - ' . $invoice_url);

                            $bbstrar_invoices[] = array(
                                'invoice_date' => $invoice_date,
                                'invoice_amount' => $invoice_amount,
                                'invoice_url' => $invoice_url
                            );
                        }
                    } else if ($this->exts->querySelector('a[href*="/businessprime/manage/paymenthistory"]', $row) != null) {
                        $cols = $this->exts->querySelectorAll('td', $row);
                        if (count($cols) > 4) {
                            $invoice_date = trim($cols[2]->getText());
                            $this->exts->log('Invoice Date - ' . $invoice_date);

                            try {
                                $parse_date = $this->exts->parse_date($invoice_date);
                            } catch (\Exception $exception) {
                                $this->exts->log('ERROR in parsing Date - ' . $invoice_date);
                                $parsed_date = '';
                            }
                            $this->exts->log('Parsed Invoice Date - ' . $parse_date);
                            if (!empty($parse_date) && $parse_date != null) $invoice_date = $parse_date;
                            if ($this->start_date != "" && !empty($this->start_date) && !empty($invoice_date)) {
                                if ($this->start_date > strtotime($invoice_date)) {
                                    break;
                                }
                            }

                            $invoice_amount = trim($cols[3]->getText());
                            $this->exts->log('Invoice Amount - ' . $invoice_amount);

                            $invoice_url = $this->exts->extract('a[href*="/businessprime/manage/paymenthistory"]', $row, 'href');
                            $this->exts->log('URL - ' . $invoice_url);

                            $download_btn = $this->exts->querySelector('a[href*="/businessprime/manage/paymenthistory"]', $row);
                            try {
                                $this->exts->click_element($download_btn);
                            } catch (\Exception $exception) {
                                $this->exts->execute_javascript('arguments[0].click();', [$download_btn]);
                            }
                            sleep(10);
                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf', '');
                            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                $this->exts->new_invoice("", $invoice_date, $invoice_amount, $downloaded_file);
                            }
                        }
                    }
                }

                if (count($bbstrar_invoices) > 0) {
                    foreach ($bbstrar_invoices as $bbstrar_invoice) {
                        $downloaded_file = $this->exts->direct_download($bbstrar_invoice['invoice_url'], 'pdf');
                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->new_invoice("", $bbstrar_invoice['invoice_date'], $bbstrar_invoice['invoice_amount'], $downloaded_file);
                        }
                    }
                }
            }
        } else if ($this->exts->exists('a[href="/businessprime/manage/paymenthistory"]')) {
            $this->exts->click_by_xdotool('a[href="/businessprime/manage/paymenthistory"]');
            sleep(10);

            $bbstrar_invoices = array();
            $rows = $this->exts->querySelectorAll('.a-box .a-row, table tbody tr');
            if (count($rows) > 1) {
                foreach ($rows as $row) {
                    if ($this->exts->querySelector('a[href*="/documents/download/"][href*="/Invoice.pdf"]', $row) != null) {
                        $cols = $this->exts->querySelectorAll('.a-column', $row);
                        if (count($cols) > 4) {
                            $invoice_date = trim($cols[2]->getText());
                            $this->exts->log('Invoice Date - ' . $invoice_date);

                            try {
                                $parse_date = $this->exts->parse_date($invoice_date);
                            } catch (\Exception $exception) {
                                $this->exts->log('ERROR in parsing Date - ' . $invoice_date);
                                $parsed_date = '';
                            }
                            $this->exts->log('Parsed Invoice Date - ' . $parse_date);
                            if (!empty($parse_date) && $parse_date != null) $invoice_date = $parse_date;
                            if ($this->start_date != "" && !empty($this->start_date) && !empty($invoice_date)) {
                                if ($this->start_date > strtotime($invoice_date)) {
                                    break;
                                }
                            }

                            $invoice_amount = trim($cols[3]->getText());
                            $this->exts->log('Invoice Amount - ' . $invoice_amount);

                            $invoice_url = $this->exts->extract('a[href*="/documents/download/"][href*="/Invoice.pdf"]', $row, 'href');
                            $this->exts->log('URL - ' . $invoice_url);

                            $bbstrar_invoices[] = array(
                                'invoice_date' => $invoice_date,
                                'invoice_amount' => $invoice_amount,
                                'invoice_url' => $invoice_url
                            );
                        }
                    } else if ($this->exts->querySelector('a[href*="/businessprime/manage/paymenthistory"]', $row) != null) {
                        $cols = $this->exts->querySelectorAll('td', $row);
                        if (count($cols) > 4) {
                            $invoice_date = trim($cols[2]->getText());
                            $this->exts->log('Invoice Date - ' . $invoice_date);

                            try {
                                $parse_date = $this->exts->parse_date($invoice_date);
                            } catch (\Exception $exception) {
                                $this->exts->log('ERROR in parsing Date - ' . $invoice_date);
                                $parsed_date = '';
                            }
                            $this->exts->log('Parsed Invoice Date - ' . $parse_date);
                            if (!empty($parse_date) && $parse_date != null) $invoice_date = $parse_date;
                            if ($this->start_date != "" && !empty($this->start_date) && !empty($invoice_date)) {
                                if ($this->start_date > strtotime($invoice_date)) {
                                    break;
                                }
                            }

                            $invoice_amount = trim($cols[3]->getText());
                            $this->exts->log('Invoice Amount - ' . $invoice_amount);

                            $invoice_url = $this->exts->extract('a[href*="/businessprime/manage/paymenthistory"]', $row, 'href');
                            $this->exts->log('URL - ' . $invoice_url);

                            $download_btn = $this->exts->querySelector('a[href*="/businessprime/manage/paymenthistory"]', $row);
                            try {
                                $this->exts->click_element($download_btn);
                            } catch (\Exception $exception) {
                                $this->exts->execute_javascript('arguments[0].click();', [$download_btn]);
                            }
                            sleep(10);
                            $this->exts->wait_and_check_download('pdf');
                            $downloaded_file = $this->exts->find_saved_file('pdf', '');
                            if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                $this->exts->new_invoice("", $invoice_date, $invoice_amount, $downloaded_file);
                            }
                        }
                    }
                }

                if (count($bbstrar_invoices) > 0) {
                    foreach ($bbstrar_invoices as $bbstrar_invoice) {
                        $downloaded_file = $this->exts->direct_download($bbstrar_invoice['invoice_url'], 'pdf');
                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->new_invoice("", $bbstrar_invoice['invoice_date'], $bbstrar_invoice['invoice_amount'], $downloaded_file);
                        }
                    }
                }
            }
        } else {
            $this->exts->log("No Business Prime Invoices");
            $this->exts->success();
        }
    }

    public function downloadPrimeInvoice()
    {
        $this->exts->update_process_lock();
        //Check if user is having private prime invoices
        $this->exts->openUrl('https://www.amazon.fr/mc?_encoding=UTF8&ref_=ya_d_c_prime');
        sleep(5);

        if (stripos($this->exts->getUrl(), "/ap/signin") !== false) {
            $this->fillForm(0);
            // retry if captcha showed
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                $this->fillForm(0);
            }
            $this->checkFillTwoFactor();
            sleep(4);
            if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                sleep(2);
            }
        } else {
            $this->checkFillTwoFactor();
        }

        if ($this->exts->exists('form[action*="cookieprefs"] #sp-cc-accept')) {
            $this->exts->click_by_xdotool('form[action*="cookieprefs"] #sp-cc-accept');
            sleep(1);
        }

        if ($this->exts->exists('a[href="/mc/payments"]')) {
            $this->exts->openUrl($this->exts->querySelector('a[href="/mc/payments"]')->getAttribute('href'));
        }
        $this->exts->waitTillPresent('a[href*="/mc/receipt?transaction="]');
        $this->exts->capture("private-prime");
        if ($this->exts->exists('a[href*="/mc/receipt?transaction="]')) {
            $invoices = $this->exts->querySelectorAll('a[href*="/mc/receipt?transaction="]');
            $invoice_url = [];
            foreach ($invoices as $invoice) {
                $invoice_url[] = $invoice->getAttribute('href');
            }

            foreach ($invoice_url as $url) {
                $this->exts->log('Prime Invoice URL - ' . $url);

                // Open New window To process Invoice
                // Call Processing function to process current page invoices
                $this->exts->openNewTab($url);
                sleep(2);

                if (stripos($this->exts->getUrl(), "amazon.fr/ap/signin") !== false) {
                    $this->fillForm(0);
                    sleep(4);

                    $this->checkFillTwoFactor();
                    sleep(4);
                    if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                        $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                        sleep(2);
                    }
                }

                if (stripos($this->exts->getUrl(), "amazon.fr/ap/signin") === false && stripos($this->exts->getUrl(), "print=1") !== false) {
                    $currentUrl = $this->exts->getUrl();
                    $this->exts->log($currentUrl);
                    if (stripos($currentUrl, 'orderID=') !== false) {
                        $tempArr = explode("orderID=", $currentUrl);
                        $tempArr = explode('&', end($tempArr));
                        $orderNum = trim($tempArr[0]);
                        $filename = !empty($orderNum) ? $orderNum . '.pdf' : '';
                    } else {
                        $orderNum = trim(end(explode("#", $this->exts->extract('b.h1'))));
                        $filename = !empty($orderNum) ? $orderNum . '.pdf' : '';
                    }
                    sleep(5);
                    $downloaded_file = $this->exts->download_current($filename, 5);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($orderNum, '', '', $filename);
                    }
                }

                // Close new window
                $this->exts->switchToInitTab();
                sleep(2);
                $this->exts->closeAllTabsButThis();
            }
        } else {
            $this->exts->log("No Prime Invoices");
            $this->exts->success();
        }
    }

    public function triggerMsgInvoice()
    {
        if ((int)@$this->msg_invoice_triggerd == 0) {
            $this->msg_invoice_triggerd = 1;
            if ((int)@$this->download_invoice_from_message == 1 && empty($this->only_years)) {
                $this->msgTimeLimitReached = 0;
                $this->processMSInvoice(0);
            }
        }
    }

    public function processMSInvoice($currentMessagePage)
    {
        $this->exts->update_process_lock();
        if ((int)@$currentMessagePage == 0) {
            $this->exts->openUrl($this->messagePageUrl);
            sleep(5);
        }

        if (stripos($this->exts->getUrl(), "/ap/signin") !== false) {
            $this->fillForm(0);
            // retry if captcha showed
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                $this->fillForm(0);
            }
            $this->checkFillTwoFactor();
            sleep(4);
            if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                sleep(2);
            }
        } else {
            $this->checkFillTwoFactor();
        }
        $this->exts->openUrl($this->messagePageUrl);
        sleep(5);
        $this->exts->waitTillPresent('li[data-a-tab-name="inbox_bsm_tab"]');
        $this->exts->click_element('li[data-a-tab-name="inbox_bsm_tab"]');
        $inv_msgs = array();
        $invMsgRows = $this->exts->queryXpathAll("//*[@id='inbox_bsm_tab_content']/tbody/tr");
        $this->exts->log("Message Rows on page - " . $currentMessagePage . " - " . count($invMsgRows));
        if (count($invMsgRows) == 0) {
            if ((int)@$currentMessagePage == 0) {
                $this->exts->openUrl($this->alt_messagePageUrl);
                sleep(5);
            }

            if (stripos($this->exts->getUrl(), "/ap/signin") !== false) {
                $this->fillForm(0);
                // retry if captcha showed
                if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                    $this->fillForm(0);
                }
                $this->checkFillTwoFactor();
                sleep(4);
                if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                    $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                    sleep(2);
                }
            } else {
                $this->checkFillTwoFactor();
            }

            if ((int)@$currentMessagePage == 0 && $this->exts->querySelector("div#inbox-page ul.a-tabs.a-declarative li[data-a-tab-name=\"inbox_bsm_tab\"] a") != null) {
                $this->exts->click_by_xdotool("div#inbox-page ul.a-tabs.a-declarative li[data-a-tab-name=\"inbox_bsm_tab\"] a");
            }
            sleep(10);

            $invMsgRows = $this->exts->queryXpathAll("//*[@id='inbox_bsm_tab_content']/tbody/tr");
            $this->exts->log("Message Rows on page - " . $currentMessagePage . " - " . count($invMsgRows));
        }
        if (count($invMsgRows) > 0) {
            $msgTimeDiff = time() - (1440 * 60 * 60);  //60*24 = 1440
            foreach ($invMsgRows as $key => $invMsgRow) {
                $mj = $key + 1;
                $invMsgCols = $this->exts->queryXpathAll("//*[@id='inbox_bsm_tab_content']/tbody/tr[$mj]/td");
                $invMsgColImg = $this->exts->queryXpathAll("//*[@id='inbox_bsm_tab_content']/tbody/tr[$mj]//span/img");

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
                                'msg_thread_id' => $invMsgCols[0]->getAttribute("threadid"),
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
            //Call Processing function to process current page invoices
            $this->startCurrentPageMessageDownload($inv_msgs);
            sleep(2);
        } else {
            $this->startCurrentPageMessageDownloadNew();
        }

        if (count($invMsgRows) > 0) {
            if ($this->exts->querySelector("#inbox_button_next_page input[type=\"submit\"]") != null && (int)@$this->msgTimeLimitReached == 0) {
                if ($this->exts->querySelector(".a-button-disabled#inbox_button_next_page input[type=\"submit\"]") == null) {
                    $this->exts->click_element("#inbox_button_next_page input[type=\"submit\"]");
                    $currentMessagePage++;
                    sleep(10);

                    $this->processMSInvoice($currentMessagePage);
                }
            }
        }
    }
    public function startCurrentPageMessageDownloadNew()
    {
        $this->exts->waitTillPresent('table#inbox_bsm_tab_content tbody tr', 30);
        $this->exts->capture("4-invoices-page");

        $rows = $this->exts->querySelectorAll('table#inbox_bsm_tab_content tbody tr');
        for ($i = 1; $i <= count($rows); $i++) {
            $this->exts->waitTillPresent('table#inbox_bsm_tab_content tbody tr', 30);
            $row = $this->exts->querySelector('table#inbox_bsm_tab_content tbody tr:nth-child(' . $i . ')');
            $this->exts->click_element($row);
            sleep(3);
            $this->exts->switchToNewestActiveTab();
            if (stripos($this->exts->getUrl(), "/ap/signin") !== false) {
                $this->fillForm(0);
                // retry if captcha showed
                if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
                    $this->fillForm(0);
                }
                $this->checkFillTwoFactor();
                sleep(4);
                if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
                    $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
                    sleep(2);
                }
            } else {
                $this->checkFillTwoFactor();
            }
            $this->exts->waitTillPresent('div.view-order', 30);
            $this->exts->click_element('div.view-order');
            $this->exts->waitTillPresent('span[data-action="a-popover"] a.a-popover-trigger', 30);
            if ($this->exts->querySelector('span[data-action="a-popover"] a.a-popover-trigger', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('span.order-date-invoice-item [dir]', $row);
                $invoiceAmount =  $this->exts->extract('div#od-subtotals div.a-row:last-child div.a-span-last', $row);
                $invoiceDate =  $this->exts->extract('span.order-date-invoice-item:first-child', $row);

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd. F Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $this->exts->click_element('span[data-action="a-popover"] a.a-popover-trigger');
                sleep(3);



                if (!$this->invoice_overview_exists($invoiceName)) {
                    $downloaded_file = $this->exts->click_and_download($this->exts->querySelector("div.a-popover-content a[href*='invoice.pdf']"), 'pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }

                $this->exts->switchToInitTab();
                sleep(2);
                $this->exts->closeAllTabsButThis();
            }
        }
    }
    public function startCurrentPageMessageDownload($inv_msgs)
    {
        foreach ($inv_msgs as $inv_msg) {
            $this->exts->log("Message ID - " . $inv_msg['msg_id']);
            $this->exts->log("Message Timestamp - " . $inv_msg['msg_time']);
            $this->exts->log("Message Thread ID - " . $inv_msg['msg_thread_id']);

            // Open New window To process Invoice

            if (trim($inv_msg['msg_thread_id']) != "") {
                $msgUrl = "https://www.amazon.fr/gp/message?ref_=ya_d_l_msg_center#!/detail/" . $inv_msg['msg_id'] . "/" . $inv_msg['msg_thread_id'] . "/bsm/" . $inv_msg['msg_time'] . "/inbox";
            } else {
                $msgUrl = "https://www.amazon.fr/gp/message?ref_=ya_d_l_msg_center#!/detail/" . $inv_msg['msg_id'] . "/bsm/" . $inv_msg['msg_time'] . "/inbox";
            }

            $this->exts->log("Message URL - " . $msgUrl);
            $this->exts->openNewTab($msgUrl);
            sleep(5);

            if ($this->exts->querySelector('.a-ordered-list a.a-link-normal') == null) {
                if (trim($inv_msg['msg_thread_id']) != "") {
                    $msgUrl = "https://www.amazon.fr/gp/message?ref_=ya_d_l_msg_center#!/detail/" . $inv_msg['msg_id'] . "/" . $inv_msg['msg_thread_id'] . "//bsm/" . $inv_msg['msg_time'] . "/inbox";
                } else {
                    $msgUrl = "https://www.amazon.fr/gp/message?ref_=ya_d_l_msg_center#!/detail/" . $inv_msg['msg_id'] . "//bsm/" . $inv_msg['msg_time'] . "/inbox";
                }

                $this->exts->log("Message URL - " . $msgUrl);

                $this->exts->openUrl($msgUrl);
                sleep(5);
            }

            if ($this->exts->querySelector('.a-ordered-list a.a-link-normal') != null) {
                $links = $this->exts->querySelectorAll(".a-ordered-list a.a-link-normal");
                foreach ($links as $link_item) {
                    $invoice_data = array();
                    $invoice_name = trim($link_item->getText());

                    if (stripos($invoice_name, ".pdf") !== false) {
                        $order_number = "";

                        $ordItems = $this->exts->querySelectorAll("div#detail-page .a-box-inner a[href*=\"summary/edit.html\"]");
                        if (count($ordItems) > 0) {
                            $order_number = trim($ordItems[0]->getText());
                        }

                        $invoice_data = array(
                            'invoice_name' => $invoice_name,
                            'invoice_url' => $link_item->getAttribute("href"),
                            'order_number' => $order_number
                        );
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
                                        $filename = !empty($invoice_name) ? $invoice_name . ".pdf" : '';

                                        $invoice_url = $invoice_data['invoice_url'];
                                        if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.fr") === false && stripos($invoice_url, "https://") === false) {
                                            $invoice_url = "https://www.amazon.fr" . $invoice_url;
                                        }

                                        $downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                            $invoice_date = date("Y-m-d", $inv_msg['msg_time'] / 1000);
                                            $invoice_note = "Marketplace Seller - " . $invoice_data['order_number'];

                                            if ($this->invoice_overview_exists($invoice_name)) {
                                                $invoice_name = $invoice_name . '-1';
                                            }
                                            $this->exts->new_invoice($invoice_name, $invoice_date, "", $filename, 1, $invoice_note, 0, '', array(
                                                'tags' => (int)@$this->auto_tagging == 1 && !empty($this->marketplace_invoice_tags) ? $this->marketplace_invoice_tags : ''
                                            ));

                                            $this->exts->sendRequestEx($invoice_name . ":::" . $invoice_note, "===NOTE-DATA===");
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

            // close new tab too avoid too much tabs
            sleep(5);
            $this->exts->switchToInitTab();
            sleep(2);
            $this->exts->closeAllTabsButThis();
        }
    }

    public function invoice_overview_exists($invoice_number)
    {
        $this->exts->update_process_lock();

        if (!empty($invoice_number) && !empty($this->exts->config_array['overview_invoices'])) {
            return in_array($invoice_number, $this->exts->config_array['overview_invoices']);
        }
        return false;
    }

    public function download_procurment_document($pageNum = 1)
    {
        $this->exts->update_process_lock();
        //Added do /while and remove calling recursive function because after 256 php stop recurssion.
        do {
            if ($pageNum > 1) {
                if ((int)$this->restrictPages == 0) {
                    if ($this->exts->exists('.report-table-footer button[data-testid="next-button"]') && !$this->exts->exists('.report-table-footer button[data-testid="next-button"][disabled]')) {
                        $this->exts->click_by_xdotool('.report-table-footer button[data-testid="next-button"]');
                        sleep(15);
                        $pageNum++;
                    } else {
                        break;
                    }
                } else {
                    if ($this->exts->exists('.report-table-footer button[data-testid="next-button"]') && !$this->exts->exists('.report-table-footer button[data-testid="next-button"][disabled]') && $pageNum < 50) {
                        $this->exts->click_by_xdotool('.report-table-footer button[data-testid="next-button"]');
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
            $rows = $this->exts->querySelectorAll('.report-table .column:nth-child(3) [class*="cell-row-"]');
            foreach ($rows as $key => $row) {
                $row =  $this->exts->querySelectorAll('.report-table .column:nth-child(3) [class*="cell-row-"]')[$key];
                $linkBtn = $this->exts->querySelector('[data-action="a-popover"] a', $row);
                $datecell = $this->exts->querySelectorAll('.report-table .column:nth-child(2) [class*="cell-row-"]')[$key];
                if ($linkBtn != null) {
                    $orderNum = trim($linkBtn->getText());
                    $this->exts->log('Order - ' . $orderNum);
                    try {
                        $this->exts->click_element($linkBtn);
                    } catch (\Exception $exception) {
                        $this->exts->execute_javascript('arguments[0].click();', [$linkBtn]);
                    }
                    sleep(1);


                    if ($this->exts->exists('.a-popover .a-popover-content a')) {
                        $linkElements = $this->exts->querySelectorAll('.a-popover .a-popover-content a');
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
                            $invoice_date = $datecell->getText();
                            $this->exts->log('Invoice Date - ' . $invoice_date);
                            try {
                                $parse_date = $this->exts->parse_date($invoice_date);
                            } catch (\Exception $exception) {
                                $this->exts->log('ERROR in parsing Date - ' . $invoice_date);
                                $parse_date = '';
                            }
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
                                if (trim($orderNum) !== '' && !$this->exts->invoice_exists($orderNum) && !$this->invoice_overview_exists($orderNum)) {
                                    $invoice_name = $orderNum;
                                    $fileName = !empty($orderNum) ? $orderNum . '.pdf' : '';

                                    // Open New window To process Invoice
                                    $this->exts->openNewTab($downloadLink);

                                    // Call Processing function to process current page invoices
                                    // $this->exts->openUrl($downloadLink);
                                    sleep(2);

                                    if (stripos($this->exts->getUrl(), "amazon.fr/ap/signin") !== false) {
                                        $this->fillForm(0);
                                        sleep(4);
                                    }
                                    sleep(2);

                                    if (stripos($this->exts->getUrl(), "amazon.fr/ap/signin") === false && stripos($this->exts->getUrl(), "/print.html") !== false) {
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
                                    } else if (stripos($this->exts->getUrl(), "amazon.fr/ap/signin") === false && (stripos($this->exts->getUrl(), "order-document.pdf") !== false || stripos($this->exts->getUrl(), ".pdf") !== false)) {
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
                                        $this->exts->log('Current URL - ' . $this->exts->getUrl());
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
                                        $this->exts->click_by_xdotool('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]');
                                        sleep(2);
                                    }
                                    $this->accept_cookies();

                                    // Close new window
                                    $this->exts->switchToInitTab();
                                    sleep(2);
                                    $this->exts->closeAllTabsButThis();
                                } else {
                                    $this->exts->log('Already Invoice Exists - ' . $orderNum);
                                }
                            } else {
                                //I am opening this becasue sometime download link gives technical error.
                                $this->exts->openNewTab($this->baseUrl);

                                // $this->exts->openUrl($this->baseUrl);
                                sleep(2);
                                if ($this->exts->exists('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]')) {
                                    $this->exts->click_by_xdotool('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]');
                                    sleep(2);
                                }
                                $this->accept_cookies();

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

                                $this->exts->switchToInitTab();
                                sleep(2);
                                $this->exts->closeAllTabsButThis();
                            }
                        }
                    } else {
                        $this->exts->log('No Invoice - ' . $orderNum);
                    }
                }

                $popups = $this->exts->querySelectorAll('.a-popover.a-popover-no-header.a-arrow-right');
                if (count($popups) > 0) {
                    $this->exts->execute_javascript("
                var popups = document.querySelectorAll(\".a-popover.a-popover-no-header.a-arrow-right\");
                for(var i=0; i<popups.length; i++) {
                    popups[i].remove();
                }
            ");
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
