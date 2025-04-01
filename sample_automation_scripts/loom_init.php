public $baseUrl = 'https://www.loom.com/';
public $loginUrl = 'https://www.loom.com/login';
public $invoicePageUrl = 'https://www.loom.com/settings/workspace#plans';

public $username_selector = 'div[class*="signup_loomSignupEmailForm"] input#email, div[class*="signup_loomSignupComponent"] input#email, form input[type="email"]';
public $password_selector = 'input#password';

public $check_login_failed_selector = 'div[class*="email-signup-form_loomMaskedInputContainer"] + span, div.error-bar.error, form input[type=email] + span';
public $check_login_success_selector = 'a[href="/settings/account"], button[aria-controls="settings-panel"], [class*="profile-bubble_avatarLink"]';

public $login_with_google = 0;

public $login_with_apple = 0;

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    // $this->disable_uBlock_extensions();
    $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)$this->exts->config_array["login_with_google"] : $this->login_with_google;
    $this->login_with_apple = isset($this->exts->config_array["login_with_apple"]) ? (int)$this->exts->config_array["login_with_apple"] : $this->login_with_apple;
    $this->exts->openUrl($this->baseUrl);
    sleep(12);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(20);
    $this->exts->capture('1-init-page');

    if ($this->exts->exists('ul[class*="header_actions"] img[src*="avatar"]')) {
        $this->exts->log('have avatar');
        $this->exts->moveToElementAndClick('ul[class*="header_actions"] img[src*="avatar"]');
        sleep(4);
    }

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->checkFillLogin();
        sleep(20);
    }

    // then check user logged in or not
    if ($this->exts->urlContains('/select-workspace')) {
        $this->exts->moveToElementAndClick('a[class*="workspace-selector"]');
        sleep(16);
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
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'your login information was incorrect') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'unable to log this user in at this time') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('#email-signup-button[type="submit"]', null, 'innerText')), 'create free account') !== false) {
            $this->exts->capture('Account-not-register');
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('.signup_loomSignupComponentWrap_cao', null, 'innerText')), 'sent a verification link to the email you provided') !== false || $this->exts->exists('button[id="resendVerificationEmailButton"]')) {
            $this->exts->account_not_ready();
        } else if (strpos(strtolower($this->exts->extract('div[class*="select-workspace_container"]', null, 'innerText')), 'this account is deactivated in the following workspaces') !== false) {
            $this->exts->account_not_ready();
        } else if (stripos($this->exts->extract('.select-workspace_container_1Y1  > div > div > span[font-weight="bold"]'), 'Your account was deactivated') !== false) {
            $this->exts->account_not_ready();
        } else if ($this->exts->exists('div[direction="column"] > span')) {
            $this->exts->log('Your login information was incorrect.');
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin()
{

    $this->exts->log('login_with_google_value ' . $this->login_with_google);

    $this->exts->log('login_with_apple_value ' . $this->login_with_apple);

    if ($this->login_with_google == 1) {
        $this->exts->moveToElementAndClick('button#googleOAuthButton, button[data-testid*="googleOAuthButton"]');
        $this->exts->log('Open Google Login Page');
        sleep(3);
        $this->exts->switchToNewestActiveTab();
        $this->loginGoogleIfRequired();
        $this->exts->capture("after-login-google");
        $tab_buttons = $this->exts->getElements('div.sticky button');
        foreach ($tab_buttons as $key => $tab_button) {
            $tab_name = trim($tab_button->getAttribute('innerText'));
            if (stripos($tab_name, 'I Agree') !== false) {
                $tab_button->click();
                sleep(20);
                break;
            }
        }
        $this->exts->capture("after-login-google-1");
        $this->exts->switchToInitTab();
    } else if ($this->login_with_apple == 1) {
        $this->exts->moveToElementAndClick('button#appleOAuthButton, button[data-testid*="appleOAuthButton"]');
        sleep(5);
        $this->exts->switchToNewestActiveTab();
        $this->loginAppleIfRequired();
        $this->exts->capture("after-login-apple");
        $this->exts->switchToInitTab();
    } else if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(4);
        $this->exts->capture("2-email-filled");

        if (stripos($this->exts->extract('#email-signup-button[type="submit"]', null, 'innerText'), 'Atlassian') !== false) {
            $this->exts->log('inside Atlassian Login');
            $this->exts->moveToElementAndClick('#email-signup-button[type="submit"]');
            sleep(7);
        }

        $loginTab = $this->exts->findTabMatchedUrl(['id.atlassian.com/login?']);
        if ($loginTab != null) {
            $this->exts->switchToTab($loginTab);
            sleep(4);
        }

        if ($this->exts->exists('button.amzn-captcha-verify-button')) {
            $this->exts->moveToElementAndClick('button.amzn-captcha-verify-button');
            sleep(10);
        }

        if ($this->exts->exists('button[id="login-submit"]')) {
            $this->exts->moveToElementAndClick('button[id="login-submit"]');
            sleep(10);
        }

        if ($this->exts->exists($this->password_selector)) {
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->capture("2-password-filled");
            $this->exts->moveToElementAndClick('button[id="login-submit"]');
            sleep(7);
            $this->exts->capture("2-after-password-submit");

            $error_text = strtolower($this->exts->extract('div[data-testid="form-error--content"] div'));

            $this->exts->log('error text:: ' . $error_text);

            if (stripos($error_text, strtolower('Incorrect email address and/or password. Try again or')) !== false) {
                $this->exts->loginFailure(1);
            }
        } else if (stripos($this->exts->extract('#email-signup-button[type="submit"]', null, 'innerText'), 'Google') !== false) {
            $this->exts->moveToElementAndClick('#email-signup-button[type="submit"]');
            sleep(5);
            $this->exts->switchToNewestActiveTab();
            $this->exts->log('inside google Login');
            $this->loginGoogleIfRequired();
        } else if (stripos($this->exts->extract('#email-signup-button[type="submit"]', null, 'innerText'), 'Slack') !== false) {
            $this->exts->loginFailure(1);
        } else if (stripos($this->exts->extract('#email-signup-button[type="submit"]', null, 'innerText'), 'Apple') !== false) {
            $this->exts->moveToElementAndClick('#email-signup-button[type="submit"]');
            sleep(5);
            $this->exts->switchToNewestActiveTab();
            $this->loginAppleIfRequired();
            $this->exts->capture("after-login-apple");
            $this->exts->switchToInitTab();
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

// -------------------- GOOGLE login
public $google_username_selector = 'input[name="identifier"]';
public $google_submit_username_selector = '#identifierNext';
public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
public $google_submit_password_selector = '#passwordNext, #passwordNext button';
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
                $this->exts->executeSafeScript("arguments[0].click()", [$back_button]);
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
        } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="10"]:not([data-challengeunavailable="true"])')) {
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
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

// ================================== BEGIN LOGIN WITH APPLE ==================================
public $apple_username_selector = 'input#account_name_text_field';
public $apple_password_selector = '#stepEl:not(.hide) .password:not([aria-hidden="true"]) input#password_text_field';
public $apple_submit_login_selector = 'button#sign-in';
private function loginAppleIfRequired()
{
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->urlContains('apple.com/auth/authorize')) {
        $this->checkFillAppleLogin();
        sleep(1);
        $this->exts->switchToDefault();
        if ($this->exts->exists('iframe[name="aid-auth-widget"]')) {
            $this->exts->switchToFrame('iframe[name="aid-auth-widget"]');
        }
        if ($this->exts->exists('.signin-error #errMsg + a')) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('iframe[src*="/account/repair"], repair-missing-items, button[id*="unlock-account-"]')) {
            $this->exts->account_not_ready();
        }

        $this->exts->switchToDefault();
        $this->checkFillAppleTwoFactor();
        $this->exts->switchToDefault();
        if ($this->exts->exists('iframe[src*="/account/repair"]')) {
            $this->exts->switchToFrame('iframe[src*="/account/repair"]');
            // If 2FA setting up page showed, click to cancel
            if ($this->exts->allExists(['.idms-step-content .icon-2fa', 'button.button-secondary.nav-cancel'])) {
                // Click "Other Option"
                $this->exts->moveToElementAndClick('button.button-secondary.nav-cancel');
                sleep(5);
                // Click "Dont upgrade"
                $this->exts->moveToElementAndClick('.confirmCancelContainer.fade-in button.button-secondary.nav-cancel');
                sleep(15);
            }
            $this->exts->switchToDefault();
        }

        // Click to accept consent temps, Must go inside 2 frame
        if ($this->exts->exists('iframe#aid-auth-widget-iFrame')) {
            $this->exts->switchToFrame('iframe#aid-auth-widget-iFrame');
        }
        if ($this->exts->exists('iframe[src*="/account/repair"]')) {
            $this->exts->switchToFrame('iframe[src*="/account/repair"]');
            // If 2FA setting up page showed, click to cancel
            if ($this->exts->allExists(['.idms-step-content .icon-2fa', 'button.button-secondary.nav-cancel'])) {
                // Click "Other Option"
                $this->exts->moveToElementAndClick('button.button-secondary.nav-cancel');
                sleep(5);
                // Click "Dont upgrade"
                $this->exts->moveToElementAndClick('.confirmCancelContainer.fade-in button.button-secondary.nav-cancel');
                sleep(15);
            }
            $this->exts->switchToDefault();
        }
        if ($this->exts->exists('.privacy-consent.fade-in button.nav-action')) {
            $this->exts->moveToElementAndClick('.privacy-consent.fade-in button.nav-action');
            sleep(15);
        }
        // end accept consent
    }
}
private function checkFillAppleLogin()
{
    $this->exts->switchToFrame('iframe[name="aid-auth-widget"]');
    $this->exts->capture("2-apple_login-page");
    if ($this->exts->getElement($this->apple_username_selector) != null) {
        sleep(1);
        $this->exts->log("Enter apple_ Username");

        $this->exts->moveToElementAndClick($this->apple_username_selector);
        sleep(2);
        $this->exts->moveToElementAndType($this->apple_username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick($this->apple_submit_login_selector);
        sleep(10);
    }

    if ($this->exts->getElement($this->apple_password_selector) != null) {
        $this->exts->log("Enter apple_ Password");
        $this->exts->moveToElementAndType($this->apple_password_selector, $this->password);
        sleep(1);
        if ($this->exts->exists('#remember-me:not(:checked)')) {
            $this->exts->moveToElementAndClick('label#remember-me-label');
            // sleep(2);
        }
        $this->exts->capture("2-apple_login-page-filled");
        $this->exts->moveToElementAndClick($this->apple_submit_login_selector);
        sleep(2);

        $this->exts->capture("2-apple_after-login-submit");
        $this->exts->switchToDefault();

        $this->exts->log(count($this->exts->getElements('iframe[name="aid-auth-widget"]')));
        $this->exts->switchToFrame('iframe[name="aid-auth-widget"]');
        sleep(1);

        if ($this->exts->exists('iframe[src*="/account/repair"]')) {
            $this->exts->switchToFrame('iframe[src*="/account/repair"]');
            // If 2FA setting up page showed, click to cancel
            if ($this->exts->allExists(['.idms-step-content .icon-2fa', 'button.button-secondary.nav-cancel'])) {
                // Click "Other Option"
                $this->exts->moveToElementAndClick('button.button-secondary.nav-cancel');
                sleep(5);
                // Click "Dont upgrade"
                $this->exts->moveToElementAndClick('.confirmCancelContainer.fade-in button.button-secondary.nav-cancel');
                sleep(15);
            }
            $this->exts->switchToDefault();
        }

        $this->exts->log(count($this->exts->getElements('iframe[name="aid-auth-widget"]')));
        $this->exts->switchToFrame('iframe[name="aid-auth-widget"]');
        sleep(1);
        if ($this->exts->getElement($this->apple_password_selector) != null && !$this->exts->exists('.signin-error #errMsg + a')) {
            $this->exts->log("Re-enter apple_ Password");
            $this->exts->moveToElementAndType($this->apple_password_selector, $this->password);
            sleep(1);
            $this->exts->capture("2-re-enter-apple_password");
            $this->exts->type_key_by_xdotool('Return');
        }
    } else {
        $this->exts->capture("2-apple_login-page-not-found");
    }
}
private function checkFillAppleTwoFactor()
{
    $this->exts->switchToFrame('#aid-auth-widget-iFrame');
    if ($this->exts->exists('.devices [role="list"] [role="button"][device-id]')) {
        $this->exts->moveToElementAndClick('.devices [role="list"] [role="button"][device-id]');
        sleep(5);
    }
    if ($this->exts->exists('div#stepEl div.phones div[class*="si-phone-name"]')) {
        $this->exts->log("Choose apple Phone");
        $this->exts->moveToElementAndClick('div#stepEl div.phones div[class*="si-phone-name"]');
        sleep(5);
    }
    if ($this->exts->getElement('input[id^="char"]') != null) {
        $this->exts->two_factor_notif_title_en = 'Apple login for ' . $this->exts->two_factor_notif_title_en;
        $this->exts->two_factor_notif_title_de = 'Apple login fur ' . $this->exts->two_factor_notif_title_de;

        $this->exts->log("Current apple URL - " . $this->exts->getUrl());
        $this->exts->log("Two apple factor page found.");
        $this->exts->capture("2.1-apple-two-factor");

        if ($this->exts->getElement('.verify-code .si-info, .verify-phone .si-info, .si-info') != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement('.verify-code .si-info, .verify-phone .si-info, .si-info')->getAttribute('innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        if ($this->exts->two_factor_attempts > 1) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->log("apple Message:\n" . $this->exts->two_factor_notif_msg_en);
        if ($this->exts->two_factor_attempts > 1) {
            $this->exts->moveToElementAndClick('.verify-device a#no-trstd-device-pop, .verify-phone a#didnt-get-code, a#didnt-get-code, a#no-trstd-device-pop');
            sleep(1);

            $this->exts->moveToElementAndClick('.verify-device .try-again a#try-again-link, .verify-phone a#try-again-link, .try-again a#try-again-link');
        }

        $two_factor_code = $this->exts->fetchTwoFactorCode();
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log(__FUNCTION__ . ": Entering apple two_factor_code." . $two_factor_code);
            $resultCodes = str_split($two_factor_code);
            $code_inputs = $this->exts->getElements('input[id^="char"]');
            foreach ($code_inputs as $key => $code_input) {
                if (array_key_exists($key, $resultCodes)) {
                    $this->exts->log(__FUNCTION__ . ': Entering apple key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('id'));

                    $this->exts->moveToElementAndType($code_input, $resultCodes[$key]);
                    $this->exts->capture("2.2-apple-two-factor-filled-" . $this->exts->two_factor_attempts);
                } else {
                    $this->exts->log(__FUNCTION__ . ': Have no char for input #' . $code_input->getAttribute('id'));
                }
            }
            sleep(15);
            $this->exts->capture("2.2-apple-two-factor-submitted-" . $this->exts->two_factor_attempts);
            $this->exts->switchToFrame('#aid-auth-widget-iFrame');

            if ($this->exts->getElement('input[id^="char"]') != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = "";

                $this->checkFillAppleTwoFactor();
            }

            if ($this->exts->exists('.button-bar button:last-child[id*="trust-browser-"]')) {
                $this->exts->moveToElementAndClick('.button-bar button:last-child[id*="trust-browser-"]');
                sleep(10);
            }
        } else {
            $this->exts->log("Not received apple two factor code");
        }
    }
}
// ================================== END LOGIN WITH APPLE ==================================