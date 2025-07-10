public $baseUrl = 'https://www.conrad.de/de/account.html';
public $invoicePageUrl = 'https://www.conrad.de/de/account.html#/invoices';
public $username_selector = 'app-login input#username';
public $password_selector = 'app-login input#password';
public $submit_login_selector = 'app-login [type="submit"]';

public $check_login_failed_selector = 'app-login .error-label.text-center div';
public $check_login_success_selector = 'form[action*="logout.html"], button.logoutButton, .cmsFlyout.myAccount a[data-logout="data-logout"], button[data-e2e="logout"]';

public $restrictPages = 3;
public $isNoInvoice = true;
public $totalFiles = 0;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->disable_uBlock_extensions();
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

    $this->exts->openUrl($this->baseUrl);
    sleep(2);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(3);
    $this->exts->openUrl($this->baseUrl);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    $this->exts->waitTillPresent($this->check_login_success_selector, 60);
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->clearChrome();
        $this->exts->openUrl($this->baseUrl);
        sleep(20);
        for ($i = 0; $i < 5 && $this->exts->exists('div.la-ball-clip-rotate-multiple'); $i++) {
            sleep(10);
        }
        $this->checkFillLogin();
        sleep(15);
        $this->exts->moveToElementAndClick('.cmsCookieNotification__button--reject span.cmsCookieNotification__button__label');
        sleep(3);
        if ($this->exts->querySelector($this->check_login_success_selector) == null && $this->exts->querySelector($this->check_login_failed_selector) == null) {
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(15);
            if ($this->exts->exists('.cmsCookieNotification .cmsCookieNotification__body .cmsCookieNotification__button--accept')) {
                $this->exts->moveToElementAndClick('.cmsCookieNotification .cmsCookieNotification__body .cmsCookieNotification__button--accept');
                sleep(5);
            }
        }
    }

    // then check user logged in or not
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(5);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
        
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector, 20);
    if ($this->exts->querySelector($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        $this->exts->capture("2-login-page-filled");
        $this->checkFillRecaptcha();
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(3);
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
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
            $recaptcha_textareas = $this->exts->getElements($recaptcha_textarea_selector);
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

private function disable_uBlock_extensions()
{
    $this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm'); // disable Block origin extension
    sleep(2);
    $this->exts->executeSafeScript("
    if(document.querySelector('extensions-manager') != null) {
        if(document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view')  != null){
            var disable_button = document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]');
            if(disable_button != null){
                disable_button.click();
            }
        }
    }
");
    sleep(1);
}

