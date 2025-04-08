public $baseUrl = 'https://customer-self-care.waipu.tv';
public $loginUrl = 'https://auth.waipu.tv/ui/login';
public $invoicePageUrl = 'https://customer-self-care.waipu.tv/ui/my_invoices';

public $username_selector = 'form#loginForm input[name="emailAddress"]';
public $password_selector = 'form#loginForm input[name="password"]';
public $remember_me_selector = '';
public $submit_login_btn = 'form#loginForm button.button';

public $checkLoginFailedSelector = 'form#loginForm .alert--error';
public $checkLoggedinSelector = 'a[href*="/ui/logout"], .header--logged-in, .welcome__text + button';
public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(15);
    $this->clearChrome();
    // after load cookies and open base url, check if user logged in
    // Wait for selector that make sure user logged in
    // If user hase not logged in, open the login url and wait for login form
    $this->exts->log('NOT logged in from initPortal');
    $this->exts->capture('0-init-portal-not-loggedin');

    $this->exts->openUrl($this->loginUrl);
    $this->exts->waitTillPresent('iframe[id*="sp_message_iframe"]', 20);
    $this->switchToFrame('iframe[id*="sp_message_iframe"]');
    sleep(5);
    if ($this->exts->exists('button[title="Zustimmen und weiter"]')) {
        $this->exts->click_by_xdotool('button[title="Zustimmen und weiter"]');
    }
    $this->exts->switchToDefault();
    sleep(10);
    $this->checkFillLogin();
    sleep(5);
    if ($this->exts->allExists([$this->password_selector])) {
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->exts->waitTillPresent('iframe[id*="sp_message_iframe"]', 20);
        $this->switchToFrame('iframe[id*="sp_message_iframe"]');
        sleep(5);
        if ($this->exts->exists('button[title="Zustimmen und weiter"]')) {
            $this->exts->click_by_xdotool('button[title="Zustimmen und weiter"]');
        }
        $this->exts->switchToDefault();
        sleep(2);
    }
    $this->checkFillLogin();
    sleep(10);
    $this->exts->capture("2-post-login");
    if ($this->exts->allExists([$this->password_selector])) {
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->checkFillLogin();
        sleep(10);
        $this->exts->capture("2-post-login");
    }

    sleep(15);

    if ($this->exts->exists($this->checkLoggedinSelector) || $this->exts->urlContains('waipu.tv/ARD')) {
        $this->exts->log('User logged in.');

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
        
        $this->exts->success();
    } else {
        $this->exts->log('Timeout waitForLogin: ' . $this->exts->getUrl());
        $this->exts->capture("LoginFailed");

        if (strpos(strtolower($this->exts->extract($this->checkLoginFailedSelector, null, 'innerText')), 'die zugangsdaten sind ung') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
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
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
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

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->username_selector, 5);
    if ($this->exts->querySelector($this->username_selector) != null) {
        // $this->capture_by_chromedevtool("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);
        $this->checkFillRecaptcha();

        $this->exts->capture("1-filled-login");
        sleep(5);
        if ($this->exts->exists($this->submit_login_btn)) {
            $this->exts->click_element($this->submit_login_btn);
            sleep(7);
        }

        if (strpos(strtolower($this->exts->extract($this->checkLoginFailedSelector, null, 'innerText')), 'die zugangsdaten sind ung') !== false) {
            $this->exts->loginFailure(1);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'div#grecaptcha-root iframe[title="reCAPTCHA"]';
    $recaptcha_textarea_selector = 'div#grecaptcha-root textarea[name="g-recaptcha-response"]';
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, true);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {

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

            $this->exts->capture('recaptcha-filled');
        } else {
            // try again if recaptcha expired
            if ($count < 5) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}