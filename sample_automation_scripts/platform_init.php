public $baseUrl = 'https://accounts.platform.sh/';
public $loginUrl = 'https://auth.api.platform.sh/';
public $homeUrl = 'https://accounts.platform.sh/user/orders';

public $username_selector = 'input#email_address, form input#username';
public $password_selector = 'input#password, form input#password';
public $remember_me_selector = '';
public $submit_login_btn = '';

public $checkLoginFailedSelector = '';
public $checkLoggedinSelector = 'div.profile, input#project-search-input';

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
    $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
    // Wait for selector that make sure user logged in
    sleep(10);
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
    }
}

private function waitForLoginPage($count = 1)
{
    $this->exts->moveToElementAndClick('#onetrust-accept-btn-handler');
    sleep(5);
    if ($this->exts->getElement($this->username_selector) != null) {
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $multi_languages_next = ['next', 'NÃ¤chster', 'suivant'];
        $next_button = $this->exts->getElementByText('button', $multi_languages_next);
        $this->exts->click_element($next_button);
        sleep(2);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);
        if ($this->remember_me_selector != '') {
            $this->exts->click_by_xdotool($this->remember_me_selector);
            sleep(2);
        }


        $this->exts->capture("1-filled-login");

        $multi_languages_submit = ['log in', 'Anmeldung', 's\'identifier'];
        $submit_button = $this->exts->getElementByText('button', $multi_languages_submit);
        $this->exts->click_element($submit_button);
        sleep(5);
        $this->checkFillTwoFactor();
        sleep(5);
        $this->waitForLogin();
    } else {
        if ($count < 5) {
            $count = $count + 1;
            $this->waitForLoginPage($count);
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }
}

private function waitForLogin($count = 1)
{
    sleep(5);
    if (strpos($this->exts->extract('div#fallback .with-js p'), 'disable any ad blockers and if all else fails') !== false && $this->exts->exists('div#fallback .with-js a[href="/"]')) {
        $this->exts->moveToElementAndClick('div#fallback .with-js a[href="/"]');
        sleep(10);
        $this->exts->capture("after-click-button-back");
    }


    if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
        sleep(3);
        $this->exts->log('User logged in.');
        $this->exts->capture("2-post-login");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if ($count < 5) {
            $count = $count + 1;
            $this->waitForLogin($count);
        } else {
            $this->exts->log('Timeout waitForLogin');
            $this->exts->capture("LoginFailed");
            $logged_in_failed_selector = $this->exts->getElementByText('div', ['Incorrect email address and password combination', 'Please enter a valid email address']);
            if ($logged_in_failed_selector != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
}

// 2 FA
private function checkFillTwoFactor()
{
    $two_factor_selector = 'div input[id*="fa"]';
    $two_factor_message_selector = 'div label';
    $two_factor_submit_selector = 'div button:not([width*=cal])';

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