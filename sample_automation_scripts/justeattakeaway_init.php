public $baseUrl = "https://restaurants.takeaway.com/dashboard";
public $loginUrl = "https://restaurants.takeaway.com/dashboard";
public $invoicePageUrl = 'https://restaurants.takeaway.com/reporting/invoices';
public $username_selector = 'input[id="username"]';
public $password_selector = 'input[id="password"]';
public $submit_button_selector = 'button[id="kc-login"]';
public $check_login_failed_selector = 'div[id="form-error-message-internal"] p';
public $check_login_success_selector = 'div[data-testid="profile-icon"]';
public $login_tryout = 0;
public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->fillForm(0);
        sleep(5);
    }
    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->waitTillPresent($this->check_login_failed_selector, 20);
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 20);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->click_by_xdotool($this->submit_button_selector);
            sleep(10); // Portal itself has one second delay after showing toast

            $stop = 0;
            while ($stop < 3) {
                $this->checkFillRecaptcha();
                sleep(5);
                $stop++;
            }

            if ($this->exts->querySelector($this->submit_button_selector) != null) {
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(5);
            }

            if ($this->exts->querySelector($this->submit_button_selector) != null) {
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(5);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
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
            $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
            for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
            }
            sleep(2);
            $this->exts->capture('recaptcha-filled');

            $gcallbackFunction = $this->exts->execute_javascript('
                    (function() {
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
                    })();
                ');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            $this->exts->log('Callback function: ' . $this->exts->recaptcha_answer);
            if ($gcallbackFunction != null) {
                $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
            }
        } else {
            // try again if recaptcha expired
            if ($count < 3) {
                $count++;
                $this->checkFillRecaptcha($count);
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
private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}