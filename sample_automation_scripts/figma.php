<?php
// Server-Portal-ID: 77708 - Last modified: 18.11.2024 14:39:52 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = "https://www.figma.com";
public $loginUrl = "https://www.figma.com/login";
public $homePageUrl = "https://www.figma.com";
public $username_selector = "input[name='email']";
public $password_selector = "input[name='password']";
public $login_button_selector = "form#auth-view-page > button[type='submit']";
public $login_confirm_selector = 'a[data-tooltip-text*="notifications"], div[class*="notifications"], div[class*="workspace_icon"] div[class*="avatar--avatar"] img, div[class*="profileAction"] div[class*="workspace_icon"] span[class*="avatar--avatar"], button[class*="profileAction"] div[class*="workspace_icon"] span[class*="avatar--avatar"]';
public $login_tryout = 0;

public $login_with_google = '0';
public $isNoInvoice = true;


/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    // $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)@$this->exts->config_array["login_with_google"] : 0;
    // Hardcoded for test google login in testing enigne
    $this->login_with_google = 1;

    $this->exts->openUrl($this->baseUrl);

    sleep(15);
    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        $this->exts->openUrl($this->homePageUrl);
        sleep(30);
        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        }
    }

    if (!$isCookieLoginSuccess) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);

        if ((int)@$this->login_with_google == 1) {
            $this->exts->moveToElementAndClick('button[class*="landing_form--googleButton"], button div[class*="googleButtonInner"]');
            sleep(10);
            $google_tab = $this->exts->findTabMatchedUrl(['google']);
            if ($google_tab != null) {
                $this->exts->switchToTab($google_tab);
            }

            $this->loginGoogleIfRequired();
            sleep(5);
            $this->exts->switchToInitTab();
            sleep(5);
            if ($this->exts->urlContains('onelogin.com/login')) {
                $this->fillFormOnelogin();
                sleep(10);
            }
            // $this->exts->webdriver->switchTo()->window($this->exts->webdriver->getWindowHandles()[0]);
        } else {
            $this->exts->capture("after-login-clicked");
            $this->fillForm(0);
            sleep(20);
            $this->check_and_solve_challenge();

            $this->exts->moveToElementAndClick('div[class*="onboarding_components"] div[class*="close_button--closeX"]');
            sleep(5);
            // if(strpos($this->exts->extract('h1[class*="validate_email--header"]'), 'Check your inbox') !== false){
            //     $this->exts->log("Two factor page found.");
            //     $this->exts->capture("2.1-two-factor");
            //     $two_factor_message_selector = 'div[data-component-name="auth-view"] p';
            //     $this->exts->log($this->exts->extract($two_factor_message_selector));
            // }
            $this->checkFillTwoFactor();
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->doAfterLogin();
        } else {
            if ($this->exts->extract('#auth-view-page div[class*="auth_form--error"]') != '') {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure(1);
            } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->log("Username is not a valid email address");
                $this->exts->loginFailure(1);
            } elseif (stripos($this->exts->extract('p[class*="error"]'), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    } else {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->doAfterLogin();
    }
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
private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        // if( $this->exts->getElement("div.header-module--desktopHeaderLinks--3sLEo > div:nth-child(5)") != null){
        // 	$this->exts->getElement("div.header-module--desktopHeaderLinks--3sLEo > div:nth-child(5)")->click();
        // 	sleep(10);
        // }
        // $my_iframe = $this->exts->getElement("div[role='group'] > div:nth-child(2) > div > div > iframe");
        // $this->exts->webdriver->switchTo()->frame($my_iframe);
        if ($this->exts->getElement($this->username_selector) != null && $this->exts->getElement($this->password_selector) != null) {
            sleep(2);

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(5);
            if ($this->exts->exists($this->login_button_selector) &&  !$this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndClick($this->login_button_selector);
                sleep(5);
            }
            
            if ($this->exts->exists($this->password_selector)) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(5);
                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->login_button_selector);
            } else {
                if ($this->exts->exists($this->login_button_selector)) {
                    $this->exts->moveToElementAndClick($this->login_button_selector);
                    sleep(5);
                }
            }
            if ($this->exts->urlContains("login.microsoftonline")) {
                $this->loginMicrosoftIfRequired();
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}
private function fillFormOnelogin()
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        if ($this->exts->getElement('input#username') != null) {
            sleep(2);

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType('input#username', $this->username);
            sleep(5);
            $this->exts->moveToElementAndClick('button[type="submit"]');
            sleep(10);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType('input#password', $this->password);
            sleep(5);
            $this->exts->moveToElementAndClick('button[type="submit"]');
            if ($this->exts->waitTillVisible('[type="error"]', 10)) {
                $this->exts->log("Login Failure : " . $this->exts->extract('[type="error"]'));
                if (strpos(strtolower($this->exts->extract('[type="error"]')), 'passwor') !== false) {
                    $this->exts->loginFailure(1);
                }
            }
            sleep(10);
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
        if ($this->exts->getElement($this->login_confirm_selector) != null && !$this->exts->exists($this->username_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}
private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[name="totp_key"]';
    $two_factor_message_selector = 'form#auth-view-page div[class*="auth_form--header"], div[data-component-name="auth-view"] p';
    $two_factor_submit_selector = 'form#auth-view-page button';

    if (($this->exts->getElement($two_factor_selector) != null || strpos($this->exts->extract('h1[class*="validate_email--header"]'), 'Check your inbox') !== false) && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
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
        if (strpos($this->exts->extract('h1[class*="validate_email--header"]'), 'Check your inbox') !== false) {
            $this->exts->log("Sending 2FA request to ask user click on confirm link");
            $this->exts->two_factor_notif_msg_en = 'Click the confirm link that has sent to your email.' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = urldecode('Cliquez sur le lien de confirmation dans votre e-mail.') . "\n>>>Entrez OK apres confirmation.";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("User clicked on confirm link");
                sleep(5);
            }
        } elseif (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->getElement($two_factor_selector)->sendKeys($two_factor_code);

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
private function check_and_solve_challenge()
{
    // Check and solve Funcaptcha, It can require 1 pics, 2 or 5 pics. So check 5 times.
    $funcaptcha_displayed = $this->process_funcaptcha_by_clicking();
    if ($funcaptcha_displayed) {
        $this->process_funcaptcha_by_clicking();
        $this->process_funcaptcha_by_clicking();
        $this->process_funcaptcha_by_clicking();
        $this->process_funcaptcha_by_clicking();
        $funcaptcha_displayed = $this->process_funcaptcha_by_clicking();
    }

    // if Step above failed, try again
    if ($funcaptcha_displayed) {
        $this->process_funcaptcha_by_clicking();
        $this->process_funcaptcha_by_clicking();
        $this->process_funcaptcha_by_clicking();
        $this->process_funcaptcha_by_clicking();
        $this->process_funcaptcha_by_clicking();
        $this->process_funcaptcha_by_clicking();
    }
}
private function process_funcaptcha_by_clicking()
{
    $this->exts->log("Checking Funcaptcha");
    $language_code = $this->exts->extract('[lang]', null, "lang");
    if ($this->exts->exists('iframe#arkoseFrame')) {
        $this->exts->switchToFrame('iframe#arkoseFrame');
    }

    if ($this->exts->exists('[data-e2e="enforcement-frame"].show')) {
        $this->exts->capture("funcaptcha");
        $this->exts->switchToFrame('[data-e2e="enforcement-frame"].show');

        if ($this->exts->exists('#fc-iframe-wrap')) {
            $this->exts->switchToFrame('#fc-iframe-wrap');
        }
        if ($this->exts->exists('.front [id*="CaptchaFrame"]')) {
            $this->exts->switchToFrame('.front [id*="CaptchaFrame"]');
            // Click button to show images challenge
            if ($this->exts->exists('#home_children_button')) {
                $this->exts->moveToElementAndClick('#home_children_button');
                sleep(2);
            } else if ($this->exts->exists('#wrong_children_button, a#wrongTimeout_children_button')) {
                $this->exts->moveToElementAndClick('#wrong_children_button, a#wrongTimeout_children_button');
                sleep(2);
            }
            $captcha_instruction = $this->exts->extract('#game #game_children_text');
            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
            $this->exts->switchToDefault();
            // if($this->exts->exists('iframe#arkoseFrame')){
            // 	$this->exts->switchToFrame('iframe#arkoseFrame');
            // }
            // $this->exts->switchToFrame('[data-e2e="enforcement-frame"].show');

            $captcha_wraper_selector = 'iframe#arkoseFrame';
            $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, $language_code, $json_result = true);
            if ($coordinates == '') {
                $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, $language_code, $json_result = true);
            }
            if ($coordinates != '') {
                $wraper = $this->exts->getElement($captcha_wraper_selector);
                $actions = $this->exts->webdriver->action();
                $this->exts->log('Clicking X/Y: ' . $coordinates[0]['x'] . '/' . $coordinates[0]['y']);
                $actions->moveToElement($wraper, intval($coordinates[0]['x']), intval($coordinates[0]['y']))->click()->perform();
                sleep(5);
            }
        }
        $this->exts->switchToDefault();
        return true;
    }
    $this->exts->switchToDefault();
    return false;
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

            $this->exts->type_key_by_xdotool('Return');
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
            }
            else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="12"]:not([data-challengeunavailable="true"])')) {
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
        } else if ($this->exts->getElement('#passwordError a[href*="ResetPassword"], #passwordError a[href*="passwordreset"]') != null) {
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
    if ($this->exts->isVisible('[role="listbox"] .row #otherTile[role="option"], div#otherTile')) {
        $this->exts->moveToElementAndClick('[role="listbox"] .row #otherTile[role="option"], div#otherTile');
        sleep(10);
    }

    $this->exts->capture("2-microsoft-login-page");
    if ($this->exts->getElement($this->microsoft_username_selector) != null) {
        sleep(3);
        $this->exts->log("Enter microsoft Username");
        $this->exts->moveToElementAndType($this->microsoft_username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick($this->microsoft_submit_login_selector);
        sleep(10);
    }

    //Some user need to approve login after entering username on the app
    if ($this->exts->exists('div#idDiv_RemoteNGC_PollingDescription')) {
        $this->exts->two_factor_timeout = 5;
        $polling_message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
        $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->getElementsAttribute($polling_message_selector, 'innerText')));
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->isVisible($this->remember_me_selector)) {
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            }
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            $this->exts->two_factor_timeout = 15;
        } else {
            if ($this->exts->exists('a#idA_PWD_SwitchToPassword')) {
                $this->exts->moveToElementAndClick('a#idA_PWD_SwitchToPassword');
                sleep(5);
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    if ($this->exts->exists('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]')) {
        // if site show: Already login with .. account, click logout and login with other account
        $this->exts->moveToElementAndClick('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]');
        sleep(10);
    }
    if ($this->exts->exists('a#mso_account_tile_link, #aadTile, #msaTile')) {
        // if site show: This Email is being used with multiple Microsoft accounts, Select depending on account_type variable
        //if account type is 1 then only personal account will be selected otherwise business account.
        if ($this->microsoft_account_type == 1) {
            $this->exts->moveToElementAndClick('#msaTile');
        } else {
            $this->exts->moveToElementAndClick('a#mso_account_tile_link, #aadTile');
        }
        sleep(10);
    }
    if ($this->exts->exists('form #idA_PWD_SwitchToPassword')) {
        $this->exts->moveToElementAndClick('form #idA_PWD_SwitchToPassword');
        sleep(5);
    }

    if ($this->exts->urlContains('jumpcloud')) {
        //Login with JumpCloud
        $this->exts->log("Enter JumpCloud Username");
        $this->exts->moveToElementAndType('input[name="email"]', $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick('input[type="checkbox"]');
        sleep(2);
        $this->exts->moveToElementAndClick('button[data-automation="loginButton"]');
        sleep(5);
        $this->exts->log("Enter JumpCloud Password");
        $this->exts->moveToElementAndType('input[name="password"]', $this->password);
        sleep(1);
        $this->exts->capture("2-microsoft-jumpcloud-page-filled");
        $this->exts->moveToElementAndClick('button[data-automation="loginButton"]');
        sleep(10);
        if ($this->getElementByText('div.LoginAlert__error', ['Authentication failed.'], null, false) != null) {
            $this->exts->loginFailure(1);
        }
    }

    if ($this->exts->getElement($this->microsoft_password_selector) != null) {
        $this->exts->log("Enter microsoft Password");
        $this->exts->moveToElementAndType($this->microsoft_password_selector, $this->password);
        sleep(1);
        $this->exts->moveToElementAndClick($this->microsoft_remember_me_selector);
        sleep(2);
        $this->exts->capture("2-microsoft-password-page-filled");
        $this->exts->moveToElementAndClick($this->microsoft_submit_login_selector);
        sleep(10);
        $this->exts->capture("2-microsoft-after-submit-password");
    } else {
        $this->exts->log(__FUNCTION__ . '::microsoft Password page not found');
    }

    $this->checkConfirmMicrosoftButton();
}

private function checkConfirmMicrosoftButton()
{
    // After submit password, It have many button can be showed, check and click it
    if ($this->exts->isVisible('form[action*="/kmsi"] input[name="DontShowAgain"], input#idSIButton9[aria-describedby="KmsiDescription"]')) {
        // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
        $this->exts->moveToElementAndClick('form input[name="DontShowAgain"] + span');
        sleep(3);
        $this->exts->moveToElementAndClick('form[action*="/kmsi"] input#idSIButton9, input#idSIButton9[aria-describedby="KmsiDescription"]');
        sleep(10);
    }
    if ($this->exts->exists('input#btnAskLater')) {
        $this->exts->moveToElementAndClick('input#btnAskLater');
        sleep(10);
    }
    if ($this->exts->exists('a[data-bind*=SkipMfaRegistration]')) {
        $this->exts->moveToElementAndClick('a[data-bind*=SkipMfaRegistration]');
        sleep(10);
    }
    if ($this->exts->exists('input#idSIButton9[aria-describedby="KmsiDescription"]')) {
        $this->exts->moveToElementAndClick('input#idSIButton9[aria-describedby="KmsiDescription"]');
        sleep(10);
    }
    if ($this->exts->isVisible('input#idSIButton9[aria-describedby*="landingDescription"]')) {
        $this->exts->moveToElementAndClick('input#idSIButton9[aria-describedby*="landingDescription"]');
        sleep(3);
    }
    if ($this->exts->getElement("#verifySetup a#verifySetupCancel") != null) {
        $this->exts->moveToElementAndClick("#verifySetup a#verifySetupCancel");
        sleep(10);
    }
    if ($this->exts->getElement('#authenticatorIntro a#iCancel') != null) {
        $this->exts->moveToElementAndClick('#authenticatorIntro a#iCancel');
        sleep(10);
    }
    if ($this->exts->getElement("input#iLooksGood") != null) {
        $this->exts->moveToElementAndClick("input#iLooksGood");
        sleep(10);
    }
    if ($this->exts->exists("input#StartAction") && !$this->exts->urlContains('/Abuse?')) {
        $this->exts->moveToElementAndClick("input#StartAction");
        sleep(10);
    }
    if ($this->exts->getElement(".recoveryCancelPageContainer input#iLandingViewAction") != null) {
        $this->exts->moveToElementAndClick(".recoveryCancelPageContainer input#iLandingViewAction");
        sleep(10);
    }
    if ($this->exts->getElement("input#idSubmit_ProofUp_Redirect") != null) {
        $this->exts->moveToElementAndClick("input#idSubmit_ProofUp_Redirect");
        sleep(10);
    }
    if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__11')) {
        // Great job! Your security information has been successfully set up. Click "Done" to continue login.
        $this->exts->moveToElementAndClick(' #id__11');
        sleep(10);
    }
    if ($this->exts->getElement('div input#iNext') != null) {
        $this->exts->moveToElementAndClick('div input#iNext');
        sleep(10);
    }
    if ($this->exts->getElement('input[value="Continue"]') != null) {
        $this->exts->moveToElementAndClick('input[value="Continue"]');
        sleep(10);
    }
    if ($this->exts->getElement('form[action="/kmsi"] input#idSIButton9') != null) {
        $this->exts->moveToElementAndClick('form[action="/kmsi"] input#idSIButton9');
        sleep(10);
    }
    if ($this->exts->getElement('a#CancelLinkButton') != null) {
        $this->exts->moveToElementAndClick('a#CancelLinkButton');
        sleep(10);
    }
    if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__7')) {
        // Confirm your info.
        $this->exts->moveToElementAndClick(' #id__7');
        sleep(10);
    }
    if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__11')) {
        // Great job! Your security information has been successfully set up. Click "Done" to continue login.
        $this->exts->moveToElementAndClick(' #id__11');
        sleep(10);
    }
    if ($this->exts->isVisible('form[action*="/kmsi"] input[name="DontShowAgain"]')) {
        // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
        $this->exts->moveToElementAndClick('form[action*="/kmsi"] input[name="DontShowAgain"] + span');
        sleep(3);
        $this->exts->moveToElementAndClick('form[action*="/kmsi"] input#idSIButton9');
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
        $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]');
        sleep(10);
    } else if ($this->exts->exists('#iProofList input[name="proof"]')) {
        $this->exts->moveToElementAndClick('#iProofList input[name="proof"]');
        sleep(10);
    } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"]')) {
        // Updated 11-2020
        if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]')) { // phone SMS
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]')) { // phone SMS
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]')) { // Email 
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]')) {
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]')) {
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]');
        } else {
            $this->exts->moveToElementAndClick('#idDiv_SAOTCS_Proofs [role="listitem"]');
        }
        sleep(5);
    }

    // STEP 2: (Optional)
    if ($this->exts->exists('#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc')) {
        // If method is click some number on Microsoft Authenticator app, send 2FA to ask user to do click it
        $message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
        $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText')));
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
            $this->exts->moveToElementAndClick('a#idA_SAASTO_TOTP');
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
            $this->exts->moveToElementAndClick($submit_selector);
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
        if ($this->phone_number != '' && is_numeric(trim(substr($this->phone_number,   - 1,  4)))) {
            $last4digit = substr($this->phone_number,   - 1,  4);
            $this->exts->moveToElementAndType($input_selector, $last4digit);
            sleep(3);
            $this->exts->moveToElementAndClick($submit_selector);
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
    if ($this->exts->getElement($message_selector) != null) {
        $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($message_selector, 'innerText'));
        $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
    }
    $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (!empty($two_factor_code) && trim($two_factor_code) != '') {
        if ($this->exts->getElement($input_selector) != null) {
            $this->exts->log("microsoftfillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->getElement($input_selector)->sendKeys($two_factor_code);
            sleep(2);
            if ($this->exts->exists($remember_selector)) {
                $this->exts->moveToElementAndClick($remember_selector);
            }
            $this->exts->capture("2.2-microsoft-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->exts->exists($submit_selector)) {
                $this->exts->log("microsoftfillTwoFactor: Clicking submit button.");
                $this->exts->moveToElementAndClick($submit_selector);
            }
            sleep(15);

            if ($this->exts->getElement($input_selector) == null) {
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

private function doAfterLogin()
{
    $this->exts->openUrl($this->baseUrl);
    sleep(25);
    // Download in default workspace
    $this->process_multil_teams();

    if ($this->isNoInvoice) {
        $this->exts->no_invoice();
    }

    $this->exts->success();
}
private function process_multil_teams()
{
    $this->exts->capture("process_multil_teams");
    if ($this->exts->exists('button[class*="admin_console_button"], div[data-onboarding-key="PRO_ADMIN_SETTINGS_LINK_ONBOARING_KEY"], div[data-testid="admin-settings-link"]')) {
        $team_len = count($this->exts->getElements('button[class*="admin_console_button"], div[data-onboarding-key="PRO_ADMIN_SETTINGS_LINK_ONBOARING_KEY"], div[data-testid="admin-settings-link"]'));
        $this->exts->log('Total Team length : '. $team_len);
        if ($team_len > 0) {
            for ($i = 0; $i < $team_len; $i++) {
                $team_sel = $this->exts->getElements('button[class*="admin_console_button"], div[data-onboarding-key="PRO_ADMIN_SETTINGS_LINK_ONBOARING_KEY"], div[data-testid="admin-settings-link"]')[$i];
                if ($team_sel) {
                    try {
                            $this->exts->log('Click team button');
                            $team_sel->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click team button by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$team_sel]);
                        }

                        sleep(15);
                        if (strpos($this->exts->getUrl(), 'Admin-Console?')) {
                            $billingPageUrl = str_replace('Admin-Console?', 'Admin-Console/billing?', $this->exts->getUrl());
                        } elseif (strpos($this->exts->getUrl(), 'admin?')) {
                            $billingPageUrl = str_replace('admin?', 'admin/billing?', $this->exts->getUrl());
                        }elseif ($this->exts->urlContains('admin-console/dashboard')) {
                            $billingPageUrl = str_replace('admin-console/dashboard', 'admin-console/billing?', $this->exts->getUrl());
                        }else{
                            $billingPageUrl = str_replace('admin-console?', 'admin-console/billing?', $this->exts->getUrl());
                        }
        
                        $this->exts->openUrl($billingPageUrl);
                        sleep(25);
                        $this->exts->moveToElementAndClick('div[data-testid="billing-subtabs"] div button:nth-child(2)');
                        sleep(5);
                        //Call this so that window don't get close in 30mins.
                        $this->exts->update_process_lock();
                        $this->downloadInvoice();

                }else {
                    $this->exts->log('Team button not found.');
                }
                
            }
        }
    }
}
private function downloadInvoice()
{
    $this->exts->log("Begin download invoice ");
    sleep(2);
    try {
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('a[href*="invoice"]');
        foreach ($rows as $row) {
            $invoiceUrl = $row->getAttribute("href");
            array_push($invoices, array(
                'invoiceName' => '',
                'invoiceDate' => '',
                'invoiceAmount' => '',
                'invoiceUrl' => $invoiceUrl
            ));
            $this->isNoInvoice = false;
        }

        // Download all invoices
        $this->exts->switchToInitTab();
        sleep(5);
       
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(10);
            $this->exts->moveToElementAndClick('div.InvoiceDetailsRow-Container button:nth-child(1)');
            sleep(5);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');

            if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                $invoiceFileName = basename($downloaded_file);
                $invoiceName = explode('.pdf', $invoiceFileName)[0];
                $this->exts->log('Final invoice name: '.$invoiceName);

                // Create new invoice if it not exisited
                if($this->exts->invoice_exists($invoiceFileName)){
                    $this->exts->log('Invoice existed '.$invoiceFileName);

                } else {
                    $this->exts->new_invoice($invoiceName, $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                }
            } else {
                $this->exts->log('Timeout when download '.$invoiceFileName);
            }
        }
        $this->exts->closeAllTabsButThis();
        sleep(2);
    } catch (\Exception $exception) {
        $this->exts->log("Exception downloading invoice " . $exception->getMessage());
    }
}