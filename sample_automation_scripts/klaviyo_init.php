public $loginUrl = 'https://www.klaviyo.com/login';
public $invoicePageUrl = 'https://www.klaviyo.com/account';

public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $submit_login_selector = 'button[type="submit"]:not([disabled])';

public $check_login_failed_selector = 'div[data-testid="banner-error"]';
public $check_login_success_selector = 'a[href*="/logout"], .nav-primary a[href="/dashboard"], div[class*="UserDisplay"]';
public $isNoInvoice = true;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->loginUrl);
    $this->exts->loadCookiesFromFile();
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);

        $this->fillForm(0);

        $this->checkFillTwoFactor();
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->exts->success();
        sleep(2);

        if (!empty($this->exts->config_array['allow_login_success_request'])) {

            $this->exts->triggerLoginSuccess();
        }
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'login konnte') !== false  || stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'invalid') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}





function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 5);

    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->checkFillRecaptcha();

            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/enterprise"]';
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

function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        sleep(10);
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

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[data-testid="verification-code"], input[name="mfa_code"]';
    $two_factor_message_selector = 'p.subtitle, span[class*="Tooltipstyles"] p';
    $two_factor_submit_selector = 'button[type="submit"].btn-primary, button[type="submit"].submit-button, button[title="Log in"], button#login';
    $this->exts->waitTillPresent($two_factor_selector, 10);
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
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
            sleep(2);
            $this->exts->type_text_by_xdotool($two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);
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