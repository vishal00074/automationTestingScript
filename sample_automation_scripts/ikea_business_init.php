public $baseUrl = "https://biz.ikea.com/de/de/profile/transactions#";
public $loginUrl = "https://www.ikea.com/de/de/profile/login";
public $homePageUrl = "https://www.ikea.com/de/de/purchases";
public $username_selector = 'form input#username';
public $password_selector = 'form input#password';
public $submit_button_selector = 'button[name="login"], form button[type="submit"]';
public $check_login_success_selector = 'a[href*="/profile/login/"][data-tracking-label="profile"][class*="header__profile-link__neutral"]:not(.hnf-header__profile-link--hidden)';
public $check_login_fail_selector = 'div.toast--show div.toast__body';
public $login_tryout = 0;
public $restrictPages = 3;
public $isNoInvoice = true;
public $tmpFlag = 0;
public $moreBtn = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

    $this->exts->openUrl($this->baseUrl);
    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        $this->exts->openUrl($this->homePageUrl);
        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
        }
    }
    // for  testing 
    $isCookieLoginSuccess = false;


    for ($wait = 0; $wait < 4 && $this->exts->executeSafeScript('return !!document.querySelector("a[href*=\\"/#/login\\"]");') != 1; $wait++) {
        $this->exts->log('Waiting for login.....');
        sleep(10);
    }

    if ($this->isExists('a[href*="/#/login"]')) {
        $this->exts->moveToElementAndClick('a[href*="/#/login"]');
    }

    $this->waitFor('form a[href="#"]', 5);
    if ($this->isExists('form a[href="#"]')) {
        $this->exts->click_element('form a[href="#"]');
    }

    sleep(5);
    if (!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");

        $this->fillForm(0);

        $this->waitFor('#onetrust-accept-btn-handler', 7);
        if ($this->isExists('#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
        }

        $err_msg = $this->exts->extract('div.loading-spinner .error-text');

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
        } elseif (
            ($err_msg != "" && $err_msg != null && $this->isExists('div.loading-spinner .error-text'))
            || $this->isExists($this->check_login_fail_selector)
        ) {
            $this->exts->log($err_msg);
            $this->exts->loginFailure(1);
        } elseif ($this->isExists('form[name*="verifyPhone"]')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    } else {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    }
}

private function fillForm($count)
{
    $this->waitFor($this->username_selector, 10);

    if ($this->isExists($this->username_selector)) {
        $this->exts->capture("2-login-page");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        if (!$this->isExists($this->password_selector)) {
            //click "Sign in with your password"
            $this->exts->moveToElementAndClick('button[type="submit"]');
            $this->waitFor("div.toast--show div.toast__body", 50);
            if ($this->isExists("div.toast--show div.toast__body")) {
                $this->exts->loginFailure(1);
            }
        }

        sleep(7);
        if ($this->isExists('form[name="OTPVerification"]')) {
            $this->checkFillTwoFactor();
        }

        $this->waitFor($this->password_selector, 10);
        if ($this->isExists($this->password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);
            $this->exts->capture("2-login-page-filled");
            if ($this->waitFor($this->check_login_fail_selector, 10)) {
                $this->exts->log("Login Failure : " . $this->exts->extract($this->check_login_fail_selector));
                if (
                    strpos(strtolower($this->exts->extract($this->check_login_fail_selector)), 'passwor') !== false ||
                    strpos(strtolower($this->exts->extract($this->check_login_fail_selector)), 'wir haben dein konto aufgrund zu vieler fehlgeschlagener anmeldeversuche gesperrt') !== false ||
                    strpos(strtolower($this->exts->extract($this->check_login_fail_selector)), 'anmeldung scheint nicht zu') !== false ||
                    strpos(strtolower($this->exts->extract($this->check_login_fail_selector)), 'unser system hat deine aktivität leider als bot-aktion gekennzeichnet.') !== false

                ) {
                    $this->exts->loginFailure(1);
                }
            }
        }

        if ($this->isExists($this->submit_button_selector)) {
            $this->exts->moveToElementAndClick($this->submit_button_selector);

            for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('div.toast--show div.toast__body');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(2);
            }
            // $this->waitFor("div.toast--show div.toast__body",50);
            if ($this->isExists("div.toast--show div.toast__body")) {
                $this->exts->loginFailure(1);
            }
        }

        sleep(10);
        if ($this->isExists('form[name="OTPVerification"]')) {
            $this->checkFillTwoFactor();
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}


private function checkFillTwoFactor()
{
    $two_factor_selector = 'form[name="OTPVerification"] input';
    $two_factor_message_selector = 'form[name="OTPVerification"] .form-field__content .form-field__message';
    $two_factor_submit_selector = 'form[name="OTPVerification"] button';

    if ($this->exts->getElement($two_factor_selector) != null) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            $total_message_selectors = count($this->exts->getElements($two_factor_message_selector));
            for ($i = 0; $i < $total_message_selectors; $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        // clear input
        $this->exts->click_by_xdotool($two_factor_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");

        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code. " . $two_factor_code);
            // $this->exts->moveToElementAndClick($two_factor_selector);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            $this->exts->type_key_by_xdotool("Tab");

            if ($this->isExists('input#trust_device_checkbox, [class*="Checkbox"] label [type="checkbox"]:not(:checked)')) {
                $this->exts->moveToElementAndClick('input#trust_device_checkbox, [class*="Checkbox"] label [type="checkbox"]:not(:checked)');
            }
            // sleep(1);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(10);

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
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    $this->waitFor($this->check_login_success_selector, 10);
    try {
        if (($this->isExists('a[href*="/logout"], div#greeting button') && !$this->isExists($this->password_selector)) || $this->isExists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
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

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}
