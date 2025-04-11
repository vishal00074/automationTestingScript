<?php // remove undefined variable support_restart  updated check_login_failed_selector i have migrated the script and trigger loginFailedConfirmed in case incorrect email and password and invalid otp

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
    
    public $baseUrl = "https://www.amazon.es";
    public $orderPageUrl = "https://www.amazon.es/gp/css/order-history/ref=nav_youraccount_orders";
    public $messagePageUrl = "https://www.amazon.es/gp/message?e=UTF8&cl=1&ref_=ya_d_l_msg_center#!/inbox";
    public $businessPrimeUrl = "https://www.amazon.es/businessprimeversand";
    public $loginLinkPrim = "div[id=\"nav-flyout-ya-signin\"] a";
    public $loginLinkSec = "div[id=\"nav-signin-tooltip\"] a";
    public $loginLinkThr = "div#nav-tools a#nav-link-yourAccount";
    public $username_selector = 'input[autocomplete="username"]';
    public $password_selector = "#ap_password";
    public $submit_button_selector = "#signInSubmit";
    public $continue_button_selector = "#continue";
    public $logout_link = 'a#nav-item-signout, #nav-main a[href*="/sign-out.html"]';
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
    public $invalid_filename_keywords = array('agb', 'terms', 'datenschutz', 'privacy', 'rechnungsbeilage', 'informationsblatt', 'gesetzliche', 'retouren', 'widerruf', 'allgemeine gesch', 'mfb-buchung', 'informationen zu zahlung', 'nachvertragliche', 'retourenschein', 'allgemeine_gesch', 'rcklieferschein');
    public $invalid_filename_pattern = '';
    public $isNoInvoice = true;
    public $check_login_failed_selector = 'div#auth-error-message-box div.a-alert-content';

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
            $this->start_page = isset($this->exts->config_array["start_page"]) ? $this->exts->config_array["start_page"] : '';
            $this->last_invoice_date = isset($this->exts->config_array["last_invoice_date"]) ? $this->exts->config_array["last_invoice_date"] : '';
            $this->procurment_report = isset($this->exts->config_array["procurment_report"]) ? (int)$this->exts->config_array["procurment_report"] : 0;

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

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(2);

            $this->exts->openUrl($this->baseUrl);
            sleep(5);
            $this->exts->capture("Home-page-with-cookie");

            $this->exts->openUrl($this->orderPageUrl);
            sleep(5);

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            }
        }

        if (!$isCookieLoginSuccess) {
            if ($this->exts->querySelector($this->loginLinkThr) != null) {
                $this->exts->log("Found Third Login Link!!");
                $this->exts->click_element($this->loginLinkThr);
            } else if ($this->exts->querySelector($this->loginLinkSec) != null) {
                $this->exts->log("Found Secondry Login Link!!");
                $this->exts->click_element($this->loginLinkSec);
            } else if ($this->exts->querySelector($this->loginLinkPrim) != null) {
                $this->exts->log("Found Primary Login Link!!");
                $this->exts->click_element($this->loginLinkPrim);
            } else {
                $this->exts->openUrl($this->orderPageUrl);
            }
            sleep(5);

            $this->fillForm(0);
            sleep(20);

            if ($this->exts->exists('[action="/errors/validateCaptcha"] img')) {
                $this->fillForm(0);
                sleep(20);
            }

            if ($this->exts->exists('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img')) {
                $this->fillForm(0);
                sleep(20);
            }
        }

        if (!$isCookieLoginSuccess) {
            if ($this->checkLogin()) {
                $this->exts->openUrl($this->orderPageUrl);
                sleep(5);

                $this->exts->capture("LoginSuccess");

                $this->processAfterLogin(0);
                $this->exts->success();
            } else {
                // Captcha and Two Factor Check
                if ($this->checkCaptcha() || stripos($this->exts->getUrl(), "/ap/cvf/request") !== false) {
                    $this->processImageCaptcha();
                }

                sleep(5);
                if ($this->checkLogin()) {
                    $this->exts->openUrl($this->orderPageUrl);
                    sleep(5);
                    $this->exts->capture("LoginSuccess");

                    $this->processAfterLogin(0);
                    $this->exts->success();
                } else {
                    $this->exts->log(__FUNCTION__ . '::Use login failed');
                    $this->exts->log('::URL login failure:: ' . $this->exts->getUrl());
                    $this->exts->capture("LoginFailed");

                    $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
                    $emailFailed = strtolower($this->exts->extract('div#auth-email-invalid-claim-alert div.a-alert-content'));

                    $this->exts->log(__FUNCTION__ . '::Email Failed text: ' . $emailFailed);
                    $this->exts->log(__FUNCTION__ . '::error text: ' . $error_text);
                    if (
                        stripos($emailFailed, strtolower('La dirección de correo electrónico o el número de teléfono móvil faltan o son inválidos. Corríjalo e inténtelo de nuevo.')) !== false ||
                        stripos($emailFailed, strtolower('The email address or mobile phone number is missing or invalid. Please correct it and try again.')) !== false
                    ) {
                        $this->exts->loginFailure(1);
                    } elseif (
                        stripos($error_text, strtolower('La contraseña no es correcta')) !== false ||
                        stripos($error_text, strtolower('The password is not correct')) !== false  ||
                        stripos($error_text, strtolower('El código que ha introducido no es válido. Vuelva a intentarlo.')) !== false ||
                        stripos($error_text, strtolower('The code you entered is invalid. Please try again.')) !== false
                    )
                        $this->exts->loginFailure(1);
                    else {
                        $this->exts->loginFailure();
                    }
                }
            }
        } else {
            $this->exts->openUrl($this->orderPageUrl);
            sleep(5);
            $this->exts->capture("LoginSuccess");

            $this->processAfterLogin(0);
            $this->exts->success();
        }
    }

    /**
     * Method to fill login form
     * @param Integer $count Number of times portal is retried.
     */
    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->log("Begin fillForm URL - " . $this->exts->getUrl());

        try {
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

            if ($this->login_tryout == 0) {
                if ($this->exts->querySelector($this->password_selector) != null || $this->exts->querySelector($this->username_selector) != null) {
                    $this->exts->capture("1-pre-login");
                    $formType = $this->exts->querySelector($this->password_selector);
                    if ($formType == null) {
                        $this->exts->log("Form with Username Only");
                        $this->exts->log("Enter Username");
                        $this->exts->moveToElementAndType($this->username_selector, $this->username);
                        sleep(2);

                        $this->exts->log("Username form button click");
                        $this->exts->moveToElementAndClick($this->continue_button_selector);
                        sleep(5);

                        if ($this->exts->exists('#ap_captcha_guess, #auth-captcha-guess')) {
                            $this->exts->processCaptcha('#auth-captcha-image-container img', '#ap_captcha_guess, #auth-captcha-guess');
                            sleep(15);
                        } else if ($this->exts->exists('[action="/errors/validateCaptcha"] img')) {
                            $this->exts->processCaptcha('[action="/errors/validateCaptcha"] img', '#captchacharacters');
                            sleep(2);
                            $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                            sleep(15);
                        } else if ($this->exts->exists('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img')) {
                            $this->exts->processCaptcha('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img', '#ap_captcha_guess, #auth-captcha-guess, #captchacharacters');
                            sleep(2);
                            $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                            sleep(15);
                        } else if ($this->exts->exists('div#auth-error-message-box')) {
                            $this->exts->loginFailure(1);
                        }

                        $this->exts->log("Enter Password");
                        $this->exts->moveToElementAndType($this->password_selector, $this->password);
                        sleep(2);

                        if ($this->exts->querySelector($this->remember_me) != null) {
                            $checkboxElements = $this->exts->querySelectorAll($this->remember_me);
                            if (count($checkboxElements) > 0) {
                                $this->exts->log("Check remeber me");
                                $this->exts->click_element($checkboxElements[0]);
                            }
                        }

                        if ($this->exts->exists('#ap_captcha_guess, #auth-captcha-guess')) {
                            $this->exts->processCaptcha('#auth-captcha-image-container img', '#ap_captcha_guess, #auth-captcha-guess');
                            sleep(15);
                        } else if ($this->exts->exists('[action="/errors/validateCaptcha"] img')) {
                            $this->exts->processCaptcha('[action="/errors/validateCaptcha"] img', '#captchacharacters');
                            sleep(2);
                            $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                            sleep(15);
                        } else if ($this->exts->exists('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img')) {
                            $this->exts->processCaptcha('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img', '#ap_captcha_guess, #auth-captcha-guess, #captchacharacters');
                            sleep(2);
                            $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                            sleep(15);
                        }

                        $this->exts->capture("1-filled-login");
                        $this->exts->click_by_xdotool($this->submit_button_selector);
                    } else {
                        if ($this->exts->querySelector($this->remember_me) != null) {
                            $checkboxElements = $this->exts->querySelectorAll($this->remember_me);
                            if (count($checkboxElements) > 0) {
                                $this->exts->log("Check remeber me");
                                $this->exts->click_element($checkboxElements[0]);
                            }
                        }

                        if ($this->exts->querySelector($this->username_selector) != null && $this->exts->querySelector("input#ap_email[type=\"hidden\"]") == null) {
                            $this->exts->log("Enter Username");
                            $this->exts->querySelector($this->username_selector, $this->username);
                            sleep(2);
                        }

                        if ($this->exts->exists('#ap_captcha_guess, #auth-captcha-guess')) {
                            $this->exts->processCaptcha('#auth-captcha-image-container img', '#ap_captcha_guess, #auth-captcha-guess');
                            sleep(15);
                        } else if ($this->exts->exists('[action="/errors/validateCaptcha"] img')) {
                            $this->exts->processCaptcha('[action="/errors/validateCaptcha"] img', '#captchacharacters');
                            sleep(2);
                            $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                            sleep(15);
                        } else if ($this->exts->exists('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img')) {
                            $this->exts->processCaptcha('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img', '#ap_captcha_guess, #auth-captcha-guess, #captchacharacters');
                            sleep(2);
                            $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                            sleep(15);
                        }

                        if ($this->exts->querySelector($this->password_selector) != null) {
                            $this->exts->log("Enter Password");
                            $this->exts->moveToElementAndType($this->password_selector, $this->password);
                            sleep(2);
                        }

                        if ($this->exts->exists('#ap_captcha_guess, #auth-captcha-guess')) {
                            $this->exts->processCaptcha('#auth-captcha-image-container img', '#ap_captcha_guess, #auth-captcha-guess');
                            sleep(15);
                        } else if ($this->exts->exists('[action="/errors/validateCaptcha"] img')) {
                            $this->exts->processCaptcha('[action="/errors/validateCaptcha"] img', '#captchacharacters');
                            sleep(2);
                            $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                            sleep(15);
                        } else if ($this->exts->exists('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img')) {
                            $this->exts->processCaptcha('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img', '#ap_captcha_guess, #auth-captcha-guess, #captchacharacters');
                            sleep(2);
                            $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                            sleep(15);
                        }

                        $this->exts->capture("2-filled-login");
                        $this->exts->click_by_xdotool($this->submit_button_selector);
                    }
                    sleep(6);
                }

                if ($this->exts->exists('form[action="verify"] input#continue')) {
                    $this->exts->click_by_xdotool('form[action="verify"] input#continue');
                    sleep(15);

                    if ($this->exts->exists('input[name="code"]')) {
                        $this->checkFillTwoFactor('input[name="code"]', 'form[action="verify"] span[class*="verify"] [type="submit"]', 'form[action="verify"] div.a-row.a-spacing-none');
                    } else if ($this->exts->exists('#auth-mfa-otpcode')) {
                        $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'div.a-row.a-spacing-none');
                    }
                }

                $this->exts->log("END fillForm URL - " . $this->exts->getUrl());
            }
            if ($this->exts->exists('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link')) {
                $this->exts->click_by_xdotool('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link');
                sleep(15);
            }

            if ($this->exts->exists('div#auth-error-message-box div.a-alert-content')) {
                $this->exts->loginFailure(1);
            }

            $this->exts->capture('after-click-login');

            if ($this->exts->exists('input[name="verifyToken"]')) {
                $this->exts->click_by_xdotool('input[name="verifyToken"] ~ div input#continue');
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
            } else if ($this->exts->exists('input[name="otpCode"]')) {
                $this->checkFillTwoFactor('input[name="otpCode"]', 'input[name="mfaSubmit"]', 'form#auth-mfa-form div.a-box-inner > h1, form#auth-mfa-form div.a-box-inner > p');
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]')) {
                $this->exts->click_by_xdotool('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]:not(:checked)');
                sleep(2);
                $this->exts->click_by_xdotool('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]')) {
                $this->exts->click_by_xdotool('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]:not(:checked)');
                sleep(2);
                $this->exts->click_by_xdotool('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]')) {
                $this->exts->click_by_xdotool('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]:not(:checked)');
                sleep(2);
                $this->exts->click_by_xdotool('input#auth-send-code');
                sleep(15);

                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
            }

            if ($this->exts->urlContains('forgotpassword/reverification')) {
                $this->exts->account_not_ready();
            }

            if ($this->exts->exists('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link')) {
                $this->exts->click_by_xdotool('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link');
                sleep(15);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Catch fillForm URL - " . $this->exts->getUrl());
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector)
    {
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $total_2fa = count($this->exts->querySelectorAll($two_factor_message_selector));
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < $total_2fa; $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
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

                $this->exts->click_by_xdotool($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->querySelector($two_factor_selector) == null) {
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
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $total_2fa = count($this->exts->querySelectorAll($two_factor_message_selector));
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < $total_2fa; $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
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

                $this->exts->click_by_xdotool($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->querySelector($two_factor_selector) == null) {
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

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $total_2fa = count($this->exts->querySelectorAll($two_factor_message_selector));
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < $total_2fa; $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
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

                if ($this->exts->querySelector($two_factor_message_selector) == null && !$this->exts->exists('input[name="transactionApprovalStatus"]')) {
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

    /**
     * Method to check captcha form
     * return boolean true/false
     */
    public function checkCaptcha()
    {
        $this->exts->capture("check-captcha");

        $isCaptchaFound = false;
        if ($this->exts->querySelector("input#ap_captcha_guess") != null || $this->exts->querySelector("input#auth-captcha-guess") != null) {
            $this->login_tryout = (int)$this->login_tryout + 1;
            $isCaptchaFound = true;
        }

        return $isCaptchaFound;
    }

    /**
     * Method to check Two Factor form
     * return boolean true/false
     */
    public function checkMultiFactorAuth()
    {
        $this->exts->capture("check-two-factor");

        $isTwoFactorFound = false;
        if ($this->exts->querySelector("form#auth-mfa-form") != null) {
            $isTwoFactorFound = true;
        } else if ($this->exts->querySelector("form.cvf-widget-form[action=\"verify\"]") != null) {
            $isTwoFactorFound = true;
        }

        return $isTwoFactorFound;
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
            if ($this->exts->querySelector($this->logout_link) != null) {
                // $this->exts->waitForCssSelectorPresent($this->logout_link, function() {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                // 	$isLoggedIn = true;
                // }, function() {
                // 	$isLoggedIn = false;
                // }, 30);
                return true;
            } else {
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

    /**
     * Method to Process Image Catcha and Password field if present
     */
    public function processImageCaptcha()
    {
        $this->exts->log("Processing Image Captcha");
        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
        }
        $this->exts->processCaptcha("form[name=\"signIn\"]", "form[name=\"signIn\"] input[name=\"guess\"]");
        sleep(2);

        $this->exts->capture("filled-captcha");
        $this->exts->click_element($this->submit_button_selector);
        sleep(2);
    }

    public function processAfterLogin($count)
    {
        $this->exts->log("Begin processAfterLogin " . $count);
        if ($count == 0) {
            $this->exts->openUrl($this->orderPageUrl);
            sleep(2);
        }

        if (stripos($this->exts->getUrl(), "amazon.es/ap/signin") === false) {
            $isMultiAccount = count($this->exts->querySelectorAll("select[name=\"selectedB2BGroupKey\"] option")) > 1 ? true : false;
            $this->exts->log("isMultiAccount - " . $isMultiAccount);

            if (empty($this->last_state['stage']) || $this->last_state['stage'] == 'ORDER') {
                // keep current state of processing
                $this->exts->log('access order');
                $this->current_state['stage'] = 'ORDER';
                $this->last_state['stage'] = '';

                if ($isMultiAccount > 0) {
                    // Get Business Accounts Filter, only first time of execution, not in restart
                    $optionAccountSelectors = array();
                    $selectAccountElements = $this->exts->querySelectorAll("select[name=\"selectedB2BGroupKey\"] option");
                    if (count($selectAccountElements) > 0) {
                        foreach ($selectAccountElements as $selectAccountElement) {
                            $elementAccountValue = trim($selectAccountElement->getAttribute('value'));
                            $optionAccountSelectors[] = $elementAccountValue;
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
                            $optionSelAccEle = "select[name=\"selectedB2BGroupKey\"] option[value=\"" . $optionAccountSelector . "\"]";
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

            if ($this->procurment_report == 1) {
                $this->exts->openUrl('https://www.amazon.es/b2b/aba/dashboard');
                if ($this->exts->querySelector($this->password_selector) != null || $this->exts->urlContains('ap/signin')) {
                    $this->fillForm(0);
                    sleep(4);
                }
                $this->exts->click_by_xdotool('a[href*="/b2b/aba/reports?reportType=items_report_1"]');
                sleep(15);
                if ($this->exts->exists('#date_range_selector__range')) {
                    $this->exts->click_by_xdotool('#date_range_selector__range');
                    sleep(1);

                    if ((int)$this->restrictPages == 0) {
                        $this->exts->click_by_xdotool('.date-range-selector .b-dropdown-menu a[value="PAST_12_MONTHS"]');
                    } else {
                        $this->exts->click_by_xdotool('.date-range-selector .b-dropdown-menu a[value="PAST_12_WEEKS"]');
                    }
                    sleep(15);

                    if (!$this->exts->exists('.report-table .column:nth-child(3) [class*="cell-row-"]')) {
                        sleep(25);
                    }

                    $this->download_procurment_document(1);
                }
            }

            //Check Business Prime Account
            //https://www.amazon.es/businessprimeversand
            if (empty($this->last_state['stage']) || $this->last_state['stage'] == 'BUSINESS_PRIME') {
                // Keep current state of processing
                $this->exts->log('access BUSINESS_PRIME');
                $this->current_state['stage'] = 'BUSINESS_PRIME';
                $this->last_state['stage'] = '';

                $this->downloadBusinessPrimeInvoices();
            }

            // Process Message Center
            if (empty($this->last_state['stage']) || $this->last_state['stage'] == 'MESSAGE') {
                // Keep current state of processing
                $this->exts->log('access MESSAGE');
                $this->current_state['stage'] = 'MESSAGE';
                $this->last_state['stage'] = '';

                $this->triggerMsgInvoice();
            }

            if ($this->exts->document_counter == 0) {
                $this->exts->no_invoice();
            }
        } else {
            if ($this->login_tryout == 0) {
                $this->fillForm(0);
            }
        }
    }

    public function orderYearFilters($selectedBusinessAccount = "")
    {
        if (trim($selectedBusinessAccount) != "") {
            $this->exts->log("selectedBusinessAccount Account-value  " . $selectedBusinessAccount);
        }

        // Get Order Filter years
        $optionSelectors = array();
        $selectElements = $this->exts->querySelectorAll("select[name=\"orderFilter\"] option");
        if (count($selectElements) == 0) {
            $selectElements = $this->exts->querySelectorAll('select[name="timeFilter"] option');
            $this->exts->log("selectElements " . count($selectElements));
        }
        $this->exts->log("selectElements " . count($selectElements));
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
                        if ($elementValue != "last30" && $elementValue != "months-6") {
                            $optionSelectors[] = $elementValue;
                        }
                    }
                }
            }
        }

        $this->exts->log("optionSelectors " . count($optionSelectors));
        if (!empty($optionSelectors)) {
            $total_option_selectors = count($optionSelectors);
            for ($i = 0; $i < $total_option_selectors; $i++) {
                $this->exts->log("year-value  " . $optionSelectors[$i]);
            }

            // Process Each Year
            $this->processYears($optionSelectors);
        }
    }

    public function processYears($optionSelectors)
    {
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
            $this->exts->log("processing year element  " . $optionSelector);
            if ($this->exts->exists("select[name=\"orderFilter\"]")) {
                $optionSelEle = "select[name=\"orderFilter\"] option[value=\"" . $optionSelector . "\"]";
                $selectElement = $this->exts->querySelector($optionSelEle);
                $this->exts->click_element($selectElement);
            }
            sleep(2);

            if ($this->exts->querySelector($this->password_selector) != null || stripos($this->exts->getUrl(), "/ap/signin?") !== FALSE) {
                if ($this->login_tryout == 0) {
                    $this->fillForm(0);
                }
            }

            $this->exts->capture("orders-" . $optionSelector);
            $pages = $this->getTotalYearPages(0);

            $this->exts->log("Total pages -" . $pages);
            $total_pages_found = $pages;

            $hrefsArr = array();
            if ($total_pages_found > 1) {
                $firstPageHref = $this->exts->getUrl();
                if ($this->start_page == 0) $this->start_page = 1;

                $l = -1;
                if ($this->exts->querySelector("span.num-orders-for-orders-by-date span.num-orders") != null) {
                    $total_data = $this->exts->querySelector("span.num-orders-for-orders-by-date span.num-orders")->getText();
                    $this->exts->log("total_data -" . $total_data);
                    $tempArr = explode(" ", $total_data);
                    if (count($tempArr)) {
                        $total_data = trim($tempArr[0]);
                    }
                    $this->exts->log("total_data -" . $total_data);
                    if ((int)$total_data > 0) {
                        $l = round($total_data / 10);
                    }
                }

                if ($l == -1) {
                    $pageEle = $this->exts->querySelectorAll("div.pagination-full a");
                    $liCount = count($pageEle);
                    if ($liCount > 2) {
                        $l = (int)trim($pageEle[$liCount - 2]->getText());
                    }
                }

                $pageEle = $this->exts->querySelectorAll("div.pagination-full a");
                $this->exts->log("Paging Total element -" . count($pageEle));
                if (count($pageEle) > 0) {
                    $href = $pageEle[0]->getAttribute("href");
                    $this->exts->log("First Loading page url -" . $href);
                    $firstPageHref = $href;
                    $href = substr($href, 0, strlen($href) - 1);
                    if (stripos($href, "https://www.amazon.es") === false && stripos($href, "https://") === false) {
                        $href = "https://www.amazon.es" . trim($href);
                    }
                }
                $this->exts->log("First Loading page url -" . $href);

                // In restart mode start from where it was left
                if ($this->exts->docker_restart_counter > 0 && !empty($this->last_state['last_page_count'])) {
                    $this->start_page = (int)$this->last_state['last_page_count'];
                }

                for ($i = $this->start_page; $i <= $total_pages_found; $i++) {
                    if ($i == 1) {
                        $hrefsArr[] = array(
                            "url" => $firstPageHref,
                            'page' => $i
                        );
                    } elseif ($i == $this->start_page && !empty($this->last_state['order_page_list']) && $this->start_page > 1) {
                        $hrefsArr[] = array(
                            "url" => $this->last_state['order_page_list'],
                            'page' => $i
                        );
                    } else {
                        $hrefsArr[] = array(
                            "url" => $href . (($i - 1) * 10),
                            'page' => $i
                        );
                    }
                }
            } else {
                $selectedB2BGroupKey = "";
                $currentUrl = $this->exts->getUrl();
                preg_match('/selectedB2BGroupKey\=[^&]+/', $currentUrl, $matches);
                if (count($matches) > 0) {
                    $this->exts->log("GOT B2B ACCOUNT -" . $matches[0]);
                } else {
                    $selectedB2BGroupKey = "";
                }

                $hrefsArr[] = array(
                    "url" => $currentUrl,
                    'page' => 1
                );
            }

            $this->exts->log("Order pages url total - " . count($hrefsArr));
            if (count($hrefsArr) > 0) {
                $firstPageUrl = $hrefsArr[0]['url'];
                $currentPageCount = 1;
                foreach ($hrefsArr as $key1 => $hrefArr) {
                    $this->exts->log("Crawling orders page - " . $this->exts->getUrl());

                    try {
                        if ($key1 == 0) {
                            $this->exts->openUrl($firstPageUrl);
                        } else {
                            if ($this->exts->querySelector("div.pagination-full ul.a-pagination li.a-last a[href*=\"/gp/your-account/order-history/ref=oh_aui_pagination\"]") != null) {
                                $this->exts->click_element("div.pagination-full ul.a-pagination li.a-last a[href*=\"/gp/your-account/order-history/ref=oh_aui_pagination\"]");
                                $this->exts->log("Clicked next page");
                            } else if ($this->exts->querySelector("div.pagination-full ul.a-pagination li.a-last a[href*=\"/gp/your-account/order-history/ref=ppx_yo_dt_b_pagination\"]") != null) {
                                $this->exts->click_element("div.pagination-full ul.a-pagination li.a-last a[href*=\"/gp/your-account/order-history/ref=ppx_yo_dt_b_pagination\"]");
                                $this->exts->log("Clicked next page");
                            } else {
                                break;
                            }
                        }

                        if ($this->dateLimitReached == 1) break;
                        sleep(4);

                        if ($this->exts->querySelector($this->password_selector) != null || stripos($this->exts->getUrl(), "/ap/signin?") !== false) {
                            $this->fillForm(0);
                            sleep(4);
                        }

                        if ($this->exts->querySelector("div.a-box-group.a-spacing-base.order") != null) {
                            $this->exts->log("Invoice Found");

                            $invoice_data_arr = array();
                            $rows = $this->exts->querySelectorAll("div.a-box-group.a-spacing-base.order");
                            $this->exts->log("Invoice Rows- " . count($rows));
                            if (count($rows) > 0) {
                                $total_rows = count($rows);
                                for ($i = 0, $j = 2; $i < $total_rows; $i++, $j++) {
                                    $rowItem = $rows[$i];
                                    try {
                                        $columns = $this->exts->querySelectorAll("div.order-info div.a-fixed-right-grid-col:nth-child(1) span.a-color-secondary.value", $rowItem);
                                        $this->exts->log("Invoice Row columns- $i - " . count($columns));
                                        if (count($columns) > 0) {
                                            $invoice_date = trim($columns[0]->getText());
                                            $this->exts->log("invoice_date - " . $invoice_date);

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

                                            $columns = $this->exts->querySelectorAll("div.order-info div.a-fixed-right-grid-col.actions span.a-color-secondary.value", $rowItem);
                                            $invoice_number = trim($columns[count($columns) - 1]->getText());
                                            $this->exts->log("invoice_number - " . $invoice_number);

                                            if (!$this->exts->invoice_exists($invoice_number)) {
                                                $sellerName = "";
                                                $sellerColumns = $this->exts->querySelectorAll("div.a-box.shipment div.a-fixed-left-grid-col.a-col-right span.a-color-secondary");
                                                if (count($sellerColumns) > 0) {
                                                    $sellerName = trim($sellerColumns[0]->getText());
                                                }

                                                $detailPageUrl = "";
                                                $columns = $this->exts->querySelectorAll("div.order-info div.a-fixed-right-grid-col.actions ul a.a-link-normal", $rowItem);
                                                if (count($columns) > 0) {
                                                    $detailPageUrl = $columns[0]->getAttribute("href");
                                                    if (stripos($detailPageUrl, "https://www.amazon.es") === false && stripos($detailPageUrl, "https://") === false) {
                                                        $detailPageUrl = "https://www.amazon.es" . trim($detailPageUrl);
                                                    }

                                                    $filename = !empty($invoice_number) ? trim($invoice_number) . ".pdf" : '';

                                                    //Stop Downloading invoice if invoice is older than 90 days.
                                                    if ($this->last_invoice_date != "" && !empty($this->last_invoice_date)) {
                                                        $last_date_timestamp = strtotime($this->last_invoice_date);
                                                        $last_date_timestamp = $last_date_timestamp - (45 * 24 * 60 * 60);
                                                        $parsed_date = $this->exts->parse_date($invoice_date, '', '', 'es');
                                                        if (trim($parsed_date) != "") $invoice_date = $parsed_date;
                                                        if ($last_date_timestamp > strtotime($invoice_date)) {
                                                            $this->exts->log("Skip invoice download as it is not newer than " . $this->last_invoice_date . " - " . $invoice_date);
                                                            $this->dateLimitReached = 1;
                                                            break;
                                                        }
                                                    }

                                                    if (trim($detailPageUrl) != "" && $this->dateLimitReached == 0) {
                                                        $last_date_timestamp = strtotime($this->last_invoice_date);
                                                        $last_date_timestamp = $last_date_timestamp - (45 * 24 * 60 * 60);
                                                        $parsed_date = $this->exts->parse_date($invoice_date, '', '', 'es');
                                                        if (trim($parsed_date) != "") $invoice_date = $parsed_date;
                                                        if ($last_date_timestamp > strtotime($invoice_date)) {
                                                            $this->exts->log("Skip invoice download as it is not newer than " . $this->last_invoice_date . " - " . $invoice_date);
                                                            $this->dateLimitReached = 1;
                                                            break;
                                                        } else {
                                                            $prices = array();
                                                            $price_blocks = $this->exts->querySelectorAll("div.a-box.shipment div.a-fixed-left-grid-col.a-col-right span.a-color-price", $rowItem);
                                                            if (count($price_blocks) > 0) {
                                                                foreach ($price_blocks as $price_block) {
                                                                    $currentBlockPrice = $price_block->getText();
                                                                    $currentBlockPrice = trim($currentBlockPrice);
                                                                    $currentBlockPrice = str_replace("EUR", "", $currentBlockPrice);
                                                                    $currentBlockPrice = str_replace(".", "", $currentBlockPrice);
                                                                    $currentBlockPrice = str_replace(",", ".", $currentBlockPrice);

                                                                    $prices[] = $currentBlockPrice;
                                                                }
                                                            }

                                                            $isPopOver = $this->exts->$rowItem("div.order-info div.a-fixed-right-grid-col.actions ul a.a-popover-trigger.a-declarative", $rowItem);
                                                            if (count($isPopOver) > 0) {
                                                                $this->exts->click_element($isPopOver[0]);
                                                            }
                                                            sleep(4);

                                                            $invoice_urls = array();
                                                            if (count($isPopOver) > 0) {
                                                                $links = $this->exts->querySelectorAll(".a-popover-content a.a-link-normal");
                                                                if (empty($links)) {
                                                                    sleep(4);
                                                                    $isPopOver = $this->exts->querySelectorAll(".a-spacing-none .a-popover-trigger");
                                                                    if (count($isPopOver) > 0) {
                                                                        $this->exts->click_element($isPopOver[0]);
                                                                        sleep(4);

                                                                        $links = $this->exts->querySelectorAll(".a-popover-content a.a-link-normal");
                                                                    }
                                                                }

                                                                // Still no links, move to next
                                                                if (empty($links)) {
                                                                    $popups = $this->exts->querySelectorAll("div.a-popover.a-popover-no-header.a-arrow-bottom");
                                                                    if (count($popups) > 0) {
                                                                        $this->exts->execute_javascript("
																		var popups = document.querySelectorAll(\"div.a-popover.a-popover-no-header.a-arrow-bottom\");
																		for(var i=0; i<popups.length; i++) {
																			popups[i].remove();
																		}
																	");
                                                                    }
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
                                                                    $currItemLinkText = $link_item->getText();
                                                                    $currItemLinkText = trim($currItemLinkText);

                                                                    if (stripos($currItemLinkText, "Factura o nota de crÃƒÆ’Ã‚Â©dito") === false) {
                                                                        // Sometime in .de language appears as english, so alongwith Rechnung, replace Invoice
                                                                        $currItemLinkText = str_replace("Factura ", "", $currItemLinkText);
                                                                        $currItemLinkText = str_replace("Rechnung ", "", $currItemLinkText);
                                                                        $currItemLinkText = str_replace("Invoice ", "", $currItemLinkText);

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

                                                                // Check if invoice request link is available, then request invoice
                                                                if (trim($contact_link) != "" && empty($invoice_urls)) {
                                                                    $invoice_urls[] = array(
                                                                        'link' => $overview_link,
                                                                        'overview_link' => $overview_link,
                                                                        'contact_url' => $contact_link,
                                                                        'price' => 0,
                                                                        'is_credit_note' => 0
                                                                    );
                                                                }

                                                                $inv_num = 1;
                                                                if (trim($contact_link) == "") {
                                                                    // Download credit note only if no contact link is available, because if contact link is available
                                                                    // system will download overview and do a invoice request
                                                                    // virtually in this way, system will never download credit note and in either way we don't need it.
                                                                    foreach ($links as $lkey =>  $link_item) {
                                                                        $currItemLinkText = $link_item->getText();
                                                                        $currItemLinkText = trim($currItemLinkText);

                                                                        if (stripos($currItemLinkText, "Factura o nota de crÃƒÆ’Ã‚Â©dito") !== false) {
                                                                            $currItemLinkText = str_replace("Factura o nota de crÃƒÆ’Ã‚Â©dito ", "", $currItemLinkText);
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
                                                                            $currItemLinkText = str_replace("rÃƒÆ’Ã‚Â©glage facture ", "", $currItemLinkText);
                                                                            $currItemLinkText = str_replace("ajustement de facture ", "", $currItemLinkText);
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

                                                                //Remove invoice triggered popups
                                                                $this->exts->execute_javascript("
																var popups = document.querySelectorAll(\"div.a-popover.a-popover-no-header.a-arrow-bottom\");
																for(var i=0; i<popups.length; i++) {
																	popups[i].remove();
																}
															");
                                                            } else {
                                                                $links = $this->exts->querySelectorAll("div.orderSummary a.a-link-normal", $rowItem);
                                                                if (count($links) > 0) {
                                                                    $invoice_urls[] = array(
                                                                        'link' => count($links) > 0 ? trim($links[0]->getAttribute('href')) : "",
                                                                        'overview_link' => "",
                                                                        'contact_url' => "",
                                                                        'price' => 0,
                                                                        'is_credit_note' => 0
                                                                    );
                                                                }

                                                                $popups = $this->exts->querySelectorAll("div.a-popover.a-popover-no-header.a-arrow-bottom");
                                                                if (count($popups) > 0) {
                                                                    $this->exts->execute_javascript("
																	var popups = document.querySelectorAll(\"div.a-popover.a-popover-no-header.a-arrow-bottom\");
																	for(var i=0; i<popups.length; i++) {
																		popups[i].remove();
																	}
																");
                                                                }
                                                            }

                                                            if (!empty($invoice_urls)) {
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
                                                                            $links = $this->exts->querySelectorAll(".a-popover-content a.a-link-normal", $rowItem);
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
                                                                    if (trim($sellerName) != "" && trim($sellerName) == "Vendu par : Amazon EU S.a.r.L.") {
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
                                                                            $filename =  !empty($item_invoice_number) ?  $item_invoice_number . ".pdf" : '';
                                                                        }

                                                                        if (stripos($invoice_url, "https://www.amazon.es") === false && stripos($invoice_url, "https://") === false) {
                                                                            $invoice_url = "https://www.amazon.es" . $invoice_url;
                                                                        }
                                                                        $this->exts->log("invoice_url - " . $invoice_url);

                                                                        if (trim($overview_link) != "" && stripos($overview_link, "https://www.amazon.es") === false && stripos($overview_link, "https://") === false) {
                                                                            $overview_link = "https://www.amazon.es" . $overview_link;
                                                                        }

                                                                        if (stripos($invoice_url, "print") !== false) {
                                                                            // Check if user has opted for auto invoice request, then download overview only if amazon is not seller
                                                                            $download_overview = $this->amazon_download_overview;
                                                                            if ((int)$this->auto_request_invoice == 1 && $contact_url != "") {
                                                                                $download_overview = 1;
                                                                            }

                                                                            if ($download_overview == 1 && trim($sellerName) != "" && trim($sellerName) != "Vendu par : Amazon EU S.a.r.L.") {
                                                                                $this->exts->log("Downloading overview page as invoice");

                                                                                $this->exts->log("New Overview invoiceName- " . $item_invoice_number);
                                                                                $this->exts->log("New Overview invoiceAmount- " . $invoice_amount);
                                                                                $this->exts->log("New Overview Filename- " . $filename);

                                                                                //Sometime while capturing overview page we get login form, but not in opening any other page.
                                                                                //So detect such case and process login again

                                                                                $currentUrl = $this->exts->getUrl();

                                                                                // Open New window To process Invoice
                                                                                // Call Processing function to process current page invoices
                                                                                $this->exts->openNewTab($invoice_url);
                                                                                sleep(2);

                                                                                if (stripos($this->exts->getUrl(), "amazon.es/ap/signin") !== false) {
                                                                                    $this->fillForm(0);
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
                                                                                $this->exts->switchToInitTab();
                                                                                sleep(2);
                                                                                $this->exts->closeAllTabsButThis();
                                                                            } else {
                                                                                if (trim($sellerName) != "" && trim($sellerName) == "Vendu par : Amazon EU S.a.r.L.") {
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
                                                                                    $this->exts->openNewTab();

                                                                                    // Call Processing function to process current page invoices
                                                                                    $this->exts->openUrl($invoice_url);
                                                                                    sleep(2);

                                                                                    if (stripos($this->exts->getUrl(), "amazon.es/ap/signin") !== false) {
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

                                                                                    // Close new window
                                                                                    $this->exts->switchToInitTab();
                                                                                    sleep(2);
                                                                                    $this->exts->closeAllTabsButThis();
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
                                                                            if (stripos($contact_url, "https://www.amazon.es") === false) {
                                                                                $contact_url = "https://www.amazon.es" . $contact_url;
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
                                        }
                                    } catch (\Exception $exception) {
                                        $this->exts->log("Exception finding columns element " . $exception->getMessage());
                                    }
                                }
                                sleep(2);
                            }
                        } else if ($this->exts->exists('.order-card a[href*="/ajax/invoice/"]')) {
                            // Huy added this 2023-01
                            $count_order_card = count($this->exts->getElements('.order-card a[href*="/ajax/invoice/"]'));
                            $order_invoices = [];
                            for ($i = 0; $i < $count_order_card; $i++) {
                                $order_card_invoice_dropdown = $this->exts->getElements('.order-card a[href*="/ajax/invoice/"]')[$i];
                                $temp_url = $order_card_invoice_dropdown->getAttribute('href');
                                $temp_array = explode('orderId=', $temp_url);
                                $order_invoice_name = end($temp_array);
                                $temp_array = explode('&', $order_invoice_name);
                                $order_invoice_name = reset($temp_array);

                                try {
                                    $this->exts->click_element($order_card_invoice_dropdown);
                                } catch (Exception $e) {
                                    $this->exts->executeSafeScript('arguments[0].click()', [$order_card_invoice_dropdown]);
                                }
                                sleep(5);
                                if ($this->exts->exists('.a-popover[style*="visibility: visible"]:not([aria-hidden="true"]) a[href*="/invoice.pdf"]')) {
                                    $order_invoice_url = $this->exts->getElement('.a-popover[style*="visibility: visible"]:not([aria-hidden="true"]) a[href*="/invoice.pdf"]')->getAttribute('href');
                                    $this->exts->log('--------------------------');
                                    $this->exts->log('order_invoice_name: ' . $order_invoice_name);
                                    $this->exts->log('order_invoice_url: ' . $order_invoice_url);

                                    $invoiceFileName = !empty($order_invoice_name) ? $order_invoice_name . '.pdf' : '';
                                    $downloaded_file = $this->exts->direct_download($order_invoice_url, 'pdf', $invoiceFileName);
                                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                        $this->exts->new_invoice($order_invoice_name, '', '', $downloaded_file);
                                    } else {
                                        $this->exts->log(__FUNCTION__ . '::No download ' . $order_invoice_name);
                                    }
                                }
                                $this->exts->click_by_xdotool('[data-action="a-popover-close"]');
                                sleep(2);
                            }
                        }

                        // Keep last processed page count
                        $this->last_state['last_page_count'] = '';
                        $this->current_state['last_page_count'] = $hrefArr['page'];

                        // Keep last processed order page list url
                        $this->last_state['order_page_list'] = '';
                        $this->current_state['order_page_list'] = $this->exts->getUrl();
                    } catch (\Exception $excp) {
                        $this->exts->log("Orders - " . $excp->getMessage());
                    }
                }
            } else {
                $this->exts->log("No Invoice Found");
            }

            // Keep processed years
            $this->last_state['years'] = array();
            $this->current_state['years'][] = $optionSelector;
            // for ($i = 0; $i < 5 && $this->exts->urlContains('errors/400.html'); $i++) {
            //     $this->exts->navigate()->back();
            //     sleep(5);
            // }
        }
    }

    public function getTotalYearPages($reloadCount)
    {
        $pages = 0;
        if ($this->exts->querySelector("span.num-orders-for-orders-by-date span.num-orders") != null) {
            $total_data = $this->exts->querySelector("span.num-orders-for-orders-by-date span.num-orders")->getText();
            $this->exts->log("total_data -" . $total_data);
            $tempArr = explode(" ", $total_data);
            if (count($tempArr)) {
                $total_data = trim($tempArr[0]);
            }
            $this->exts->log("total_data -" . $total_data);
            $pages = round($total_data / 10);
            $this->exts->log("total_data -" . $pages);

            if ($pages < 0) {
                $pageEle = $this->exts->querySelectorAll("div.pagination-full a");
                $liCount = count($pageEle);
                if ($liCount > 2) {
                    $pages = (int)trim($pageEle[$liCount - 2]->getText());
                }
            }
        } else if ($this->exts->querySelector("span.num-orders") != null) {
            $total_data = $this->exts->querySelector("span.num-orders")->getText();
            $this->exts->log("total_data -" . $total_data);
            $tempArr = explode(" ", $total_data);
            if (count($tempArr)) {
                $total_data = trim($tempArr[0]);
            }
            $this->exts->log("total_data -" . $total_data);
            $pages = round($total_data / 10);
            $this->exts->log("total_data -" . $pages);

            if ($pages < 0) {
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

    public function downloadBusinessPrimeInvoices()
    {
        $this->exts->openUrl($this->businessPrimeUrl);
        sleep(5);

        if ($this->exts->querySelector($this->password_selector) != null || $this->exts->urlContains('ap/signin')) {
            $this->fillForm(0);
            sleep(4);
        }

        if ($this->exts->querySelector("a[href*=\"/bb/benefits/bps/landing/?ref_=ab_bps_engmt_mgmtbox\"]") != null) {
            $this->exts->click_element("a[href*=\"/bb/benefits/bps/landing/?ref_=ab_bps_engmt_mgmtbox\"]");
            sleep(10);

            $invoice_url = "";
            if ($this->exts->querySelector("a#business-prime-shipping-view-last-invoice") != null) {
                try {
                    $invoice_url = $this->exts->querySelector("a#business-prime-shipping-view-last-invoice")->getAttribute("href");
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                    if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.es") === false && stripos($invoice_url, "https://") === false) {
                        $invoice_url = "https://www.amazon.es" . $invoice_url;
                    }
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                } catch (\Exception $exception) {
                    $this->exts->log("Getting business prime invoice 1st option - " . $exception->getMessage());
                }
            } else if ($this->exts->querySelector("a[href*=\"/documents/download/\"]") != null) {
                try {
                    $invoice_url = $this->exts->querySelector("a[href*=\"/documents/download/\"]")->getAttribute("href");
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                    if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.es") === false && stripos($invoice_url, "https://") === false) {
                        $invoice_url = "https://www.amazon.es" . $invoice_url;
                    }
                    $this->exts->log("prime invoice URL - " . $invoice_url);
                } catch (\Exception $exception) {
                    $this->exts->log("Getting business prime invoice 2nd option - " . $exception->getMessage());
                }
            }

            if (trim($invoice_url) != "" && !empty($invoice_url)) {
                try {
                    $invoiceDate = "";
                    if ($this->exts->querySelector("div#bps-mgmt-payment-history-last-invoice span > font") != null) {
                        $tempInvoiceDate = trim($this->exts->querySelector("div#bps-mgmt-payment-history-last-invoice span > font")->getText());
                        $tempArr = explode(":", $tempInvoiceDate);
                        $invoiceDate = trim($tempArr[count($tempArr) - 1]);
                    } else if ($this->exts->querySelector("div#bps-mgmt-payment-history-last-invoice") != null) {
                        $tempInvoiceDate = trim($this->exts->querySelector("div#bps-mgmt-payment-history-last-invoice")->getText());
                        $tempArr = explode(":", $tempInvoiceDate);
                        $invoiceDate = trim($tempArr[count($tempArr) - 1]);
                        $this->exts->log("prime invoice date - " . $invoiceDate);

                        $tempArr = explode(" ", $invoiceDate);
                        $invoiceDate = trim($tempArr[0]) . " " . trim($tempArr[1]) . " " . trim($tempArr[2]);
                        $this->exts->log("prime invoice date - " . $invoiceDate);
                    }
                    $this->exts->log("prime invoice date - " . $invoiceDate);

                    if (trim($invoiceDate) != "") {
                        $parsed_invoice_date = $this->exts->parse_date($invoiceDate, '', '', 'es');
                        $invoiceDate = $parsed_invoice_date;
                    }
                    $this->exts->log("prime invoice date - " . $invoiceDate);

                    $filename = "";
                    if (trim($invoiceDate) != "" && !empty($invoiceDate)) {
                        $filename = trim($invoiceDate) . ".pdf";
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
        } else {
            $this->exts->log("No Business Prime Invoices");
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

        if ((int)@$currentMessagePage == 0) {
            $this->exts->openUrl('https://www.amazon.es/gp/message');
            sleep(15);

            $this->exts->click_by_xdotool('li[data-a-tab-name="inbox_bsm_tab"] a');
            sleep(15);
        }
        $this->exts->capture('process-ms-invoice');

        $inv_msgs = array();
        $invMsgRows = $this->exts->querySelectorAll('div[data-a-name="inbox_bsm_tab"] table.message-table tr');
        $this->exts->log("Message Rows on page - " . $currentMessagePage . " - " . count($invMsgRows));
        if (count($invMsgRows) > 0) {
            $msgTimeDiff = time() - (60 * 24 * 60 * 60);
            foreach ($invMsgRows as $key => $invMsgRow) {
                $invMsgCols = $this->exts->querySelectorAll('td', $invMsgRow);
                $invMsgColImg = $this->exts->querySelectorAll('span img', $invMsgRow);
                $this->exts->log("Message cols and imgcols  - " . $key . " - " . count($invMsgCols) . " - " . count($invMsgColImg));

                if (count($invMsgCols) > 0 && $invMsgColImg != null) {
                    $invMsgColImgTitle = $this->exts->extract('span img', $invMsgRow, 'Title');
                    if (empty($invMsgColImgTitle)) {
                        $invMsgColImgTitle = $this->exts->extract('span img', $invMsgRow, 'title');
                    }

                    $this->exts->log("message img title - " . $key . " - " . $invMsgColImgTitle);
                    if (strpos($invMsgColImgTitle, "Attachment") !== false) {
                        $msgTime = ($invMsgCols[0]->getAttribute("messagesenttime") / 1000);
                        if ($msgTime > $msgTimeDiff) {
                            $inv_msgs[] = array(
                                'msg_time' => $invMsgCols[0]->getAttribute("messagesenttime"),
                                'msg_id' => $invMsgCols[0]->getAttribute("messageid"),
                                'msg_url' => explode('/inbox', $this->exts->getUrl())[0] . "/detail/" . trim($invMsgCols[0]->getAttribute("messageid")) . '/' . trim($invMsgCols[0]->getAttribute("threadid")) . "/bsm/" . trim($invMsgCols[0]->getAttribute("messagesenttime")) . "/inbox"
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
        $this->exts->capture('process-ms-invoice-1');
        foreach ($inv_msgs as $inv_msg) {
            // Open New window To process Invoice
            $this->exts->openNewTab();

            $this->exts->log('invocie url: ' . $inv_msg['msg_url']);
            $this->exts->executeSafeScript('location.href = "' . $inv_msg['msg_url'] . '";');
            sleep(15);

            //Call Processing function to process current page invoices
            $this->startCurrentPageMessageDownload($inv_msg);
            sleep(2);

            // Close new tab
            $this->exts->switchToInitTab();
            sleep(2);
            $this->exts->closeAllTabsButThis();
        }
        $this->exts->capture('process-ms-invoice-2');
        if ($this->exts->querySelector("#inbox_button_next_page") != null && (int)@$this->msgTimeLimitReached == 0) {
            $nextBtnClass = $this->exts->querySelector("#inbox_button_next_page")->getAttribute("class");
            if (stripos($nextBtnClass, "disabled") === FALSE) {

                if (!$this->exts->exists('span#inbox_button_next_page.a-button-disabled')) {
                    $this->exts->moveToElementAndClick("#inbox_button_next_page");
                    sleep(5);
                    $currentMessagePage++;
                    $this->processMSInvoice($currentMessagePage);
                }
            }
        }
    }

    function startCurrentPageMessageDownload($inv_msg)
    {
        $this->exts->log("Message ID - " . $inv_msg['msg_id']);
        $this->exts->log("Message Timestamp - " . $inv_msg['msg_time']);

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
                                    if (trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.es") === false && stripos($invoice_url, "https://") === false) {
                                        $invoice_url = "https://www.amazon.es" . $invoice_url;
                                    }

                                    $downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
                                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                        $invoice_date = date("Y-m-d", $inv_msg['msg_time'] / 1000);
                                        $invoice_note = ":::Marketplace Seller - " . $invoice_data['order_number'];
                                        $this->exts->new_invoice($invoice_name, $invoice_date, "", $filename, 1, $invoice_note);

                                        $this->exts->sendRequestEx($invoice_name . $invoice_note, "===NOTE-DATA===");
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

    public function download_procurment_document($pageNum = 1)
    {
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
            $rows = $this->exts->querySelectorAll('.report-table .column:nth-child(3) [class*="cell-row-"]');
            foreach ($rows as $key => $row) {
                $row =  $this->exts->querySelectorAll('.report-table .column:nth-child(3) [class*="cell-row-"]')[$key];
                $linkBtn = $this->exts->querySelector('[data-action="a-popover"] a', $row);

                if ($linkBtn != null) {
                    $orderNum = trim($linkBtn->getText());
                    $this->exts->log('Order - ' . $orderNum);
                    try {
                        $this->exts->click_element($linkBtn);
                    } catch (\Exception $exception) {
                        $this->exts->executeSafeScript('arguments[0].click();', [$linkBtn]);
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

                        $currentUrl = $this->exts->getUrl();

                        foreach ($downloadLinks as $downloadLink) {
                            if (stripos($downloadLink, '/b2b/aba/order-summary/') !== false) {
                                if (trim($orderNum) !== '' && !$this->exts->invoice_exists($orderNum)) {
                                    $invoice_name = $orderNum;
                                    $fileName = !empty($orderNum) ? $orderNum . '.pdf' : '';

                                    // Open New window To process Invoice
                                    $this->exts->openNewTab();

                                    // Call Processing function to process current page invoices
                                    $this->exts->openUrl($downloadLink);
                                    sleep(2);

                                    if (stripos($this->exts->getUrl(), "amazon.es/ap/signin") !== false) {
                                        $this->fillForm(0);
                                        sleep(4);
                                    }
                                    sleep(2);

                                    if (stripos($this->exts->getUrl(), "amazon.es/ap/signin") === false && stripos($this->exts->getUrl(), "/print.html") !== false) {
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
                                    } else if (stripos($this->exts->getUrl(), "amazon.de/ap/signin") === false && (stripos($this->exts->getUrl(), "order-document.pdf") !== false || stripos($this->exts->getUrl(), ".pdf") !== false)) {
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

                                    // Close new window
                                    $this->exts->switchToInitTab();
                                    sleep(2);
                                    $this->exts->closeAllTabsButThis();
                                } else {
                                    $this->exts->log('Already Invoice Exists - ' . $orderNum);
                                }
                            } else {
                                //I am opening this becasue sometime download link gives technical error.
                                $this->exts->openNewTab();

                                $this->exts->openUrl($this->baseUrl);
                                sleep(2);
                                if ($this->exts->exists('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]')) {
                                    $this->exts->click_by_xdotool('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]');
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
