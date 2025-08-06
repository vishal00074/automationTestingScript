public $baseUrl = 'https://directory.swile.co/signin';
public $loginUrl = 'https://directory.swile.co/signin';
public $username_selector = 'input#email, input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';
public $check_login_success_selector = 'ul.UserMenu__Menu, ul.MuiList-root, a[href="/profile"], a[href="/maps"], a[href*="/invoices"],div[data-intercom-target="TeamMySwileLayoutUserMenu"]';
public $check_login_failed_selector = 'div[data-testid="signinErrorLabel"]';

public $isNoInvoice = true;
public $totalInvoices = 0;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(5);
    $this->exts->capture_by_chromedevtool('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->exts->exists($this->check_login_success_selector)) {
        $this->exts->log('NOT logged via cookie');
        //$this->exts->webdriver->get($this->loginUrl);
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if (strpos(strtolower($this->exts->extract("h1")), '504 error') !== false) {
            $this->exts->refresh();
            sleep(15);
        }
        if (strpos(strtolower($this->exts->extract("h1")), '504 error') !== false) {
            $this->exts->loginFailure(1);
        }
        $this->checkFillLogin();
        sleep(20);
        for ($i = 0; $i < 5 && $this->exts->getElement('//span[contains(text(), "Everything did not go as planned")]', null, 'xpath') != null; $i++) {
            $this->checkFillLogin();
            sleep(20);
        }
        $this->checkFillTwoFactor();
        //agree cook
        if ($this->exts->exists('div#axeptio_overlay button.ButtonGroup__BtnStyle-sc-1usw1pe-0.iVYBhe')) {
            $this->exts->moveToElementAndClick('div.MuiGrid-justify-xs-flex-end .MuiBadge-root');
            sleep(3);
        }
    }

    //Remove block start
    if ($this->exts->exists('div[data-overlay-container] button:not([color="#ffffff"])')) {
        $this->exts->moveToElementAndClick('div[data-overlay-container] button:not([color="#ffffff"])');
        sleep(5);
    }

    if ($this->exts->exists('div[id="root"]>div:first-child>button')) {
        $this->exts->moveToElementAndClick('div[id="root"]>div:first-child>button');
        sleep(5);
    }
    //Remove block close


    if (!$this->exts->exists($this->check_login_success_selector)) {
        $this->exts->moveToElementAndClick('div[data-intercom-target="TeamMySwileLayoutUserMenu"]');
        sleep(5);
    }



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
        if ($this->exts->exists('form[name="sign-in-form"]')) {
            $form = $this->exts->getElement('form[name="sign-in-form"]');
            if ($this->exts->getElementByText('span', 'password is incorrect', $form, false) != null) {
                $this->exts->loginFailure(1);
            }
        }

        $err_msg1 = $this->exts->extract('div[class="sc-gsTDqH gStKln"]');
        $lowercase_err_msg = strtolower($err_msg1);
        $substrings = array('password is incorrect', 'try again', 'incorrect');
        foreach ($substrings as $substring) {
            if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                $this->exts->log($err_msg1);
                $this->exts->loginFailure(1);
                break;
            }
        }

        if ($this->exts->getElementByText('span', 'password is incorrect', null, false) != null) {
            $this->exts->loginFailure(1);
        }
        if (strpos(strtolower($this->exts->extract('form[method="POST"]')), 'de passe est incorrect') !== false || strpos(strtolower($this->exts->extract('form[method="POST"]')), 'your password is incorrect') !== false) {
            $this->exts->loginFailure(1);
        } elseif (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false || stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'code is incorrect') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->exists($this->username_selector)) {
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->capture("1-username-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(6);
        $this->exts->capture("1-username-submitted");
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }

    if ($this->exts->exists($this->password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);
        $this->exts->capture("1-password-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(6);
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'form[name="sign-in-mfa-form"] input#authenticationCode, div[data-testid="authenticationCode"] input';
    $two_factor_message_selector = 'p[id="otp-description"], form[name="sign-in-mfa-form"] span';
    $two_factor_submit_selector = 'form[name="sign-in-mfa-form"] button[type=submit]';

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
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}