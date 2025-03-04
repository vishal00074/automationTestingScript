<?php // optimize the script
// Server-Portal-ID: 68981 - Last modified: 03.03.2025 23:12:02 UTC - User: 1

public $baseUrl = 'https://www.notion.so';
public $loginUrl = 'https://www.notion.so/login';

public $username_selector = '.notion-login input[type="email"]';
public $password_selector = '.notion-login input[type="password"]';
public $onetime_code_selector = '.notion-login input[type="text"], form input[type="text"]';
public $submit_login_selector = '.notion-login form div[role="button"]';

public $check_login_failed_selector = '//div[text()="Incorrect password."]';
public $check_login_success_selector = '.notion-sidebar .notion-sidebar-switcher';

public $login_with_google = '0';
public $isNoInvoice = true; 

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */

private function initPortal($count) {
    
    $this->exts->log('Begin initPortal '.$count);
    if(isset($this->exts->config_array['login_with_google'])){
        $this->login_with_google = trim($this->exts->config_array['login_with_google']);
    }

    $this->exts->openUrl($this->loginUrl);
    sleep(10);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->loginUrl);

    $this->exts->capture('1-init-page');
    $this->exts->waitTillPresent('.notion-onboarding-workspace > [role="button"]',10);
    if($this->exts->exists('.notion-onboarding-workspace > [role="button"]')){
        if($this->exts->urlContains('/onboarding')){
            $this->exts->moveToElementAndClick('.notion-onboarding-workspace > [role="button"]');
            sleep(5);
        }
    }
    
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');

        $this->exts->openUrl($this->loginUrl);
        sleep(10);

        if ($this->exts->urlContains('/de-de')) {
            $this->exts->click_element('span[class*="languagePickerButton_button"]');
            sleep(2);
            $this->exts->click_element('//p[contains(text(), "English")]');
            sleep(10);
        }

        if($this->login_with_google == '1'){
            if($this->exts->exists('svg.googleLogo')){
                $this->exts->click_element('svg.googleLogo');
            }
            sleep(5);
            $google_tab = $this->exts->findTabMatchedUrl(['google']);
            if ($google_tab != null) {
                $this->exts->switchToTab($google_tab);
            }

            $this->loginGoogleIfRequired();

            $google_tab = $this->exts->findTabMatchedUrl(['google']);
            if ($google_tab != null) {
                $this->exts->switchToTab($google_tab);
            }
        } else {

            $this->exts->openUrl($this->loginUrl);

            $this->exts->waitTillPresent('a.notion-link',10);
            $login_with_email_button = $this->exts->getElementByText('a.notion-link', ['email'], null, false);
            if($login_with_email_button != null){
                try {
                    $this->exts->log('Click button login with email...');
                    $this->exts->click_element($login_with_email_button);
                } catch (Exception $e) {
                    $this->exts->log('Click button login with email by javascript...');
                    $this->exts->execute_javascript("arguments[0].click()", [$login_with_email_button]);
                }
                sleep(5);
                $this->exts->capture('after-click-login-with-email-button');
            }
            $this->checkFillLogin();
            sleep(5);
            if($this->exts->getElement($this->username_selector) != null) {
                $this->checkFillLogin();
            }

            //If required to login with email code, try login again with email code
            if ($this->exts->getElementByText($this->check_login_failed_selector, ['You must login with an email login code'], null, false) != null) {
                $this->exts->capture('2.1-Required-login-with-email-code');
                $this->exts->openUrl($this->loginUrl);
                sleep(10);
                $login_with_email_button = $this->exts->getElementByText('a.notion-link', ['email'], null, false);
                if($login_with_email_button != null){
                    try {
                        $this->exts->log('Click button login with email...');
                        $this->exts->click_element($login_with_email_button);
                    } catch (Exception $e) {
                        $this->exts->log('Click button login with email by javascript...');
                        $this->exts->execute_javascript("arguments[0].click()", [$login_with_email_button]);
                    }
                    sleep(5);
                    $this->exts->capture('after-click-login-with-email-button');
                }
                
                $this->checkFillLogin();
                sleep(10);
                if($this->exts->getElement($this->username_selector) != null) {
                    $this->checkFillLogin();
                }
            }

            $this->exts->waitTillPresent("div.notion-cursor-listener > div > div",10);
            $divs = $this->exts->getElements('div.notion-cursor-listener > div > div');
            $this->exts->log('Divs :  =======> '. count($divs));
            if(count($divs) > 0){
                foreach ($divs as $div) {
                    $txt = $div->getAttribute('innerText');
                    if (strpos($txt, 'you do not have access to') !== false || strpos($txt, 'his is a private page') !== false) {
                        $this->exts->account_not_ready();
                        break;
                    }
                }
            }
        }
    }
    $this->exts->waitTillPresent($this->check_login_success_selector, 15);
    $this->checkLoggedIn();
}

