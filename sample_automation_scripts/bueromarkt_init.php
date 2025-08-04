public $baseUrl = 'https://www.bueromarkt-ag.de/';
public $loginUrl = 'https://www.bueromarkt-ag.de/anmeldung/anmelden_auswahl.php?reg=1';
public $invoicePageUrl = 'https://www.bueromarkt-ag.de/mein-konto/bestellungen/rechnungen';

public $username_selector = 'form[name="login"] input[name="Kundennummer_neu"], form.login input[name="Email"]';
public $password_selector = 'form[name="login"] input[name="Passwort_neu"], input#passwort';
public $remember_me_selector = '';
public $submit_login_btn = 'form[name="login"] input[type="submit"], form.login .btn-login';

public $checkLoginFailedSelector = 'div.message.error';
public $checkLoggedinSelector = 'a[href*="/logout.php"], a[href*="/mein-konto/abmelden"]';

public $isNoInvoice = true;
public $restrictPages = 3;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    $this->exts->openUrl($this->baseUrl);
    sleep(4);

    for ($i = 0; $i < 11; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(4);
    $this->exts->openUrl($this->baseUrl);
    sleep(4);

    if ($this->exts->exists($this->checkLoggedinSelector)) {
        $this->exts->log('Logged in from initPortal');
        $this->exts->capture('0-init-portal-loggedin');
        $this->checkFillRecaptcha();
        $this->waitForLogin();
    } else {
        $this->exts->openUrl($this->loginUrl);
        sleep(4);
        $this->checkFillRecaptcha();
        if ($this->exts->exists('button#uc-btn-accept-banner')) {
            $this->exts->moveToElementAndClick('button#uc-btn-accept-banner');
            sleep(1);
        }
        // accept cookies button
        $this->exts->execute_javascript('
    var shadow = document.querySelector("#usercentrics-root").shadowRoot;
    var button = shadow.querySelector(\'button[data-testid="uc-accept-all-button"]\')
    if(button){
        button.click();
    }
');

        $this->waitForLoginPage();
        sleep(20);
        // check if has shown 403 page
        if ($this->exts->exists('.error-code-truck .no-user-select')) {
            $this->exts->log('-- 403 page --');
            $this->exts->capture('1.1-blocked-page');
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillRecaptcha();
            $this->waitForLoginPage();
        }

        $this->waitForLogin();
    }
}

private function waitForLoginPage()
{
    sleep(5);
    $this->exts->capture("1-pre-login");

    if ($this->exts->exists($this->username_selector)) {
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
    }

    if ($this->exts->exists($this->password_selector)) {
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);
    }

    $this->exts->capture("1-filled-login");
    $this->exts->moveToElementAndClick($this->submit_login_btn);
    sleep(10);
    $this->checkFillRecaptcha();
    if ($this->exts->exists($this->submit_login_btn)) {
        $this->exts->execute_javascript('document.querySelector(arguments[0]).click', [$this->submit_login_btn]);
        sleep(10);
    }
}

private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    if ($this->exts->exists('iframe[name="recaptcha"]')) {
        $this->switchToFrame('iframe[name="recaptcha"]');
    }
    if ($this->exts->exists('iframe#grcv3enterpriseframe')) {
        $this->switchToFrame('iframe#grcv3enterpriseframe');
    }

    if ($this->exts->exists('iframe#main-iframe')) {
        $this->switchToFrame('iframe#main-iframe');
    }
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
            $this->exts->switchToDefault();
        } else {
            if ($count < 4) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
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

private function waitForLogin()
{
    if ($this->exts->exists($this->checkLoggedinSelector)) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture('Login-success');

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
			$this->exts->triggerLoginSuccess();
		}
        
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if ($this->exts->exists($this->checkLoginFailedSelector)) {
            $errorMsg = $this->exts->extract($this->checkLoginFailedSelector);
            $this->exts->log($errorMsg);
            if (stripos($errorMsg, 'Sie Ihren Benutzernamen und Ihr Passwort') !== false && stripos($errorMsg, 'Fehlerhafte Eingabe') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($errorMsg), 'richtige e-mail-adresse / benutzer') !== false || strpos(strtolower($errorMsg), 'das richtige passwort') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        } else {
            $this->exts->loginFailure();
        }
    }
}
