public $baseUrl = 'https://partnernet.amazon.de/';
public $loginUrl = 'https://partnernet.amazon.de/';
public $invoicePageUrl = '';

public $username_selector = 'input[id="ap_email"]';
public $password_selector = 'input[id="ap_password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'input[id="continue"],input[id="signInSubmit"]';

public $check_login_failed_selector = 'div[id="auth-error-message-box"] h4[class="a-alert-heading"]';
public $check_login_success_selector = '';

public $isNoInvoice = true;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.  a[href*="SignIn"]

    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);

        $this->waitFor('a[href="/login"]');
        if ($this->exts->querySelector('a[href="/login"]') != null) {
            $this->exts->moveToElementAndClick('a[href="/login"]');
            sleep(1);
        }

        if ($this->exts->querySelector('a#ap_switch_account_link') != null) {
            $this->exts->moveToElementAndClick('a#ap_switch_account_link');
            sleep(1);
        }

        $this->fillForm(0);
    }

    sleep(5);
    $this->checkFillTwoFactor();

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $alertBox = $this->exts->extract('div.a-alert-content');
        $this->exts->log("Message:: " . $alertBox);
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else if (stripos(strtolower($alertBox), strtolower('nicht mit einem Amazon-Partnerkonto verbunden')) !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->waitFor($this->username_selector, 5);
    $this->waitFor($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }

            sleep(5);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }

            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
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

private function waitFor($selector, $seconds = 30)
{
    for ($i = 1; $i <= $seconds && $this->exts->querySelector($selector) == null; $i++) {
        $this->exts->log('Waiting for Selector (' . $i . '): ' . $selector);
        sleep(1);
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[id="input-box-otp"], input#auth-mfa-otpcode';
    $two_factor_message_selector = 'div[id="channelDetailsForOtp"], form#auth-mfa-form p';
    $two_factor_submit_selector = 'span[id="cvf-submit-otp-button"], input#auth-signin-button';

    $this->exts->waitTillPresent($two_factor_selector, 20);
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
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
            sleep(1);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->exts->exists($two_factor_submit_selector)) {
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
            }

            sleep(10);

            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->notification_uid = '';
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