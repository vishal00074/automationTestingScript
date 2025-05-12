public $baseUrl = 'https://www.weclapp.com/en/login/';
public $username_selector = 'form#loginform input#user_login';
public $password_selector = 'form#loginform input#user_pass';
public $remember_me_selector = 'form#loginform label[for="user_keep_login"]';
public $submit_login_selector = 'form#loginform button[type="submit"]';

public $check_login_failed_selector = '.login-outer-block .error-panel span, .login-block .error-panel span,  form#loginform span.text-weclapp-black';
public $check_login_success_selector = '#headerMyMenu a[id="naviForm:globalLogoutAction"], a[href*="home"], form [id*="selectAccountPanel"]';

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
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(4);
        $this->checkFillLogin();
        sleep(5);
        $this->checkFillTwoFactor();
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

        $isTwoFAFailed = $this->exts->extract('span[id*="loginTwoFactorAuthenticatorPG"] span.rich-messages-label');

        $this->exts->log(__FUNCTION__ . 'isTwoFAFailed ' . $isTwoFAFailed);

        if ($this->exts->exists('div[class="error-panel"][style="display: block;"]')) {

            $err_msg1 = $this->exts->extract('div[class="error-panel"][style="display: block;"]');
            $lowercase_err_msg = strtolower($err_msg1);
            $substrings = array('e-mail or password wrong', 'wrong', 'e-mail or password', 'Login or Password wrong');
            foreach ($substrings as $substring) {
                if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                    $this->exts->log($err_msg1);
                    $this->exts->loginFailure(1);
                    break;
                }
            }
        }

        if ($this->exts->getElement($this->check_login_failed_selector) != null && strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->getElement($this->check_login_failed_selector) != null && strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'bitte anmeldedaten') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos($isTwoFAFailed, strtolower('The entered 2FA code is incorrect')) !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector);
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
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
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
    try {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
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

    $two_factor_selector = 'input[id="loginForm:twoFactorAuthenticatorCode"]';
    $two_factor_message_selector = 'form#loginForm span[id="loginForm:softTokenRequired"]';
    $two_factor_submit_selector = 'form#loginForm input[name="loginForm:loginButton"]';
    $this->exts->waitTillPresent($two_factor_selector);

    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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
            sleep(5);

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
