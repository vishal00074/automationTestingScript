public $baseUrl = 'https://web.placetel.de/invoices';

public $username_selector = 'input[name="user[login]"]';
public $password_selector = '[data-sso-target="passwordSection"]:not(.d-none) input[name="user[password]"], input[name="passwd"]';
public $remember_me_selector = 'input[name="user[remember_me]"]:not(:checked) + label';
public $check_login_success_selector = '.top-navigation-account-name, a[href*="/sign_out"], a[href*="signout"]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    // Load cookies
    // $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        if ($this->exts->querySelector('div a#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll') != null) {
            $this->exts->moveToElementAndClick('div a#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            sleep(5);
        }
        $this->checkFillLogin();
        sleep(7);
        if (stripos($this->exts->extract('div.alert.alert-primary'), 'reCAPTCHA verification failed') !== false) {
            $this->checkFillLogin();
            sleep(7);
        }
        $this->checkFillRecaptcha();
        sleep(5);
        //after submit login, site reditect to https://accounts.webex.placetel.de/de/users/sign_in?user%5Blogin%5D=kboehm%40neuland-it.de
        if ($this->exts->exists('a#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
            $this->exts->moveToElementAndClick('a#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            sleep(5);
        }
        if ($this->exts->urlContains('accounts.webex.placetel')) {
            $this->checkFillLoginwebex();
            for ($i = 0; $i < 2 && $this->exts->exists('button#reload-button'); $i++) {
                $this->exts->moveToElementAndClick('button#reload-button');
                sleep(10);
            }
            if ($this->exts->exists('button#reload-button')) {

                sleep(5);
                $this->exts->openUrl('https://accounts.webex.placetel.de/users/sign_in');
                $this->checkFillLogin();
                sleep(20);
            }
        }

        $this->checkFillTwoFactor();
        if (
            stripos($this->exts->extract('div.alert.alert-primary'), 'Erfolgreich angemeldet') !== false
            || stripos($this->exts->extract('div.alert.alert-primary'), 'Logged in successfully') !== false
        ) {
            $this->exts->capture("after-login-");
            if (
                stripos($this->exts->extract('.form h2'), 'password entered incorrectly') !== false
                || stripos($this->exts->extract('.form h2'), 'passwort zu oft falsch') !== false
            ) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->openUrl($this->baseUrl);
                sleep(15);
            }
        }
    }

    // then check user logged in or not
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if (stripos($this->exts->extract('.alert-danger'), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else if (stripos($this->exts->extract('#passwordError'), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else if (stripos($this->exts->extract('div.alert.alert-danger'), 'Your account is not activated yet') !== false) {
            $this->exts->account_not_ready();
        } else if (stripos($this->exts->extract('.dialog h1'), 'Forbidden') !== false) {
            $this->exts->no_permission();
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin()
{
    $this->exts->capture("2-login-page");
    $this->exts->waitTillPresent($this->username_selector, 20);
    if ($this->exts->querySelector($this->username_selector) != null) {
        sleep(1);
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->capture("2-username-filled");
        $this->exts->moveToElementAndClick('[name="commit"]');
        sleep(2);
    }

    if ($this->exts->querySelector($this->password_selector) != null) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);
        $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);
        $this->exts->capture("2-password-filled");
        $this->checkFillRecaptcha();
        $this->exts->moveToElementAndClick('[name="commit"], [data-report-event="Signin_Submit"]');
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillLoginwebex()
{
    if ($this->exts->querySelector('input#user_password') != null) {
        sleep(3);
        $this->exts->capture("2-login-page-webex");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType('input[id="user_login"]', $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType('input#user_password', $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled-webex");
        $this->exts->moveToElementAndClick('input[name="commit"]');
    } else if ($this->exts->querySelector('input#user_password') == null && $this->exts->querySelector('input[id="user_login"]') != null) {
        sleep(3);
        $this->exts->capture("2-login-page-webex");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType('input[id="user_login"]', $this->username);
        sleep(1);

        $this->exts->moveToElementAndClick('input[name="commit"]');
        sleep(8);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType('input#user_password', $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled-webex");
        $this->exts->moveToElementAndClick('input[name="commit"]');
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found- webex');
        $this->exts->capture("2-login-page-not-found-webex");
    }
}
private function checkFillTwoFactor()
{
    $two_factor_selector = 'form[action*="two_factor_authentication"] input[name="code"]';
    $two_factor_message_selector = 'form[action*="two_factor_authentication"] > p';
    $two_factor_submit_selector = 'form[action*="two_factor_authentication"] [name="commit"]';

    if ($this->exts->querySelector($two_factor_selector) != null) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->querySelector($two_factor_selector) == null) {
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

private function checkFillRecaptcha()
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
            $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
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