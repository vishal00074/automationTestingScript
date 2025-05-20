public $baseUrl = "https://www.dell.com/identity/global/loginorregister";
public $username_selector = '#frmSignIn input[name=EmailAddress]';
public $password_selector = '#frmSignIn input[name=Password]';
public $submit_btn = '#frmSignIn #sign-in-button';
public $logout_btn = 'a[href*="/out/"], #user_name, a[href*="/signout"]';
public $wrong_credential_selector = '#frmSignIn #validationSummaryText';

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
    $this->exts->openUrl('https://www.dell.com');
    sleep(10);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    if ($isCookieLoaded) {
        $this->exts->capture("Home-page-with-cookie");
    } else {
        $this->exts->capture("Home-page-without-cookie");
    }

    sleep(10);
    if (!$this->checkLogin() && !$this->isWrongCredential()) {
        sleep(10);
        $this->fillForm(0);
    }

    sleep(10);
    $this->fillForm(0);
    sleep(10);

    $isCaptcha = strtolower($this->exts->extract('#frmSignIn #validationSummaryText'));

    $this->exts->log('Captcha:: ' . $isCaptcha);

    if (stripos($isCaptcha, strtolower('Bitte geben Sie die im Bild angezeigten Zeichen ein, um fortzufahren.')) !== false) {
        sleep(10);
        $this->fillForm(0);
    } else if (
        stripos($isCaptcha, strtolower('do not match the image')) !== false ||
        stripos($isCaptcha, strtolower('die eingegebenen zeichen entsprechen nicht der abbildung. bitte geben sie die unten angezeigten zeichen ein, um fortzufahren')) !== false
    ) {
        $this->exts->moveToElementAndClick('button.btn-dont-register-click-event');
        sleep(5);
        $this->fillForm(0);
    }

    sleep(10);
    if ($this->isExists('input#OTP')) {
        $this->checkFillTwoFactor();
        sleep(10);
    }

    // request again in case wrong 2fa entered
    $isTwoError = strtolower($this->exts->extract('div#validationSummaryContainer'));

    $this->exts->log('isTwoError:: ' . $isTwoError);

    if (stripos($isTwoError, strtolower('Der einmalige Bestätigungscode ist falsch')) !== false) {

        $this->exts->moveToElementAndClick('a#send-verification-email-link');
        sleep(7);
        $this->checkFillTwoFactor();
        sleep(10);
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

        $isTwoError = strtolower($this->exts->extract('div#validationSummaryContainer'));
        $this->exts->log('isTwoError:: ' . $isTwoError);

        if ($this->isWrongCredential()) {
            $this->exts->log($this->exts->extract($this->wrong_credential_selector, null));
            $this->exts->loginFailure(1);
        } else if (
            stripos($isTwoError, strtolower('Der einmalige Bestätigungscode ist falsch')) !== false ||
            stripos($isTwoError, strtolower('we are unable to match the details you entered with our records')) !== false
        ) {
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
function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {

        if ($this->isExists($this->username_selector)) {
            sleep(2);
            $this->exts->capture("1-pre-login");

            sleep(2);
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username, 5);

            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password, 5);

            $this->exts->capture("1-pre-login-1");
            $this->checkFillRecaptcha(0);

            sleep(5);
            if ($this->isExists('[id*=captcha-image]')) {
                $this->exts->processCaptcha('[id*=captcha-image]', '[name="ImageText"]');
                sleep(2);
            }

            sleep(2);
            $this->exts->moveToElementAndClick($this->submit_btn);
        } else if ($this->isExists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->isExists("textarea[name=\"g-recaptcha-response\"]")) {
            $this->checkFillRecaptcha(0);
            $this->fillForm($count + 1);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling command " . $exception->getMessage());
    }
}

public function isWrongCredential()
{
    $tag = false;
    $error_text = strtolower($this->exts->extract($this->wrong_credential_selector));

    if (stripos($error_text, strtolower('Passwort')) !== false) {
        $tag = true;
    }
    return $tag;
}

public function checkFillRecaptcha($counter)
{

    if ($this->isExists('iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]') && $this->isExists('textarea[name="g-recaptcha-response"]')) {

        if ($this->isExists("div.g-recaptcha[data-sitekey]")) {
            $data_siteKey = trim($this->exts->querySelector("div.g-recaptcha")->getAttribute("data-sitekey"));
        } else {
            $iframeUrl = $this->exts->querySelector("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]")->getAttribute("src");
            $tempArr = explode("&k=", $iframeUrl);
            $tempArr = explode("&", $tempArr[count($tempArr) - 1]);

            $data_siteKey = trim($tempArr[0]);
            $this->exts->log("iframe url  - " . $iframeUrl);
        }
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            $this->exts->log("isCaptchaSolved");
            $this->exts->execute_javascript("document.querySelector(\"#g-recaptcha-response\").value = '" . $this->exts->recaptcha_answer . "';");
            sleep(5);
            try {
                $tag = $this->exts->querySelector("[data-callback]");
                if ($tag != null && trim($tag->getAttribute("data-callback")) != "") {
                    $func =  trim($tag->getAttribute("data-callback"));
                    $this->exts->execute_javascript(
                        $func . "('" . $this->exts->recaptcha_answer . "');"
                    );
                } else {

                    $this->exts->execute_javascript(
                        "var a = ___grecaptcha_cfg.clients[0]; for(var p1 in a ) {for(var p2 in a[p1]) { for (var p3 in a[p1][p2]) { if (p3 === 'callback') var f = a[p1][p2][p3]; }}}; if (f in window) f= window[f]; if (f!=undefined) f('" . $this->exts->recaptcha_answer . "');"
                    );
                }
                sleep(10);
            } catch (\Exception $exception) {
                $this->exts->log("Exception " . $exception->getMessage());
            }
        }
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#OTP';
    $two_factor_message_selector = '#validateotp-section #description i';
    $two_factor_submit_selector = 'button#configure-OTPNo-submit-button';

    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
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
            sleep(4);

            $this->exts->execute_javascript("document.getElementById('OTP').value = '" . $two_factor_code . "';");

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
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

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
public  function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->isExists($this->logout_btn) && $this->isExists($this->username_selector) == false) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
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