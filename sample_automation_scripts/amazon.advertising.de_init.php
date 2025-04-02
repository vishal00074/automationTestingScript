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

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if ($this->exts->urlContains('/forgotpassword/reverification')) {
            $this->exts->account_not_ready();
        }
        if ($this->isIncorrectCredential()) {
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
            // if($this->checkCaptcha() || stripos($this->exts->webdriver->getCurrentUrl(), "/ap/cvf/request") !== false) {
            // 	if($this->exts->getElement("form[name=\"claimspicker\"]") != null) {
            // 		$this->exts->moveToElementAndClick("form[name=\"claimspicker\"] input#continue[type=\"submit\"]");
            // 		$this->processTwoFactorAuth();
            // 	} else {
            // 		$this->processImageCaptcha();
            // 	}
            // } else if($this->checkMultiFactorAuth() && stripos($this->exts->webdriver->getCurrentUrl(), "/ap/mfa?") !== false) {
            // 	$this->processTwoFactorAuth();
            // } else if($this->exts->getElement("form.cvf-widget-form[action=\"verify\"] input[name=\"code\"]") != null && stripos($this->exts->webdriver->getCurrentUrl(), "/ap/cvf/verify") !== false) {
            // 	$this->processTwoFactorAuth();
            // }

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
                $this->exts->click_by_xdotool($this->continue_button_selector);
                sleep(2);

                if ($this->exts->getElement($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(3);


                    $this->exts->capture("1-filled-login");
                    $this->exts->click_by_xdotool($this->submit_button_selector);
                } else {
                    $this->exts->capture("login-failed");
                    $this->exts->exitFailure();
                }
            } else {
                if ($this->exts->getElement($this->remember_me) != null && $this->exts->getElement($this->remember_me)->isDisplayed()) {
                    $checkboxElements = $this->exts->getElements($this->remember_me);
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
private function checkCaptcha()
{
    $this->exts->capture("check-captcha");

    $isCaptchaFound = false;
    if ($this->exts->getElement("input#ap_captcha_guess") != null || $this->exts->getElement("input#auth-captcha-guess") != null) {
        $isCaptchaFound = true;
    }

    return $isCaptchaFound;
}
private function checkMultiFactorAuth()
{
    $this->exts->capture("check-two-factor");

    $isTwoFactorFound = false;
    if ($this->exts->getElement("form#auth-mfa-form") != null && $this->exts->getElement("form#auth-mfa-form")->isDisplayed()) {
        $isTwoFactorFound = true;
    } else if ($this->exts->getElement("form.cvf-widget-form[action=\"verify\"]") != null && $this->exts->getElement("form.cvf-widget-form[action=\"verify\"]")->isDisplayed()) {
        $isTwoFactorFound = true;
    }

    return $isTwoFactorFound;
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