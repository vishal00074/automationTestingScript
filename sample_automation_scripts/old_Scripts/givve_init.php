public $baseUrl = "https://b2b.givve.com";
public $username_selector = '#mat-input-0';
public $password_selector = '#mat-input-1';
public $submit_btn = '#content form [type=submit], form button.mat-primary';
public $logout_btn = 'mat-sidenav-content app-toolbar-profile, button[data-cy="logout-button"]';
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
        $this->exts->capture("after-login-clicked");
        $this->fillForm(0);
        sleep(20);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
        
        $this->exts->success();
    } else {

        $this->exts->capture("LoginFailed");
        if (strpos(strtolower($this->exts->extract('form[data-cy="login-form"] div.error-row', null, 'innerText')), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        
        for ($i = 0; $i < 15 && $this->exts->getElement($this->username_selector) == null; $i++) {
            sleep(2);
        }
        if ($this->isExists($this->username_selector)) {
            sleep(2);
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            $this->exts->capture("1-pre-login-1");
            $this->checkFillRecaptcha();

            $this->exts->click_element($this->submit_btn);
            sleep(10);
        } else if ($this->isExists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->isExists("textarea[name=\"g-recaptcha-response\"]")) {
            $this->checkFillRecaptcha();
            $this->fillForm($count + 1);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
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
            $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
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

private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->isExists($this->logout_btn) && $this->isExists($this->username_selector) == false) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        } else if ($this->isExists('button[data-cy="toolbar-profile-button"]')) {
            $this->exts->moveToElementAndClick('button[data-cy="toolbar-profile-button"]');
            sleep(1);
            if ($this->isExists($this->logout_btn)) {
                $isLoggedIn = true;
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
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