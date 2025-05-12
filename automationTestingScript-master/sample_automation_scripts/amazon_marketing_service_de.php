<?php // i have removed undefined function isDisplayed and handle empty invoice name condition 

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

    // Server-Portal-ID: 16488 - Last modified: 02.04.2025 14:31:36 UTC - User: 1

    public $baseUrl = "https://advertising.amazon.de/";
    public $orderPageUrl = "https://advertising.amazon.de/billing/history?ref_=ams_head_billing";
    public $loginLinkPrim = "div#topNavLinks div.menuClose span.topNavLink a";
    public $loginLinkSec = "a[href*=\"/ap/signin\"]";
    public $loginLinkThird = "a[href*=\"/sign-in?ref_=\"]";
    public $username_selector = "input#ap_email";
    public $password_selector = "input#ap_password";
    public $submit_button_selector = "#signInSubmit, button[type='submit'], input[type='submit']";
    public $continue_button_selector = "#continue";
    public $logout_link = "div#signOut a[href*=\"/ap/signin?openid.return_to=\"], a[data-e2e-id=\"aac-sign-out-link\"]";
    public $remember_me = "input[name=\"rememberMe\"]";
    public $login_tryout = 0;
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(2);

            $this->exts->openUrl($this->baseUrl);
            sleep(2);
            $this->exts->capture("Home-page-with-cookie");

            $this->exts->openUrl($this->orderPageUrl);
            sleep(2);

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            }
        }

        $isLoggedIn = $this->beforeLogin($isCookieLoginSuccess);

        if ($isLoggedIn) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->processAfterLogin();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if ($this->exts->urlContains('/forgotpassword/reverification')) {
                $this->exts->account_not_ready();
            }
            if ($this->isIncorrectCredential()) {
                $this->exts->loginFailure(1);
            }

            $error_text = strtolower($this->exts->extract('div#auth-email-invalid-claim-alert div.a-alert-content'));

            $this->exts->log("::Email Error Message :" . $error_text);
            if (
                stripos($error_text, strtolower('Ungültige E-Mail-Adresse oder Mobiltelefonnummer Korrigiere das Problem, und wiederhole den Vorgang.')) !== false ||
                stripos($error_text, strtolower('Invalid email address or mobile number. Correct the problem and try again.')) !== false
            ) {
                $this->exts->loginFailure(1);
            }
            $this->exts->loginFailure();
        }
    }

    private function beforeLogin($isCookieLoginSuccess)
    {
        if (!$isCookieLoginSuccess) {
            if ($this->exts->getElement($this->password_selector) == null && $this->exts->getElement($this->username_selector) == null) {
                if ($this->exts->getElement($this->loginLinkPrim) != null) {
                    $this->exts->log("Found Primary Login Link!!");
                    $this->exts->moveToElementAndClick($this->loginLinkPrim);
                } else if ($this->exts->getElement($this->loginLinkSec) != null) {
                    $this->exts->log("Found Secondry Login Link!!");
                    $this->exts->moveToElementAndClick($this->loginLinkSec);
                } else if ($this->exts->getElement($this->loginLinkThird) != null) {
                    $this->exts->log("Found Third Login Link!!");
                    //$this->exts->getElement($this->loginLinkThird)->click();
                    $this->exts->moveToElementAndClick($this->loginLinkThird);
                    sleep(5);

                    if ($this->exts->getElement('a[href*="https://www.amazon.de/ap/signin?"]') != null) {
                        $this->exts->moveToElementAndClick('a[href*="https://www.amazon.de/ap/signin?"]');
                    } else {
                        $this->exts->openUrl("https://www.amazon.de/ap/signin?openid.pape.max_auth_age=28800&openid.return_to=https%3A%2F%2Fadvertising.amazon.de%2Fmn%2F%3Fsource%3Dams%26ref_%3Da20m_de_sgnn_advcnsl&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.assoc_handle=amzn_ams_de&openid.mode=checkid_setup&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&pageId=ap-ams&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0");
                    }
                } else if ($this->exts->getElement('a[href*="https://www.amazon.de/ap/signin?"]') != null) {
                    $this->exts->moveToElementAndClick('a[href*="https://www.amazon.de/ap/signin?"]');
                } else {
                    $this->exts->openUrl("https://www.amazon.de/ap/signin?openid.pape.max_auth_age=28800&openid.return_to=https%3A%2F%2Fadvertising.amazon.de%2Fmn%2F%3Fsource%3Dams%26ref_%3Da20m_de_sgnn_advcnsl&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.assoc_handle=amzn_ams_de&openid.mode=checkid_setup&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&pageId=ap-ams&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0");
                }
                sleep(2);
            }

            $this->fillForm(0);
            sleep(5);
            $this->exts->log('After Form Filled');

            if ($this->exts->exists('img[alt="captcha"]') && !$this->isIncorrectCredential()) {
                $this->exts->log('process Image Captcha 1');
                $this->processImageCaptcha();
            }

            if ($this->exts->exists('form[name="signIn"] input[name="guess"]') && !$this->isIncorrectCredential()) {
                $this->exts->log('process Image Captcha 2');
                $this->processImageCaptcha();
            }

            if ($this->exts->exists('form[name="signIn"] input[name="guess"]') && !$this->isIncorrectCredential()) {
                $this->exts->log('process Image Captcha 3');
                $this->processImageCaptcha();
            }

            if ($this->exts->exists('form#auth-account-fixup-phone-form input#account-fixup-phone-number')) {
                if ($this->exts->exists('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link')) {
                    $this->exts->moveToElementAndClick('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link');
                    sleep(2);
                }
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");
            if ($this->checkLogin()) {
                return true;
            } else {
                //Captcha and Two Factor Check
                $this->processImageCaptcha();

                $this->exts->moveToElementAndClick('a#ap-account-fixup-phone-skip-link');
                sleep(15);

                // Handling pre check 2FA
                if ($this->exts->getElement('form[name="claimspicker"]') != null) {
                    $this->exts->moveToElementAndClick('form[name="claimspicker"] input#continue[type="submit"]');
                    sleep(15);
                }

                if ($this->exts->getElement('#auth-send-code') !== null) {
                    $this->exts->moveToElementAndClick('#auth-send-code');
                    sleep(5);
                }

                if ($this->exts->exists('form[action="verify"] input[value="sms"]')) {
                    $this->exts->moveToElementAndClick('input#continue');
                    sleep(15);
                }

                if ($this->exts->exists('form[action="verify"] [name="option"]')) {
                    $this->exts->moveToElementAndClick('form[action="verify"] input#continue');
                    sleep(5);
                }

                if ($this->exts->getElement("form#auth-mfa-form") != null) {
                    $this->exts->moveToElementAndClick('input[name="rememberDevice"]:not(:checked)');
                    $this->checkFillTwoFactor('input[name="otpCode"]', 'form#auth-mfa-form input#auth-signin-button', 'form#auth-mfa-form .a-box-inner p');
                } else if ($this->exts->getElement('form.cvf-widget-form[action*="verify"] input[name="code"]') != null) {
                    $this->exts->moveToElementAndClick('input[name="rememberDevice"]:not(:checked)');
                    $this->checkFillTwoFactor('form.cvf-widget-form[action*="verify"] input[name="code"]', 'form.cvf-widget-form.fwcim-form input.a-button-input[type="submit"]', 'form.cvf-widget-form[action*="verify"] div.a-row:nth-child(1) span');
                } else if ($this->exts->getElement('form.cvf-widget-form-dcq[action*="verify"] select[name*="dcq_question_date_picker"]') != null) {
                    $cpText = $this->exts->getElements('form.cvf-widget-form-dcq[action*="verify"] > div.a-row > div.a-row > label');
                    $isTwoFactorText = "";

                    foreach ($cpText as $notificationTextElement) {
                        $isTwoFactorText .= $notificationTextElement->getText();
                    }

                    if (trim($isTwoFactorText) != "" && !empty(trim($isTwoFactorText))) {
                        $this->exts->two_factor_notif_msg_en = trim($isTwoFactorText);
                        $this->exts->two_factor_notif_msg_de = trim($isTwoFactorText);
                    }
                    $this->exts->two_factor_notif_msg_en .=  "#br#" . "Date should be in format month-year (Example: 01-2004)";
                    $this->exts->two_factor_notif_msg_de .=  "#br#" . "Datum muss im Format Monat-Jahr sein (zB. 01-2004)";

                    $this->exts->moveToElementAndClick('input[name="rememberDevice"]:not(:checked)');
                    $this->checkFillTwoFactor('select[name*="dcq_question_date_picker_1_1"]', 'form.cvf-widget-form-dcq input[name="cvfDcqAction"][type="submit"]', '');
                } else if ($this->exts->exists('div#tiv-message + form[action="verify"]')) {
                    $this->checkFillTwoFactorWithPushNotify('div#tiv-message');
                } else if ($this->exts->exists('input[name="transactionApprovalStatus"]')) {
                    $this->checkFillTwoFactorWithPushNotify('div.a-section.a-spacing-large span.a-text-bold, div#channelDetails');
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
                } else if ($this->exts->exists('form#verification-code-form')) {

                    $this->checkFillTwoFactor('input[id="input-box-otp"]', 'span[id="cvf-submit-otp-button"] input', 'div#channelDetailsForOtp');
                }

                sleep(2);
                if ($this->checkLogin()) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return true;
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            $this->exts->capture("account-switcher");
            $account_switcher_elements = $this->exts->getElements("div.cvf-account-switcher-profile-details-after-account-removed");
            if (count($account_switcher_elements) > 0) {
                $this->exts->log("click account-switcher");
                $account_switcher_elements[0]->click();
                sleep(2);
            } else {
                $account_switcher_elements = $this->exts->getElements("div.cvf-account-switcher-profile-details");
                if (count($account_switcher_elements) > 0) {
                    $this->exts->log("click account-switcher");
                    $account_switcher_elements[0]->click();
                    sleep(2);
                }
            }

            if ($this->exts->getElement('.nav__sign-in') != null || $this->exts->getElement($this->username_selector) == null) {
                // loaded cookie is expired
                $this->exts->moveToElementAndClick('.nav__sign-in');
                sleep(5);
            }

            if ($this->exts->getElement($this->password_selector) != null || $this->exts->getElement($this->username_selector) != null) {
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                $formType = $this->exts->getElement($this->password_selector);
                if ($formType == null) {
                    $this->exts->log("Form with Username Only");
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(2);

                    $this->exts->log("Username form button click");
                    $this->exts->moveToElementAndClick($this->continue_button_selector);
                    sleep(7);
                    $this->exts->capture("username-filled-2");
                    $this->exts->waitTillPresent($this->password_selector);

                    if ($this->exts->getElement($this->password_selector) != null) {
                        $this->exts->log("Enter Password");
                        $this->exts->moveToElementAndType($this->password_selector, $this->password);
                        sleep(3);


                        $this->exts->capture("1-filled-login");
                        $this->exts->click_by_xdotool($this->submit_button_selector);
                    } else {
                        $error_text = strtolower($this->exts->extract('div#auth-email-invalid-claim-alert div.a-alert-content'));
                        $this->exts->capture("login-failed");
                        $this->exts->log("::Email Error Message :" . $error_text);
                        if (
                            stripos($error_text, strtolower('Ungültige E-Mail-Adresse oder Mobiltelefonnummer Korrigiere das Problem, und wiederhole den Vorgang.')) !== false ||
                            stripos($error_text, strtolower('Invalid email address or mobile number. Correct the problem and try again.')) !== false
                        ) {
                            $this->exts->loginFailure(1);
                        }
                    }
                } else {
                    if ($this->exts->getElement($this->remember_me) != null) {
                        $this->exts->click_by_xdotool($this->remember_me);
                    }

                    if ($this->exts->getElement($this->username_selector) != null && $this->exts->getElement("input#ap_email[type=\"hidden\"]") == null) {
                        $this->exts->log("Enter Username");
                        $this->exts->moveToElementAndType($this->username_selector, $this->username);
                        sleep(1);
                    }

                    if ($this->exts->getElement($this->password_selector) != null) {
                        $this->exts->log("Enter Password");
                        $this->exts->moveToElementAndType($this->password_selector, $this->password);
                        sleep(1);
                    }
                    $this->exts->capture("2-filled-login");
                    $this->exts->click_by_xdotool($this->submit_button_selector);
                }
                sleep(3);
            }
            sleep(15);

            $account_switcher_elements = $this->exts->getElements("div.cvf-account-switcher-profile-details-after-account-removed");
            if (count($account_switcher_elements) > 0) {
                $this->exts->log("click account-switcher");
                $account_switcher_elements[0]->click();
                sleep(2);
            } else {
                $account_switcher_elements = $this->exts->getElements("div.cvf-account-switcher-profile-details");
                if (count($account_switcher_elements) > 0) {
                    $this->exts->log("click account-switcher");
                    $account_switcher_elements[0]->click();
                    sleep(2);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->getElement($this->logout_link) != null) {
                $isLoggedIn = true;
            } else if ($this->exts->getElement('#signOut, header button[data-ccx-e2e-id="aac-user-name-dropdown"]') != null) {
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }

        return $isLoggedIn;
    }
    private function isIncorrectCredential()
    {
        $incorrect_credential_keys = [
            'Es konnte kein Konto mit dieser',
            't find an account with that',
            'Falsches Passwort',
            'password is incorrect',
            'password was incorrect',
            'Passwort war nicht korrekt'
        ];
        $error_message = $this->exts->extract('#auth-error-message-box');
        foreach ($incorrect_credential_keys as $incorrect_credential_key) {
            if (strpos(strtolower($error_message), strtolower($incorrect_credential_key)) !== false) {
                return true;
            }
        }
        return false;
    }
    private function processImageCaptcha()
    {
        $this->exts->log("Processing Image Captcha");
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
        }
        $this->exts->processCaptcha('img[alt="captcha"], form[name="signIn"], img[src*="captcha"]', 'input[name="cvf_captcha_input"], form[name="signIn"] input[name="guess"], input[id="captchacharacters"]');
        sleep(2);

        $this->exts->capture("filled-captcha");
        $this->exts->moveToElementAndClick($this->submit_button_selector);
        sleep(2);
    }
    private function processTwoFactorAuth()
    {
        $this->exts->log("Processing Two-Factor Authentication");

        if ($this->exts->getElement("form#auth-mfa-form") != null) {

            //$this->exts->processTwoFactorAuth("input[name=\"otpCode\"]", "form#auth-mfa-form input#auth-signin-button");
            $this->handleTwoFactorCode("input[name=\"otpCode\"]", "form#auth-mfa-form input#auth-signin-button");
        } else if ($this->exts->getElement("form.cvf-widget-form[action=\"verify\"]") != null) {
            $cpText = $this->exts->getElements("form.cvf-widget-form[action=\"verify\"] div.a-row:nth-child(1) span");
            $isTwoFactorText = "";
            if (count($cpText) > 0) {
                foreach ($cpText as $notificationTextElement) {
                    $isTwoFactorText .= $notificationTextElement->getText();
                }
            }

            if (trim($isTwoFactorText) != "" && !empty(trim($isTwoFactorText))) {
                $this->exts->two_factor_notif_msg_en = trim($isTwoFactorText);
                $this->exts->two_factor_notif_msg_de = trim($isTwoFactorText);
            }
            $this->handleTwoFactorCode("input[name=\"code\"]", "form.cvf-widget-form.fwcim-form input.a-button-input[type=\"submit\"]");
        }
    }

    private function handleTwoFactorCode($two_factor_selector, $submit_btn_selector)
    {
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
        }
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
            try {
                $this->exts->log("SIGNIN_PAGE: Entering two_factor_code.");
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
                //$this->webdriver->getKeyboard()->pressKey(WebDriverKeys::ENTER);
                $this->exts->getElement($submit_btn_selector)->click();
                sleep(10);

                if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = "";
                    $this->handleTwoFactorCode($two_factor_selector, $submit_btn_selector);
                }
            } catch (\Exception $exception) {
                $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
            }
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
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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
                $this->exts->moveToElementAndType('[name="dcq_question_date_picker_1_1"]', $year);

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
                    $this->checkFillAnswerSerQuestion($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
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
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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

    private function  processAfterLogin()
    {
        $this->exts->log("Begin processing after login");

        if ($this->exts->getElement('a[href*="/billing/history"]') != null) {
            $this->exts->moveToElementAndClick('a[href*="/billing/history"]');
        } else if ($this->exts->getElement('a#billingHistoryLink') != null) {
            $this->exts->moveToElementAndClick('a#billingHistoryLink');
        } else {
            $this->exts->openUrl($this->orderPageUrl);
        }
        sleep(15);

        $this->filterInvoices();
    }

    private function filterInvoices()
    {
        sleep(25);
        $this->exts->capture("Advertiser-invoices-page");
        if ($this->exts->exists('button[data-e2e-id="aac-ftu-modal-close"]')) {
            $this->exts->moveToElementAndClick('button[data-e2e-id="aac-ftu-modal-close"]');
            sleep(5);
        }
        if ($this->exts->exists('#bannerWrapper')) {
            $this->exts->moveToElementAndClick('#bannerWrapper [class*="Close"]');
            sleep(2);
        }
        if ($this->exts->exists('button[value="paid"]')) {
            $this->exts->click_element('button[value="paid"]');
        }

        $this->exts->capture("Advertiser-invoices-page-1");
        $this->exts->waitTillAnyPresent(['.calendarsContainer input#startCal', 'button[id="dateRangeFilter:openContainer"]']);
        if ($this->exts->exists('.calendarsContainer input#startCal')) {
            $currentStart_date = $this->exts->querySelector('.calendarsContainer input#startCal')->getAttribute('aria-label');
            $date_format = "d/m/Y";
            if (stripos($currentStart_date, "d.m") !== false || stripos($currentStart_date, "m.y") !== false) {
                $date_format = "d.m.Y";
            } else if (stripos($currentStart_date, "d-m") !== false || stripos($currentStart_date, "m-y") !== false) {
                $date_format = "d-m-Y";
            }
            $this->exts->log(__FUNCTION__ . '::Date format ' . $date_format);
            // if restrictpages == 0 then 2 years otherwise 2 month
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
            $endDate = date($date_format);
            if ($restrictPages == 0) {
                $startDate = date($date_format, strtotime('-2 years'));
            } else {
                $startDate = date($date_format, strtotime('-2 months'));
            }

            $this->exts->moveToElementAndType('.calendarsContainer input#startCal', $startDate);
            sleep(1);
            $this->exts->moveToElementAndType('.calendarsContainer input#endCal', $endDate);
            sleep(1);
            $this->exts->capture("Advertiser-invoices-1-filter");
            $this->exts->moveToElementAndClick('.calendarsContainer [type="submit"]');
            sleep(15);
            $this->exts->capture("Advertiser-invoices-2-submitted");
        } elseif ($this->exts->exists('button[id="dateRangeFilter:openContainer"]')) {
            $this->exts->click_element('button[id="dateRangeFilter:openContainer"]');
            sleep(3);
            $this->exts->click_element('button[value="LIFETIME"]');
        }
        sleep(10);
        $this->exts->waitTillPresent('//*[@id="AdvertisingBillingWebAppUI"]/div/div[2]/div[2]/div/div/div/div[1]/div/nav/ul/li[2]/button', 30);
        if ($this->exts->exists('//*[@id="AdvertisingBillingWebAppUI"]/div/div[2]/div[2]/div/div/div/div[1]/div/nav/ul/li[2]/button')) {
            $this->exts->click_element('//*[@id="AdvertisingBillingWebAppUI"]/div/div[2]/div[2]/div/div/div/div[1]/div/nav/ul/li[2]/button');
        }
        $this->downloadAdvertiserInvoices();
    }

    private function downloadAdvertiserInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('div[id*="paid"] div[class="ag-center-cols-container"] div.ag-row[role="row"], table#paidTable > tbody > tr');
        // get invoice   div[id*="paid"] div[class="ag-center-cols-container"] div.ag-row[role="row"]

        $rows = count($this->exts->querySelectorAll('div[id*="paid"] div[class="ag-center-cols-container"] div.ag-row[role="row"], table#paidTable > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->querySelectorAll('div[id*="paid"] div[class="ag-center-cols-container"] div.ag-row[role="row"], table#paidTable > tbody > tr')[$i];
            $download_button = $this->exts->getElement('div[role="gridcell"]:nth-child(9), .dwnld-icon-alignment .dwnld-btn-enb', $row);
            if ($download_button != null) {
                $invoiceName = trim($this->exts->extract('div[role="gridcell"]:nth-child(2), td[id^="invoice-number"]', $row));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $invoiceDate = trim($this->exts->extract('div[role="gridcell"]:nth-child(4), td[id^="invoice-date"]', $row));
                $amountText = trim($this->exts->extract('div[role="gridcell"]:nth-child(8), td[id^="invoice-total"]', $row));
                $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $amountText);
                if (stripos($amountText, 'A$') !== false) {
                    $invoiceAmount = $invoiceAmount . ' AUD';
                } else if (stripos($amountText, '$') !== false) {
                    $invoiceAmount = $invoiceAmount . ' USD';
                } else if (stripos(urlencode($amountText), '%C2%A3') !== false) {
                    $invoiceAmount = $invoiceAmount . ' GBP';
                } else {
                    $invoiceAmount = $invoiceAmount . ' EUR';
                }

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd-M-Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName) || $this->exts->document_exists($invoiceFileName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $this->exts->click_element($download_button);
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(3);
                    if ($this->exts->exists('.a-popover[aria-hidden="false"] a[id$="invoicePDF"]')) {
                        $this->exts->moveToElementAndClick('.a-popover[aria-hidden="false"] a[id$="invoicePDF"]');
                    } elseif ($this->exts->exists('div[aria-hidden="false"] button[value*="pdf"]')) {
                        $this->exts->click_element('div[aria-hidden="false"] button[value*="pdf"]');
                    }
                    sleep(5);
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
        }

        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 && $paging_count < 50 &&
            $this->exts->querySelector('nav[aria-labelledby="table-page-status"] button[aria-label*="next page"], button#next:not([disabled])') != null
        ) {
            $paging_count++;
            $this->exts->click_element('nav[aria-labelledby="table-page-status"] button[aria-label*="next page"], button#next:not([disabled])');
            sleep(5);
            $this->downloadAdvertiserInvoices($paging_count);
        } else if ($restrictPages > 0 && $paging_count < $restrictPages && $this->exts->querySelector('nav[aria-labelledby="table-page-status"] button[aria-label*="next page"], button#next:not([disabled])') != null) {
            $paging_count++;
            $this->exts->click_element('nav[aria-labelledby="table-page-status"] button[aria-label*="next page"], button#next:not([disabled])');
            sleep(5);
            $this->downloadAdvertiserInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
