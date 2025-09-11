public $baseUrl = 'https://dashboard.clicksend.com/';
public $loginUrl = 'https://dashboard.clicksend.com/login?';
public $invoicePageUrl = 'https://dashboard.clicksend.com/account/billing-recharge/transactions';
public $username_selector = 'input[name="username"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_btn = 'button[type="submit"]';
public $checkLoginFailedSelector = '';
public $checkLoggedinSelector = 'a[ng-click*="logout"], a.avatar-online';
public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    $this->exts->capture("Home-page-without-cookie");

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(2);

    $this->exts->openUrl($this->baseUrl);
    // after load cookies and open base url, check if user logged in
    // Wait for selector that make sure user logged in
    sleep(7);
    $this->waitFor($this->checkLoggedinSelector, 5);
    if ($this->exts->querySelector($this->checkLoggedinSelector) != null) {
        // If user has logged in via cookies, call waitForLogin
        $this->exts->log('Logged in from initPortal');
        $this->exts->capture('0-init-portal-loggedin');
        $this->waitForLogin();
        sleep(10);
    } else {
        // If user hase not logged in, open the login url and wait for login form
        $this->exts->log('NOT logged in from initPortal');
        $this->exts->capture('0-init-portal-not-loggedin');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->waitForLoginPage();
        sleep(10);
    }
}

private function waitForLoginPage($count = 1)
{
    sleep(5);
    $this->exts->waitTillPresent($this->username_selector, 15);
    if ($this->exts->querySelector($this->username_selector) != null) {
        $this->exts->capture("1-pre-login");

        sleep(10);
        $this->exts->log("Enter Username");
        $this->exts->click_by_xdotool($this->username_selector);
        sleep(2);
        $this->exts->type_text_by_xdotool($this->username);
        sleep(10);

        $this->exts->log("Enter Password");
        $this->exts->click_by_xdotool($this->password_selector);
        sleep(2);
        $this->exts->type_text_by_xdotool($this->password);
        sleep(10);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(10);

        $this->exts->capture("1-filled-login");
        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(10);
        $this->waitForLogin();
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
        $this->exts->loginFailure();
    }
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'div.verification-code .code-box';
    $two_factor_message_selector = '//p[contains(text(),"code to mobile number ending")]';
    // $two_factor_submit_selector = 'form[action="/admin/auth/two_factor_authentication"] input[name="commit"]';

    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
            $this->exts->two_factor_notif_msg_en = "";

            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[0]->getText();

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
            sleep(2);
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            // $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

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

private function waitForLogin($count = 1)
{
    sleep(15);
    $this->checkFillTwoFactor();
    $this->waitFor($this->checkLoggedinSelector, 5);
    if ($this->exts->querySelector($this->checkLoggedinSelector) != null) {
        sleep(3);
        $this->exts->log('User logged in.');
        $this->exts->capture("2-post-login");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $logged_in_failed_selector = $this->exts->getElementByText('p.text-danger', ['Username or password is incorrect.', 'Benutzername oder Passwort ist falsch.'], null, false);
        if ($logged_in_failed_selector != null) {
            $this->exts->loginFailure(1);
        } elseif ($this->exts->urlContains('signup/select-product')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}