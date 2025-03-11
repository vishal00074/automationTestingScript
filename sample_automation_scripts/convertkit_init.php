public $baseUrl = 'https://app.convertkit.com/';
public $loginUrl = 'https://app.convertkit.com/';
public $invoicePageUrl = 'https://app.convertkit.com/';

public $username_selector = 'form#new_user input[name="user[email]"]';
public $password_selector = 'form#new_user input[name="user[password]"]';
public $remember_me_selector = 'form#new_user input#user_remember_me';
public $submit_login_selector = 'button#user_log_in, form#new_user button[type="submit"]';

public $check_login_failed_selector = 'div.alert:not([style*="display: none"])';
public $check_login_success_selector = 'a[href="/users/logout"]';

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
    // $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(5);
        $this->checkFillLogin();
        sleep(10);

        $this->checkFillTwoFactor();
        sleep(10);
    }

    if ($this->exts->exists('div[data-account*="openInvoices"] form')) {
        $this->exts->account_not_ready();
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
 
            $this->exts->triggerLoginSuccess();
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
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
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(5);
        }

        $isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Failed to Login");');
        $this->exts->log("isErrorMessage: ". $isErrorMessage);
        if($isErrorMessage){
            $this->exts->capture("login-failed-confirm-1");
            $this->exts->loginFailure(1);
        }

        if(strpos(strtolower($this->exts->waitTillPresent('div.Toaster__message')), 'wait done') !== false){
            $this->exts->capture("login-failed-confirm");
            $this->exts->loginFailure(1);
        }

        

    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'form#devise_authy input#token, input#token_input';
    $two_factor_message_selector = 'form#devise_authy p.auth-box__content__intro';
    $two_factor_submit_selector = 'form#devise_authy input[name="commit"], input#submit_button';

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

            if ($this->exts->getElement($two_factor_selector) == null) {
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
    } else if (
        strpos(strtolower($this->exts->extract('div[data-page="users/unknown-device"]')), "we don't recognize this device") !== false
    ) {
        $this->exts->capture("2-check-fill-2fa");
        $this->exts->log("Sending 2FA request to ask user click on confirm link");
        $message = trim($this->exts->extract('div[data-page="users/unknown-device"]'));
        $this->exts->two_factor_notif_msg_en = $message . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = $message . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        sleep(5);
        $this->exts->capture("2-after-2fa");
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("User clicked on confirm link");
            $this->checkFillLogin();
            sleep(5);
        }
    }
}


