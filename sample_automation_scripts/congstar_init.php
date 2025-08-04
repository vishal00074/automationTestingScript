public $baseUrl = 'https://www.congstar.de/meincongstar/meine-rechnungen/';

public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'input[name="login"]';

public $check_login_failed_selector = 'feuser-login .notice--error strong';
public $check_login_success_selector = 'header a[data-test-action="logout"]';

public $isNoInvoice = true;

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        if ($this->exts->exists('div#cookie-policy button.btn-primary')) {
            $this->exts->moveToElementAndClick('div#cookie-policy button.btn-primary');
            sleep(15);
        }
        if ($this->exts->getElement('div#usercentrics-root') != null) {
            $this->exts->execute_javascript('document.querySelector("div#usercentrics-root").shadowRoot.querySelector("button[data-testid=\'uc-accept-all-button\']").click();');
            sleep(2);
        }
        $this->checkFillLogin();
        if (strpos(strtolower($this->exts->extract('p.notice.notice--error')), 'roboter') !== false) {
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            $this->checkFillLogin();
        }
        sleep(20);

        $this->checkFillTwoFactor();
        sleep(10);
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
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        //alert alert-error
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('.form-errors p', null, 'innerText')), 'meincongstar login gesperrt') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('.form-errors p', null, 'innerText')), 'ogin or password is incorrect') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('.form-errors p', null, 'innerText')), 'passwort sind nicht korrekt') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('.form-errors p', null, 'innerText')), 'login aktuell nicht') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('div.alert.alert-error', null, 'innerText')), 'korrekt') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->exists($this->password_selector)) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->checkFillRecaptcha();
        sleep(5);
        $this->exts->click_by_xdotool($this->submit_login_selector);
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
            $gcallbackFunction = $this->exts->execute_javascript('
                if(document.querySelector("[data-callback]") != null){
                    document.querySelector("[data-callback]").getAttribute("data-callback");
                }

                var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ return;};
                    try {
                        if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
                        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                        } else { deep++;
                            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                        }
                    } catch(ex) { console.log("ERROR in function: " + ex); return; }
                }

                recurse(___grecaptcha_cfg.clients[0], "", 0);
                found ? "___grecaptcha_cfg.clients[0]." + result : null;');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            if ($gcallbackFunction != null) {
                $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(3);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[name*="otp-input"]';
    $two_factor_message_selector = 'p.otp-info';
    $two_factor_submit_selector = 'form[id="kc-otp-step2-form"] input[type="submit"], input[data-feuserlogin-send-otp-button]';

    if ($this->exts->getElement('[data-feuserlogin-request-otp]:not(.d-none) input[data-feuserlogin-request-otp-button][value*="Code"], #kc-otp-step1-form [type="submit"]') != null) {
        if ($this->exts->exists('select[data-feuserlogin-contact-select]')) {
            $this->exts->click_by_xdotool('select[data-feuserlogin-contact-select]', 50, 10);
            sleep(2);
            $this->exts->click_by_xdotool('select[data-feuserlogin-contact-select]', 50, 50);
        }
        $this->exts->moveToElementAndClick('input[data-feuserlogin-request-otp-button][value*="Code"], #kc-otp-step1-form [type="submit"]');
        sleep(5);
        if ($this->exts->exists($two_factor_selector)) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code. " . $two_factor_code);
                // $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                $this->exts->click_by_xdotool('input[name*="otp-input"]');
                sleep(3);
                $this->exts->type_text_by_xdotool($two_factor_code);
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
}