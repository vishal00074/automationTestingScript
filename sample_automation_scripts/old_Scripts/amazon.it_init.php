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

public $check_login_failed_selector = 'div#auth-error-message-box h4';
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
private function initPortal($count) {
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
        $this->procurment_report = isset($this->exts->config_array["procurment_report"]) ? (int)$this->exts->config_array["procurment_report"] : 0;
        $this->start_page = isset($this->exts->config_array["start_page"]) ? $this->exts->config_array["start_page"] : '';
        $this->last_invoice_date = isset($this->exts->config_array["last_invoice_date"]) ? $this->exts->config_array["last_invoice_date"] : '';
        $this->start_date = (isset($this->exts->config_array["start_date"]) && !empty($this->exts->config_array["start_date"])) ? trim($this->exts->config_array["start_date"]) : "";
        
        if(!empty($this->start_date)) {
            $this->start_date = strtotime($this->start_date);
        }
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
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    $this->exts->capture("Home-page-with-cookie");

    $this->exts->openUrl($this->orderPageUrl);
    sleep(5);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if(!$this->checkLogin()) {
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
        }else if ($this->exts->exists('input#input-box-otp')) {
            $this->checkFillTwoFactor('input#input-box-otp', 'input[aria-labelledby="cvf-submit-otp-button-announce"]', 'span.a-size-base.transaction-approval-word-break');
        }
    }

    if($this->checkLogin()) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
        
    } else {
        $this->exts->log(__FUNCTION__.'::Use login failed');
        $this->exts->log('::URL login failure:: '.$this->exts->getUrl());

        $isErrorMessage =$this->exts->execute_javascript('document.body.innerHTML.includes("Si è verificato un problema!")');
        $this->exts->log('::isErrorMessage:: ' . $isErrorMessage);

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
        } elseif ($this->exts->exists($isErrorMessage)) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkLogin() {
    $this->exts->log("Begin checkLogin ");
    $this->exts->capture(__FUNCTION__);
    $isLoggedIn = false;
    sleep(5);
    if($this->exts->exists($this->check_login_success_selector)) {
        $isLoggedIn = true;
    } elseif($this->exts->exists("div#nav-tools a#nav-link-accountList, div#nav-tools a#nav-link-yourAccount")) {
        $href = $this->exts->getElement("div#nav-tools a#nav-link-accountList, div#nav-tools a#nav-link-yourAccount")->getAttribute("href");
        if(trim($href) != "" && stripos($href, "/gp/css/homepage.html") !== false) {
            $isLoggedIn = true;
        }
    }

    return $isLoggedIn;
}

private function checkFillLogin($count=0) {
    sleep(3);
    $this->exts->log(__FUNCTION__);
    $this->exts->capture(__FUNCTION__);
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
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function processImageCaptcha() {
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
    if ($this->exts->exists('div#auth-error-message-box h4')
        && $this->exts->exists('form[name="signIn"] input#auth-captcha-guess')) {
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

private function checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector) {
    if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        
        if($this->exts->getElement($two_factor_message_selector) != null){
            $total_2fa = count($this->exts->getElements($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < $total_2fa; $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText')."\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        }
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }
        
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
            
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);
            
            if($this->exts->getElement($two_factor_selector) == null){
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

private function checkFillAnswerSerQuestion($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector) {
    if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        
        if($this->exts->getElement($two_factor_message_selector) != null){
            $total_2fa = count($this->exts->getElements($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < $total_2fa; $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText')."\n";
            }
            $this->exts->two_factor_notif_msg_en = 'Please enter answer of below question (MM/YYYY): ' . trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = 'Bitte geben Sie die Antwort der folgenden Frage ein (MM/YYYY): ' . $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        }
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }
        
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
            $month = trim(explode('/', $two_factor_code)[0]);
            $year = trim(end(explode('/', $two_factor_code)));
            $this->exts->moveToElementAndType('[name="dcq_question_date_picker_1_1"]', $month);
            $this->exts->moveToElementAndType('[name="dcq_question_date_picker_1_2"]', $year);
            
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
            
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);
            
            if($this->exts->getElement($two_factor_selector) == null){
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

private function checkFillTwoFactorWithPushNotify($two_factor_message_selector) {
    if($this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        
        if($this->exts->getElement($two_factor_message_selector) != null){
            $total_2fa = count($this->exts->getElements($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < $total_2fa; $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText')."\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . 'Please input "OK" after responded email/approve notification!';
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . 'Please input "OK" after responded email/approve notification!';;
            $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
        }
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }
        
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if(!empty($two_factor_code) && trim($two_factor_code) != '' && strtolower($two_factor_code) == 'ok') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
            sleep(15);
            
            if($this->exts->getElement($two_factor_message_selector) == null && !$this->exts->exists('input[name="transactionApprovalStatus"]')){
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
private function check2FAScreen(){
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
    }else if ($this->exts->exists('input#input-box-otp')) {
        $this->checkFillTwoFactor('input#input-box-otp', 'input[aria-labelledby="cvf-submit-otp-button-announce"]', 'span.a-size-base.transaction-approval-word-break');
    }
}


