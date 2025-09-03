public $baseUrl = 'https://dash.cloudflare.com/';
public $loginUrl = 'https://dash.cloudflare.com/login';
public $invoicePageUrl = 'https://dash.cloudflare.com/account/billing';
public $username_selector = 'form[action="/login"] input[name="email"]';
public $password_selector = 'form[action="/login"] input[name="password"]';
public $remember_me_selector = 'form[action="/login"] input#remember_me';
public $submit_login_btn = 'button[data-testid="login-submit-button"]';
public $checkLoginFailedSelector = '';
public $check_login_success_selector = 'a[href*="logout"], button[data-testid*="user-selector-dropdown-button"], li[data-testid="manage-account-link"], main[data-testid="page-account-selector"]';
public $hcaptcha_existed = false;
public $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(15);
    $this->check_solve_cloudflare_page();
    $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector, $this->unsolved_cloudflare_input_xpath]);
    if ($this->isExists('#onetrust-consent-sdk button#onetrust-accept-btn-handler')) {
        $this->exts->moveToElementAndClick('#onetrust-consent-sdk button#onetrust-accept-btn-handler');
    }
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->waitTillPresent($this->unsolved_cloudflare_input_xpath, 10);
        if ($this->isExists('#onetrust-consent-sdk button#onetrust-accept-btn-handler')) {
            $this->exts->click_by_xdotool('#onetrust-consent-sdk button#onetrust-accept-btn-handler');
            sleep(3);
        }
        $this->check_solve_blocked_page();
        if ($this->isExists($this->unsolved_cloudflare_input_xpath)) {
            $this->clearChrome();
            $this->exts->openUrl($this->baseUrl);
            sleep(3);
            $this->exts->waitTillPresent($this->unsolved_cloudflare_input_xpath, 10);
            if ($this->isExists('#onetrust-consent-sdk button#onetrust-accept-btn-handler')) {
                $this->exts->click_by_xdotool('#onetrust-consent-sdk button#onetrust-accept-btn-handler');
                sleep(3);
            }
            $this->check_solve_blocked_page();
        }
        $this->fillLoginPage();
        sleep(15);
        if (
            ($this->isExists($this->password_selector) && $this->hcaptcha_existed) ||
            strpos(strtolower($this->exts->extract('[data-testid="hcaptcha-div-container"] [id^="hcaptcha"] + div')), 'erforderlich') !== false ||
            strpos(strtolower($this->exts->extract('[data-testid="hcaptcha-div-container"] [id^="hcaptcha"] + div')), 'required') !== false ||
            strpos(strtolower($this->exts->extract('[data-testid="hcaptcha-div-container"] [id^="hcaptcha"] + div')), 'requis') !== false
        ) {
            $this->login_via_api();
        } else if ($this->isExists($this->password_selector) && !$this->isExists('//div[contains(., "do not match") or contains(., "stimmen nicht überein")]')) {
            $this->exts->log('Try to login again!!!');
            $this->check_solve_blocked_page();
            $this->fillLoginPage();
        }

        $this->checkFillTwoFactor();
        for ($h = 0; $h < 15 && $this->isExists('div#loading-state div.spinner'); $h++) {
            sleep(1);
        }
        for ($k = 0; $k < 15 && $this->exts->getElement($this->check_login_success_selector) == null; $k++) {
            sleep(1);
        }
    }
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log('Timeout waitForLogin');
        $this->exts->capture("LoginFailed");
        $isLoginFailed = $this->exts->execute_javascript('try{return document.querySelector(\'div[role="alert"]\').innerText.toLocaleUpperCase().indexOf("PASSWORD") > -1;}catch(ex){return false}');

        if ($isLoginFailed || $this->isExists('//div[contains(., "do not match") or contains(., "stimmen nicht überein")]')) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->urlContains('/enable-two-factor')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function login_via_api()
{
    $this->exts->openUrl($this->loginUrl);
    sleep(35);

    if (!$this->isExists('iframe[src*="hcaptcha"]')) {
        $login_result = $this->exts->execute_javascript('
        var data = {
            "email": "' . $this->username . '",
            "password": "' . $this->password . '"
        }
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "https://dash.cloudflare.com/api/v4/login", false);
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.setRequestHeader("x-cross-site-security", "dash");
        xhr.send(JSON.stringify(data));
        var result = JSON.parse(xhr.responseText);
        if(result.success){
            return document.location.origin + result.result.redirect_uri;
        } else if(JSON.stringify(result.errors).indexOf("login.error.email_password_mismatch") > -1){
            return "CREDENTIAL_WRONG"
        } else if(JSON.stringify(result.errors).indexOf("login.error.no_email") > -1){
            return "CREDENTIAL_WRONG"
        }
        return null;
    ');
        $this->exts->log($login_result);
        if ($login_result == 'CREDENTIAL_WRONG') {
            $this->exts->loginFailure(1);
        } else if ($login_result != null) {
            $this->exts->openUrl($login_result);
        }
    } else {
        $jsonRes = $this->checkFillHcaptcha();
        if ($jsonRes == null) {
            $jsonRes = $this->checkFillHcaptcha();
        }
        $this->exts->log('hcaptcha result: ' . $jsonRes);
        $login_result = $this->exts->execute_javascript('
        var data = {
            "h-captcha-response": "' . $jsonRes . '",
            "email": "' . $this->username . '",
            "password": "' . $this->password . '"
        }
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "https://dash.cloudflare.com/api/v4/login", false);
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.setRequestHeader("x-cross-site-security", "dash");
        xhr.send(JSON.stringify(data));
        var result = JSON.parse(xhr.responseText);
        if(result.success){
            return document.location.origin + result.result.redirect_uri;
        } else if(JSON.stringify(result.errors).indexOf("login.error.email_password_mismatch") > -1){
            return "CREDENTIAL_WRONG"
        } else if(JSON.stringify(result.errors).indexOf("login.error.no_email") > -1){
            return "CREDENTIAL_WRONG"
        }
        return null;
    ');
        $this->exts->log($login_result);
        if ($login_result == 'CREDENTIAL_WRONG') {
            $this->exts->loginFailure(1);
        } else if ($login_result != null) {
            $this->exts->openUrl($login_result);
        }
    }
}
private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#pages").querySelector("#clearFromBasic").shadowRoot.querySelector("#dropdownMenu").value = 4;');
    sleep(1);
    $this->exts->capture("clear-page");
    $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#clearButton").click();');
    sleep(15);
    $this->exts->capture("after-clear");
}
private function fillLoginPage()
{
    if ($this->isExists($this->password_selector)) {
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
        sleep(3);
        $this->check_solve_cloudflare_page();

        $this->exts->capture("2-login-page-filled");
        $this->exts->click_by_xdotool($this->submit_login_btn);
        for ($i = 0; $i < 10 && $this->exts->getElement('//*[contains(text(),"password do not match")]') == null; $i++) {
            sleep(1);
        }


        if ($this->exts->getElement('//*[contains(text(),"password do not match")]') != null) {
            $this->exts->loginFailure(1);
        }
        $this->exts->waitTillPresent('div[role="alert"]', 5);
        if ($this->isExists('div[role="alert"]')) {
            $mesg = strtolower($this->exts->extract('div[role="alert"]'));
            $this->exts->log($mesg);
            $this->exts->capture("1-login-error");
            if (strpos($mesg, 'password do not match') !== false) {
                $this->exts->loginFailure(1);
            }
        }
        sleep(10);
    }
}
private function checkFillHcaptcha()
{
    $hcaptcha_iframe_selector = 'iframe[src*="hcaptcha"]';
    if ($this->isExists($hcaptcha_iframe_selector)) {
        $this->hcaptcha_existed = true;
        $iframeUrl = $this->exts->extract($hcaptcha_iframe_selector, null, 'src');
        $data_siteKey = end(explode("&sitekey=", $iframeUrl));
        $data_siteKey = explode("&", $data_siteKey)[0];
        $jsonRes = $this->exts->processHumanCaptcha("", $data_siteKey, $this->exts->getUrl(), false);
        $captchaScript = '
        function submitToken(token) {
        document.querySelector("[name=g-recaptcha-response]").innerText = token;
        document.querySelector("[name=h-captcha-response]").innerText = token;
        document.querySelector("[name=h-captcha-response]").dispatchEvent(new Event("change"));
        document.querySelector("[data-testid=login-form] iframe[src*=hcaptcha]").setAttribute("data-hcaptcha-response", token);
        document.querySelector("[data-testid=login-form] iframe[src*=hcaptcha]").dispatchEvent(new Event("change"));
        }
        submitToken(arguments[0]);
    ';
        $params = array($jsonRes);
        $this->exts->execute_javascript($captchaScript, $params);
        sleep(2);
        $this->exts->switchToDefault();
        // if ($this->isExists('form.challenge-form')) {
        //      $this->exts->execute_javascript('document.querySelector("form.challenge-form").submit();');
        //    }
        return $jsonRes;
    }
}
private function checkFillTwoFactor()
{
    $two_factor_selector = 'form input[name="twofactor_token"]';
    $two_factor_message_selector = '[data-testid="two-factor-page-totp"] h1';
    $two_factor_submit_selector = '[data-testid="two-factor-login-submit-button"]';
    if ($this->isExists('[data-testid="two-factor-page-hw-key"] a[data-testid="try_another_2fa_method"]')) {
        // if 2Fa methos is Insert your security key and touch it.
        // We have to click Choose another method
        $this->exts->type_key_by_xdotool('space');
        sleep(2);
        $this->exts->moveToElementAndClick('[data-testid="two-factor-page-hw-key"] a[data-testid="try_another_2fa_method"]');
        sleep(3);
    }
    if ($this->exts->getElement($two_factor_selector) != null) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
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
private function check_solve_blocked_page()
{
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if (
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $this->unsolved_cloudflare_input_xpath]) &&
        $this->isExists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ) {
        for ($waiting = 0; $waiting < 10; $waiting++) {
            sleep(2);
            if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $this->unsolved_cloudflare_input_xpath])) {
                sleep(3);
                break;
            }
        }
    }

    if ($this->isExists($this->unsolved_cloudflare_input_xpath)) {
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
        if ($this->isExists($this->unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-2", true);
            sleep(15);
        }
        if ($this->isExists($this->unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-3", true);
            sleep(15);
        }
    }
}


private function check_solve_cloudflare_page()
{
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if (
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
        $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ) {
        for ($waiting = 0; $waiting < 10; $waiting++) {
            sleep(2);
            if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                sleep(3);
                break;
            }
        }
    }

    if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-2", true);
            sleep(15);
        }
        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-3", true);
            sleep(15);
        }
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