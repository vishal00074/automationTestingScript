public $baseUrl = 'https://de.todoist.com';
public $loginUrl = 'https://todoist.com/users/showlogin?lang=de';
public $invoicePageUrl = 'https://de.todoist.com/Users/viewPrefs?page=payments';
public $businessInvoicePageUrl = 'https://de.todoist.com/business/accountAndBilling';

public $username_selector = 'input#email, form input[type="email"]';
public $password_selector = 'input#password, form input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button.ist_button, form button[type="submit"]';

public $check_login_failed_selector = 'div.error_msg';
public $check_login_success_selector = '#top_bar_inner, button.settings_btn, nav button[aria-haspopup="menu"]';

public $login_with_google = 0;
public $isNoInvoice = true;


/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)@$this->exts->config_array["login_with_google"] : 0;

    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        //redirected you too many times.
        for ($i = 0; $i < 2; $i++) {
            if ($this->exts->exists('#reload-button')) {
                $this->exts->moveToElementAndClick('#reload-button');
                sleep(10);
            } else {
                break;
            }
        }

        if ($this->login_with_google == 1) {


            $this->exts->markCurrentTabByName('rootTab');
            $this->exts->moveToElementAndClick('.login_signup_form a.ist_button_google');
            sleep(5);
            $google_login_tab = $this->exts->findTabMatchedUrl(['accounts.google.com']);
            if ($google_login_tab != null) {
                $this->exts->switchToTab($google_login_tab);
            }
            $this->loginGoogleIfRequired();
            $this->exts->capture("Page-after-login-google");
            // go back to root tab
            $this->exts->switchToTab($this->exts->getMarkedTab('rootTab'));
        } else {
            $this->checkFillLogin();
        }
        sleep(20);
        $this->checkFillTwoFactor();
    }

    if ($this->exts->getElement('a.timezone_link_block, div[data-dialog] button:nth-child(2)') != null) {
        $this->exts->moveToElementAndClick('a.timezone_link_block:nth-child(4), div[data-dialog] button:nth-child(2)');
        sleep(3);
    }
    if ($this->exts->exists('div#loading') && $this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('wait load page');
        sleep(15);
        if ($this->exts->exists('div#loading') && $this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->capture("wait-load-page");
            $this->exts->refresh();
            sleep(15);
            if ($this->exts->exists('div#loading') && $this->exts->getElement($this->check_login_success_selector) == null) {
                // $this->clearChrome();
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
                $this->checkFillLogin();
                sleep(20);
                $this->checkFillTwoFactor();
            }
        }
    }
    sleep(10);
    if ($this->exts->exists('div[data-dialog] button:nth-child(2)')) {
        $this->exts->moveToElementAndClick('div[data-dialog] button:nth-child(2)');
        sleep(2);
    }
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
        
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->getElementByText('form div', ['password', 'Passwort', 'mot de passe erron'], null, false)) {
            $this->exts->loginFailure(1);
        }
        $this->exts->loginFailure();
    }
}

private function getElementByText($selector, $multi_language_texts, $parent_element = null, $is_absolutely_matched = true)
{
    $this->exts->log(__FUNCTION__);
    if (is_array($multi_language_texts)) {
        $multi_language_texts = join('|', $multi_language_texts);
    }
    // Seaching matched element
    $object_elements = $this->exts->getElements($selector, $parent_element);
    foreach ($object_elements as $object_element) {
        $element_text = trim($object_element->getAttribute('textContent'));
        // First, search via text
        // If is_absolutely_matched = true, seach element matched EXACTLY input text, else search element contain the text
        if ($is_absolutely_matched) {
            $multi_language_texts = explode('|', $multi_language_texts);
            foreach ($multi_language_texts as $searching_text) {
                if (strtoupper($element_text) == strtoupper($searching_text)) {
                    $this->exts->log('Matched element found');
                    return $object_element;
                }
            }
            $multi_language_texts = join('|', $multi_language_texts);
        } else {
            if (preg_match('/' . $multi_language_texts . '/i', $element_text) === 1) {
                $this->exts->log('Matched element found');
                return $object_element;
            }
        }

        // Second, is search by text not found element, support searching by regular expression
        if (@preg_match($multi_language_texts, '') !== FALSE) {
            if (preg_match($multi_language_texts, $element_text) === 1) {
                $this->exts->log('Matched element found');
                return $object_element;
            }
        }
    }
    return null;
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[autocomplete="one-time-code"]';
    $two_factor_message_selector = '//*[text()="Two-factor authentication"]/../following-sibling::div[1]';
    $two_factor_submit_selector = '';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getAttribute('innerText') . "\n";
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
                $this->exts->notification_uid = '';
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

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(3);
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
}
public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}
// -------------------- GOOGLE login
public $google_username_selector = 'input[name="identifier"]:not([aria-hidden="true"])';
public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
private function loginGoogleIfRequired()
{
    if ($this->exts->urlContains('google.')) {
        if ($this->exts->urlContains('/webreauth')) {
            $this->exts->moveToElementAndClick('#identifierNext');
            sleep(6);
        }
        $this->googleCheckFillLogin();
        sleep(5);
        if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[name="Passwd"][aria-invalid="true"]') != null) {
            $this->exts->loginFailure(1);
        }
        if ($this->exts->querySelector('form[action*="/signin/v2/challenge/password/"] input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], span#passwordError, input[name="Passwd"][aria-invalid="true"]') != null) {
            $this->exts->loginFailure(1);
        }

        // Click next if confirm form showed
        $this->exts->moveToElementAndClick('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
        $this->googleCheckTwoFactorMethod();

        if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
            $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
            sleep(10);
        }
        if ($this->exts->exists('#tos_form input#accept')) {
            $this->exts->moveToElementAndClick('#tos_form input#accept');
            sleep(10);
        }
        if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
            $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
            sleep(10);
        }
        if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('.action-button.signin-button');
            sleep(10);
        }
        if ($this->exts->exists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')) {
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
            sleep(10);
        }
        if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
            $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
            sleep(10);
        }
        if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
            $this->exts->moveToElementAndClick('input[name="later"]');
            sleep(7);
        }
        if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
            $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
            sleep(7);
        }
        if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
            $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
            sleep(10);
        }

        if ($this->exts->exists('#submit_approve_access')) {
            $this->exts->moveToElementAndClick('#submit_approve_access');
            sleep(10);
        } else if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->moveToElementAndClick('form #approve_button[name="submit_true"]');
            sleep(10);
        }
        $this->exts->capture("3-google-before-back-to-main-tab");
    } else {
        $this->exts->log(__FUNCTION__ . '::Not required google login.');
        $this->exts->capture("3-no-google-required");
    }
}

