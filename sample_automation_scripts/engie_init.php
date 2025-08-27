public $baseUrl = 'https://espace-client.pro.engie.fr/';
public $loginUrl = 'https://espace-client.pro.engie.fr/user/auth';
public $invoicePageUrl = 'https://espace-client.pro.engie.fr/mes-factures';

public $username_selector = 'form input#okta-signin-username, input#edit-email-login';
public $password_selector = 'form input#okta-signin-password, input[name="mdp_login"][type="password"]';
public $submit_login_selector = 'form input#okta-signin-submit, input.engie-login-submit';

public $check_login_success_selector = 'a[href*="/logout"]';

public $isNoInvoice = true;
public $filesCompleted = 0;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->disable_extensions();

    $this->exts->openUrl($this->baseUrl);
    sleep(1);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        // die;
        if ($this->exts->exists('div#popin_tc_privacy_container_button button[type="button"]')) {
            $this->exts->moveToElementAndClick('div#popin_tc_privacy_container_button button[type="button"]');
            sleep(3);
        }
        if ($this->exts->querySelector('button#popin_tc_privacy_button_2') != null) {
            $this->exts->moveToElementAndClick('button#popin_tc_privacy_button_2');
            sleep(3);
        }
        $this->exts->click_if_existed('#tc-privacy-wrapper button#accept_all');

        $this->checkFillLogin();
        sleep(10);

        if ($this->exts->querySelector('input[name="factor_id"]') != null) {
            $this->exts->moveToElementAndClick('input[name="factor_id"]');
            sleep(10);
        }

        if (
            $this->exts->oneExists([$this->username_selector, $this->password_selector]) &&
            !$this->exts->exists('.login-form-page .form-item--error-message')
        ) {
            sleep(12);
        }
        if (stripos($this->exts->extract('.login-form-page .form-item--error-message', null, 'innerText'), 'Captcha') !== false) {
            $this->checkFillLogin();
            sleep(10);
            if (
                $this->exts->oneExists([$this->username_selector, $this->password_selector]) &&
                !$this->exts->exists('.login-form-page .form-item--error-message')
            ) {
                sleep(12);
            }
        }
        if (stripos($this->exts->extract('.login-form-page .form-item--error-message', null, 'innerText'), 'Captcha') !== false) {
            $this->checkFillLogin();
            sleep(10);
            if (
                $this->exts->oneExists([$this->username_selector, $this->password_selector]) &&
                !$this->exts->exists('.login-form-page .form-item--error-message')
            ) {
                sleep(12);
            }
        }

        $this->checkFillTwoFactor();
    }
    if ($this->exts->querySelector('.contacts-form') != null) {
        $this->exts->moveToElementAndClick('.name-user');
        sleep(2);
        $this->exts->moveToElementAndClick('input#edit-submit');
        sleep(10);
    }
    if ($this->exts->querySelector('form.cgu-form') != null) {
        $this->exts->moveToElementAndClick('input#edit-consent');
        sleep(2);
        $this->exts->moveToElementAndClick('input#edit-submit');
        sleep(1);
    }

    if (!$this->checkLogin()) {
        $this->exts->capture('before-login');
        sleep(50);
    }

    if ($this->checkLogin()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if (stripos($this->exts->extract('.login-form-page .form-item--error-message', null, 'innerText'), 'le mot de passe sont incorrects') !== false) {
            $this->exts->loginFailure(1);
        } else if (stripos(strtolower($this->exts->extract('.infobox-error')), 'dresse email inconnue') !== false) {
            $this->exts->loginFailure(1);
        } else if (stripos(strtolower($this->exts->extract('.infobox-error')), 'otre compte est bloqu') !== false) {
            $this->exts->loginFailure(1);
        } else if (
            stripos(strtolower($this->exts->extract('p.okta-form-input-error')), 'veuillez saisir une adresse email valide') !== false
            || stripos(strtolower($this->exts->extract('p.okta-form-input-error')), 'please enter a valid email address') !== false
        ) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('div.message-card-title', null, 'innerText')), 'pas de contrat actif avec cette adresse mail') !== false) {
            $this->exts->account_not_ready();
        } else if (strpos(strtolower($this->exts->extract('div.form-item--error-message', null, 'innerText')), 'otre mot de passe a expir') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('h2.card-big-white-title', null, 'innerText')), 'verrouiller le compte') !== false) {
            $this->exts->account_not_ready();
        } else if (strpos(strtolower($this->exts->extract('div.form-item--error-message', null, 'innerText')), 'euillez saisir une adresse e-mail valide') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('div.form-item--error-message', null, 'innerText')), 'dresse e-mail inconnue') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->querySelector($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        // $this->exts->moveToElementAndType($this->username_selector, $this->username);
        $this->exts->click_by_xdotool($this->username_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        // $this->exts->moveToElementAndType($this->password_selector, $this->password);
        $this->exts->click_by_xdotool($this->password_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->password);
        sleep(1);
        // $this->checkFillRecaptcha();
        $this->exts->capture("2-login-page-filled");
        $this->checkFillRecaptcha();
        $this->exts->click_by_xdotool($this->submit_login_selector);
        sleep(4);

        if($this->exts->querySelector($this->submit_login_selector) != null){
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(2);
        } 
        if($this->exts->querySelector($this->submit_login_selector) != null){
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(2);
        }  

    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = '[id*="factor-verify-form"] input[name*="box"]';
    $two_factor_message_selector = '.mfa-code-send-message .body';
    $two_factor_submit_selector = '[id*="factor-verify-form"] #edit-submit';

    $this->exts->waitTillPresent('input[type="radio"][name="factor_id"]', 25);
    if ($this->exts->exists('input[type="radio"][name="factor_id"]')) {
        $this->exts->capture('2fa-mothods');
        $this->exts->moveToElementAndClick('input[type="radio"][name="factor_id"]');
        sleep(3);
        $this->exts->waitTillPresent($two_factor_selector, 25);
        sleep(1);
    }

    if ($this->exts->exists($two_factor_selector)) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        if ($this->exts->exists($two_factor_message_selector)) {
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector, null, 'innerText');
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->click_by_xdotool($two_factor_selector);
            sleep(1);
            $this->exts->type_text_by_xdotool($two_factor_code);
            sleep(1);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->notification_uid = "";
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

private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = "iframe[src*='recaptcha/enterprise']";
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
            $recaptcha_textareas = $this->exts->querySelectorAll($recaptcha_textarea_selector);
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

private function disable_extensions()
{
    $this->exts->openUrl('chrome://extensions/'); // disable Block origin extension
    sleep(2);
    $this->exts->execute_javascript("
    let manager = document.querySelector('extensions-manager');
    if (manager && manager.shadowRoot) {
        let itemList = manager.shadowRoot.querySelector('extensions-item-list');
        if (itemList && itemList.shadowRoot) {
            let items = itemList.shadowRoot.querySelectorAll('extensions-item');
            items.forEach(item => {
                let toggle = item.shadowRoot.querySelector('#enableToggle[checked]');
                if (toggle) toggle.click();
            });
        }
    }
");
}

private function checkLogin()
{
    return $this->exts->querySelector($this->check_login_success_selector) != null || ($this->exts->urlContains('check_logged_in=1') && $this->exts->urlContains('liste-contacts'));
}