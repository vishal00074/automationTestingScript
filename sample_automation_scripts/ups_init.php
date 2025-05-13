public $baseUrl = 'https://billing.ups.com/home';
public $username_selector = 'input[name="userID"]';
public $password_selector = 'input[name="password"]';
public $check_login_failed_selector = 'form[name="LoginTest"] p#errorMessages';
public $check_login_success_selector = 'a[href*="/logout"]';
public $restrictPages = 3;
public $isNoInvoice = true;
public $account_number = '';
public $user_lang = '';
public $only_plan_invoice = 0;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->disable_extensions();

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
    $this->account_number = isset($this->exts->config_array["account_number"]) ? $this->exts->config_array["account_number"] : '';
    $this->user_lang = isset($this->exts->config_array["user_lang"]) ? $this->exts->config_array["user_lang"] : '';
    $this->only_plan_invoice = isset($this->exts->config_array["only_plan_invoice"]) ? (int) $this->exts->config_array["only_plan_invoice"] : 0;

    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        if ($this->check_solve_fobidden()) {
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
        }
        if ($this->check_solve_fobidden()) {
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
        }
        //Redirecting to Login...
        if ($this->isExists('#ups-main-container .ups-landing-container')) {
            sleep(10);
        }
        $this->checkFillLogin();
        sleep(15);
        if ($this->check_solve_fobidden()) {
            $this->exts->openUrl($this->baseUrl);
            $this->checkFillLogin();
            sleep(15);
        }

        if (stripos($this->exts->extract('#ups-main-container'), 'Anmelden im Rechnungscenter') !== false) {
            sleep(15);
        }
        // message: "Logging in Billing Center .... We're sorry, but there's a problem, please try again later.". open url and login again
        if (stripos($this->exts->extract('div.ups-landing-container'), 're sorry, but there\'s a problem, please try again later') !== false) {
            $this->clearChrome();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(25);
        }
        if ($this->isExists('input[name="legalAccepted"]')) {
            $this->exts->moveToElementAndClick('input[name="legalAccepted"]:not(:checked) + label');
            $this->exts->moveToElementAndClick('button[name="accept"]');
            sleep(15);
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
        $this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());
        if (stripos($this->exts->extract('#Login #generic_error', null, 'innerText'), 'again or visit the Forgot Username/Password') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('p.ups-formError', null, 'innerText')), 'or maybe you mistyped the') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->isExists('.ups-enroll-section #enrollmentAccountDetailsSection, form[action="/lasso/veremail"] a[href*="javascript:processEmailResend"]')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin()
{

    $this->exts->capture("2-login-page");
    $this->waitFor($this->username_selector, 15);
    if ($this->exts->getElement($this->username_selector) != null) {
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->capture("2-username-filled");
        // $this->exts->moveToElementAndClick('button[name="getTokenWithPassword1"]');
        $this->exts->click_element('button[name="getTokenWithPassword1"]');
        sleep(10);
    }
    $this->waitFor($this->password_selector, 15);
    if ($this->exts->getElement($this->password_selector) != null) {
        $this->exts->log("Enter Password");
        // $this->exts->moveToElementAndType($this->password_selector, $this->password);
        $this->exts->click_by_xdotool($this->password_selector, 5, 5);
        sleep(3);
        $this->exts->type_text_by_xdotool($this->password);
        sleep(3);
        $this->exts->capture("2-password-filled");
        $this->checkFillRecaptcha();


        $this->exts->moveToElementAndClick('button[name="getTokenWithPassword"]');
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
            $recaptcha_textareas = $this->exts->getElements($recaptcha_textarea_selector);
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
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
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

private function check_solve_fobidden()
{
    $is_error = false;
    if (stripos($this->exts->extract('div.redErrorBold', null, 'innerText'), 'application encountered an error during processing') !== false) {
        $this->exts->capture('processing-error');
        $is_error = true;
    }
    if (stripos($this->exts->extract('body h1', null, 'innerText'), '403 forbidden') !== false) {
        $this->exts->capture('forbidden');
        $is_error = true;
    }
    if (stripos($this->exts->extract('body'), 'Access Denied') !== false) {
        $this->exts->capture('access-denied');
        $is_error = true;
    }

    if ($is_error) {
        $this->clearChrome();
    }

    return $is_error;
}
// solve block
private function disable_extensions()
{
    $this->exts->openUrl('chrome://extensions/'); // disable Block origin extension
    sleep(2);
    $this->exts->execute_javascript("
    let manager = document.querySelector('extensions-manager');
    if (manager && manager.shadowRoot) {
        let itemList = manager.shadowRoot.querySelector('extensions-item-list');
        if (itemList && itemList.shadowRoot) {
            let items = itemList.shadowRoot.querySelectorAll('extensions-item');
            items.forEach(item => {
                let toggle = item.shadowRoot.querySelector('#enableToggle[checked]');
                if (toggle) toggle.click();
            });
        }
    }
");
}

private function clearChrome()
{

    $this->exts->log("Clearing browser history, cookies, and cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);

    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(1);

    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(10);
    $this->exts->capture("after-clear");
}