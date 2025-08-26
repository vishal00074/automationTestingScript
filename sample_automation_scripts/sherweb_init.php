public $baseUrl = "https://www.sherweb.com/customer-login";
public $username_selector = '#Username';
public $password_selector = '#Password';
public $submit_btn = '.loginButton, #usernamepassword-div button.btn';
public $logout_btn = '.fa-power-off, a[data-bind*="logout()"], .billing a[href*="/billing/invoices"], button.UserMenu-logout';

public $isNoInvoice = true;

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $isCookieLoaded = false;
    if ($this->exts->loadCookiesFromFile()) {
        sleep(1);
        $isCookieLoaded = true;
    }

    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    if ($isCookieLoaded) {
        $this->exts->capture("Home-page-with-cookie");
    } else {
        $this->exts->capture("Home-page-without-cookie");
    }


    if (!$this->checkLogin()) {
        $this->exts->openUrl($this->baseUrl);
        sleep(10);


        if ($this->exts->queryXpath('.//button[@onclick="window.open(\'https://cumulus.sherweb.com/\', \'_blank\', \'noopener\')"]') != null) {
            $this->exts->click_element('.//button[@onclick="window.open(\'https://cumulus.sherweb.com/\', \'_blank\', \'noopener\')"]');
            sleep(10);
        }

        $this->exts->switchToNewestActiveTab();
        sleep(2);
        $this->exts->closeAllTabsExcept();
        sleep(2);
    }

    if (!$this->checkLogin()) {
        $this->exts->capture("after-login-clicked");
        $this->fillForm(0);
        sleep(20);
        $this->checkFillTwoFactor();
        for ($i = 0; $i < 10 && $this->exts->exists('#SplashLoadingSpinner'); $i++) {
            sleep(2);
        }
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {

        if ($this->exts->exists('form[aspcontroller="Logout"]') && strpos(strtolower($this->exts->extract('.title')), 'unauthorized') !== false) {
            $this->exts->no_permission();
        }
        if ($this->exts->exists('app-mfa-required')) {
            $this->exts->account_not_ready();
        }
        $this->exts->capture("LoginFailed");
        if ($this->exts->exists('.validation-summary-errors')) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

/**
    * Method to fill login form
    * @param Integer $count Number of times portal is retried.
    */
function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {

        if ($this->exts->exists($this->username_selector)) {
            sleep(2);
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username, 5);

            $this->exts->moveToElementAndClick('button[type="submit"]');
            sleep(7);


            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password, 5);

            $this->exts->click_element('#EnableSSO');

            $this->exts->capture("1-pre-login-1");
            $this->checkFillRecaptcha(0);

            $this->exts->click_element($this->submit_btn);
        } else if ($this->exts->exists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->exts->exists("textarea[name=\"g-recaptcha-response\"]")) {
            $this->checkFillRecaptcha(0);
            $count++;
            $this->fillForm($count);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
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
    $two_factor_selector = 'div#token-div:not([style*="none"]) input#TwoFactorAuthenticationToken';
    $two_factor_submit_selector = 'div#token-div:not([style*="none"]) button[type="submit"]';
    $two_factor_message_selector = 'div#token-div:not([style*="none"]) span#sms-required';
    if ($this->exts->getElement($two_factor_message_selector) == null && $this->exts->extract('div#token-div:not([style*="none"]) span#email-required') !== '') {
        $two_factor_message_selector = 'div#token-div:not([style*="none"]) span#email-required';
    } else if ($this->exts->extract('div#token-div:not([style*="none"]) span#otp-required') !== '') {
        $two_factor_message_selector = 'div#token-div:not([style*="none"]) span#otp-required';
    }


    if (
        $this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3 &&
        $this->exts->getElement($two_factor_message_selector) != null && $this->exts->extract($two_factor_message_selector, null, 'innerText') !== ''
    ) {
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
            sleep(2);
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

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->exists($this->logout_btn) && $this->exts->exists($this->username_selector) == false) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}