public $baseUrl = 'https://app.klicktipp.com/';
public $loginUrl = 'https://app.klicktipp.com/user';
public $invoicePageUrl = 'https://app.klicktipp.com/user/me/digistore-invoice';

public $username_selector = 'form#user-login input.edit-name, input[id="username"]';
public $password_selector = 'form#user-login input.edit-pass, input[id="password"]';
public $submit_login_selector = 'form#user-login input.btn-submit, input[id="kc-login"]';

public $check_login_failed_selector = 'div.modal-messages div.alert-danger';
public $check_login_success_selector = "a[href*='/logout'],kt-customer-account";

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
    $this->exts->openUrl($this->invoicePageUrl);
    sleep(10);
    $this->exts->capture('1-init-page');
    if ($this->exts->exists('div[data-e2e-id="main-6"]')) {
        $this->exts->moveToElementAndClick('div[data-e2e-id="main-6"]');
        sleep(5);
    } else if ($this->exts->exists('div[data-e2e-id="main-5"]')) {
        $this->exts->moveToElementAndClick('div[data-e2e-id="main-5"]');
        sleep(5);
    }
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    $this->waitForSelectors($this->check_login_success_selector, 15, 2);

    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->checkFillLogin();
        $this->checkFillTwoFactor();
        if ($this->exts->exists('div[data-e2e-id="main-6"]')) {
            $this->exts->moveToElementAndClick('div[data-e2e-id="main-6"]');
            sleep(5);
        } else if ($this->exts->exists('div[data-e2e-id="main-5"]')) {
            $this->exts->moveToElementAndClick('div[data-e2e-id="main-5"]');
            sleep(5);
        }

        // some users can not load dashboard page
        $this->waitForSelectors($this->check_login_success_selector, 15, 2);
        if ($this->exts->getElement($this->check_login_success_selector) == null && !$this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->capture("user-can-not-load-dashboard-page");
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(20);
        }
    }

    $this->waitForSelectors($this->check_login_success_selector, 15, 2);

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
			$this->exts->triggerLoginSuccess();
		}

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwort wurden nicht akzeptiert') !== false) {
            $this->exts->loginFailure(1);
        } else if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'wurde nicht aktiviert oder ist gesperrt') !== false) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->waitForSelectors($this->password_selector, 10, 2);
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = "form#user-login input[name='LoginCode'], input#otp";
    $two_factor_message_selector = '.modal-login .alert, span#input-error-otp-code';
    $two_factor_submit_selector = 'form#user-login #edit-submit[name="op"], input#kc-login';

    $this->waitForSelectors($two_factor_selector, 10, 2);
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
            $this->exts->type_key_by_xdotool("Return");
            sleep(2);
            $this->exts->click_element($two_factor_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);
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


private function waitForSelectors($selector, $max_attempt, $sec)
{
    for (
        $wait = 0;
        $wait < $max_attempt && $this->exts->executeSafeScript("return !!document.querySelector(\"" . $selector . "\");") != 1;
        $wait++
    ) {
        $this->exts->log('Waiting for Selectors!!!!!!');
        sleep($sec);
    }
}