private function googleCheckFillLogin()
{
    if ($this->exts->exists('form ul li [role="link"][data-identifier]')) {
        $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
        sleep(5);
    }

    if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
        $this->exts->capture("google-verify-it-you");
        // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
    }

    $this->exts->capture("2-google-login-page");
    if ($this->exts->exists($this->google_username_selector)) {
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
        if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
            }
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
            }
        } else if ($this->exts->urlContains('/challenge/recaptcha')) {
            $this->googlecheckFillRecaptcha();
            $this->exts->moveToElementAndClick('[data-primary-action-label] > div > div:first-child button');
            sleep(5);
        }

        // Which account do you want to use?
        if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
            $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
            $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }

    if ($this->exts->exists($this->google_password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);
        if ($this->exts->exists('#captchaimg[src]')) {
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
        }

        $this->exts->capture("2-google-login-page-filled");
        $this->exts->moveToElementAndClick($this->google_submit_password_selector);
        sleep(5);
        if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
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
        $this->exts->log(__FUNCTION__ . '::Google password page not found');
        $this->exts->capture("2-google-password-page-not-found");
    }
}

private function googleCheckTwoFactorMethod()
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
    $this->exts->capture("2.0-before-check-two-factor-google");
    // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
    if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
        $this->exts->moveToElementAndClick('#assistActionId');
        sleep(5);
    } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
        // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list-google");
        if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
        }
    } else if ($this->exts->urlContains('/sk/webauthn')) {
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
        sleep(3);
        $this->exts->capture("2.0-cancel-security-usb-google");
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if ($this->exts->exists('input[name="ootpPin"]')) {
        // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    }

    // STEP 1: Check if list of two factor methods showed, select first
    if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
        // We most RECOMMEND confirm security phone or email, then other method
        if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
            $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
            // We RECOMMEND method type = 6 is get code from Google Authenticator
            $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')) {
            // We second RECOMMEND method type = 9 is get code from SMS
            $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')) {
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
            // Use a smartphone or tablet to receive a security code (even when offline)
            $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
            // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
        } else {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
        }
        sleep(10);
    } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
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
            $this->exts->type_key_by_xdotool("Return");
            sleep(7);
        }
        if ($this->exts->exists($input_selector)) {
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
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
            $this->exts->type_key_by_xdotool("Return");
            sleep(5);
        }
        if ($this->exts->exists($input_selector)) {
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
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
            $this->exts->type_key_by_xdotool("Return");
            sleep(7);
        }
        if ($this->exts->exists($input_selector)) {
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        }
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionId
    }

    // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
    if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
        sleep(10);
    } else if (count($this->exts->getElements('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
        $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
        sleep(7);
    }


    // STEP 4: input code
    if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
        $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
    } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
        $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->exists('input[name="Pin"]')) {
        $input_selector = 'input[name="Pin"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
        $input_selector = 'input[name="secretQuestionResponse"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
    }
}

private function googleFillTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
{
    $this->exts->log(__FUNCTION__);
    $this->exts->log("Google two factor page found.");
    $this->exts->capture("2.1-two-factor-google");

    if ($this->exts->querySelector($message_selector) != null) {
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
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
            $this->exts->log(__FUNCTION__ . ": Entering two_factor_code: " . $two_factor_code);
            $this->exts->moveToElementAndType($input_selector, '');
            $this->exts->moveToElementAndType($input_selector, $two_factor_code);
            sleep(1);
            if ($this->exts->allExists(['input[type="checkbox"]:not(:checked) + div', 'input[name="Pin"]'])) {
                $this->exts->moveToElementAndClick('input[type="checkbox"]:not(:checked) + div');
                sleep(1);
            }
            $this->exts->capture("2.2-google-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->exts->exists($submit_selector)) {
                $this->exts->log(__FUNCTION__ . ": Clicking submit button.");
                $this->exts->moveToElementAndClick($submit_selector);
            } else if ($submit_by_enter) {
                $this->exts->type_key_by_xdotool("Return");
            }
            sleep(10);
            $this->exts->capture("2.2-google-two-factor-submitted-" . $this->exts->two_factor_attempts);
            if ($this->exts->querySelector($input_selector) == null) {
                $this->exts->log("Google two factor solved");
            } else {
                if ($this->exts->two_factor_attempts < 3) {
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

private function googlecheckFillRecaptcha()
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'form iframe[src*="/recaptcha/"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);
        $url = reset(explode('?', $this->exts->getUrl()));
        $isCaptchaSolved = $this->exts->processRecaptcha($url, $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
            $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
            for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
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
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            if ($gcallbackFunction != null) {
                $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}
// End GOOGLE login