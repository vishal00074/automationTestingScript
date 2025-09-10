public $baseUrl = 'https://a.simplemdm.com';
public $loginUrl = 'https://a.simplemdm.com/admin/auth/sign_in';
public $invoicePageUrl = 'https://a.simplemdm.com/admin/billing';

public $username_selector = 'input#user_email';
public $password_selector = 'input#user_password';
public $remember_me_selector = '';
public $submit_login_selector = 'input[type="submit"]';

public $check_login_failed_selector = 'input#user_password, data-react-props="{&quot;alert&quot;:&quot;Invalid email or password.&quot;}"';
public $check_login_success_selector = 'a[href*="sign_out"]';

public $isNoInvoice = true;
public $err_msg = '';
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->clearCookies();
    $this->exts->log('Begin initPortal ' . $count);
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    $this->checkFillTwoFactor();
    $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->checkFillLogin();
        sleep(10);
        $this->checkFillTwoFactor();
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    }
    if ($this->exts->exists('a[href="/admin/account/index"]')) {
        $this->exts->moveToElementAndClick('a[href="/admin/account/index"]');
        sleep(3);
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(10);
    }
    // then check user logged in or not
    if ($this->exts->getElement($this->check_login_success_selector) != null && !$this->exts->exists('form[action="/admin/auth/two_factor_authentication"] input#code')) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->err_msg != null && stripos($this->err_msg, 'Invalid email or password.') !== false) {
            $this->exts->log('Error message: ' . $this->err_msg);
            $this->exts->loginFailure(1);
        } else if ($this->exts->queryXpath(".//h1[contains(normalize-space(.),'Create a new account')]")) {
            $this->exts->account_not_ready();
        } else if ($this->exts->exists('[href="/admin/two_factor/enable"]') != null && $this->exts->urlContains('https://a.simplemdm.com/admin/two_factor')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->password_selector) != null) {
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(1);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(1);
        $this->err_msg = $this->exts->extract('.flash .alert-warning', null, 'innerText');
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'form[action="/admin/auth/two_factor_authentication"] input#code';
    $two_factor_message_selector = 'div.signed-out-container  div > p';
    $two_factor_submit_selector = 'form[action="/admin/auth/two_factor_authentication"] input[name="commit"]';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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
            sleep(1);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(5);

            if ($this->exts->getElement($two_factor_selector) == null) {
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