public $baseUrl = 'https://www.toutsurmoneau.fr/mon-compte-en-ligne/mes-factures';
public $loginUrl = 'https://www.toutsurmoneau.fr/mon-compte-en-ligne/je-me-connecte';
public $invoicePageUrl = 'https://www.toutsurmoneau.fr/mon-compte-en-ligne/mes-factures';

public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'form[name="tsme_user_login"] button#input_connexion_valid, .sign-in-account form button[type="submit"]';

public $check_login_failed_selector = 'div.alert-message, .login-popin [role="alert"] .sz-alert-text';
public $check_login_success_selector = 'a[href*="/deconnexion"]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->checkFillLogin();
        sleep(20);
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        sleep(5);
        if ($this->exts->exists('body>div>h2')) {

            $err_msg1 = $this->exts->extract('body>div>h2');
            $lowercase_err_msg = strtolower($err_msg1);
            $substrings = array('The server returned a "500 Internal Server Error".', '500 Internal Server Error', '500');
            foreach ($substrings as $substring) {
                if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                    $this->exts->log($err_msg1);
                    $this->exts->no_permission();
                    break;
                }
            }
        }

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Identifiant ou mot de passe invalide') !== false) {
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

        if ($this->exts->exists($this->submit_login_selector) && !$this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(10);
        }
        sleep(2);
        if ($this->exts->exists('body>div>h2')) {

            $err_msg1 = $this->exts->extract('body>div>h2');
            $lowercase_err_msg = strtolower($err_msg1);
            $substrings = array('The server returned a "500 Internal Server Error".', '500 Internal Server Error', '500');
            foreach ($substrings as $substring) {
                if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                    $this->exts->log($err_msg1);
                    $this->exts->no_permission();
                    break;
                }
            }
        }

        $this->checkFillRecaptcha();
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillRecaptcha($count = 1)
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

                recurse(___grecaptcha_cfg.clients[1], "", 0);
                return found ? "___grecaptcha_cfg.clients[1]." + result : null;
            ');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            if ($gcallbackFunction != null) {
                $this->exts->executeSafeScript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
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