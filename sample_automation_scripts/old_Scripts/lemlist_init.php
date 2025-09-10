public $baseUrl = 'https://app.lemlist.com/campaigns/';
public $invoicePageUrl = 'https://app.lemlist.com/settings/billing/invoices';

public $username_selector = '.login-page input[type="email"], input[name="email"]';
public $password_selector = '.login-page input[type="password"]';
public $submit_login_selector = '.login-page button[data-test="signin-button"]';

public $check_login_success_selector = 'span.myaccount-logout button.js-logout, span.my-account-logout, div[data-test="logout"], div[data-test="user-menu-dropdown"]';

public $isNoInvoice = true;
public $errorMessage = '';

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page-before-loadcookies');
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');
    if ($this->exts->getElement('div.user .avatar') != null) {
        $this->exts->moveToElementAndClick('div.user .avatar');
        sleep(5);
    }
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        $this->checkFillLogin();
        sleep(15);
        $this->checkFillTwoFactor();
        sleep(10);
        if ($this->exts->getElement('div.user .avatar') != null) {
            $this->exts->moveToElementAndClick('div.user .avatar');
            sleep(5);
        }
    }

    if ($this->exts->getElement('div.onboarding-container') != null) {
        $this->exts->log('Account Not Ready!!');
        $this->exts->account_not_ready();
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
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if (stripos($this->errorMessage, 'Incorrect login') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin()
{
    if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick('form button[class*="validate-email"]');
        sleep(3);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);
        $this->exts->capture("2-login-page-filled");
        if ($this->exts->getElement($this->submit_login_selector) != null) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(2);
        }

        if ($this->exts->getElement('[role="alert"] .noty_body') != null) {
            $this->errorMessage = $this->exts->extract('[role="alert"] .noty_body', null, 'innerText');
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function checkFillTwoFactor()
{
    $this->exts->capture("2-2fa-checking");

    if ($this->exts->getElement('div.login-form input[type="text"]') != null && $this->exts->urlContains('/campaigns/')) {
        $two_factor_selector = 'div.login-form input[type="text"]';
        $two_factor_message_selector = 'div.login-form div.ui-group-first';
        $two_factor_submit_selector = 'div.login-form button[type="submit"]';
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(10);

            if ($this->exts->getElement($two_factor_selector) == null) {
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
    if ($this->exts->getElement('.js-confirmation-code.js-confirm-2fa') != null) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        $two_factor_selector = '.js-confirmation-code.js-confirm-2fa';
        $two_factor_message_selector = '.text-light';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $resultCodes = str_split($two_factor_code);
            $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
            foreach ($code_inputs as $key => $code_input) {
                if (array_key_exists($key, $resultCodes)) {
                    $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                    $this->exts->moveToElementAndType($two_factor_selector . ':nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                    // $code_input->sendKeys($resultCodes[$key]);
                } else {
                    $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                }
            }
            $this->waitFor('div#noty_layout__bottomRight div.noty_bar');
            if (stripos(strtolower($this->exts->extract('div#noty_layout__bottomRight div.noty_bar')), 'Incorrect login') !== false) {
                $this->exts->loginFailure(1);
                sleep(5);
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