// -------------------- GOOGLE login
public $google_username_selector = 'input[name="identifier"]:not([aria-hidden="true"])';
public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
private function loginGoogleIfRequired() {
    if($this->exts->urlContains('google.')){
        if($this->exts->urlContains('/webreauth')){
            $this->exts->moveToElementAndClick('#identifierNext');
            sleep(6);
        }
        $this->googleCheckFillLogin();
        sleep(5);
        if($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[name="Passwd"][aria-invalid="true"]') != null) {
            $this->exts->loginFailure(1);
        }
        if($this->exts->querySelector('form[action*="/signin/v2/challenge/password/"] input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], span#passwordError, input[name="Passwd"][aria-invalid="true"]') != null) {
            $this->exts->loginFailure(1);
        }

        // Click next if confirm form showed
        $this->exts->moveToElementAndClick('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
        $this->googleCheckTwoFactorMethod();
        $this->googleCheckTwoFactorMethod();

        if($this->exts->exists('#smsauth-interstitial-remindbutton')){
            $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
            sleep(10);
        }
        if($this->exts->exists('#tos_form input#accept')){
            $this->exts->moveToElementAndClick('#tos_form input#accept');
            sleep(10);
        }
        if($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')){
            $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
            sleep(10);
        }
        if($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')){
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('.action-button.signin-button');
            sleep(10);
        }
        if($this->exts->exists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')){
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
            sleep(10);
        }
        if($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')){
            $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
            sleep(10);
        }
        if($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')){
            $this->exts->moveToElementAndClick('input[name="later"]');
            sleep(7);
        }
        if($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')){
            $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
            sleep(7);
        }
        if($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')){
            $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
            sleep(10);
        }

        if($this->exts->exists('#submit_approve_access')){
            $this->exts->moveToElementAndClick('#submit_approve_access');
            sleep(10);
        } else if($this->exts->exists('form #approve_button[name="submit_true"]')){
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->moveToElementAndClick('form #approve_button[name="submit_true"]');
            sleep(10);
        }
        $this->exts->capture("3-google-before-back-to-main-tab");
    } else {
        $this->exts->log(__FUNCTION__.'::Not required google login.');
        $this->exts->capture("3-no-google-required");
    }
}
private function googleCheckFillLogin() {
    if($this->exts->exists('form ul li [role="link"][data-identifier]')){
        $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
        sleep(5);
    }

    if($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)){
        $this->exts->capture("google-verify-it-you");
        // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
    }
    
    $this->exts->capture("2-google-login-page");
    if($this->exts->exists($this->google_username_selector)) {
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
        if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)){
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)){
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
            }
            if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)){
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
            }
        } else if($this->exts->urlContains('/challenge/recaptcha')){
            $this->googlecheckFillRecaptcha();
            $this->exts->moveToElementAndClick('[data-primary-action-label] > div > div:first-child button');
            sleep(5);
        }

        // Which account do you want to use?
        if($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')){
            $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')){
            $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }
    
    if($this->exts->exists($this->google_password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);
        if($this->exts->exists('#captchaimg[src]')){
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
        }
        
        $this->exts->capture("2-google-login-page-filled");
        $this->exts->moveToElementAndClick($this->google_submit_password_selector);
        sleep(5);
        if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)){
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if($this->exts->exists('#captchaimg[src]')){
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)){
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->capture("2-google-login-pageandcaptcha-filled");
                $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            }
        } else {
            $this->googlecheckFillRecaptcha();
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Google password page not found');
        $this->exts->capture("2-google-password-page-not-found");
    }
}
private function googleCheckTwoFactorMethod() {
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
    $this->exts->capture("2.0-before-check-two-factor-google");
    // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
    if($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')){
        $this->exts->moveToElementAndClick('#assistActionId');
        sleep(5);
    } else if($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false){
        // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list-google");
        if($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false){
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
        }
    } else if($this->exts->urlContains('/sk/webauthn')){
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-".$this->exts->process_uid;
        exec("sudo docker exec ".$node_name." bash -c 'xdotool key Return'");
        sleep(3);
        $this->exts->capture("2.0-cancel-security-usb-google");
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')){
        // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if($this->exts->exists('input[name="ootpPin"]')){
        // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    }
    
    // STEP 1: Check if list of two factor methods showed, select first
    if($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')){
        // We most RECOMMEND confirm security phone or email, then other method
        if($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != ''){
            $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != ''){
            $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')){
            // We RECOMMEND method type = 6 is get code from Google Authenticator
            $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')){
            // We second RECOMMEND method type = 9 is get code from SMS
            $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')){
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')){
            // Use a smartphone or tablet to receive a security code (even when offline)
            $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')){
            // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
        } else {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
        }
        sleep(10);
    } else if($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')){
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')){
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')){
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
        sleep(10);
    }
    
    // STEP 2: (Optional)
    if($this->exts->exists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')){
        // If methos is recovery email, send 2FA to ask for email
        $this->exts->two_factor_attempts = 2;
        $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
        $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
        $submit_selector = '';
        if(isset($this->recovery_email) && $this->recovery_email != ''){
            $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
            $this->exts->type_key_by_xdotool("Return");
            sleep(7);
        }
        if($this->exts->exists($input_selector)){
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if($this->exts->exists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')){
        // If methos confirm recovery phone number, send 2FA to ask
        $this->exts->two_factor_attempts = 3;
        $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
        $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
        $submit_selector = '';
        if(isset($this->security_phone_number) && $this->security_phone_number != ''){
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool("Return");
            sleep(5);
        }
        if($this->exts->exists($input_selector)){
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if($this->exts->exists('input#phoneNumberId')){
        // Enter a phone number to receive an SMS with a confirmation code.
        $this->exts->two_factor_attempts = 3;
        $input_selector = 'input#phoneNumberId';
        $message_selector = '[data-view-id] form section > div > div > div:first-child';
        $submit_selector = '';
        if(isset($this->security_phone_number) && $this->security_phone_number != ''){
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool("Return");
            sleep(7);
        }
        if($this->exts->exists($input_selector)){
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        }
    } else if($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')){
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')){
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionId
    }
    
    // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
    if($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')){
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')){
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')){
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
        sleep(10);
    } else if(count($this->exts->getElements('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0){
        $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
        sleep(7);
    }
    
    
    // STEP 4: input code
    if($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')){
        $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
    } else if($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')){
        $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if($this->exts->exists('input[name="Pin"]')){
        $input_selector = 'input[name="Pin"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')){
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if($this->exts->exists('input[name="secretQuestionResponse"]')){
        $input_selector = 'input[name="secretQuestionResponse"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
    }
}
private function googleFillTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter=false) {
    $this->exts->log(__FUNCTION__);
    $this->exts->log("Google two factor page found.");
    $this->exts->capture("2.1-two-factor-google");
    
    if($this->exts->querySelector($message_selector) != null){
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
    }
    
    $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
    $this->exts->notification_uid = "";
    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if(!empty($two_factor_code) && trim($two_factor_code) != '') {
        if($this->exts->querySelector($input_selector) != null){
            if(substr(trim($two_factor_code), 0, 2) === 'G-'){
                $two_factor_code = end(explode('G-', $two_factor_code));
            }
            if(substr(trim($two_factor_code), 0, 2) === 'g-'){
                $two_factor_code = end(explode('g-', $two_factor_code));
            }
            $this->exts->log(__FUNCTION__.": Entering two_factor_code: ".$two_factor_code);
            $this->exts->moveToElementAndType($input_selector, '');
            $this->exts->moveToElementAndType($input_selector, $two_factor_code);
            sleep(1);
            if($this->exts->allExists(['input[type="checkbox"]:not(:checked) + div', 'input[name="Pin"]'])){
                $this->exts->moveToElementAndClick('input[type="checkbox"]:not(:checked) + div');
                sleep(1);
            }
            $this->exts->capture("2.2-google-two-factor-filled-".$this->exts->two_factor_attempts);
            
            if($this->exts->exists($submit_selector)){
                $this->exts->log(__FUNCTION__.": Clicking submit button.");
                $this->exts->moveToElementAndClick($submit_selector);
            } else if($submit_by_enter){
                $this->exts->type_key_by_xdotool("Return");
            }
            sleep(10);
            $this->exts->capture("2.2-google-two-factor-submitted-".$this->exts->two_factor_attempts);
            if($this->exts->querySelector($input_selector) == null){
                $this->exts->log("Google two factor solved");
            } else {
                if($this->exts->two_factor_attempts < 3){
                    $this->exts->notification_uid = '';
                    $this->exts->two_factor_attempts++;
                    $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
                } else {
                    $this->exts->log("Google Two factor can not solved");
                }
            }
        } else {
            $this->exts->log("Google not found two factor input");
        }
    } else {
        $this->exts->log("Google not received two factor code");
        $this->exts->two_factor_attempts = 3;
    }
}
private function googlecheckFillRecaptcha() {
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'form iframe[src*="/recaptcha/"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);
        $url = reset(explode('?', $this->exts->getUrl()));
        $isCaptchaSolved = $this->exts->processRecaptcha($url, $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
        
        if($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__."::filling reCaptcha response..");
            $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
            for ($i=0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" .$this->exts->recaptcha_answer. "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');
            
            // Step 2, check if callback function need executed
            $gcallbackFunction = $this->exts->execute_javascript('
                if(document.querySelector("[data-callback]") != null){
                    document.querySelector("[data-callback]").getAttribute("data-callback");
                } else {
                    var result = ""; var found = false;
                    function recurse (cur, prop, deep) {
                        if(deep > 5 || found){ return;}console.log(prop);
                        try {
                            if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                            } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
                                for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                            }
                        } catch(ex) { console.log("ERROR in function: " + ex); return; }
                    }

                    recurse(___grecaptcha_cfg.clients[0], "", 0);
                    found ? "___grecaptcha_cfg.clients[0]." + result : null;
                }
            ');
            $this->exts->log('Callback function: '.$gcallbackFunction);
            if($gcallbackFunction != null){
                $this->exts->execute_javascript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Not found reCaptcha');
    }
}
// End GOOGLE login

/**
    * Entry Method thats indentify and click element by element text
    * Because many website use generated html, It did not have good selector structure, indentify element by text is more reliable
    * This function support seaching element by multi language text or regular expression
    * @param String $selector Selector string of element.
    * @param String $multi_language_texts the text label of element that want to click, can input single label, or multi language array or regular expression. Exam: 'invoice', ['invoice', 'rechung'], '/invoice|rechung/i'
    * @param Element $parent_element parent element when we search element inside.
    * @param Bool $is_absolutely_matched tru if want seaching absolutely, false if want to seaching relatively.
    */ 

private function checkFillLogin() {

    $this->exts->capture("2-login-page");
    if($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(3);

        $login_with_email_button = $this->exts->getElementByText($this->submit_login_selector, ['continue', 'email'], null, false);
        try {
            $this->exts->click_element($login_with_email_button);
        } catch (Exception $e) {
            $this->exts->execute_javascript("arguments[0].click()", [$login_with_email_button]);
        }
        sleep(5);

        $this->exts->capture('after-input-email');
    }

    $selectAuthenMethod = $this->exts->getElementByText('div[role="button"]', 'Use code from authenticator', null, false);
    if ($selectAuthenMethod != null) {
        $this->exts->click_element($selectAuthenMethod);
        sleep(2);
    }
    $this->exts->waitTillPresent($this->onetime_code_selector, 20);
    if($this->exts->getElement($this->onetime_code_selector) != null) {
        $this->checkFillLoginCode(); 
    } else if($this->exts->getElement('.notion-login input[type="password"]') != null){
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType('.notion-login input[type="password"]', $this->password);
        sleep(1);

        $this->exts->capture("2-login-page-filled-1");

        $login_with_password_button = $this->exts->getElementByText($this->submit_login_selector, ['Continue with password'], null, false);
        try {
            $this->exts->click_element($login_with_password_button);
        } catch (Exception $e) {
            $this->exts->execute_javascript("arguments[0].click()", [$login_with_password_button]);
        }
        sleep(10);
        $selectAuthenMethod = $this->exts->getElementByText('div[role="button"]', 'Use code from authenticator', null, false);
        if ($selectAuthenMethod != null) {
            $this->exts->click_element($selectAuthenMethod);
            sleep(2);
        }
        $this->exts->waitTillPresent($this->onetime_code_selector, 10);
        if($this->exts->exists($this->onetime_code_selector)) {
            $this->checkFillLoginCode();
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Onetime code page not found');
        $this->exts->capture("2-code-page-not-found");
    }
}

private function checkFillLoginCode() {
    $two_factor_selector = $this->onetime_code_selector;
    $two_factor_message_selector = '//form/label[contains(@for, "notion-password-input")]/preceding-sibling::div[1]';
    $two_factor_submit_selector = $this->submit_login_selector;

    if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null){
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) { 
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
            
            $this->exts->type_key_by_xdotool('Return');
            $this->exts->waitTillPresent($two_factor_selector,20);
            if($this->exts->getElement($two_factor_selector) == null){
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
                $this->checkFillLoginCode();
            } else {
                $this->exts->log("Two factor can not solved");
            }

        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

private function checkLoggedIn() {

    // then check user logged in or not

    if($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User logged in');
        $this->exts->capture("3-login-success");

        $this->exts->execute_javascript("var div = document.querySelector('div.notion-peek-renderer'); 
            if (div != null) {  
                div.style.display = \"none\"; 
            }
        ");
        
        if($this->exts->exists('.notion-media-menu')) 
        $this->exts->execute_javascript("document.querySelector('.notion-media-menu').remove();");
    

        $this->exts->waitTillPresent('.notion-sidebar .notion-sidebar-switcher', 25);

        if($this->exts->exists('.notion-sidebar .notion-sidebar-switcher')){
            $this->exts->click_element('.notion-sidebar .notion-sidebar-switcher');
        }

        $this->exts->log('Open the scroller');

        $this->exts->waitTillPresent('div[role="menu"]',25);

        if($this->exts->exists('div[role="menu"]')){

            $this->processMultipleWorkspaces();
        }

    
        // Final, check no invoice
        if($this->isNoInvoice){
            $this->exts->no_invoice();
        }
        
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__.'::Use login failed');
        if ($this->exts->urlContains('notion.so/onboarding')) {
            $this->exts->account_not_ready();
        }
        if($this->exts->getElementByText($this->check_login_failed_selector, ['Incorrect password', 'We could not reach the email address you provided. Please try again with a different email.'], null, false) != null) {
            $this->exts->loginFailure(1);
        } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function processMultipleWorkspaces(){
    
    $workspaces = count($this->exts->getElements('div[role="menu"] > div > div >div > div > div[role="menuitem"]'));

    $this->exts->log('Total no of workspace : ' . $workspaces);

    if($workspaces > 0){
        for ($w=1; $w <= $workspaces; $w++) {
                
            $workspace_button = $this->exts->querySelector('div[role="menu"] > div > div >div:nth-child(' . $w . ') > div > div[role="menuitem"]');

            sleep(2);

            $this->exts->execute_javascript("arguments[0].click()", [$workspace_button]);

            sleep(5);
            if($this->exts->exists($workspace_button)){
                $workspace_button->click();
            }
            
            $this->exts->log('Open the scroller');

            $this->exts->waitTillPresent('.notion-sidebar .notion-sidebar-switcher',25);

            if($this->exts->exists('.notion-sidebar .notion-sidebar-switcher')){
                $this->exts->click_element('.notion-sidebar .notion-sidebar-switcher');
            }
            
            $this->exts->waitTillPresent('//div[text()="Settings" and @role="button"]', 10);

            if($this->exts->getElement('//div[text()="Settings" and @role="button"]') != null){
                
                $this->exts->click_element('//div[text()="Settings" and @role="button"]');  

                $this->exts->waitTillPresent('//div[contains(text(), "Facturation") or contains(text(), "Billing") or contains(text(), "Abrechnung")]', 10);
                    
                if($this->exts->getElement('//div[contains(text(), "Facturation") or contains(text(), "Billing") or contains(text(), "Abrechnung")]') != null){

                    $this->exts->click_element('//div[contains(text(), "Facturation") or contains(text(), "Billing") or contains(text(), "Abrechnung")]');

                    $this->exts->waitTillPresent('//div[contains(text(), "Afficher la facture") or contains(text(), "Rechnung anzeigen") or contains(text(), "View invoice")]', 10);
                    
                    if($this->exts->getElements('//div[contains(text(), "Afficher la facture") or contains(text(), "Rechnung anzeigen") or contains(text(), "View invoice")]') != null){
                        $this->processInvoices();
                    }
                } else{ 
                    $this->exts->log('NO Abrechnung or Billing button found');                      
                } 
                    
                $this->exts->waitTillPresent('.notion-sidebar .notion-sidebar-switcher', 15);
                $this->exts->click_element('.notion-sidebar .notion-sidebar-switcher');
                
            } else{
                $this->exts->log('NO Einstellungen button or Settings & members button found');  
            } 
        }
    }            
}

private function processInvoices() {
    $this->exts->waitTillPresent('//div[contains(text(), "Afficher la facture") or contains(text(), "Rechnung anzeigen") or contains(text(), "View invoice")]',15);
    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $row_count = count($this->exts->getElements('//div[@role="button"]/div[text()="View invoice"]'));
    $this->exts->log('No of rows : '. $row_count);
    for ($i=0; $i < $row_count; $i++) {
        $download_button = $this->exts->getElements('//div[@role="button"]/div[text()="View invoice"]')[$i];

        $this->exts->click_element($download_button);
        sleep(5);
        $this->exts->switchToNewestActiveTab();
        sleep(1);
        if (!$this->exts->urlContains('upcoming')) {
            $invoiceUrl = $this->exts->getUrl();
            $invoiceName = array_pop(explode('invoice/', $invoiceUrl));

            $this->exts->log('invoice url : ' . $invoiceUrl);
            $this->exts->log('invoiceName : ' . $invoiceName);


            $invoiceFileName = $invoiceName . '.pdf';
            $this->isNoInvoice = false;
            if($this->exts->invoice_exists($invoiceName)){
                $this->exts->log('Invoice existed '.$invoiceFileName);
            }else{
                sleep(10);
                if($this->exts->exists('.notion-print-ignore')){
                    // $this->exts->click_element('.notion-print-ignore');
                    // sleep(8);
                    // $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->click_and_print('.notion-print-ignore', $invoiceFileName);
                    if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                        $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
                    }
                } else {
                    $this->exts->log(__FUNCTION__.'::Seem this is not invoice '.$invoiceUrl);
                }
            }
        }
        
        sleep(2);
        $this->exts->switchToInitTab();
        sleep(2);
        $this->exts->closeAllTabsButThis();
    
    }
    // Close any overlay popup
    $this->exts->execute_javascript('document.elementFromPoint(1, 1).click();');
    sleep(5);
}