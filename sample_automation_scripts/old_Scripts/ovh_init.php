public $baseUrl = 'https://www.ovh.com/manager/dedicated/index.html';
public $loginUrl = 'https://www.ovh.com/auth/?action=disconnect&onsuccess=https%3A%2F%2Fwww.ovh.com%2Fmanager%2Fdedicated%2Findex.html%23%2Fbilling%2Fhistory';
public $invoicePageUrl = 'https://www.ovh.com/manager/dedicated/index.html#/billing/history';

public $username_selector = 'input#account';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_btn = 'button#login-submit';

public $checkLoginFailedSelector = 'div.error';
public $checkLoggedinSelector = 'li.logout button, button[data-translate="hub_user_logout"], button[data-navi-id="logout"], [href*="/dedicated/billing/history"]';

public $twoFactorInputSelector = 'form input#totp, form input#emailCode, form input#codeSMS, input#staticOTP';
public $submit_twofactor_btn_selector = 'form button[id*="Submit"]';

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
    $this->exts->capture("Home-page-without-cookie");

    // Load cookies
    // $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);

    if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
        // If user has logged in via cookies, call waitForLogin
        $this->exts->log('Logged in from initPortal');
        $this->exts->capture('0-init-portal-loggedin');
        $this->waitForLogin();
    } else {
        // If user hase not logged in, open the login url and wait for login form
        $this->exts->log('NOT logged in from initPortal');
        $this->exts->capture('0-init-portal-not-loggedin');
        $this->exts->clearCookies();

        $this->exts->openUrl($this->loginUrl);
        $this->waitForLoginPage();
        $check_2fa = strtolower($this->exts->extract('.mfa-container', null, 'innerText'));
        $this->exts->log($check_2fa);
        if ($this->exts->exists('button.accept')) {
            $this->exts->moveToElementAndClick('button.accept');
        }
        $check_2fa = strtolower($this->exts->extract('.mfa-container', null, 'innerText'));
        $this->exts->log($check_2fa);
        if (stripos($check_2fa, 'your security key') !== false) {
            $this->exts->moveToElementAndClick('.other-method a#other-method-link');
            sleep(15);
        }

        if ($this->exts->exists('div[data-mfa-type*="totp"]')) {
            $this->exts->moveToElementAndClick('div[data-mfa-type*="totp"]');
            sleep(7);
            $this->fillTwoFactor($this->twoFactorInputSelector, $this->submit_twofactor_btn_selector);
            sleep(15);
        } else if ($this->exts->exists('div[data-mfa-type*="sms"]')) {
            $this->exts->moveToElementAndClick('div[data-mfa-type*="sms"]');
            sleep(6);
            $this->fillTwoFactor($this->twoFactorInputSelector, $this->submit_twofactor_btn_selector);
            sleep(15);
        } else if ($this->exts->exists('div[data-mfa-type*="staticOTP"]')) {
            $this->exts->moveToElementAndClick('div[data-mfa-type*="staticOTP"]');
            sleep(6);
            $this->fillTwoFactor($this->twoFactorInputSelector, $this->submit_twofactor_btn_selector);
            sleep(15);
        } else if ($this->exts->exists($this->twoFactorInputSelector)) {
            $this->fillTwoFactor($this->twoFactorInputSelector, $this->submit_twofactor_btn_selector);
            sleep(15);
        }

        if ($this->exts->querySelector('osds-modal') != null) {
            $this->switchToFrame('osds-modal');
            sleep(2);
        }

        if ($this->exts->querySelector('osds-button[data-navi-id="cookie-accept"]') != null) {
            $this->exts->click_by_xdotool('osds-button[data-navi-id="cookie-accept"]');
            sleep(5);
        }

        $this->waitForLogin();
    }
}

private function waitForLoginPage()
{
    if ($this->exts->getElement($this->password_selector) != null) {
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        $this->exts->capture("1-filled-login");
        if ($this->exts->exists($this->submit_login_btn)) {
            $this->exts->moveToElementAndClick($this->submit_login_btn);
            sleep(5);
        }
        sleep(10);
    } else {
        $this->exts->log('Timeout waitForLoginPage');
        $this->exts->capture("LoginFailed");
        $this->exts->loginFailure();
    }
}

private function waitForLogin()
{
    $this->exts->waitTillPresent($this->checkLoggedinSelector, 20);
    if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
        $this->exts->log('User logged in.');
        $this->exts->capture("2-post-login");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log('Timeout waitForLogin');
        $this->exts->capture("LoginFailed");
        if (stripos($this->exts->extract($this->checkLoginFailedSelector, null, 'innerText'), "Invalid Account ID or password") !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


public function switchToFrame($query_string)
{
    $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
    $frame = null;
    if (is_string($query_string)) {
        $frame = $this->exts->queryElement($query_string);
    }

    if ($frame != null) {
        $frame_context = $this->exts->get_frame_excutable_context($frame);
        if ($frame_context != null) {
            $this->exts->current_context = $frame_context;
            return true;
        }
    } else {
        $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
    }

    return false;
}

private function fillTwoFactor($twoFactorInputSelector, $submit_twofactor_btn_selector)
{
    $two_factor_selector = $twoFactorInputSelector;
    $two_factor_message_selector = 'form[method="POST"] div.control-group:first-child, div#enter2FA > div[style*="text-align: left"], div.login-inputs form > div  div.control-group:first-child';
    $two_factor_submit_selector = $submit_twofactor_btn_selector;

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
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
            sleep(15);
            $this->exts->capture("after-submit-2fa");

            if ($this->exts->getElement($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
                $this->exts->capture("post-login-2fa");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
                $this->fillTwoFactor($twoFactorInputSelector, $submit_twofactor_btn_selector);
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}