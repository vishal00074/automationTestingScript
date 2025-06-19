public $baseUrl = 'https://app.datadoghq.com/account/billing_history';
public $loginUrl = 'https://app.datadoghq.com/account/billing_history';
// public $invoicePageUrl = 'https://flagbit.datadoghq.com/billing/history';
public $invoicePageUrl = 'https://app.datadoghq.com/account/billing_history';


public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'form[action*="login"] button[type*="submit"]';

public $check_login_failed_selector = 'span[class*="validation__message"]';
public $check_login_success_selector = 'li[class*="item-account"], a[href*="/logout"], form[action*="logout"],a[href="/personal-settings/profile"]';

public $isNoInvoice = true;
public $google_login_selector = 'button.authentication_login_google-login-button';
public $google_login = 0;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->google_login =  isset($this->exts->config_array["login_with_google"]) ? (int)@$this->exts->config_array["login_with_google"] : 0;

    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);

        $this->exts->log('google login config: ' . $this->google_login);
        if ($this->google_login == 1) {
            $this->exts->moveToElementAndClick($this->google_login_selector);
            sleep(30);
            $this->exts->log("google login page loaded");
            $this->loginGoogleIfRequired();
        } else {
            $this->checkFillLogin();
            sleep(20);
        }
        $this->checkFillTwoFactor();
    }

    if ($this->checkLogin()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'The username or password you entered is incorrect.') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
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
        sleep(5);
        $this->waitFor('iframe[src*="/recaptcha/api2/anchor?"]');
        $this->checkFillRecaptcha();
        $this->checkFillRecaptcha();
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillRecaptcha()
{

    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if ($this->exts->querySelector($recaptcha_iframe_selector) != null) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
            $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
            for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->executeSafeScript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');

            // Step 2, check if callback function need executed
            $gcallbackFunction = $this->exts->executeSafeScript('
            if(document.querySelector("[data-callback]") != null){
                return document.querySelector("[data-callback]").getAttribute("data-callback");
            }

            var result = ""; var found = false;
            function recurse (cur, prop, deep) {
                if(deep > 5 || found){ return;}console.log(prop);
                try {
                    if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
                    if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                    } else { deep++;
                        for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                    }
                } catch(ex) { console.log("ERROR in function: " + ex); return; }
            }

            recurse(___grecaptcha_cfg.clients[0], "", 0);
            return found ? "___grecaptcha_cfg.clients[0]." + result : null;
        ');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            if ($gcallbackFunction != null) {
                $this->exts->executeSafeScript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}

