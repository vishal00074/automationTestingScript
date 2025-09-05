public $homePage = 'https://free-now.com/';
public $loginUrl = 'https://login.free-now.com/';
public $baseUrl = 'https://business.free-now.com/statements';
public $invoicePageUrl = 'https://business.free-now.com/statements';

public $username_selector = '#text-field-username';
public $password_selector = '#text-field-password';
public $submit_login_selector = 'button[type="submit"],form>button';

public $check_login_success_selector = '[data-testid="logout-button"], [data-testid="logged-in-bootstrap-page-wrapper"] [data-testid="user-dropdown"]';
public $isNoInvoice = true;

public $isInvalidCreds = false;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->homePage);
    sleep(10);
    $this->acceptCookieButton();

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    for ($i = 0; $i < 5; $i++) {
        $this->exts->log(strtolower($this->exts->extract('div.layout p', null, 'innerText')));
        if (strpos(strtolower($this->exts->extract('div.layout p', null, 'innerText')), 'please refresh the page') !== false) {
            $this->exts->refresh();
            sleep(15);
        } else {
            break;
        }
    }
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    $username_txt = trim($this->exts->extract('[data-testid="user-dropdown"] span', null, 'innerText'));
    if ($this->exts->getElement($this->check_login_success_selector) == null || $username_txt != '') {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(15);

        for ($i = 0; $i < 5; $i++) {
            $this->exts->log(strtolower($this->exts->extract('div.layout p', null, 'innerText')));
            if (strpos(strtolower($this->exts->extract('div.layout p', null, 'innerText')), 'please refresh the page') !== false) {
                $this->exts->refresh();
                sleep(15);
            } else {
                break;
            }
        }

        $this->checkFillLogin();
        sleep(5);
        if (stripos($this->exts->getUrl(), "/enter-code?") !== false && $this->exts->exists('[class*="CodeFormField__"],input[autocomplete="one-time-code"]')) {
            $this->checkFillTwoFactor();
            sleep(5);
        }
    }

    // then check user logged in or not
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
        if ($this->exts->getElementByText('h4', ['You are not an admin', 'Sie sind kein Administrator'], null, false) != null) {
            $this->exts->log('account not ready. You are not an admin');
            $this->exts->account_not_ready();
        } else if (filter_var($this->username, FILTER_VALIDATE_EMAIL) == false) {
            $this->exts->loginFailure(1);
        } else if ($this->isInvalidCreds) {
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
        $this->exts->click_by_xdotool($this->username_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->username);
        sleep(2);
        $this->exts->log("Enter Password");
        $this->exts->click_by_xdotool($this->password_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->password);
        sleep(4);
        $this->checkFillRecaptcha();

        $this->exts->capture("2-login-page-filled");
        $this->exts->click_by_xdotool($this->submit_login_selector);
        sleep(7);

        if ($this->exts->getElement($this->submit_login_selector) != null) {
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(1);
        }

        if ($this->exts->getElement($this->submit_login_selector) != null) {
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(1);
        }
        if ($this->exts->getElement($this->submit_login_selector) != null) {
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(1);
        }

        if ($this->exts->getElement($this->submit_login_selector) != null) {
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(2);
        }
        if ($this->exts->getElement($this->submit_login_selector) != null) {
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(2);
        }
        $this->exts->waitTillPresent(".banner-animation-appear-done.banner-animation-enter-done", 10);
        if ($this->exts->exists(".banner-animation-appear-done.banner-animation-enter-done")) {
            $this->exts->log("Login Failure : " . $this->exts->extract(".banner-animation-appear-done.banner-animation-enter-done"));
            if (
                strpos(strtolower($this->exts->extract(".banner-animation-appear-done.banner-animation-enter-done")), 'deine login-daten sind') !== false ||
                strpos(strtolower($this->exts->extract(".banner-animation-appear-done.banner-animation-enter-done")), 'credentials are invalid') !== false ||
                $this->exts->exists(".banner-animation-appear-done.banner-animation-enter-done")
            ) {
                $this->isInvalidCreds = true;
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillRecaptcha()
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="recaptcha/enterprise/anchor"]';
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
    $two_factor_selector = '[class*="CodeFormField__"],input[autocomplete="one-time-code"]';
    $two_factor_message_selector = '';
    $two_factor_submit_selector = 'button[class*="BaseButton"]';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
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

private function acceptCookieButton()
{
    if ($this->exts->exists('#Give-GDPR-Consent, [data-testid="confirmation-button"]')) {
        $this->exts->moveToElementAndClick('#Give-GDPR-Consent, [data-testid="confirmation-button"]');
    }
}