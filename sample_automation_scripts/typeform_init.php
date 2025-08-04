public $baseUrl = "https://admin.typeform.com/";
public $loginUrl = "https://admin.typeform.com/login";
public $username_selector = 'input[name="username"], input#email';
public $password_selector = 'input[name="password"]';
public $submit_button_selector = 'input[type="submit"], button[data-qa="login-password-submit-button"]';
public $restrictPages = 3;
public $isNoInvoice = true;

public $check_login_success_selector = "button[data-qa='header-account-dropdown'], a[data-qa='sidebar-account-plan-billing'], button[data-qa='edit-name-button'], button[data-qa='header-user-dropdown'], a[href*='/accounts/'][href$='/workspaces']";

public $login_with_google = 0;
public $phone_number = '';
public $recovery_email = '';
public $login_with_microsoft = 0;
public $security_phone_number = '';


/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    // $this->disable_unexpected_extensions();

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->capture('1-init-page');
    sleep(3);
    //sometime login success with cookie, but download invoice then site redirect to login page
    if ($this->checkLogin()) {
        $this->waitForSelectors("a[href='/']", 10, 2);
        $this->exts->moveToElementAndClick('a[href="/"]');
        sleep(10);
    }
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->isExists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(2);
        }
        if ($this->exts->config_array['login_with_google'] == '1') {
            $this->exts->markCurrentTabByName('rootTab');
            $this->exts->moveToElementAndClick('button[data-qa="google-signin-button"]');
            sleep(10);
            $google_login_tab = $this->exts->findTabMatchedUrl(['accounts.google.com']);
            if ($google_login_tab != null) {
                $this->exts->switchToTab($google_login_tab);
            }
            $this->loginGoogleIfRequired();

            // go back to root tab
            $this->exts->switchToTab($this->exts->getMarkedTab('rootTab'));
        } else if ($this->exts->config_array['login_with_microsoft'] == '1') {
            $this->exts->markCurrentTabByName('rootTab');
            $this->exts->moveToElementAndClick('button[data-qa="microsoft-signin-button"]');
            sleep(10);
            $microsoft_login_tab = $this->exts->findTabMatchedUrl(['login.microsoftonline.com']);
            if ($microsoft_login_tab != null) {
                $this->exts->switchToTab($microsoft_login_tab);
            }
            $this->loginMicrosoftIfRequired();

            // go back to root tab
            $this->exts->switchToTab($this->exts->getMarkedTab('rootTab'));
        } else {
            // First, try to login as normal and expect it lead user in
            $this->checkFillLogin();
            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(7);
            // BUT if recaptcha showed challenger, call recaptcha function to solve it
            if ($this->isExists('[style*="visibility: visible; position: absolute"][style*="top: 10px"] iframe[title*="challenge"]')) {
                $this->exts->openUrl($this->loginUrl);
                sleep(10);
                $this->checkFillLogin();
                sleep(10);
                $submited = $this->checkFillRecaptcha();
                if (!$submited) {
                    $this->exts->moveToElementAndClick($this->submit_button_selector);
                }
            }

            // if (strpos(strtolower($this->getInnerTextByJS('body')), 't have access to this workspace') !== false || strpos(strtolower($this->getInnerTextByJS('body')), 'a problem on our side') !== false) {
            //     $this->exts->refresh();
            //     sleep(15);
            //     $this->exts->openUrl($this->loginUrl);
            //     sleep(2);
            //     $this->checkFillLogin();
            //     sleep(10);
            //     $submited = $this->checkFillRecaptcha();
            //     if (!$submited) {
            //         $this->exts->moveToElementAndClick($this->submit_button_selector);
            //     }
            // }
            //sleep(10);

            //Hold tight-just getting this page ready. if loadCookiesFromFile than site can not stop loading the page after submit login
            sleep(15);
            $this->waitForSelectors("div.placeholder-spinner", 10, 2);
            if ($this->exts->getElement("div.placeholder-spinner") != null) {
                $this->exts->capture("after-submited-login");
                $this->exts->openUrl($this->baseUrl);
                sleep(10);
            }
            $this->waitForSelectors("form.mfa-verify-email input[type='submit'], button[data-qa='mfa-send-code-form-send-button']", 10, 2);
            if ($this->exts->getElement('form.mfa-verify-email input[type="submit"], button[data-qa="mfa-send-code-form-send-button"]') != null) {
                $this->exts->moveToElementAndClick('form.mfa-verify-email input[type="submit"], button[data-qa="mfa-send-code-form-send-button"]');
                sleep(10);
                $this->checkFillTwoFactor();
            }
        }
        sleep(20);
    }

    // then check user logged in or not
    if ($this->checkLogin()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        if ($this->isExists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(2);
        }
        $this->exts->capture("3-login-success");

        $this->invoicePage();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if (strpos($this->getInnerTextByJS('div[role="alert"] p'), 'login info is not right') !== false || strpos(strtolower($this->getInnerTextByJS('div.okta-form-infobox-error p')), 'account is locked') !== false || strpos($this->exts->extract('[data-qa="email-form-email-error"]'), 'email you entered isn’t linked to any') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->getElement('button[data-qa="reveal-account-btn"]') != null) {
            $this->exts->account_not_ready();
        } else if ($this->isExists('//div[contains(text(),"Your login info is not right")]')) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function getInnerTextByJS($selector_or_object, $parent = null)
{
    if ($selector_or_object == null) {
        $this->exts->log(__FUNCTION__ . ' Can not get innerText of null');
        return;
    }
    $element = $selector_or_object;
    if (is_string($selector_or_object)) {
        $element = $this->exts->getElement($selector_or_object, $parent);
        if ($element == null) {
            $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
        }
        if ($element == null) {
            $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
        }
    }
    if ($element != null) {
        return $this->exts->execute_javascript("return arguments[0].innerText", [$element]);
    }
}
private function checkFillLogin()
{
    for ($i = 0; $i < 10; $i++) {
        if ($this->isExists($this->username_selector)) {
            break;
        }
        sleep(5);
    }
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(5);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);
        $this->exts->capture("2-login-page-filled");
    } else if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick('button[data-qa="email-form-continue-button"]');
        sleep(10);
        $google_login_tab = $this->exts->findTabMatchedUrl(['accounts.google.com']);
        if ($google_login_tab != null) {
            $this->exts->switchToTab($google_login_tab);
        }
        $this->loginGoogleIfRequired();
        $this->exts->switchToInitTab();
        sleep(4);
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->capture("2-login-page-filled");
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function checkFillTwoFactor()
{
    $two_factor_selector = 'form.mfa-verify-email input[name="answer"]';
    $two_factor_message_selector = 'form.mfa-verify-email .mfa-email-sent-content';
    $two_factor_submit_selector = 'form.mfa-verify-email input[type="submit"]';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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
                $this->exts->notification_uid = "";
                $this->exts->two_factor_attempts++;
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    } else {
        $two_factor_selector = 'input[data-qa="mfa-verify-code-form-code-input"]';
        $two_factor_message_selector = '//div[contains(text(),"A verifaction code was sent to")]';
        $two_factor_submit_selector = 'button[data-qa="mfa-verify-code-form-verify-button"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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
    }
}
private function checkFillRecaptcha()
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if ($this->isExists($recaptcha_iframe_selector)) {
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
                $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');

            // Step 2, check if callback function need executed
            $gcallbackFunction = $this->exts->execute_javascript('
    if(document.querySelector("[data-callback]") != null){
        return document.querySelector("[data-callback]").getAttribute("data-callback");
    }

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
    return found ? "___grecaptcha_cfg.clients[0]." + result : null;
');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            if ($gcallbackFunction != null) {
                $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
                return true;
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
    return false;
}

private function isExists($selector = '')
{
    $safeSelector = addslashes($selector);
    $this->exts->log('Element:: ' . $safeSelector);
    $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
    if ($isElement) {
        $this->exts->log('Element Found');
        return true;
    } else {
        $this->exts->log('Element not Found');
        return false;
    }
}
private function waitForSelectors($selector, $max_attempt, $sec)
{
    for (
        $wait = 0;
        $wait < $max_attempt && $this->exts->executeSafeScript("return !!document.querySelector(\"" . $selector . "\");") != 1;
        $wait++
    ) {
        $this->exts->log('Waiting for Selectors!!!!!!');
        sleep($sec);
    }
}
private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->waitForSelectors($this->check_login_success_selector, 20, 2);
        if ($this->isExists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

//*********** Start Microsoft Login
public $microsoft_username_selector = 'input[name="loginfmt"]';
public $microsoft_password_selector = 'input[name="passwd"]';
public $microsoft_remember_me_selector = 'input[name="KMSI"] + span';
public $remember_me_selector = 'input[name="KMSI"] + span';
public $microsoft_submit_login_selector = 'input[type="submit"]#idSIButton9';

public $microsoft_account_type = 0;
public $microsoft_phone_number = '';
public $microsoft_recovery_email = '';

private function loginMicrosoftIfRequired($count = 0)
{
    $this->microsoft_phone_number = isset($this->exts->config_array["phone_number"]) ? $this->exts->config_array["phone_number"] : '';
    $this->microsoft_recovery_email = isset($this->exts->config_array["recovery_email"]) ? $this->exts->config_array["recovery_email"] : '';
    $this->microsoft_account_type = isset($this->exts->config_array["account_type"]) ? (int)@$this->exts->config_array["account_type"] : 0;

    if ($this->exts->urlContains('microsoft') || $this->exts->urlContains('live.')) {
        $this->checkFillMicrosoftLogin();
        sleep(10);
        $this->checkMicrosoftTwoFactorMethod();

        if ($this->isExists('input#newPassword')) {
            $this->exts->account_not_ready();
        } else if ($this->exts->querySelector('#passwordError a[href*="ResetPassword"], #passwordError a[href*="passwordreset"]') != null) {
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
    $this->exts->waitTillPresent('[role="listbox"] .row #otherTile[role="option"], div#otherTile', 20);
    if ($this->isExists('[role="listbox"] .row #otherTile[role="option"], div#otherTile')) {
        $this->exts->click_by_xdotool('[role="listbox"] .row #otherTile[role="option"], div#otherTile');
        sleep(10);
    }

    $this->exts->capture("2-microsoft-login-page");
    if ($this->exts->querySelector($this->microsoft_username_selector) != null) {
        sleep(3);
        $this->exts->log("Enter microsoft Username");
        $this->exts->moveToElementAndType($this->microsoft_username_selector, $this->username);
        sleep(1);
        $this->exts->click_by_xdotool($this->microsoft_submit_login_selector);
        sleep(10);
    }

    //Some user need to approve login after entering username on the app
    if ($this->isExists('div#idDiv_RemoteNGC_PollingDescription')) {
        $this->exts->two_factor_timeout = 5;
        $polling_message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
        $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->extract($polling_message_selector)));
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->isExists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
            }
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            $this->exts->two_factor_timeout = 15;
        } else {
            if ($this->isExists('a#idA_PWD_SwitchToPassword')) {
                $this->exts->click_by_xdotool('a#idA_PWD_SwitchToPassword');
                sleep(5);
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    if ($this->isExists('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]')) {
        // if site show: Already login with .. account, click logout and login with other account
        $this->exts->click_by_xdotool('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]');
        sleep(10);
    }
    if ($this->isExists('a#mso_account_tile_link, #aadTile, #msaTile')) {
        // if site show: This Email is being used with multiple Microsoft accounts, Select depending on account_type variable
        //if account type is 1 then only personal account will be selected otherwise business account.
        if ($this->microsoft_account_type == 1) {
            $this->exts->click_by_xdotool('#msaTile');
        } else {
            $this->exts->click_by_xdotool('a#mso_account_tile_link, #aadTile');
        }
        sleep(10);
    }
    if ($this->isExists('form #idA_PWD_SwitchToPassword')) {
        $this->exts->click_by_xdotool('form #idA_PWD_SwitchToPassword');
        sleep(5);
    }

    if ($this->exts->urlContains('jumpcloud')) {
        //Login with JumpCloud
        $this->exts->log("Enter JumpCloud Username");
        $this->exts->moveToElementAndType('input[name="email"]', $this->username);
        sleep(1);
        $this->exts->click_by_xdotool('input[type="checkbox"]');
        sleep(2);
        $this->exts->click_by_xdotool('button[data-automation="loginButton"]');
        sleep(5);
        $this->exts->log("Enter JumpCloud Password");
        $this->exts->moveToElementAndType('input[name="password"]', $this->password);
        sleep(1);
        $this->exts->capture("2-microsoft-jumpcloud-page-filled");
        $this->exts->click_by_xdotool('button[data-automation="loginButton"]');
        sleep(10);
        if ($this->exts->getElementByText('div.LoginAlert__error', ['Authentication failed.'], null, false) != null) {
            $this->exts->loginFailure(1);
        }
    }

    if ($this->exts->querySelector($this->microsoft_password_selector) != null) {
        $this->exts->log("Enter microsoft Password");
        $this->exts->moveToElementAndType($this->microsoft_password_selector, $this->password);
        sleep(1);
        $this->exts->click_by_xdotool($this->microsoft_remember_me_selector);
        sleep(2);
        $this->exts->capture("2-microsoft-password-page-filled");
        $this->exts->click_by_xdotool($this->microsoft_submit_login_selector);
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
    if ($this->isExists('form[action*="/kmsi"] input[name="DontShowAgain"], input#idSIButton9[aria-describedby="KmsiDescription"]')) {
        // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
        $this->exts->click_by_xdotool('form input[name="DontShowAgain"] + span');
        sleep(3);
        $this->exts->click_by_xdotool('form[action*="/kmsi"] input#idSIButton9, input#idSIButton9[aria-describedby="KmsiDescription"]');
        sleep(10);
    }
    if ($this->isExists('input#btnAskLater')) {
        $this->exts->click_by_xdotool('input#btnAskLater');
        sleep(10);
    }
    if ($this->isExists('a[data-bind*=SkipMfaRegistration]')) {
        $this->exts->click_by_xdotool('a[data-bind*=SkipMfaRegistration]');
        sleep(10);
    }
    if ($this->isExists('input#idSIButton9[aria-describedby="KmsiDescription"]')) {
        $this->exts->click_by_xdotool('input#idSIButton9[aria-describedby="KmsiDescription"]');
        sleep(10);
    }
    if ($this->isExists('input#idSIButton9[aria-describedby*="landingDescription"]')) {
        $this->exts->click_by_xdotool('input#idSIButton9[aria-describedby*="landingDescription"]');
        sleep(3);
    }
    if ($this->exts->querySelector("#verifySetup a#verifySetupCancel") != null) {
        $this->exts->click_by_xdotool("#verifySetup a#verifySetupCancel");
        sleep(10);
    }
    if ($this->exts->querySelector('#authenticatorIntro a#iCancel') != null) {
        $this->exts->click_by_xdotool('#authenticatorIntro a#iCancel');
        sleep(10);
    }
    if ($this->exts->querySelector("input#iLooksGood") != null) {
        $this->exts->click_by_xdotool("input#iLooksGood");
        sleep(10);
    }
    if ($this->isExists("input#StartAction") && !$this->exts->urlContains('/Abuse?')) {
        $this->exts->click_by_xdotool("input#StartAction");
        sleep(10);
    }
    if ($this->exts->querySelector(".recoveryCancelPageContainer input#iLandingViewAction") != null) {
        $this->exts->click_by_xdotool(".recoveryCancelPageContainer input#iLandingViewAction");
        sleep(10);
    }
    if ($this->exts->querySelector("input#idSubmit_ProofUp_Redirect") != null) {
        $this->exts->click_by_xdotool("input#idSubmit_ProofUp_Redirect");
        sleep(10);
    }
    if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->isExists('#id__11')) {
        // Great job! Your security information has been successfully set up. Click "Done" to continue login.
        $this->exts->click_by_xdotool(' #id__11');
        sleep(10);
    }
    if ($this->exts->querySelector('div input#iNext') != null) {
        $this->exts->click_by_xdotool('div input#iNext');
        sleep(10);
    }
    if ($this->exts->querySelector('input[value="Continue"]') != null) {
        $this->exts->click_by_xdotool('input[value="Continue"]');
        sleep(10);
    }
    if ($this->exts->querySelector('form[action="/kmsi"] input#idSIButton9') != null) {
        $this->exts->click_by_xdotool('form[action="/kmsi"] input#idSIButton9');
        sleep(10);
    }
    if ($this->exts->querySelector('a#CancelLinkButton') != null) {
        $this->exts->click_by_xdotool('a#CancelLinkButton');
        sleep(10);
    }
    if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->isExists('#id__7')) {
        // Confirm your info.
        $this->exts->click_by_xdotool(' #id__7');
        sleep(10);
    }
    if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->isExists('#id__11')) {
        // Great job! Your security information has been successfully set up. Click "Done" to continue login.
        $this->exts->click_by_xdotool(' #id__11');
        sleep(10);
    }
    if ($this->isExists('form[action*="/kmsi"] input[name="DontShowAgain"]')) {
        // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
        $this->exts->click_by_xdotool('form[action*="/kmsi"] input[name="DontShowAgain"] + span');
        sleep(3);
        $this->exts->click_by_xdotool('form[action*="/kmsi"] input#idSIButton9');
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
    if ($this->isExists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]')) {
        $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]');
        sleep(10);
    } else if ($this->isExists('#iProofList input[name="proof"]')) {
        $this->exts->click_by_xdotool('#iProofList input[name="proof"]');
        sleep(10);
    } else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"]')) {
        // Updated 11-2020
        if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]')) { // phone SMS
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]');
        } else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]')) { // phone SMS
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]');
        } else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]')) { // Email 
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]');
        } else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]')) {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]');
        } else if ($this->isExists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]')) {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]');
        } else {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"]');
        }
        sleep(5);
    }

    // STEP 2: (Optional)
    if ($this->isExists('#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc')) {
        // If method is click some number on Microsoft Authenticator app, send 2FA to ask user to do click it
        $message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
        $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->extract($message_selector)));
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

        $this->exts->two_factor_attempts = 2;
        $this->fillMicrosoftTwoFactor('', '', '', '');
    } else if ($this->isExists('[data-bind*="Type.TOTPAuthenticatorV2"]')) {
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

        if ($this->isExists('a#idA_SAASTO_TOTP')) {
            $this->exts->click_by_xdotool('a#idA_SAASTO_TOTP');
            sleep(5);
        }
    } else if ($this->isExists('input[value="TwoWayVoiceOffice"]') && $this->isExists('div#idDiv_SAOTCC_Description')) {
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
    } else if ($this->isExists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"])')) {
        // If method is email code or phone code, This site may be ask for confirm phone/email first, So send 2FA to ask user phone/email
        $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"])';
        $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
        $remember_selector = '';
        $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"], input[type="submit"]';
        $this->exts->two_factor_attempts = 1;
        if ($this->microsoft_recovery_email != '' && filter_var($this->recovery_email, FILTER_VALIDATE_EMAIL) !== false) {
            $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
            sleep(1);
            $this->exts->click_by_xdotool($submit_selector);
            sleep(10);
        } else {
            $this->exts->two_factor_attempts = 1;
            $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }
    } else if ($this->isExists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])')) {
        // If method is phone code, This site may be ask for confirm phone first, So send 2FA to ask user phone
        $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])';
        $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
        $remember_selector = '';
        $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"], input[type="submit"]';
        $this->exts->two_factor_attempts = 1;
        if ($this->phone_number != '' && is_numeric(trim(substr($this->phone_number, -1, 4)))) {
            $last4digit = substr($this->phone_number, -1, 4);
            $this->exts->moveToElementAndType($input_selector, $last4digit);
            sleep(3);
            $this->exts->click_by_xdotool($submit_selector);
            sleep(10);
        } else {
            $this->exts->two_factor_attempts = 1;
            $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }
    }

    // STEP 3: input code
    if ($this->isExists('input[name="otc"], input[name="iOttText"]')) {
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
    if ($this->exts->querySelector($message_selector) != null) {
        $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->extract($message_selector));
        $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
    }
    $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (!empty($two_factor_code) && trim($two_factor_code) != '') {
        if ($this->exts->querySelector($input_selector) != null) {
            $this->exts->log("microsoftfillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->querySelector($input_selector)->sendKeys($two_factor_code);
            sleep(2);
            if ($this->isExists($remember_selector)) {
                $this->exts->click_by_xdotool($remember_selector);
            }
            $this->exts->capture("2.2-microsoft-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->isExists($submit_selector)) {
                $this->exts->log("microsoftfillTwoFactor: Clicking submit button.");
                $this->exts->click_by_xdotool($submit_selector);
            }
            sleep(15);

            if ($this->exts->querySelector($input_selector) == null) {
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

        if ($this->isExists('#smsauth-interstitial-remindbutton')) {
            $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
            sleep(10);
        }
        if ($this->isExists('#tos_form input#accept')) {
            $this->exts->moveToElementAndClick('#tos_form input#accept');
            sleep(10);
        }
        if ($this->isExists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
            $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
            sleep(10);
        }
        if ($this->isExists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('.action-button.signin-button');
            sleep(10);
        }
        if ($this->isExists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')) {
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
            sleep(10);
        }
        if ($this->isExists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
            $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
            sleep(10);
        }
        if ($this->isExists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
            $this->exts->moveToElementAndClick('input[name="later"]');
            sleep(7);
        }
        if ($this->isExists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
            $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
            sleep(7);
        }
        if ($this->isExists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
            $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
            sleep(10);
        }

        if ($this->isExists('#submit_approve_access')) {
            $this->exts->moveToElementAndClick('#submit_approve_access');
            sleep(10);
        } else if ($this->isExists('form #approve_button[name="submit_true"]')) {
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
    if ($this->isExists('form ul li [role="link"][data-identifier]')) {
        $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
        sleep(5);
    }

    if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->isExists($this->google_submit_username_selector) && !$this->isExists($this->google_username_selector)) {
        $this->exts->capture("google-verify-it-you");
        // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
    }

    $this->exts->capture("2-google-login-page");
    if ($this->isExists($this->google_username_selector)) {
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
        sleep(5);
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
        if ($this->isExists('#captchaimg[src]') && !$this->isExists($this->google_password_selector) && $this->isExists($this->google_username_selector)) {
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if ($this->isExists('#captchaimg[src]') && !$this->isExists($this->google_password_selector) && $this->isExists($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
            }
            if ($this->isExists('#captchaimg[src]') && !$this->isExists($this->google_password_selector) && $this->isExists($this->google_username_selector)) {
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
        if ($this->isExists('form[action*="/lookup"] button.account-chooser-button')) {
            $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if ($this->isExists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
            $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }

    if ($this->isExists($this->google_password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(5);
        if ($this->isExists('#captchaimg[src]')) {
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
        }

        $this->exts->capture("2-google-login-page-filled");
        $this->exts->moveToElementAndClick($this->google_submit_password_selector);
        sleep(5);
        if ($this->isExists('#captchaimg[src]') && !$this->isExists('input[name="password"][aria-invalid="true"]') && $this->isExists($this->google_password_selector)) {
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(5);
            if ($this->isExists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if ($this->isExists('#captchaimg[src]') && $this->isExists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(8);
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
    if ($this->isExists('#assistActionId') && $this->isExists('[data-illustration="securityKeyLaptopAnim"]')) {
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
        sleep(6);
        $this->exts->capture("2.0-cancel-security-usb-google");
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if ($this->isExists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if ($this->isExists('input[name="ootpPin"]')) {
        // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    }

    // STEP 1: Check if list of two factor methods showed, select first
    if ($this->isExists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
        // We most RECOMMEND confirm security phone or email, then other method
        if ($this->isExists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
            $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
            // We RECOMMEND method type = 6 is get code from Google Authenticator
            $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')) {
            // We second RECOMMEND method type = 9 is get code from SMS
            $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')) {
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
            // Use a smartphone or tablet to receive a security code (even when offline)
            $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
        } else if ($this->isExists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
            // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
        } else {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
        }
        sleep(10);
    } else if ($this->isExists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->isExists('#authzenNext') && $this->isExists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->isExists('#idvpreregisteredemailNext') && !$this->isExists('form input:not([type="hidden"])')) {
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
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
            $this->exts->type_key_by_xdotool("Return");
            sleep(7);
        }
        if ($this->isExists($input_selector)) {
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if ($this->isExists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')) {
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
        if ($this->isExists($input_selector)) {
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if ($this->isExists('input#phoneNumberId')) {
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
        if ($this->isExists($input_selector)) {
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        }
    } else if ($this->isExists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->isExists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionId
    }

    // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
    if ($this->isExists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->isExists('#authzenNext') && $this->isExists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->isExists('#idvpreregisteredemailNext') && !$this->isExists('form input:not([type="hidden"])')) {
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
        sleep(10);
    } else if (count($this->exts->getElements('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
        $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
        sleep(7);
    }


    // STEP 4: input code
    if ($this->isExists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
        $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
    } else if ($this->isExists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
        $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->isExists('input[name="Pin"]')) {
        $input_selector = 'input[name="Pin"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->isExists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->isExists('input[name="secretQuestionResponse"]')) {
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

            if ($this->isExists($submit_selector)) {
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
    if ($this->isExists($recaptcha_iframe_selector)) {
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

private function invoicePage()
{
    if (!empty($this->exts->config_array['allow_login_success_request'])) {
        $this->exts->triggerLoginSuccess();
    }
    $this->exts->success();
}