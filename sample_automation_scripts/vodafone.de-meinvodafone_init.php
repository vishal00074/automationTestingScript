public $baseUrl = "https://www.vodafone.de/meinvodafone/account/login";
public $username_selector = 'input#txtUsername';
public $password_selector = 'input#txtPassword';
public $submit_btn = '.login-onelogin [type=submit]';
public $logout_btn = 'div.dashboard-module';
public $totalInvoices = 0;
public $itemized_bill = 0;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    sleep(10);
    $this->clearChrome();

    $isCookieLoaded = false;
    if ($this->exts->loadCookiesFromFile()) {
        sleep(1);
        $isCookieLoaded = true;
    }

    $this->exts->openUrl($this->baseUrl);

    if ($isCookieLoaded) {
        $this->exts->capture("Home-page-with-cookie");
    } else {
        $this->exts->capture("Home-page-without-cookie");
    }

    $this->cookieConsent();

    if (!$this->checkLogin()) {
        $this->exts->openUrl($this->baseUrl);
        if ($this->exts->urlContains('/captcha')) {
            $this->waitForSelectors('img.captcha', 16, 1);
            for ($i = 0; $i < 5; $i++) {
                if ($this->exts->exists('img.captcha')) {
                    $this->exts->capture('captcha-found-' . $i);
                    $this->exts->click_element('a[href*="captcha"].btn');
                    sleep(10);
                    $this->exts->refresh();
                    $this->exts->processCaptcha('img.captcha', 'input[name="captcha"]');
                    $this->exts->capture('captcha-filled');
                    $this->waitForSelectors("form[action*='/captcha'] [type='submit']", 5, 3);
                    if ($this->exts->exists('form[action*="/captcha"] [type="submit"]')) {
                        $this->exts->click_element('form[action*="/captcha"] [type="submit"]');
                    }
                    sleep(15);
                } else {
                    break;
                }
            }
        }

        $captchaErrorText = strtolower($this->exts->extract('div[class="fm-formerror error-body"] p:nth-child(3)'));

        $this->exts->log(__FUNCTION__ . '::captcha Error text: ' . $captchaErrorText);
        if (stripos($captchaErrorText, strtolower('Bitte sieh Dir das Bild an und geben den Sicherheitscode erneut ein.')) !== false) {
            $this->clearChrome();
            $this->exts->openUrl($this->baseUrl);

            $this->waitForSelectors('button#dip-consent-summary-accept-all', 5, 3);

            if ($this->exts->exists('button#dip-consent-summary-accept-all')) {
                $this->exts->moveToElementAndClick('button#dip-consent-summary-accept-all');
                sleep(4);
            }

            for ($i = 0; $i < 7; $i++) {
                if ($this->exts->exists('img.captcha')) {
                    $this->exts->capture('captcha-found-' . $i);
                    $this->exts->processCaptcha('img.captcha', 'input[name="captcha"]');
                    $this->exts->capture('captcha-filled');
                    $this->waitForSelectors("form[action*='/captcha'] [type='submit']", 5, 3);
                    if ($this->exts->exists('form[action*="/captcha"] [type="submit"]')) {
                        $this->exts->click_element('form[action*="/captcha"] [type="submit"]');
                    }
                    sleep(15);
                } else {
                    break;
                }
                if ($this->exts->exists('img.captcha')) {
                    $this->exts->openUrl($this->baseUrl);
                    sleep(15);
                }
            }
        }

        $this->cookieConsent();
        $this->exts->capture("after-login-clicked");
        $this->checkFillTwoFactor();
        if (!$this->checkLogin() && !$this->exts->exists('div.login-onelogin div.error div.alert-content, .alert-old div.alert.error')) {
            $this->exts->refresh();

            $this->waitForSelectors($this->logout_btn, 10, 1);

            if ($this->exts->exists($this->password_selector)) {
                $this->fillForm(0);
                $this->waitForSelectors('form#totpWrapper input#totpcontrol, div.login-onelogin div.error div.alert-content, div.dashboard-module', 20, 1);
                // $this->exts->waitTillAnyPresent([$this->logout_btn, 'form#totpWrapper input#totpcontrol', 'div.login-onelogin div.error div.alert-content']);
            }
            $this->checkFillTwoFactor();
        }

        $this->cookieConsent();

        $err_msg = $this->exts->extract('div.login-onelogin div.error div.alert-content');
        // if ($this->exts->querySelector("div.login-onelogin div.error div.alert-content") != null) {
        //  $err_msg = trim($this->exts->querySelectorAll("div.login-onelogin div.error div.alert-content")[0]->getAttribute('innerText'));
        // }

        if ($err_msg != "" && $err_msg != null && $this->exts->exists('div.login-onelogin div.error div.alert-content')) {
            $this->exts->log($err_msg);
            $this->exts->loginFailure(1);
        }
    }


    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        if ($this->exts->urlContains('/captcha')) {
            $this->waitForSelectors('img.captcha', 16, 1);
            for ($i = 0; $i < 5; $i++) {
                if ($this->exts->exists('img.captcha')) {
                    sleep(10);
                    $this->exts->refresh();
                    $this->exts->processCaptcha('img.captcha', 'input[name="captcha"]');
                    $this->exts->capture('captcha-filled');
                    $this->waitForSelectors("form[action*='/captcha'] [type='submit']", 5, 3);
                    if ($this->exts->exists('form[action*="/captcha"] [type="submit"]')) {
                        $this->exts->click_element('form[action*="/captcha"] [type="submit"]');
                    }
                    sleep(15);
                } else {
                    break;
                }
            }
        }
        $this->exts->moveToElementAndClick('#ds-consent-modal button');
        sleep(3);
        $this->exts->moveToElementAndClick('#personalOfferModal button.btn--submit');
        sleep(3);
        $this->cookieConsent();

        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists('div[ng-if*="overlayPromotions"] #ejmOverlay [ng-if="canClose"]')) {
                $this->exts->moveToElementAndClick('div[ng-if*="overlayPromotions"] #ejmOverlay [ng-if="canClose"]');
                sleep(2);
            }
        }

        if ($this->exts->exists('div#overlayId a.btn-alt')) {
            $this->exts->moveToElementAndClick('div#overlayId a.btn-alt');
            sleep(3);
        }

        if ($this->exts->exists('.notification-message-container [class*="icon-close"]')) {
            $this->exts->moveToElementAndClick('.notification-message-container [class*="icon-close"]');
            sleep(2);
        }
        $this->exts->capture("LoginSuccess");

        if ($this->exts->exists('div.simple-accord form.standard-form button[type="submit"]')) {
            if ($this->exts->exists('[formcontrolname="privacyPermissionFlagField"]')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->moveToElementAndClick('div.simple-accord form.standard-form button[type="submit"]');
                sleep(15);

                if ($this->exts->exists('app-submit-security-questions input[id*="answer"]')) {
                    $this->exts->account_not_ready();
                }
            }
        }

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log("LoginFailed " . $this->exts->getUrl());
        if ($this->exts->exists('.alert.error') && $this->exts->exists('.alert.error') && $this->exts->exists($this->username_selector)) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('form p')), 'bestätige bitte deine e-mail-adresse oder nenn uns eine andere. nur so können wir dir helfen, wenn du deine zugangsdaten vergessen hast') !== false) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {

        if ($this->exts->exists($this->username_selector)) {
            sleep(2);
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(3);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("1-pre-login-1");
            $this->checkFillRecaptcha();
            $this->checkFillRecaptcha();

            $this->exts->moveToElementAndClick($this->submit_btn);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}
private function checkFillTwoFactor()
{
    $two_factor_selector = 'form#totpWrapper input#totpcontrol';
    $two_factor_message_selector = 'p[automation-id="totpcodeTxt_tv"]';
    $two_factor_submit_selector = 'div[automation-id="SUBMITCODEBTN_btn"] button[type="submit"]';

    if ($this->exts->exists($two_factor_selector) && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->click_by_xdotool($two_factor_selector);
            $this->exts->type_text_by_xdotool($two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            $this->waitForSelectors($this->logout_btn, 5, 3);

            if ($this->exts->querySelector($two_factor_selector) == null) {
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
            $recaptcha_textareas = $this->exts->querySelectorAll($recaptcha_textarea_selector);
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
                    if(deep > 5 || found){ 
                        return;
                    }
                    console.log(prop);
                    try {
                        if(prop.indexOf(".callback") > -1){
                            result = prop; 
                            found = true; 
                            return;
                        } else { 
                            if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ 
                                return;
                            }
                            deep++;
                            for (var p in cur) { 
                                recurse(cur[p], prop ? prop + "." + p : p, deep);
                            }
                        }
                    } catch(ex) { 
                        console.log("ERROR in function: " + ex); 
                        return; 
                    }
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

private function cookieConsent()
{
    if ($this->exts->exists('#dip-consent .dip-consent-btn.red-btn, [show-overlay="true"] a[class="btn"], button[id="dip-consent-summary-accept-all"]')) {
        $this->exts->moveToElementAndClick('#dip-consent .dip-consent-btn.red-btn, [show-overlay="true"] a[class="btn"], button[id="dip-consent-summary-accept-all"]');
        sleep(3);
    }
}

private function waitForSelectors($selector, $max_attempt, $sec)
{
    for (
        $wait = 0;
        $wait < $max_attempt && $this->exts->executeSafeScript("return !!document.querySelector(\"" . $selector . "\");") != 1;
        $wait++
    ) {
        $this->exts->log('Waiting for Selectors!!!!!!');
        sleep($sec);
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
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 6; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->waitForSelectors($this->logout_btn, 10, 3);
        if ($this->exts->exists($this->logout_btn) && !$this->exts->exists($this->password_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}