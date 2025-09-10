public $baseUrl = 'https://app.sellerlogic.com/';
public $loginUrl = 'https://app.sellerlogic.com/';
public $invoicePageUrl = 'https://app.sellerlogic.com/payments/invoice?sort=-date_published&page=1&pageSize=10';
public $username_selector = 'input#username';
public $password_selector = 'form#login-form input[type="password"], form#login-form input#password';
public $remember_me_selector = '';
public $submit_login_btn = 'button#loginButton';
public $checkLoginFailedSelector = 'form#login-form input.error, div#error-password, div#error-username';
public $checkLoggedinSelector = 'span[aria-label="icnUser"], a[data-action="userLogout"], a[href="/site/logout/"], a[href*="/payments/invoice"], [class*="navLinks_userDetailsMenuItem"], [data-nested-menu-id="profile"] a[href="/userSetting"]';
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
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    // after load cookies and open base url, check if user logged in

    // Wait for selector that make sure user logged in
    sleep(10);
    if ($this->exts->querySelector($this->checkLoggedinSelector) != null) {
        // If user has logged in via cookies, call waitForLogin
        $this->exts->log('Logged in from initPortal');
        $this->exts->capture('0-init-portal-loggedin');
        $this->waitForLogin();
    } else {
        // If user hase not logged in, open the login url and wait for login form
        $this->exts->log('NOT logged in from initPortal');
        $this->exts->capture('0-init-portal-not-loggedin');
        $this->clearChrome();

        $this->exts->openUrl($this->loginUrl);
        $this->waitForLoginPage();
    }
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(3);
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
}

private function waitForLoginPage($count = 1)
{
    sleep(25);
    $this->exts->capture(__FUNCTION__);
    $this->exts->waitTillPresent($this->username_selector, 30);
    if ($this->exts->querySelector($this->password_selector) != null) {
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("1-filled-login");
        $this->exts->click_element($this->submit_login_btn);
        sleep(5);
        $this->checkFillTwoFactor();
        sleep(5);
        $this->waitForLogin($count);
    } else if ($this->exts->querySelector($this->username_selector) != null) {
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        //click next
        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(8);
        $emailRegex = "/^[^\s@]+@[^\s@]+\.[^\s@]+$/";
        $isEmailFormat = (bool)preg_match($emailRegex, $this->username);

        // Output the result
        if (!$isEmailFormat) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure(1);
        }
        $this->exts->waitTillAnyPresent($this->password_selector, 50);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("1-filled-login");
        $this->exts->waitTillAnyPresent($this->submit_login_btn, 50);
        $this->exts->moveToElementAndClick($this->submit_login_btn);

        $this->exts->waitTillPresent('div[class*="password error"]', 20);
        if ($this->exts->exists('div[class*="password error"]')) {
            $this->exts->loginFailure(1);
        }

        $this->checkFillTwoFactor();
        $this->waitForLogin($count);
    } else {
        $this->exts->log('Timeout waitForLoginPage');
        $this->exts->capture("LoginFailed");
        $this->exts->loginFailure();
    }
}

private function waitForLogin($count = 1)
{
    sleep(35);

    if ($this->exts->exists('.ant-spin-dot-spin')) {
        $this->exts->openUrl($this->baseUrl);
        sleep(20);
    }
    for ($wait_count = 1; $wait_count <= 10 && $this->exts->exists('.ant-spin-text'); $wait_count++) {
        $this->exts->log('Waiting for load page after submit login...');
        sleep(5);
    }
    $error_msg = $this->exts->extract('#error-username');
    if (strpos($error_msg, 'Ihr Benutzer ist gesperrt') !== false || strpos($error_msg, 'Your user is locked') !== false) {
        $this->exts->log('account not ready');
        $this->exts->account_not_ready();
    }

    if ($this->exts->exists(".input-container.password-with-web-auth.field-password.error")) {
        $error_login_msg = $this->exts->getElement('#error-password');
        $err_text = $this->exts->executeSafeScript('return arguments[0].textContent;', [$error_login_msg]);
        if ($err_text !== null && strpos(strtolower($err_text), 'passwor') !== false) {
            $this->exts->log('Timeout waitForLogin ' . $this->exts->getUrl());
            $this->exts->capture("LoginFailed");
            if (strpos(strtolower($err_text), 'passwor') === 0 || strpos(strtolower($err_text), 'passwor') > 0) {
                $this->exts->log($err_text);
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    $error_change_email_msg = $this->exts->extract('.change-email-modal .change-email-modal__content');
    $this->exts->log('Waiting ---------' . $error_change_email_msg);
    if ((strpos($error_change_email_msg, 'tigen Sie die E-Mail-Adresse, um die Registrierung abzuschlie') !== false || strpos($error_change_email_msg, 'and confirm the email address to finish registration') !== false) && $this->exts->exists('.change-email-modal #change-email')) {
        $this->exts->log('account not ready');
        $this->exts->account_not_ready();
    }
    $error_change_email_msg = $this->exts->extract('.change-email-modal .change-email-modal__content');
    $this->exts->log('Waiting ---------' . $error_change_email_msg);
    if ((strpos($error_change_email_msg, 'tigen Sie die E-Mail-Adresse, um die Registrierung abzuschlie') !== false || strpos($error_change_email_msg, 'and confirm the email address to finish registration') !== false) && $this->exts->exists('.change-email-modal #change-email')) {
        $this->exts->log('account not ready');
        $this->exts->account_not_ready();
    }
    $error_setup2FA_msg = $this->exts->extract('div[class*="twoFA_container"] .ant-alert');
    $this->exts->log('Waiting ---------' . $error_setup2FA_msg);
    if ((strpos($error_setup2FA_msg, 'Die globalen Einstellungen erfordern, dass Sie die Zwei-Faktor-Authentifizierung') !== false
        || strpos($error_setup2FA_msg, 'The global settings require you to enable two-factor authentication for your account') !== false)) {
        $this->exts->log('account not ready');
        $this->exts->account_not_ready();
    }


    $this->exts->capture("timeout-after-submit-login");
    $this->exts->openUrl($this->baseUrl);
    sleep(20);

    if ($this->exts->exists('[class*="privacy_confirmText"]')) {
        $this->exts->moveToElementAndClick('[class*="privacy_confirmText"]');
        sleep(2);
        $this->exts->moveToElementAndClick('[class*="privacy_confirmBtn"]');
        sleep(10);
    }
    $this->exts->moveToElementAndClick('.anticon-user');
    sleep(5);
    if ($this->exts->exists('button.main-modal-btn-ok')) {
        $this->exts->moveToElementAndClick('button.main-modal-btn-ok');
        sleep(3);
    }

    $this->exts->capture(__FUNCTION__);
    if ($this->exts->querySelector($this->checkLoggedinSelector) != null) {
        sleep(3);
        $this->exts->log('User logged in.');

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#validategoogle2facodeform-secret';
    $two_factor_message_selector = 'div.form_wrapper__two_factory_auth, form#two-factor-auth-login-form div.text-centered';
    $two_factor_submit_selector = 'button.submit.auth-btn, button[type="submit"]';

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
            $this->exts->querySelector($two_factor_selector)->clear();
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}