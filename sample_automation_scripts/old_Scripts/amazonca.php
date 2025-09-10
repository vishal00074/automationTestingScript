<?php
// Server-Portal-ID: 8531 - Last modified: 31.07.2024 14:19:18 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://www.amazon.ca';
public $orderPageUrl = 'https://www.amazon.ca/gp/css/order-history/ref=nav_youraccount_orders';
public $messagePageUrl = 'https://www.amazon.ca/gp/message?ie=UTF8&ref_=ya_mc_bsm&#!/inbox';
public $businessPrimeUrl = "https://www.amazon.ca/businessprimeversand";
public $login_button_selector = 'div[id="nav-flyout-ya-signin"] a, div[id="nav-signin-tooltip"] a, div#nav-tools a#nav-link-yourAccount';

public $username_selector = 'input[name="email"]:not([type="hidden"])';
public $password_selector = '#ap_password';
public $remember_me_selector = 'input[name="rememberMe"]:not(:checked)';
public $submit_login_selector = '#signInSubmit';
public $continue_button_selector = "form input#continue";

public $check_login_failed_selector = 'div#auth-error-message-box h4';
public $check_login_success_selector = 'a#nav-item-signout, a#nav-item-signout-sa,.nav-right a[href*="sign-out"], .nav-panel a[href*="sign-out"]';

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
public $last_state = array();
public $current_state = array();
public $invalid_filename_keywords = array('agb', 'terms', 'datenschutz', 'privacy', 'rechnungsbeilage', 'informationsblatt', 'gesetzliche', 'retouren', 'widerruf', 'allgemeine gesch', 'mfb-buchung', 'informationen zu zahlung', 'nachvertragliche', 'retourenschein', 'allgemeine_gesch', 'rcklieferschein');

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) 
{
    $this->exts->log('Begin initPortal '.$count);
    if($this->exts->docker_restart_counter == 0) {
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

        $this->exts->log('Download Overview - '.$this->amazon_download_overview);
        $this->exts->log('Download Invoice from message - '.$this->download_invoice_from_message);
        $this->exts->log('Auto Invoice Request - '.$this->auto_request_invoice);

        $this->invalid_filename_pattern = '';
        if(!empty($this->invalid_filename_keywords)) {
            $this->invalid_filename_pattern = '';
            foreach($this->invalid_filename_keywords as $s) {
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
    sleep(5);
    $this->exts->capture("Home-page-with-cookie");
    sleep(5);
    $this->exts->openUrl($this->orderPageUrl);
    sleep(5);
    $this->check_solve_captcha_page();
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if(!$this->isLoginSuccess()) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->orderPageUrl);
        sleep(10);
        $this->check_solve_captcha_page();
        // Login, retry few time since it show captcha
        $this->checkFillLogin();
        sleep(5);
        // retry if captcha showed
        if($this->exts->allExists([$this->password_selector,'input#auth-captcha-guess']) && !$this->isIncorrectCredential()){
            $this->checkFillLogin();
            sleep(5);
        }
        if($this->exts->allExists([$this->password_selector,'input#auth-captcha-guess']) && !$this->isIncorrectCredential()){
            $this->checkFillLogin();
            sleep(5);
        }
        if($this->exts->allExists([$this->password_selector,'input#auth-captcha-guess']) && !$this->isIncorrectCredential()){
            $this->checkFillLogin();
            sleep(5);
            if($this->exts->allExists([$this->password_selector,'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()){
                $this->checkFillLogin();
                sleep(5);
            }
            if($this->exts->allExists([$this->password_selector,'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()){
                $this->checkFillLogin();
                sleep(5);
            }
            if($this->exts->allExists([$this->password_selector,'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()){
                $this->checkFillLogin();
                sleep(5);
            }
        }
        $this->check_solve_captcha_page();
        // End handling login form
        $this->checkFillTwoFactor();
        $this->check_solve_captcha_page();

        if($this->exts->exists('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link')) {
            $this->exts->moveToElementAndClick('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link');
            sleep(2);
        }
    }

    $this->check_solve_captcha_page();
    if($this->isLoginSuccess()) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User logged in');
        $this->exts->capture("3-login-success");

        $this->processAfterLogin();

    // Final, check no invoice
    if($this->isNoInvoice){
        $this->exts->no_invoice();
    }
    $this->exts->success();

    } else {
        $this->exts->log(__FUNCTION__.'::Use login failed');
        $this->exts->log('::URL login failure:: '.$this->exts->getUrl());
        if ($this->exts->urlContains('forgotpassword/reverification')) {
            // Password reset required
            // Please set a new password for your account that you have not used elsewhere.
            // We'll email you a One Time Password (OTP) to authenticate this change.
            $this->exts->account_not_ready();
        } elseif ($this->exts->exists('input#account-fixup-phone-number')) {
            // Add Cell Number
            $this->exts->account_not_ready();
        } elseif($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin() {
    if($this->exts->exists("button.a-button-close.a-declarative")) {
    $this->exts->moveToElementAndClick("button.a-button-close.a-declarative");
    }

    if($this->exts->exists('div.cvf-account-switcher-profile-details-after-account-removed')) {
    $this->exts->log("click account-switcher");
    $this->exts->capture("account-switcher");
    $this->exts->moveToElementAndClick('div.cvf-account-switcher-profile-details-after-account-removed');
    sleep(4);
    }

    if($this->exts->exists($this->password_selector) || $this->exts->exists($this->username_selector)) {
    $this->exts->capture("2-login-page");

    $this->exts->log("Enter Username");
    $this->exts->moveToElementAndType($this->username_selector, $this->username);
    sleep(2);
    if($this->exts->exists('input#auth-captcha-guess')){
        $this->exts->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
    }
    $this->exts->log("Click Continue button");
    $this->exts->moveToElementAndClick($this->continue_button_selector);
    sleep(2);

    $this->exts->log("Enter Password");
    $this->exts->moveToElementAndType($this->password_selector, $this->password);
    sleep(1);
    if($this->exts->exists('input#auth-captcha-guess')){
        $this->exts->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
    }
    $this->exts->moveToElementAndClick($this->remember_me_selector);
    sleep(1);

    $this->exts->capture("2-login-page-filled");
    $this->exts->moveToElementAndClick($this->submit_login_selector);
    sleep(2);
    } else {
    $this->exts->log(__FUNCTION__.'::Login page not found');
    $this->exts->capture("2-login-page-not-found");
    }
    }
    private function checkFillTwoFactor() {
    $this->exts->capture("2.0-two-factor-checking");
    if ($this->exts->exists('div.auth-SMS input[type="radio"], input[type="radio"][value="mobile"]')) {
    $this->exts->moveToElementAndClick('div.auth-SMS input[type="radio"]:not(:checked), input[type="radio"][value="mobile"]:not(:checked)');
    sleep(2);
    $this->exts->moveToElementAndClick('input#auth-send-code, input#continue');
    sleep(5);
    } else if ($this->exts->exists('div.auth-TOTP input[type="radio"], input[type="radio"][value="email"]')) {
    $this->exts->moveToElementAndClick('div.auth-TOTP input[type="radio"]:not(:checked), input[type="radio"][value="email"]:not(:checked)');
    sleep(2);
    $this->exts->moveToElementAndClick('input#auth-send-code, input#continue');
    sleep(5);
    } else if ($this->exts->allExists(['input[type="radio"]', 'input#auth-send-code']) || $this->exts->exists('input[name="OTPChallengeOptions"]')) {
    $this->exts->moveToElementAndClick('input[type="radio"]');
    sleep(2);
    $this->exts->moveToElementAndClick('input#auth-send-code, input#continue');
    sleep(5);
    }

    if($this->exts->exists('input[name="otpCode"]:not([type="hidden"]), input[name="code"], input#input-box-otp')){
    $two_factor_selector = 'input[name="otpCode"]:not([type="hidden"]), input[name="code"], input#input-box-otp';
    $two_factor_message_selector = '#auth-mfa-form h1 + p, #verification-code-form > .a-spacing-small > .a-spacing-none, #channelDetailsForOtp';
    $two_factor_submit_selector = '#auth-signin-button, #verification-code-form input[type="submit"]';
    $this->exts->log("Two factor page found.");
    $this->exts->capture("2.1-two-factor");

    $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
    $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
    $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
    $this->exts->notification_uid = "";
    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if(!empty($two_factor_code)) {
        $this->exts->log("checkFillTwoFactor: Entering two_factor_code: ".$two_factor_code);
        if(!$this->exts->exists($two_factor_selector)){// by some javascript reason, sometime selenium can not find the input
            $this->exts->refresh();
            sleep(5);
            $this->exts->capture("2.1-two-factor-refreshed.".$this->exts->two_factor_attempts);
        }
        $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
        if($this->exts->exists('label[for="auth-mfa-remember-device"] input[name="rememberDevice"]:not(:checked)')){
            $this->exts->moveToElementAndClick('label[for="auth-mfa-remember-device"]');
        }
        sleep(1);
        $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
        if($this->exts->exists('#cvf-submit-otp-button input[type="submit"]')){
            $this->exts->moveToElementAndClick('#cvf-submit-otp-button input[type="submit"]');
        } else {
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
        }
        
        sleep(5);
        $this->exts->waitTillPresent('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', 7);
        $this->exts->capture("2.2-two-factor-submitted-".$this->exts->two_factor_attempts);
    } else {
        $this->exts->log("Not received two factor code");
    }

    // Huy added this 2022-12 Retry if incorrect code inputted
    if($this->exts->exists($two_factor_selector) && $this->exts->exists('#auth-error-message-box .a-alert-content, #invalid-otp-code-message')){
        $temp_text = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
        if(!empty($temp_text)){
            $this->exts->two_factor_notif_msg_en = $temp_text;
        }
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        for ($t=2; $t <= 3; $t++) {
            $this->exts->log("Retry 2FA Message:\n".$this->exts->two_factor_notif_msg_en);
            $this->exts->notification_uid = "";
            $this->exts->two_factor_attempts++;
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if(!empty($two_factor_code)) {
                $this->exts->log("Retry 2FA: Entering two_factor_code: ".$two_factor_code);
                if(!$this->exts->exists($two_factor_selector)){// by some javascript reason, sometime selenium can not find the input
                    $this->exts->refresh();
                    sleep(5);
                    $this->exts->capture("2.1-two-factor-refreshed.".$this->exts->two_factor_attempts);
                }
                
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                if($this->exts->exists('label[for="auth-mfa-remember-device"] input[name="rememberDevice"]:not(:checked)')){
                    $this->exts->moveToElementAndClick('label[for="auth-mfa-remember-device"]');
                }
                sleep(1);
                $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
                if($this->exts->exists('#cvf-submit-otp-button input[type="submit"]')){
                    $this->exts->moveToElementAndClick('#cvf-submit-otp-button input[type="submit"]');
                } else {
                    $this->exts->moveToElementAndClick($two_factor_submit_selector);
                }
                sleep(10);
                $this->exts->capture("2.2-two-factor-submitted-".$this->exts->two_factor_attempts);
                if(!$this->exts->exists($two_factor_selector)){
                    break;
                }
            } else {
                $this->exts->log("Not received Retry two factor code");
            }
        }
    }
    } else if($this->exts->exists('div.otp-input-box-container input[name*="otc"]')){
    $two_factor_selector = 'div.otp-input-box-container input[name*="otc"], input#input-box-otp';
    $two_factor_message_selector = 'div#channelDetailsForOtp';
    $two_factor_submit_selector = 'form#verification-code-form input[type="submit"][aria-labelledby="cvf-submit-otp-button-announce"]';

    if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }

        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
            $this->exts->moveToElementAndClick($two_factor_selector);
            sleep(1);
            $this->exts->simulatetypekeys($two_factor_code);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(10);

            if($this->exts->exists($two_factor_selector)){
                $temp_text = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
                if(!empty($temp_text)){
                    $this->exts->two_factor_notif_msg_en = $temp_text;
                }
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
                for ($t=2; $t <= 3; $t++) {
                    $this->exts->log("Retry 2FA Message:\n".$this->exts->two_factor_notif_msg_en);
                    $this->exts->notification_uid = "";
                    $this->exts->two_factor_attempts++;
                    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
                    if(!empty($two_factor_code)) {
                        $this->exts->log("Retry 2FA: Entering two_factor_code: ".$two_factor_code);
                        $this->exts->moveToElementAndClick($two_factor_selector);
                        sleep(1);
                        $this->exts->simulatetypekeys($two_factor_code);
                        $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
                        
                        $this->exts->moveToElementAndClick($two_factor_submit_selector);
                        sleep(10);
                        $this->exts->capture("2.2-two-factor-submitted-".$this->exts->two_factor_attempts);
                        if(!$this->exts->exists($two_factor_selector)){
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
    } else if($this->exts->exists('[name="transactionApprovalStatus"], form[action*="/approval/poll"]')){
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
    if(!empty($two_factor_code) && stripos($two_factor_code, 'OK') !== false) {
        sleep(7);
    } else {
        sleep(5*60);
        $this->exts->update_process_lock();
        if($this->exts->exists('[name="transactionApprovalStatus"], form[action*="/approval/poll"]')){
            $this->exts->two_factor_expired();
        }
    }
    }
}
private function isIncorrectCredential(){
$incorrect_credential_keys = [
'Es konnte kein Konto mit dieser', 
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
if(strpos(strtolower($error_message), strtolower($incorrect_credential_key)) !== false){
    return true;
}
}
return false;
}
private function check_solve_captcha_page(){
    $captcha_iframe_selector = '#aa-challenge-whole-page-iframe';
    $image_selector = 'img[src*="captcha"]';
    $captcha_input_selector = 'input#captchacharacters, input#aa_captcha_input, input[name="cvf_captcha_input"]';
    $captcha_submit_button = 'button[type="submit"], [name="submit_button"], [type="submit"][value="verifyCaptcha"]';
    if($this->exts->exists($captcha_iframe_selector)){
    $this->exts->switchToFrame($captcha_iframe_selector);
    }
    if($this->exts->allExists([$image_selector, $captcha_input_selector, $captcha_submit_button])) {
    $this->exts->processCaptcha($image_selector, $captcha_input_selector);
    $this->exts->moveToElementAndClick($captcha_submit_button);
    sleep(5);
    $this->exts->switchToDefault();
    if($this->exts->exists($captcha_iframe_selector)){
        $this->exts->switchToFrame($captcha_iframe_selector);
    }
    if ($this->exts->allExists([$image_selector, $captcha_input_selector, $captcha_submit_button])) {
        $this->exts->processCaptcha($image_selector, $captcha_input_selector);
        $this->exts->moveToElementAndClick($captcha_submit_button);
        sleep(5);
    }
    $this->exts->switchToDefault();
    if($this->exts->exists($captcha_iframe_selector)){
        $this->exts->switchToFrame($captcha_iframe_selector);
    }
    if ($this->exts->allExists([$image_selector, $captcha_input_selector, $captcha_submit_button])) {
        $this->exts->processCaptcha($image_selector, $captcha_input_selector);
        $this->exts->moveToElementAndClick($captcha_submit_button);
        sleep(5);
    }
    }
    $this->exts->switchToDefault();
}
private function captcha_required(){
    // Supporting de, fr, en, es, it, nl language
    $captcha_required_keys = [
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
    if(strpos(strtolower($error_message), strtolower($captcha_required_key)) !== false){
        return true;
    }
    }
    return false;
}
private function isLoginSuccess()
{
  return $this->exts->exists($this->check_login_success_selector) && !$this->exts->exists($this->password_selector);
}

function processAfterLogin($count=0) {
    $this->exts->log("Begin processAfterLogin ".$count);
    if($count == 0) {
    $this->openUrl($this->orderPageUrl);
    sleep(2);
    }

    if(stripos($this->exts->getUrl(), "amazon.ca/ap/signin") === false) {
    $isMultiAccount = count($this->exts->getElements("select[name=\"selectedB2BGroupKey\"] option")) > 1 ? true : false;
    $this->exts->log("isMultiAccount - ".$isMultiAccount);

    if(empty($this->last_state['stage']) || $this->last_state['stage'] == 'ORDER') {
        // keep current state of processing
        $this->current_state['stage'] = 'ORDER';
        $this->last_state['stage'] = '';
        
        if($isMultiAccount > 0) {
            // Get Business Accounts Filter, only first time of execution, not in restart
            $optionAccountSelectors = array();
            $selectAccountElements = $this->exts->getElements("select[name=\"selectedB2BGroupKey\"] option");
            if(count($selectAccountElements) > 0) {
                foreach($selectAccountElements as $selectAccountElement) {
                    $elementAccountValue = trim($selectAccountElement->getAttribute('value'));
                    $optionAccountSelectors[] = $elementAccountValue;
                }
            }
            
            $this->exts->log("optionAccountSelectors ".count($optionAccountSelectors));
            if(!empty($optionAccountSelectors)) {
                foreach($optionAccountSelectors as $optionAccountSelector) {
                    // In restart mode, process only those account which is not processed yet
                    if($this->exts->docker_restart_counter > 0 && !empty($this->last_state['accounts']) && in_array($optionAccountSelector, $this->last_state['accounts'])) {
                        $this->exts->log("Restart: Already processed earlier - Account-value  ".$optionAccountSelector);
                        continue;
                    }
                    
                    $this->exts->log("Account-value  ".$optionAccountSelector);
                    
                    // Fill Account Select
                    // $this->exts->getElement("select[name=\"orderFilter\"]")->selectOptionByValue($yearOrderSelection);
                    $optionSelAccEle = "select[name=\"selectedB2BGroupKey\"] option[value=\"".$optionAccountSelector."\"]";
                    $this->exts->log("processing account element  ".$optionSelAccEle);
                    $this->exts->moveToElementAndClick($optionSelAccEle);
                    sleep(5);
                    
                    $this->exts->capture("Account-Selected-".$optionAccountSelector);
                    
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

//Check Business Prime Account
//https://www.amazon.ca/businessprimeversand
if(empty($this->last_state['stage']) || $this->last_state['stage'] == 'BUSINESS_PRIME') {
    // Keep current state of processing
    $this->current_state['stage'] = 'BUSINESS_PRIME';
    $this->last_state['stage'] = '';
    
    $this->downloadBusinessPrimeInvoices();
}

// Process Message Center
if(empty($this->last_state['stage']) || $this->last_state['stage'] == 'MESSAGE') {
    // Keep current state of processing
    $this->current_state['stage'] = 'MESSAGE';
    $this->last_state['stage'] = '';
    
    $this->triggerMsgInvoice();
}
} else {
if($this->login_tryout == 0) {
    $this->checkFillLogin(0);
}
}
}
function orderYearFilters($selectedBusinessAccount = "") {
    if(trim($selectedBusinessAccount) != "") {
    $this->exts->log("selectedBusinessAccount Account-value  ".$selectedBusinessAccount);
}

// Get Order Filter years
$optionSelectors = array();
$selectElements = $this->exts->getElements("select[name=\"orderFilter\"] option");
$this->exts->log("selectElements ".count($selectElements));
if(count($selectElements) > 0) {
$restrictYears = array();
if(!empty($this->only_years)) {
    $this->exts->log("only_years - ".$this->only_years);
    $restrictYears = explode(",", $this->only_years);
    if(!empty($restrictYears)) {
        foreach($restrictYears as $key => $restrictYear) {
            $restrictYears[$key] = strtolower("year-".$restrictYear);
        }
    }
}
$this->exts->log("restrictYears ".print_r($restrictYears, true));

if((int)@$this->restrictPages > 0 ) {
    $elementValue = trim($selectElements[1]->getAttribute('value'));
    $optionSelectors[] = $elementValue;
} else {
    foreach($selectElements as $selectElement) {
        $elementValue = trim($selectElement->getAttribute('value'));
        $this->exts->log("elementValue - ".$elementValue);
        if(!empty($this->only_years)) {
            if(count($optionSelectors) < count($restrictYears)) {
                if(in_array(strtolower($elementValue), $restrictYears)) {
                    $optionSelectors[] = $elementValue;
                }
            } else {
                break;
            }
        } else {
            if($elementValue != "last30" && $elementValue != "months-6") {
                $optionSelectors[] = $elementValue;
            }
        }
    }
}
}

$this->exts->log("optionSelectors ".count($optionSelectors));
if(!empty($optionSelectors)) {
for($i=0; $i<count($optionSelectors); $i++) {
    $this->exts->log("year-value  ".$optionSelectors[$i]);
}

// Process Each Year
$this->processYears($optionSelectors);
}
}
function processYears($optionSelectors=array()) {
$this->exts->capture("Process-Years");

foreach($optionSelectors as $optionSelector) {
$this->exts->log("processing year  ".$optionSelector);

if($this->dateLimitReached == 1) break;

// In restart mode, process only those years which is not processed yet
if($this->exts->docker_restart_counter > 0 && !empty($this->last_state['years']) && in_array($optionSelector, $this->last_state['years'])) {
    $this->exts->log("Restart: Already processed year - ".$optionSelector);
    continue;
}

// Fill order Select
// $this->exts->getElement("select[name=\"orderFilter\"]")->selectOptionByValue($yearOrderSelection);
$optionSelEle = "select[name=\"orderFilter\"] option[value=\"".$optionSelector."\"]";
$this->exts->log("processing year element  ".$optionSelEle);
$this->exts->moveToElementAndClick($optionSelEle);
sleep(2);

if($this->exts->getElement($this->password_selector) != null || stripos($this->exts->getUrl(), "/ap/signin?") !== FALSE) {
    if($this->login_tryout == 0) {
        $this->checkFillLogin(0);
    }
}

$this->exts->capture("orders-".$optionSelector);
$pages = $this->getTotalYearPages(0);

$this->exts->log("Total pages -".$pages);
$total_pages_found = $pages;

$hrefsArr = array();
if($total_pages_found > 1) {
    $firstPageHref = $this->exts->getUrl();
    if($this->start_page == 0) $this->start_page = 1;
    
    $l = -1;
    if($this->exts->getElement("span.num-orders-for-orders-by-date span.num-orders") != null) {
        $total_data = $this->exts->getElement("span.num-orders-for-orders-by-date span.num-orders")->getText();
        $this->exts->log("total_data -".$total_data);
        $tempArr = explode(" ", $total_data);
        if(count($tempArr)) {
            $total_data = trim($tempArr[0]);
        }
        $this->exts->log("total_data -".$total_data);
        if((int)$total_data > 0) {
            $l = round($total_data / 10);
        }
    }
    
    if($l == -1) {
        $pageEle = $this->exts->webdriver->findElements(WebDriverBy::cssSelector("div.pagination-full a"));
        $liCount = count($pageEle);
        if($liCount > 2) {
            $l = (int)trim($pageEle[$liCount - 2]->getText());
        }
    }
    
    $pageEle = $this->exts->webdriver->findElements(WebDriverBy::cssSelector("div.pagination-full a"));
    $this->exts->log("Paging Total element -".count($pageEle));
    if(count($pageEle) > 0) {
        $href = $pageEle[0]->getAttribute("href");
        $this->exts->log("First Loading page url -".$href);
        $firstPageHref = $href;
        $href = substr($href, 0, strlen($href)-1);
        if(stripos($href, "https://www.amazon.ca") === false && stripos($href, "https://") === false) {
            $href = "https://www.amazon.ca".trim($href);
        }
    }
    $this->exts->log("First Loading page url -".$href);
    
    // In restart mode start from where it was left
    if($this->exts->docker_restart_counter > 0 && !empty($this->last_state['last_page_count'])) {
        $this->start_page = (int)$this->last_state['last_page_count'];
    }
    
    for($i=$this->start_page; $i<=$total_pages_found; $i++) {
        if($i == 1) {
            $hrefsArr[] = array(
                "url" => $firstPageHref,
                'page' => $i
            );
        } elseif($i == $this->start_page && !empty($this->last_state['order_page_list']) && $this->start_page > 1) {
            $hrefsArr[] = array(
                "url" => $this->last_state['order_page_list'],
                'page' => $i
            );
        } else {
            $hrefsArr[] = array(
                "url" => $href.(($i-1)*10),
                'page' => $i
            );
        }
    }
} else {
    $selectedB2BGroupKey = "";
    $currentUrl = $this->exts->getUrl();
    preg_match('/selectedB2BGroupKey\=[^&]+/', $currentUrl, $matches);
    if(count($matches) > 0) {
        $this->exts->log("GOT B2B ACCOUNT -".$matches[0]);
    } else {
        $selectedB2BGroupKey = "";
    }
    
    $hrefsArr[] = array(
        "url" => $currentUrl,
        'page' => 1
    );
}

$this->exts->log("Order pages url total - " . count($hrefsArr));
if(count($hrefsArr) > 0) {
    $firstPageUrl = $hrefsArr[0]['url'];
    $currentPageCount = 1;
    foreach($hrefsArr as $key1 => $hrefArr) {
        $this->exts->log("Crawling orders page - " . $this->exts->getUrl());
        $this->isNoInvoice = false;
        
        try {
            if($key1 == 0) {
                $this->openUrl($firstPageUrl);
            } else {
                if($this->exts->getElement("div.pagination-full ul.a-pagination li.a-last a[href*=\"/gp/your-account/order-history/ref=oh_aui_pagination\"]") != null) {
                    $this->exts->moveToElementAndClick("div.pagination-full ul.a-pagination li.a-last a[href*=\"/gp/your-account/order-history/ref=oh_aui_pagination\"]");
                    $this->exts->log("Clicked next page");
                } else if($this->exts->getElement("div.pagination-full ul.a-pagination li.a-last a[href*=\"/gp/your-account/order-history/ref=ppx_yo_dt_b_pagination\"]") != null) {
                    $this->exts->moveToElementAndClick("div.pagination-full ul.a-pagination li.a-last a[href*=\"/gp/your-account/order-history/ref=ppx_yo_dt_b_pagination\"]");
                    $this->exts->log("Clicked next page");
                } else {
                    break;
                }
            }
            
            if($this->dateLimitReached == 1) break;
            sleep(4);
            
            if($this->exts->getElement($this->password_selector) != null || stripos($this->exts->getUrl(), "/ap/signin?") !== false) {
                $this->checkFillLogin(0);
                sleep(4);
            }
            
            // $this->exts->capture("invoice pagination-".$optionSelector."-".$key1);
            if($this->exts->getElement("div.a-box-group.a-spacing-base.order") != null) {
                $this->exts->log("Invoice Found");
                
                $invoice_data_arr = array();
                $rows = $this->exts->getElements("div.a-box-group.a-spacing-base.order");
                $this->exts->log("Invoice Rows- " . count($rows));
                if(count($rows) > 0) {
                    for($i=0, $j=2; $i<count($rows); $i++,$j++) {
                        $rowItem = $rows[$i];
                        try {
                            $columns =    
                            $this->exts->querySelector("div.order-info div.a-fixed-right-grid-col:nth-child(1) span.a-color-secondary.value", $rowItem);
                            $this->exts->log("Invoice Row columns- $i - " . count($columns));
                            if(count($columns) > 0) {
                                $invoice_date = trim($columns[0]->getText());
                                $this->exts->log("invoice_date - " . $invoice_date);
                                
                                $invoice_amount = trim($columns[count($columns)-1]->getText());
                                $this->exts->log("invoice_amount - " . $invoice_amount);
                                
                                if(stripos($invoice_amount, "EUR") !== false && stripos($invoice_amount, "EUR") <= 1) {
                                    $invoice_amount = trim(substr($invoice_amount, 3))." EUR";
                                } else if(stripos($invoice_amount, "EUR") !== false && stripos($invoice_amount, "EUR") > 1) {
                                    $invoice_amount = trim(substr($invoice_amount, 3, strlen($invoice_amount)-3))." EUR";
                                } else if(stripos($invoice_amount, "USD") !== false && stripos($invoice_amount, "USD") <= 1) {
                                    $invoice_amount = trim(substr($invoice_amount, 3))." USD";
                                } else if(stripos($invoice_amount, "USD") !== false && stripos($invoice_amount, "USD") > 1) {
                                    $invoice_amount = trim(substr($invoice_amount, 3, strlen($invoice_amount)-3))." USD";
                                } else if(stripos($invoice_amount, "CDN$") !== false && stripos($invoice_amount, "CDN$") <= 1) {
                                    $invoice_amount = trim(substr($invoice_amount, 4))." CAD";
                                } else if(stripos($invoice_amount, "CDN$") !== false && stripos($invoice_amount, "CDN$") > 1) {
                                    $invoice_amount = trim(substr($invoice_amount, 3, strlen($invoice_amount)-4))." CAD";
                                }
                                
                                $columns = $this->exts->querySelector("div.order-info div.a-fixed-right-grid-col.actions span.a-color-secondary.value", $rowItem);
                                $invoice_number = trim($columns[count($columns)-1]->getText());
                                $this->exts->log("invoice_number - " . $invoice_number);
                                
                                if(!$this->exts->invoice_exists($invoice_number)) {
                                    $sellerName = "";
                                    $sellerColumns =$this->exts->querySelector("div.a-box.shipment div.a-fixed-left-grid-col.a-col-right span.a-color-secondary", $rowItem);
                                    if(count($sellerColumns) > 0) {
                                        $sellerName =trim($sellerColumns[0]->getText());
                                    } else {
                                        $sellerColumns = $this->exts->querySelector("div.a-box div.a-fixed-left-grid-col.a-col-right .a-row", $rowItem);
                                        if(count($sellerColumns) > 0) {
                                            $sellerName = trim($sellerColumns[0]->getText());
                                            if(trim($sellerName) != "" && stripos(trim($sellerName), ": Amazon.com.ca, Inc") !== false && count($sellerColumns) > 1) {
                                                $sellerColumns1 = $this->exts->querySelector("div.a-box div.a-fixed-left-grid-col.a-col-right .a-row", $rowItem);
                                                if(count($sellerColumns1) > 0) {
                                                    foreach($sellerColumns1 as $sellerColumnEle) {
                                                        $sellerColumnEleText = trim($sellerColumnEle->getText());
                                                        if(trim($sellerColumnEleText) != "" && stripos(trim($sellerColumnEleText), ": Amazon.com.ca, Inc") === false) {
                                                            $sellerName = trim($sellerColumnEleText);
                                                            break;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $this->exts->log("Seller Name - " . $sellerName);
                                    
                                    $detailPageUrl = "";
                                    $columns = $this->exts->querySelector("div.order-info div.a-fixed-right-grid-col.actions ul a.a-link-normal", $rowItem);
                                    if(count($columns) > 0) {
                                        $detailPageUrl = $columns[0]->getAttribute("href");
                                        if(stripos($detailPageUrl, "https://www.amazon.ca") === false && stripos($detailPageUrl, "https://") === false) {
                                            $detailPageUrl = "https://www.amazon.ca".trim($detailPageUrl);
                                        }
                                        $this->exts->log("Detail page URL - " . $detailPageUrl);
                                        
                                        $filename = trim($invoice_number).".pdf";
                                        
                                        //Stop Downloading invoice if invoice is older than 90 days. 45*24 = 1080
                                        if($this->last_invoice_date != "" && !empty($this->last_invoice_date)) {
                                            $last_date_timestamp = strtotime($this->last_invoice_date);
                                            $last_date_timestamp = $last_date_timestamp-(1080*60*60);
                                            $parsed_date = $this->exts->parse_date($invoice_date);
                                            if(trim($parsed_date) != "") $invoice_date = $parsed_date;
                                            if($last_date_timestamp > strtotime($invoice_date) && trim($parsed_date) != "") {
                                                $this->exts->log("Skip invoice download as it is not newer than " . $this->last_invoice_date . " - " . $invoice_date);
                                                $this->dateLimitReached = 1;
                                                break;
                                            }
                                        }
                                        
                                        if(trim($detailPageUrl) != "" && $this->dateLimitReached == 0) {
                                            if($this->last_invoice_date != "" && !empty($this->last_invoice_date)) {
                                                $last_date_timestamp = strtotime($this->last_invoice_date);
                                                $last_date_timestamp = $last_date_timestamp - (1080*60*60);
                                            }
                                            $parsed_date = $this->exts->parse_date($invoice_date);
                                            if(trim($parsed_date) != "") $invoice_date = $parsed_date;
                                            if($last_date_timestamp > strtotime($invoice_date) && $this->last_invoice_date != "" && !empty($this->last_invoice_date) && trim($parsed_date) != "") {
                                                $this->exts->log("Skip invoice download as it is not newer than " . $this->last_invoice_date . " - " . $invoice_date);
                                                $this->dateLimitReached = 1;
                                                break;
                                            } else {
                                                $prices = array();
                                                $price_blocks = $this->exts->querySelector("div.a-box.shipment div.a-fixed-left-grid-col.a-col-right span.a-color-price", $rowItem);                                                
                                                if(count($price_blocks) > 0) {
                                                    foreach($price_blocks as $price_block) {
                                                        $currentBlockPrice = $price_block->getText();
                                                        $currentBlockPrice = trim($currentBlockPrice);
                                                        $currentBlockPrice = str_replace("EUR", "", $currentBlockPrice);
                                                        $currentBlockPrice = str_replace(".", "", $currentBlockPrice);
                                                        $currentBlockPrice = str_replace(",", ".", $currentBlockPrice);
                                                        
                                                        $prices[] = $currentBlockPrice;
                                                    }
                                                }
                                                
                                                //Open Detail page and get invoice URL OR Invoice Request URL and Overview URL
                                                
                                                // Open New window To process Invoice
                                                $this->exts->open_new_window();
                                                
                                                // Call Processing function to process current page invoices
                                                $this->openUrl($detailPageUrl);
                                                sleep(2);
                                                
                                                if(stripos($this->exts->getUrl(), "amazon.ca/ap/signin") !== false) {
                                                    $this->checkFillLogin(0);
                                                    sleep(4);
                                                }
                                                
                                                $invoice_urls = array();
                                                $links = $this->getItemLinks();
                                                sleep(2);
                                                
                                                $this->exts->log(print_r($links, true));
                                                
                                                // Close new window
                                                $this->exts->close_new_window();
                                                
                                                if(empty($links)) {
                                                    continue;
                                                }
                                                
                                                // Find overview link
                                                $overview_link = "";
                                                foreach($links as $link_item) {
                                                    //$currItemLink = $link_item->getAttribute('href');
                                                    $currItemLink = trim($link_item['link']);
                                                    if(stripos($currItemLink, "print.html") !== false) {
                                                        $overview_link = $currItemLink;
                                                        break;
                                                    }
                                                }
                                                
                                                // Find contact link
                                                $contact_link = "";
                                                foreach($links as $link_item) {
                                                    //$currItemLink = $link_item->getAttribute('href');
                                                    $currItemLink = trim($link_item['link']);
                                                    if(stripos($currItemLink, "contact.html") !== false) {
                                                        $contact_link = $currItemLink;
                                                        break;
                                                    }
                                                }
                                                
                                                // Find invoice links
                                                $inv_num = 1;
                                                foreach($links as $lkey =>  $link_item) {
                                                    //$currItemLinkText = $link_item->getText();
                                                    $currItemLinkText = trim($link_item['text']);
                                                    
                                                    if(stripos($currItemLinkText, "Credit note") === false) {
                                                        // Sometime in .de language appears as english, so alongwith Rechnung, replace Invoice
                                                        $currItemLinkText = str_replace("Rechnung ","",$currItemLinkText);
                                                        $currItemLinkText = str_replace("Invoice ","",$currItemLinkText);
                                                        $currItemLinkText = str_replace("Facture ","",$currItemLinkText);
                                                        
                                                        if((int)trim($currItemLinkText) == $inv_num) {
                                                            //$currItemLink = $link_item->getAttribute('href');
                                                            $currItemLink = trim($link_item['link']);
                                                            
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
                                                
                                                // Check if invoice request link is available, then request invoice
                                                if(trim($contact_link) != "") {
                                                    $invoice_urls[] = array(
                                                        'link' => $overview_link,
                                                        'overview_link' => $overview_link,
                                                        'contact_url' => $contact_link,
                                                        'price' => 0,
                                                        'is_credit_note' => 0
                                                    );
                                                }
                                                
                                                $inv_num = 1;
                                                if(trim($contact_link) == "") {
                                                    // Download credit note only if no contact link is available, because if contact link is available
                                                    // system will download overview and do a invoice request
                                                    // virtually in this way, system will never download credit note and in either way we don't need it.
                                                    foreach($links as $lkey =>  $link_item) {
                                                        //$currItemLinkText = $link_item->getText();
                                                        $currItemLinkText = trim($link_item['text']);
                                                        
                                                        if(stripos($currItemLinkText, "Credit note") !== false) {
                                                            $currItemLinkText = str_replace("Credit note ","",$currItemLinkText);
                                                            if((int)trim($currItemLinkText) == $inv_num) {
                                                                //$currItemLink = $link_item->getAttribute('href');
                                                                $currItemLink = trim($link_item['link']);
                                                                
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
                                                            $currItemLinkText = str_replace("invoice adjustment ","",$currItemLinkText);
                                                            if((int)trim($currItemLinkText) == $inv_num) {
                                                                //$currItemLink = $link_item->getAttribute('href');
                                                                $currItemLink = trim($link_item['link']);
                                                                
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
                                                
                                                if(empty($invoice_urls)) {
                                                    $invoice_urls[] = array(
                                                        'link' => trim($links[0]['link']),
                                                        'overview_link' => $overview_link,
                                                        'contact_url' => $contact_link,
                                                        'price' => 0,
                                                        'is_credit_note' => 0
                                                    );
                                                }
                                                
                                                if(!empty($invoice_urls)) {
                                                    $invoicePrefix = 0;
                                                    $invoiceSize = 0;
                                                    $savedInvoices = array();
                                                    
                                                    foreach($invoice_urls as $invoice_url_item) {
                                                        $item_invoice_number = $invoice_number;
                                                        $this->exts->log("Invoice url - ".$invoice_url_item['link']);
                                                        $this->exts->log("Invoice Overview url - ".$invoice_url_item['overview_link']);
                                                        $this->exts->log("Invoice cost - ".$invoice_url_item['price']);
                                                        $this->exts->log("Invoice is_credit_note - ".$invoice_url_item['is_credit_note']);
                                                        
                                                        $contact_url = $invoice_url_item['contact_url'];
                                                        $invoice_url = $invoice_url_item['link'];
                                                        $overview_link = $invoice_url_item['overview_link'];
                                                        $orderPrice = $invoice_url_item['price'];
                                                        
                                                        if((int)$invoice_url_item['is_credit_note'] == 1) {
                                                            $item_invoice_number = $item_invoice_number."-CN";
                                                        } else {
                                                            if(stripos($invoice_url, "print") === false) {
                                                                $contact_url = "";
                                                            }
                                                        }
                                                        
                                                        if(trim($invoice_url) != "") {
                                                            if(stripos($invoice_url, "contact") !== false) {
                                                                $this->exts->querySelector("div.order-info a.a-link-normal", $rowItem);
                                                                $contact_url = $invoice_url;
                                                                $invoice_url = $links[1]->getAttribute('href');
                                                            }
                                                        }
                                                        
                                                        if(trim($invoice_url) == "") {
                                                            $invoice_url = trim($detailPageUrl)."&print=1";
                                                        }
                                                        $this->exts->log("invoice_url - ".$invoice_url);
                                                        
                                                        // If seller is amazon, then download orders only after 2 day.
                                                        // we have noticed sometime it downloads credit note linked as invoice
                                                        if(trim($sellerName) != "" && trim($sellerName) == "Sold by: Amazon.com.ca, Inc.") {
                                                            $this->exts->log("invoiceDate - ".$invoice_date);
                                                            $timeDiff = strtotime("now") - strtotime($invoice_date);
                                                            $diffDays = ceil($timeDiff / (3600 * 24));
                                                            $this->exts->log("diffDays - ".$diffDays);
                                                            if($diffDays < 2) {
                                                                $invoice_url = "";
                                                                $this->exts->log("Skipped Amazon seller invoice as it is not 2 days old");
                                                                continue;
                                                            }
                                                        }
                                                        
                                                        if(stripos($invoice_url, "oh_aui_ajax_request_invoice") !== false) {
                                                            $invoice_url = "";
                                                            $this->exts->log("Skipped Business Account, No Invoice Url, No Auto Request url");
                                                        }
                                                        
                                                        if(trim($invoice_url) != "") {
                                                            if($invoicePrefix > 0) {
                                                                $item_invoice_number = $item_invoice_number."-".$invoicePrefix;
                                                                $filename = $item_invoice_number.".pdf";
                                                            }
                                                            
                                                            if(stripos($invoice_url, "https://www.amazon.ca") === false && stripos($invoice_url, "https://") === false) {
                                                                $invoice_url = "https://www.amazon.ca".$invoice_url;
                                                            }
                                                            $this->exts->log("invoice_url - ".$invoice_url);
                                                            
                                                            if(trim($overview_link) != "" && stripos($overview_link, "https://www.amazon.ca") === false && stripos($overview_link, "https://") === false) {
                                                                $overview_link = "https://www.amazon.ca".$overview_link;
                                                            }
                                                            
                                                            if(stripos($invoice_url, "print") !== false) {
                                                                // Check if user has opted for auto invoice request, then download overview only if amazon is not seller
                                                                $download_overview = $this->amazon_download_overview;
                                                                if((int)$this->auto_request_invoice == 1 && $contact_url != "") {
                                                                    $download_overview = 1;
                                                                }
                                                                
                                                                if($download_overview == 1 && trim($sellerName) != "" && trim($sellerName) != "Sold by: Amazon.com.ca, Inc.") {
                                                                    $this->exts->log("Downloading overview page as invoice");
                                                                    
                                                                    $this->exts->log("New Overview invoiceName- ".$item_invoice_number);
                                                                    $this->exts->log("New Overview invoiceAmount- ".$invoice_amount);
                                                                    $this->exts->log("New Overview Filename- ".$filename);
                                                                    
                                                                    //Sometime while capturing overview page we get login form, but not in opening any other page.
                                                                    //So detect such case and process login again
                                                                    
                                                                    $currentUrl = $this->exts->getUrl();
                                                                    
                                                                    // Open New window To process Invoice
                                                                    $this->exts->open_new_window();
                                                                    
                                                                    // Call Processing function to process current page invoices
                                                                    $this->openUrl($invoice_url);
                                                                    sleep(2);
                                                                    
                                                                    if(stripos($this->exts->getUrl(), "amazon.ca/ap/signin") !== false) {
                                                                        $this->checkFillLogin(0);
                                                                        sleep(4);
                                                                    }
                                                                    
                                                                    if(stripos($this->exts->getUrl(), "amazon.ca/ap/signin") === false) {
                                                                        $downloaded_file = $this->exts->download_current($filename,5);
                                                                        if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                                                            $pdf_content = file_get_contents($downloaded_file);
                                                                            if(stripos($pdf_content, "%PDF") !== false) {
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
                                                                                $this->exts->log("Not Valid PDF - ".$filename);
                                                                            }
                                                                        }
                                                                    }
                                                                    
                                                                    // Close new window
                                                                    $this->exts->close_new_window();
                                                                } else {
                                                                    if(trim($sellerName) != "" && trim($sellerName) == "Sold by: Amazon.com.ca, Inc.") {
                                                                        $this->exts->log("Skip download overview for amazon");
                                                                    } else {
                                                                        $this->exts->log("Skip download overview as user has not opted for");
                                                                    }
                                                                }
                                                            } else {
                                                                $downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
                                                                if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                                                    $pdf_content = file_get_contents($downloaded_file);
                                                                    if(stripos($pdf_content, "%PDF") !== false) {
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
                                                                        $this->exts->open_new_window();
                                                                        
                                                                        // Call Processing function to process current page invoices
                                                                        $this->openUrl($invoice_url);
                                                                        sleep(2);
                                                                        
                                                                        if(stripos($this->exts->getUrl(), "amazon.ca/ap/signin") !== false) {
                                                                            $this->checkFillLogin(0);
                                                                            sleep(4);
                                                                            
                                                                            unlink($downloaded_file);
                                                                            $downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
                                                                            if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                                                                $pdf_content = file_get_contents($downloaded_file);
                                                                                if(stripos($pdf_content, "%PDF") !== false) {
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
                                                                        $this->exts->close_new_window();
                                                                    }
                                                                }
                                                            }
                                                            $invoicePrefix++;
                                                        }
                                                    }
                                                }
                                                
                                                if(!empty($savedInvoices)) {
                                                    foreach($savedInvoices as $sikey => $savedInvoice) {
                                                        $this->exts->log("Invoice Name - ".$savedInvoice['invoiceName']);
                                                        $this->exts->log("Invoice Date - ".$savedInvoice['invoiceDate']);
                                                        $this->exts->log("Invoice Amount - ".$savedInvoice['invoiceAmount']);
                                                        $this->exts->log("Invoice filename - ".$savedInvoice['filename']);
                                                        $this->exts->log("Invoice invoiceSize - ".$savedInvoice['invoiceSize']);
                                                        $this->exts->log("Invoice contact_url - ".$savedInvoice['contact_url']);
                                                        $this->exts->log("Invoice invoice_url - ".$savedInvoice['invoice_url']);
                                                        $this->exts->log("Invoice orderPrice - ".$savedInvoice['orderPrice']);
                                                        
                                                        $useOrderPrice = 0;
                                                        $inv_size = 0;
                                                        if($sikey == 0) {
                                                            $inv_size = $savedInvoice['invoiceSize'];
                                                        } else {
                                                            if($inv_size == $savedInvoice['invoiceSize'] && $sikey == 1) {
                                                                $useOrderPrice = 1;
                                                            }
                                                        }
                                                    }
                                                    $this->exts->log("Use order price -  -".$useOrderPrice);
                                                    
                                                    if(count($savedInvoices) > 1) {
                                                        foreach($savedInvoices as $sikey => $savedInvoice) {
                                                            $savedInvoices[$sikey]['invoiceAmount'] = ($useOrderPrice == 1) ? $savedInvoices[$sikey]['orderPrice'] : 0;
                                                        }
                                                    }
                                                    
                                                    foreach($savedInvoices as $savedInvoice) {
                                                        if(stripos($savedInvoice['invoice_url'], "print") !== false) {
                                                            $this->exts->new_invoice($savedInvoice['invoiceName'], $savedInvoice['invoiceDate'], $savedInvoice['invoiceAmount'], $savedInvoice['filename']);
                                                            
                                                            $contact_url = $savedInvoice['contact_url'];
                                                            if(trim($contact_url) != "") {
                                                                if(stripos($contact_url, "https://www.amazon.ca") === false) {
                                                                    $contact_url = "https://www.amazon.ca".$contact_url;
                                                                }
                                                                $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::" . $contact_url, "===EXTRA-DATA===");
                                                            } else {
                                                                $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::AMAZON_NO_DOWNLOAD", "===EXTRA-DATA===");
                                                            }
                                                            $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::Order Overview - " . $savedInvoice['invoiceName'], "===NOTE-DATA===");
                                                            
                                                            if((int)@$this->auto_tagging == 1 && !empty($this->order_overview_tags)) {
                                                                $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::" . $this->order_overview_tags, "===INVOICE-TAGS===");
                                                            }
                                                        } else {
                                                            $this->exts->new_invoice($savedInvoice['invoiceName'], $savedInvoice['invoiceDate'], $savedInvoice['invoiceAmount'], $savedInvoice['filename']);
                                                            
                                                            $this->exts->sendRequestEx($savedInvoice['invoiceName'] . ":::Amazon Direct - " . $savedInvoice['invoiceName'], "===NOTE-DATA===");
                                                            if((int)@$this->auto_tagging == 1 && !empty($this->amazon_invoice_tags)) {
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
                        } catch(\Exception $exception){
                            $this->exts->log("Exception finding columns element ".$exception->getMessage());
                        }
                    }
                    sleep(2);
                }
            } else {
                //$this->exts->init_required();
                //$this->exts->capture("orders-".$key1);
            }
            
            // Keep last processed page count
            $this->last_state['last_page_count'] = '';
            $this->current_state['last_page_count'] = $hrefArr['page'];
            
            // Keep last processed order page list url
            $this->last_state['order_page_list'] = '';
            $this->current_state['order_page_list'] = $this->exts->getUrl();
        } catch(\Exception $excp) {
            $this->exts->log("Orders - " . $excp->getMessage());
        }
    }
} else {
    $this->exts->log("No Invoice Found");
}

// Keep processed years
$this->last_state['years'] = array();
$this->current_state['years'][] = $optionSelector;
}
}
function getItemLinks() {
$links = array();
$tempLinkArr = array();

sleep(2);
try {
if($this->exts->getElement("div#a-popover-invoiceLinks a.a-link-normal") != null) {
    $tempLinkArr = $this->exts->getElements("div#a-popover-invoiceLinks a.a-link-normal");
}
$this->exts->log("Temp Link - ".count($tempLinkArr));

$isPopOver = $this->exts->getElements("div#orderDetails a.a-popover-trigger.a-declarative");
if(count($isPopOver) > 0) {
    $isPopOver[0]->click();
}
sleep(4);

if(count($isPopOver) > 0) {
    $links = $this->exts->getElements(".a-popover-content a.a-link-normal");
    if(empty($links)) {
        sleep(4);
        $isPopOver = $this->exts->getElements(".a-spacing-none .a-popover-trigger");
        if(count($isPopOver) > 0) {
            $isPopOver[0]->click();
            sleep(4);
            
            $links = $this->exts->getElements(".a-popover-content a.a-link-normal");
        }
    }
    $this->exts->log("Popover Link - ".count($links));
} else {
    if(count($tempLinkArr) > 0) {
        $links = $tempLinkArr;
    }
}

if(!empty($links)) {
    $linkArr = array();
    foreach($links as $linkItem) {
        $linkArr[] = array(
            'link' => trim($linkItem->getAttribute("href")),
            'text' => trim($linkItem->getText())
        );
    }
    $links = $linkArr;
}
} catch(\Exception $excp) {
$this->exts->log("Getting Popover Links - " . $excp->getMessage());
}

return $links;
}
function getTotalYearPages($reloadCount=0) {
$pages = 0;
if($this->exts->getElement("span.num-orders-for-orders-by-date span.num-orders") != null) {
$total_data = $this->exts->getElement("span.num-orders-for-orders-by-date span.num-orders")->getText();
$this->exts->log("total_data -".$total_data);
$tempArr = explode(" ", $total_data);
if(count($tempArr)) {
    $total_data = trim($tempArr[0]);
}
$this->exts->log("total_data -".$total_data);
$pages = round($total_data / 10);
$this->exts->log("total_data -".$pages);

if($pages < 0) {
    $pageEle = $this->exts->getElements("div.pagination-full a");
    $liCount = count($pageEle);
    if($liCount > 2) {
        $pages = (int)trim($pageEle[$liCount - 2]->getText());
    }
}
} else if($this->exts->getElement("span.num-orders") != null) {
$total_data = $this->exts->getElement("span.num-orders")->getText();
$this->exts->log("total_data -".$total_data);
$tempArr = explode(" ", $total_data);
if(count($tempArr)) {
    $total_data = trim($tempArr[0]);
}
$this->exts->log("total_data -".$total_data);
$pages = round($total_data / 10);
$this->exts->log("total_data -".$pages);

if($pages < 0) {
    $pageEle = $this->exts->getElements("div.pagination-full a");
    $liCount = count($pageEle);
    if($liCount > 2) {
        $pages = (int)trim($pageEle[$liCount - 2]->getText());
    }
}
} else {
$pageEle = $this->exts->getElements("div.pagination-full a");
if(count($pageEle)) {
    $liCount = count($pageEle);
    if($liCount > 2) {
        $pages = (int)trim($pageEle[$liCount - 2]->getText());
    }
}
}

//$this->exts->getElement("div#partial-order-fail-alert")->isDisplayed();
if((int)@$pages == 0 && (int)@$reloadCount == 0) {
$reloadCount++;
$currentUrl = $this->exts->getUrl();
$this->openUrl($currentUrl);
sleep(5);

$pages = $this->getTotalYearPages($reloadCount);
}

return $pages;
}
function downloadBusinessPrimeInvoices() {
$this->openUrl($this->businessPrimeUrl);
sleep(5);

if(stripos($this->exts->getUrl(), "amazon.ca/ap/signin") !== false) {
$this->checkFillLogin(0);
sleep(4);
}

if($this->exts->getElement("a[href*=\"/bb/benefits/bps/landing/?ref_=ab_bps_engmt_mgmtbox\"]") != null) {
$this->exts->moveToElementAndClick("a[href*=\"/bb/benefits/bps/landing/?ref_=ab_bps_engmt_mgmtbox\"]");
sleep(10);

$invoice_url = "";
if($this->exts->getElement("a#business-prime-shipping-view-last-invoice") != null) {
    try {
        $invoice_url = $this->exts->getElement("a#business-prime-shipping-view-last-invoice")->getAttribute("href");
        $this->exts->log("prime invoice URL - ".$invoice_url);
        if(trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.ca") === false && stripos($invoice_url, "https://") === false) {
            $invoice_url = "https://www.amazon.ca".$invoice_url;
        }
        $this->exts->log("prime invoice URL - ".$invoice_url);
    } catch(\Exception $exception) {
        $this->exts->log("Getting business prime invoice 1st option - " . $exception->getMessage());
    }
} else if($this->exts->getElement("a[href*=\"/documents/download/\"]") != null) {
    try {
        $invoice_url = $this->exts->getElement("a[href*=\"/documents/download/\"]")->getAttribute("href");
        $this->exts->log("prime invoice URL - ".$invoice_url);
        if(trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.ca") === false && stripos($invoice_url, "https://") === false) {
            $invoice_url = "https://www.amazon.ca".$invoice_url;
        }
        $this->exts->log("prime invoice URL - ".$invoice_url);
    } catch(\Exception $exception) {
        $this->exts->log("Getting business prime invoice 2nd option - " . $exception->getMessage());
    }
}

if(trim($invoice_url) != "" && !empty($invoice_url)) {
    $this->isNoInvoice = false;
    try {
        $invoiceDate = "";
        if($this->exts->getElement("div#bps-mgmt-payment-history-last-invoice span > font") != null) {
            $tempInvoiceDate = trim($this->exts->getElement("div#bps-mgmt-payment-history-last-invoice span > font")->getText());
            $tempArr = explode(":",$tempInvoiceDate);
            $invoiceDate = trim($tempArr[count($tempArr)-1]);
        } else if($this->exts->getElement("div#bps-mgmt-payment-history-last-invoice") != null) {
            $tempInvoiceDate = trim($this->exts->getElement("div#bps-mgmt-payment-history-last-invoice")->getText());
            $tempArr = explode(":",$tempInvoiceDate);
            $invoiceDate = trim($tempArr[count($tempArr)-1]);
            $this->exts->log("prime invoice date - ".$invoiceDate);
            
            $tempArr = explode(" ",$invoiceDate);
            $invoiceDate = trim($tempArr[0])." ".trim($tempArr[1])." ".trim($tempArr[2]);
            $this->exts->log("prime invoice date - ".$invoiceDate);
        }
        $this->exts->log("prime invoice date - ".$invoiceDate);
        
        if(trim($invoiceDate) != "") {
            $parsed_invoice_date = $this->exts->parse_date($invoiceDate);
            $invoiceDate = $parsed_invoice_date;
        }
        $this->exts->log("prime invoice date - ".$invoiceDate);
        
        $filename = "";
        if(trim($invoiceDate) != "") $filename = trim($invoiceDate).".pdf";
        $downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
        if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
            $this->exts->new_invoice("", $invoiceDate, "", $downloaded_file);
        }
    } catch(\Exception $exception) {
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
function triggerMsgInvoice() {
if((int)@$this->msg_invoice_triggerd == 0) {
$this->msg_invoice_triggerd = 1;
if((int)@$this->download_invoice_from_message == 1 && empty($this->only_years)) {
    $this->msgTimeLimitReached = 0;
    $this->processMSInvoice(0);
}
}
}
function processMSInvoice($currentMessagePage=0) {
if((int)@$currentMessagePage == 0) {
$this->openUrl($this->messagePageUrl);
sleep(5);
}

if(stripos($this->exts->getUrl(), "amazon.ca/ap/signin") !== false) {
$this->checkFillLogin(0);
sleep(4);
}

if((int)@$currentMessagePage == 0 && $this->exts->getElement("div#inbox-page ul.a-tabs.a-declarative li[data-a-tab-name=\"inbox_bsm_tab\"] a") != null && $this->exts->getElement("div#inbox-page ul.a-tabs.a-declarative li[data-a-tab-name=\"inbox_bsm_tab\"] a")->isDisplayed()) {
$this->exts->moveToElementAndClick("div#inbox-page ul.a-tabs.a-declarative li[data-a-tab-name=\"inbox_bsm_tab\"] a");
sleep(10);
}

$inv_msgs = array();
$invMsgRows = $this->exts->queryXpath("//*[@id=\"inbox_bsm_tab_content\"]/tbody/tr");
$this->exts->log("Message Rows on page - ".$currentMessagePage." - ".count($invMsgRows));
if(count($invMsgRows) > 0) {
$msgTimeDiff = time()-(60*24*60*60);
foreach($invMsgRows as $key => $invMsgRow) {
    $mj = $key+1;
    $invMsgCols =  $this->exts->queryXpath("//*[@id=\"inbox_bsm_tab_content\"]/tbody/tr[$mj]/td");
    $invMsgColImg =  $this->exts->queryXpath("//*[@id=\"inbox_bsm_tab_content\"]/tbody/tr[$mj]//span/img");
    
    $this->exts->log("Message cols and imgcols  - ".$key." - ".count($invMsgCols) . " - " . count($invMsgColImg));
    if(count($invMsgColImg) > 0 && count($invMsgCols) > 0) {
        $invMsgColImgTitle = $invMsgColImg[0]->getAttribute("Title");
        $invMsgColImgTitle = trim($invMsgColImgTitle);
        if(empty($invMsgColImgTitle)) $invMsgColImgTitle = trim($invMsgColImg[0]->getAttribute("title"));
        $this->exts->log("message img title - ".$key." - ".$invMsgColImgTitle);
        if($invMsgColImgTitle == "Attachment") {
            $msgTime = ($invMsgCols[0]->getAttribute("messagesenttime")/1000);
            if($msgTime > $msgTimeDiff) {
                $inv_msgs[] = array(
                    'msg_time' => $invMsgCols[0]->getAttribute("messagesenttime"),
                    'msg_id' => $invMsgCols[0]->getAttribute("messageid")
                );
            } else {
                $this->msgTimeLimitReached = 1;
                $this->exts->log("Skipping message older than 60 days - ".date("Y-m-d H:i:s",$msgTime));
                break;
            }
        }
    }
}
}

$this->exts->log("Found message on page - ".$currentMessagePage." - ".count($inv_msgs));
if(!empty($inv_msgs)) {
// Open New window To process Invoice
$this->exts->openNewTab();

//Call Processing function to process current page invoices
$this->startCurrentPageMessageDownload($inv_msgs);
sleep(2);

// Close new tab
$this->exts->closeCurrentTab();
}

if(count($invMsgRows) > 0) {
if($this->exts->getElement("#inbox_button_next_page input[type=\"submit\"]") != null && $this->exts->getElement("#inbox_button_next_page input[type=\"submit\"]")->isDisplayed() && (int)@$this->msgTimeLimitReached == 0) {
    if($this->exts->getElement(".a-button-disabled#inbox_button_next_page input[type=\"submit\"]") == null && $this->exts->getElement("#inbox_button_next_page input[type=\"submit\"]")->isDisplayed()) {
        $this->exts->moveToElementAndClick("#inbox_button_next_page input[type=\"submit\"]");
        $currentMessagePage++;
        sleep(10);
        
        $this->processMSInvoice($currentMessagePage);
    }
}
}
}
function startCurrentPageMessageDownload($inv_msgs=array()) {
foreach($inv_msgs as $inv_msg) {
$this->exts->log("Message ID - ".$inv_msg['msg_id']);
$this->exts->log("Message Timestamp - ".$inv_msg['msg_time']);

$msgUrl = "https://www.amazon.ca/gp/message?ie=UTF8&cl=4&ref_=ya_mc_bsm&#!/detail/" . $inv_msg['msg_id'] . "/bsm/" . $inv_msg['msg_time'] . "/inbox";
$this->exts->log("Message URL - ".$msgUrl);

$this->openUrl($msgUrl);
sleep(5);

if($this->exts->getElement('.a-ordered-list a.a-link-normal') != null) {
    $links = $this->exts->getElements(".a-ordered-list a.a-link-normal");
    foreach($links as $link_item) {
        $this->isNoInvoice = false;
        $invoice_data = array();
        $invoice_name = trim($link_item->getText());
        
        if(stripos($invoice_name, ".pdf") !== false) {
            $order_number = "";
            
            $ordItems = $this->exts->getElements("div#detail-page .a-box-inner a[href*=\"summary/edit.html\"]");
            if(count($ordItems) > 0) {
                $order_number = trim($ordItems[0]->getText());
            }
            
            $invoice_data = array(
                'invoice_name' => $invoice_name,
                'invoice_url' => $link_item->getAttribute("href"),
                'order_number' => $order_number
            );
            $this->exts->log("Invoice data found - ".count($invoice_data));
            
            if(!empty($invoice_data)){
                $this->exts->log("Invoice Name - ".$invoice_data['invoice_name']);
                $this->exts->log("Invoice Order Number - ".$invoice_data['order_number']);
                $this->exts->log("Invoice Url - ".$invoice_data['invoice_url']);
                
                if(trim($invoice_data['invoice_url']) != "" && !$this->exts->invoice_exists($invoice_name)) {
                    $file_ext = $this->exts->get_file_extension($invoice_name);
                    if($file_ext == 'pdf') {
                        $invoice_name = basename($invoice_name, '.'.$file_ext);
                        if(trim($invoice_name) != "" && !empty($this->invalid_filename_pattern) && preg_match("/".$this->invalid_filename_pattern."/i", $invoice_name)) {
                            $this->exts->log("Skipping as file name is in blacklist - " . $invoice_name);
                        } else {
                            preg_replace('/^\D+/i', '', $invoice_name);
                            if(trim($invoice_name) == "") {
                                $invoice_name = $inv_msg['msg_id'];
                            }
                            $filename = $invoice_name.".pdf";
                            
                            $invoice_url = $invoice_data['invoice_url'];
                            if(trim($invoice_url) != "" && stripos($invoice_url, "https://www.amazon.ca") === false && stripos($invoice_url, "https://") === false) {
                                $invoice_url = "https://www.amazon.ca".$invoice_url;
                            }
                            
                            $downloaded_file = $this->exts->direct_download($invoice_url, "pdf", $filename);
                            if(trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                
                                $this->exts->new_invoice($invoice_name, "", "", $filename);
                                
                                $this->exts->sendRequestEx($invoice_name.":::Marketplace Seller - ".$invoice_data['order_number'], "===NOTE-DATA===");
                                if((int)@$this->auto_tagging == 1 && !empty($this->marketplace_invoice_tags)) {
                                    $this->exts->sendRequestEx($invoice_name.":::".$this->marketplace_invoice_tags, "===INVOICE-TAGS===");
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