private function checkFillTwoFactor()
{
    $two_factor_message_selector = '[role="alert"] [class*="message-box"], input#totp';

    if (strpos(strtolower($this->exts->extract($two_factor_message_selector)), 'this was me') !== false) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . 'Please input "OK" after Tap "This was me"!';
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . 'Please input "OK" after Tap "This was me"!';;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);

            $this->checkFillLogin();
            if ($this->exts->getElement($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->notification_uid = "";
                $this->exts->two_factor_attempts++;
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
    $two_factor_selector = 'input#totp';
    $two_factor_submit_selector = 'form[action*="/login/totp"] button[type="submit"]';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                if (!$this->exts->exists('[role="alert"] [class*="message-box"]')) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->extract($two_factor_message_selector, null, 'placeholder') . "\n";
                    break;
                } else {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
                }
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
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

public  function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

// -------------------- GOOGLE login
public $google_username_selector = 'input[name="identifier"]';
public $google_submit_username_selector = '#identifierNext';
public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
public $google_submit_password_selector = '#passwordNext, #passwordNext button';
public $google_solved_rejected_browser = false;

public $security_phone_number = '';
public $recovery_email = '';

private function loginGoogleIfRequired()
{
    $this->security_phone_number = isset($this->exts->config_array["security_phone_number"]) ? $this->exts->config_array["security_phone_number"] : '';
    $this->recovery_email = isset($this->exts->config_array["recovery_email"]) ? $this->exts->config_array["recovery_email"] : '';

    if ($this->exts->urlContains('google.')) {
        $this->checkFillGoogleLogin();
        sleep(10);
        $this->check_solve_rejected_browser();

        if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null) {
            $this->exts->loginFailure(1);
        }

        if ($this->isExists('form #approve_button[name="submit_true"]')) {
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
            sleep(10);
        }
        // Click next if confirm form showed
        $this->exts->click_by_xdotool('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
        $this->checkGoogleTwoFactorMethod();
        sleep(10);
        if ($this->isExists('#smsauth-interstitial-remindbutton')) {
            $this->exts->click_by_xdotool('#smsauth-interstitial-remindbutton');
            sleep(10);
        }
        if ($this->isExists('#tos_form input#accept')) {
            $this->exts->click_by_xdotool('#tos_form input#accept');
            sleep(10);
        }
        if ($this->isExists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
            $this->exts->click_by_xdotool('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
            sleep(10);
        }
        if ($this->isExists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
            // SKIP setup 2FA
            $this->exts->click_by_xdotool('.action-button.signin-button');
            sleep(10);
        }
        if ($this->isExists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
            $this->exts->click_by_xdotool('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
            sleep(10);
        }
        if ($this->isExists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
            $this->exts->click_by_xdotool('input[name="later"]');
            sleep(7);
        }
        if ($this->isExists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
            $this->exts->click_by_xdotool('#editLanguageAndContactForm a[href*="/adsense/app"]');
            sleep(7);
        }
        if ($this->isExists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
            $this->exts->click_by_xdotool('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
            sleep(10);
        }
        if ($this->exts->urlContains('gds.google.com/web/chip')) {
            $this->exts->click_by_xdotool('[role="button"]:first-child [jsslot]');
            sleep(10);
        }

        if ($this->isExists('form #approve_button[name="submit_true"]')) {
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
    if ($this->isExists('[data-view-id*="signInChooserView"] li [data-identifier]')) {
        $this->exts->click_by_xdotool('[data-view-id*="signInChooserView"] li [data-identifier]');
        sleep(10);
    } else if ($this->isExists('form li [role="link"][data-identifier]')) {
        $this->exts->click_by_xdotool('form li [role="link"][data-identifier]');
        sleep(10);
    }
    if ($this->isExists('form [data-profileindex]')) {
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
        if ($this->isExists('#captchaimg[src]') && !$this->isExists($this->google_password_selector) && $this->isExists($this->google_username_selector)) {
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(5);
        }

        // Which account do you want to use?
        if ($this->isExists('form[action*="/lookup"] button.account-chooser-button')) {
            $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if ($this->isExists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
            $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }

    if ($this->exts->querySelector($this->google_password_selector) != null) {
        $this->exts->log("Enter Google Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);

        if ($this->isExists('#captchaimg[src]')) {
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
        }

        $this->exts->capture("2-google-password-filled");
        $this->exts->click_by_xdotool($this->google_submit_password_selector);
        sleep(5);
        if ($this->isExists('#captchaimg[src]') && $this->isExists($this->google_password_selector)) {
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            $this->exts->capture("2-login-google-pageandcaptcha-filled");
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(10);
            if ($this->isExists('#captchaimg[src]') && $this->isExists($this->google_password_selector)) {
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
    if ($this->isExists('form [data-profileindex]')) {
        $this->exts->click_by_xdotool('form [data-profileindex]');
        sleep(5);
    } else if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->isExists($this->google_submit_username_selector) && !$this->isExists($this->google_username_selector)) {
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
        if ($this->isExists('#captchaimg[src]')) {
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
        }

        $this->exts->capture("2-google-password-filled");
        $this->exts->click_by_xdotool($this->google_submit_password_selector);
        sleep(5);
        if ($this->isExists('#captchaimg[src]') && !$this->isExists('input[name="password"][aria-invalid="true"]') && $this->isExists($this->google_password_selector)) {
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->isExists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(5);
            if ($this->isExists('#captchaimg[src]') && $this->isExists($this->google_password_selector)) {
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
    if ($this->isExists('#assistActionId') && $this->isExists('[data-illustration="securityKeyLaptopAnim"]')) {
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
        sleep(5);
        $this->exts->type_key_by_xdotool('Escape');
        sleep(5);
        $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(10);
        $this->exts->capture("2.0-backed-methods-list");
    } else if ($this->isExists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->click_by_xdotool('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
        sleep(7);
    } else if ($this->isExists('input[name="ootpPin"]')) {
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
                $this->exts->executeSafeScript("arguments[0].click()", [$back_button]);
            }
        }
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list");
    }

    if ($this->isExists('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button')) {
        $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(10);
        $this->exts->capture("try-another-way-0");
    }

    // STEP 1: Check if list of two factor methods showed, select first
    if ($this->isExists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
        $this->exts->capture("2.1-2FA-method-list");

        // Updated 03-2023 since we setup sub-system to get authenticator code without request to end-user. So from now, We priority for code from Authenticator app top 1, sms code or email code 2st, then other methods
        if ($this->isExists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
            // We RECOMMEND TOP 1 method type = 6 is get code from Google Authenticator
            $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->click_by_xdotool('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
            $this->exts->click_by_xdotool('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype="1"]:not([data-challengeunavailable="true"])')) {
            // Select enter your passowrd, if only option is passkey
            $this->exts->click_by_xdotool('li [data-challengetype="1"]:not([data-challengeunavailable="true"])');
            sleep(3);
            $this->checkFillGoogleLogin();
            sleep(3);
            $this->checkGoogleTwoFactorMethod();
        } else if ($this->isExists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
            // We RECOMMEND method type = 6 is get code from Google Authenticator
            $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])') && (isset($this->security_phone_number) && $this->security_phone_number != '')) {
            // We second RECOMMEND method type = 9 is get code from SMS
            $this->exts->click_by_xdotool('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="10"]:not([data-challengeunavailable="true"])')) {
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="12"]:not([data-challengeunavailable="true"])')) {
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
            // Use a smartphone or tablet to receive a security code (even when offline)
            $this->exts->click_by_xdotool('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li div[data-sendmethod="SMS"]')) {
            // Click on phone option

            $this->exts->click_by_xdotool('li div[data-sendmethod="SMS"]');
            $this->exts->capture('select-phone');
        } else if ($this->isExists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
            // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
            $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
        } else {
            $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"])');
        }
        sleep(10);
    } else if ($this->isExists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->isExists('#authzenNext') && $this->isExists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->isExists('#idvpreregisteredemailNext') && !$this->isExists('form input:not([type="hidden"])')) {
        $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
        sleep(10);
    }

    // STEP 2: (Optional)
    if ($this->isExists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')) {
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
        if ($this->isExists($input_selector)) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if ($this->exts->querySelector('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]') != null) {
        // If methos confirm recovery phone number, send 2FA to ask
        $this->exts->log('Request for 2fa mobile-1');
        $this->exts->capture('mobile-2fa-1');
        $this->exts->two_factor_attempts = 3;
        $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
        $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
        $submit_selector = '';
        if (isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool('Return');
            sleep(5);
        }
        if ($this->exts->querySelector($input_selector) != null) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if ($this->exts->querySelector('input#phoneNumberId') != null) {
        // Enter a phone number to receive an SMS with a confirmation code.
        $this->exts->log('Request for 2fa mobile-2');
        $this->exts->capture('mobile-2fa-2');
        $this->exts->two_factor_attempts = 3;
        $input_selector = 'input#phoneNumberId';
        $message_selector = '[data-view-id] form section > div > div > div:first-child';
        $submit_selector = '';
        if (isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool('Return');
            sleep(7);
        }
        if ($this->exts->querySelector($input_selector) != null) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
        }
    } else if ($this->exts->querySelector('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') != null || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->querySelector('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]') != null) {
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionId
    }

    // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
    if ($this->exts->querySelector('#smsButton, [data-illustration="accountRecoverySmsPin"]') != null) {
        // Sometime user must confirm before google send sms
        $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->exts->querySelector('#authzenNext') != null && $this->exts->querySelector('[data-view-id*="authzenView"], [data-illustration*="authzen"]') != null) {
        $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->exts->querySelector('#idvpreregisteredemailNext') != null && !$this->exts->querySelector('form input:not([type="hidden"])') != null) {
        $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
        sleep(10);
    } else if (count($this->exts->querySelectorAll('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
        $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
        sleep(7);
    }


    // STEP 4: input code
    if ($this->exts->querySelector('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId') != null) {
        $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext';
        $this->exts->two_factor_attempts = 3;
        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->querySelector('input[name="ootpPin"], input#securityKeyOtpInputId') != null) {
        $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->querySelector('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?') != null) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->querySelector('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]') != null) {
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionIdk
    } else if ($this->exts->querySelector('input[name="secretQuestionResponse"]') != null) {
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

            if ($this->exts->querySelector($submit_selector) != null) {
                $this->exts->log("fillTwoFactor: Clicking submit button.");
                $this->exts->click_by_xdotool($submit_selector);
            } else if ($submit_by_enter) {
                $this->exts->type_key_by_xdotool('Return');
            }
            sleep(10);
            $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
            if ($this->exts->querySelector($input_selector) == null) {
                $this->exts->log("Two factor solved");
                $this->exts->capture("Two-factor-solved");
            } else {
                if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
                    $this->exts->two_factor_attempts++;
                    if ($this->exts->querySelector('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin') != null) {
                        $this->exts->click_by_xdotool('[aria-relevant="additions"] + [style*="visibility: visible;"] [role="button"]');
                        sleep(2);
                        $this->exts->capture("2.2-two-factor-resend-code-" . $this->exts->two_factor_attempts);
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
// End GOOGLE login
public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}