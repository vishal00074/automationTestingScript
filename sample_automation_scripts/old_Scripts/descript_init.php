public $baseUrl = 'https://web.descript.com/invoices';
public $loginUrl = 'https://www.descript.com/';
public $invoicePageUrl = 'https://web.descript.com/invoices';
public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[name="submit"]';
public $check_login_failed_selector = 'div.auth0-global-message-error';
public $check_login_success_selector = 'button[aria-label="Open Account Menu"]';
public $isNoInvoice = true;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(3);
        $this->waitFor('a[data-click-event="www_sign_in_clicked"]', 5);
        $signInClicked = $this->exts->getElement('a[data-click-event="www_sign_in_clicked"]');
        if ($signInClicked != null) {
            $this->exts->execute_javascript("arguments[0].click();", [$signInClicked]);
        }
        sleep(15);
        for ($i = 0; $i < 3; $i++) {
            $this->exts->processCaptcha('.auth0-lock-captcha .auth0-lock-captcha-image', '.auth0-lock-input-captcha input');
        }
        $this->fillForm(0);
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

        $twoFAError = strtolower($this->exts->extract('div[color="error"]'));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $twoFAError);


        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else if (stripos($twoFAError, strtolower('Invalid passcode, please try again')) !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->waitFor($this->username_selector, 10);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(10);
            $this->exts->click_element('button[type="submit"]');
            sleep(10);
            $this->checkFillTwoFactor();

            sleep(5);
            $this->exts->extract('div[color="error"]');

            $twoFAError = strtolower($this->exts->extract('div[color="error"]'));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $twoFAError);
            if (stripos($twoFAError, strtolower('Invalid passcode, please try again')) !== false) {
                $this->exts->moveToElementAndClick('div[color="secondary"] button:not(:disabled)');
                sleep(7);
                $this->checkFillTwoFactor();
            }

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
                $this->exts->click_by_xdotool($this->submit_login_selector);
            }
            sleep(5);
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

/**

    * Method to Check where user is logged in or not

    * return boolean true/false

    */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->waitFor($this->check_login_success_selector, 10);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (TypeError $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
        sleep(10);
        $this->waitFor($this->check_login_success_selector, 10);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    }
    return $isLoggedIn;
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[autocomplete="one-time-code"]';
    $two_factor_message_selector = 'div[size="body"]';
    $two_factor_submit_selector = 'button[type="submit"]';
    $this->waitFor($two_factor_selector, 10);
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


            // $this->exts->click_by_xdotool($two_factor_submit_selector);
            // sleep(15);
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

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}