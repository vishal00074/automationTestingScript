public $baseUrl = "https://mijn.simpel.nl/";
public $username_selector = '#login [name=username]';
public $password_selector = '#login [name=password]';
public $submit_btn = '#login [type=submit]';
public $logout_btn = '[href*="/uitloggen"], button[data-qa="account-menu-logout"]';
public $isNoInvoice = true;


/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
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
        $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
    }

    if ($this->exts->exists('button[onclick="acceptAll()"]')) {
        $this->exts->moveToElementAndClick('button[onclick="acceptAll()"]');
        sleep(4);
    }

    $this->fillForm();
    sleep(20);


    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->capture("LoginFailed");
        if (stripos($this->exts->extract('.--error', null, 'innerText'), "wachtwoord klopt niet") !== false) { //wachtwoord klopt niet
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
function fillForm($count = 1)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        if ($this->exts->exists($this->username_selector)) {

            $this->checkFillRecaptcha();
            sleep(2);
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists('div.vc-checkbox-input input[name="remember"]')) {
                $this->exts->moveToElementAndClick('div.vc-checkbox-input input[name="remember"]');
                sleep(2);
            }

            $this->exts->capture("1-pre-login-1");


            $this->exts->moveToElementAndClick($this->submit_btn);
            sleep(15);
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

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->exists($this->logout_btn)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}