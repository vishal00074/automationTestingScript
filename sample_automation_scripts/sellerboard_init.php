public $baseUrl = "https://app.sellerboard.com/en/dashboard/";
public $loginUrl = "https://app.sellerboard.com/en/auth/login";
public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $submit_button_selector = 'button.btn-lg';
public $login_tryout = 0;
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
    sleep(10);
    $this->exts->capture("Home-page-without-cookie");
    $this->exts->loadCookiesFromFile();
    if (!$this->checkLogin()) {
        sleep(10);
        $this->fillForm(0);
        sleep(12);
        $this->checkFillTwoFactor();
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else if ($this->exts->getElementByText('.login-container.restart .panel-heading', ['account deactivated', 'Konto deaktiviert'], null, false) != null) {
        $this->exts->log("account not ready");
        $this->exts->account_not_ready();
    } else if (strpos(strtolower($this->exts->extract('div.login-form')), 'login error') !== false) {
        $this->exts->capture("LoginFailed");
        $this->exts->loginFailure(1);
    } else if (strpos(strtolower($this->exts->extract('#tooManyLoginAttemptsErrorModal p')), 'your account access has been blocked') !== false) {
        $this->exts->capture("LoginFailed");
        $this->exts->loginFailure(1);
    } elseif ($this->exts->getElementByText('div.login-container span', ['Error 500'], null, false) != null) {
        $this->exts->account_not_ready();
    } else {
        $this->exts->capture("LoginFailed");
        $this->exts->loginFailure();
    }
}

private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        sleep(1);
        if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
            sleep(1);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->capture("2-login-page-filled");

            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(7);

            $error_text = strtolower($this->exts->extract('div.form-group span.text-wrap'));
            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('Login error')) !== false) {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->capture("2-login-page-not-found");
        }

        sleep(10);
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#code';
    $two_factor_message_selector = '//input[@id="code"]/../../preceding-sibling::p';
    $two_factor_submit_selector = 'div.login-form button[type="submit"]';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector, null, 'xpath')); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getAttribute('innerText') . "\n";
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
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

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


/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent('button#closeFeaturePopup', 5);

        $this->exts->moveToElementAndClick('button#closeFeaturePopup');
        sleep(4);
        $this->exts->waitTillPresent('a[href*="/logout"], a[href*="/settings"]');
        if (count($this->exts->getElements('a[href*="/logout"], a[href*="/settings"]')) != 0) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}