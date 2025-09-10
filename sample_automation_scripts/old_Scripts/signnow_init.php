public $baseUrl = 'https://snseats.signnow.com/';
public $loginUrl = 'https://snseats.signnow.com/login';
public $invoicePageUrl = 'https://snseats.signnow.com/';
public $username_selector = 'input#login';
public $password_selector = 'input#pswd';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type*="submit"]';

public $check_login_failed_selector = '';
public $check_login_success_selector = 'div.snr-sn-page-header__actions-item.snr-sn-page-header__actions-item--height-lg, div.username-authenticated-caption, [aria-label*="Log out"]';
public $isNoInvoice = true;
public $errorMessage = '';
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->temp_keep_useragent = $this->exts->send_websocket_event(
        $this->exts->current_context->webSocketDebuggerUrl,
        "Network.setUserAgentOverride",
        '',
        ["userAgent" => "Mozilla/5.0 (Windows NT 11.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.6998.166 Safari/537.36"]
    );

    // $this->disable_extensions();
    $this->exts->log('Begin initPortal ' . $count);
    // Load cookies
    // $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');
    if ($this->isExists('.snr-sn-page-header__actions > div.snr-sn-page-header__actions-item:last-child')) {
        $this->exts->moveToElementAndClick('.snr-sn-page-header__actions > div.snr-sn-page-header__actions-item:last-child');
        sleep(5);
    }
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null && $this->exts->getElement('//span[contains(text(),"Log Out") or contains(text(),"Déconnecter")]', null, 'xpath') == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->checkFillLogin();
        sleep(20);
        if ($this->isExists('#captcha-v2 iframe[src*="/recaptcha/api2/anchor?"]')) {
            $this->clearChrome();
            sleep(5);
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
        }
        if ($this->isExists('#captcha-v2 iframe[src*="/recaptcha/api2/anchor?"]')) {
            $this->clearChrome();
            sleep(5);
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
        }
        sleep(20);
        $this->checkFillTwoFactor();
        sleep(15);
        if ($this->isExists('.snr-sn-page-header__actions > div.snr-sn-page-header__actions-item:last-child')) {
            $this->exts->moveToElementAndClick('.snr-sn-page-header__actions > div.snr-sn-page-header__actions-item:last-child');
            sleep(5);
        }
    }
    if ($this->exts->getElement($this->check_login_success_selector) != null || $this->exts->getElement('//span[contains(text(),"Log Out") or contains(text(),"Déconnecter")]', null, 'xpath') != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");
        
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log("Login Failure : " . $this->errorMessage);
        if (
            stripos($this->errorMessage, 'email or password incorrect') !== false
            || stripos($this->errorMessage, 'invalid domain zone') !== false
            || stripos($this->errorMessage, 'der von ihnen eingegebene bBenutzername und das passwort stimmen nicht mit unseren') !== false
        ) {
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
        // $this->checkFillRecaptcha(1);
        sleep(5);
        $this->exts->executeSafeScript("document.querySelector('button[type*=\'submit\']').disabled = false;");
        sleep(1);
        $this->exts->executeSafeScript("document.querySelector('button[type*=\"submit\"]').classList.remove('snr-is-disabled');");
        sleep(1);
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(2);
        $this->errorMessage = $this->exts->extract(".snr-notifications__item-message", null, 'innerText');
        sleep(10);

        if ($this->isExists($this->password_selector) && $this->isExists('iframe[src*="/recaptcha/api2/anchor?"]')) {
            sleep(7);
            $this->checkFillRecaptcha(1);
            sleep(5);
            $this->exts->executeSafeScript("document.querySelector('button[type*=\'submit\']').disabled = false;");
            sleep(1);
            $this->exts->executeSafeScript("document.querySelector('button[type*=\"submit\"]').classList.remove('snr-is-disabled');");
            sleep(5);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(2);
            $this->errorMessage = $this->exts->extract(".snr-notifications__item-message", null, 'innerText');
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
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

private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="recaptcha/api2/anchor?ar"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    $this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
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
                $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
            }
        } else {
            if ($count < 3) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}
private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[aria-labelledby="verify-code-input"]';
    $two_factor_message_selector = 'p.snr-login-form__subtitle';
    $two_factor_submit_selector = 'form.snr-login-form .snr-login-form__button button[type="submit"]';
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
            $resultCodes = str_split($two_factor_code);
            $code_inputs = $this->exts->getElements('input[aria-labelledby="verify-code-input"]');
            foreach ($code_inputs as $key => $code_input) {
                if (array_key_exists($key, $resultCodes)) {
                    $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('id'));
                    $this->exts->moveToElementAndType($code_input, $resultCodes[$key]);
                    $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                } else {
                    $this->exts->log('"checkFillTwoFactor: Have no char for input #' . $code_input->getAttribute('id'));
                }
            }

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