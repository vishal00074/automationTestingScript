public $baseUrl = 'https://www.yello.de/mein-yello/anmeldung';
public $loginUrl = 'https://www.yello.de/mein-yello/anmeldung';
public $invoicePageUrl = 'https://mein.yello.de';

public $username_selector = 'form#loginForm input[name="Benutzername"], input#emailinput';
public $password_selector = 'form#loginForm input[name="Passwort"], input#passwordinput';
public $remember_me_selector = '';
public $submit_login_selector = 'form#loginForm button.login-form__anmelden-button, form#loginForm button[type="submit"], button#loginbtn';

public $check_login_failed_selector = '.prozessError, form#loginForm .validation-message';
public $check_login_success_selector = 'form[action*="/logout"], a[href="/logout"], dl[class*="vertrag-location"], span.header-user-profile__initials';
public $download_all_documents = '0';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->download_all_documents = isset($this->exts->config_array["download_all_documents"]) ? (int)@$this->exts->config_array["download_all_documents"] : 0;
    $this->exts->log('download_all_documents: ' . $this->download_all_documents);

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
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->exts->moveToElementAndClick('#auth-button-login-button');
        if ($this->exts->exists('a.js_cookie-decline,button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('a.js_cookie-decline,button#onetrust-accept-btn-handler');
            sleep(15);
        }

        if ($this->exts->exists('a[href="/login?signup=false"], button#login-button')) {
            $this->exts->moveToElementAndClick('a[href="/login?signup=false"], button#login-button');
            sleep(15);
        }

        $this->checkFillLogin();
        sleep(20);
    }

    if ($this->exts->exists('a.js_cookie-decline,button#onetrust-accept-btn-handler')) {
        $this->exts->moveToElementAndClick('a.js_cookie-decline,button#onetrust-accept-btn-handler');
        sleep(15);
    }


    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract('div.flying-wrapper__error-message', null, 'innerText')), 'und dein passwort') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->getElement($this->check_login_failed_selector) != null) {
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
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);
        $this->checkFillRecaptcha();
        sleep(5);
        $this->check_solve_blocked_page();

        $this->exts->capture("2-login-page-filled");
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
        sleep(5);
        if (!$this->isValidEmail($this->username)) {
            $this->exts->loginFailure(1);
        }
        sleep(15);
        $this->checkFillRecaptcha();
        sleep(2);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}


public function isValidEmail($email)
{
    // Define the email pattern
    $pattern = "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";

    // Check if the email matches the pattern
    return preg_match($pattern, $email);
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


private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");

    for ($i = 0; $i < 5; $i++) {
        if ($this->exts->check_exist_by_chromedevtool('div.captcha-container > div > div')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            sleep(10);

            $this->exts->click_by_xdotool('div.captcha-container > div > div', 130, 28);
            sleep(15);

            if (!$this->exts->check_exist_by_chromedevtool('div.captcha-container > div > div')) {
                break;
            }
        } else {
            break;
        }
    }
}
