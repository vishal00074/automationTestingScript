public $baseUrl = 'https://ahrefs.com/account/billing/invoices';
public $loginUrl = 'https://ahrefs.com/user/login';
public $invoicePageUrl = 'https://app.ahrefs.com/account/billing/invoices';

public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = 'input[name="remember_me"]';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'div[class*="loginForm"] form > div > div:nth-child(2) > div:nth-child(2)';
public $check_login_success_selector = 'div#userMenuDropdown, body#dashboard';

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
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->exts->urlContains('app.ahrefs.com/dashboard')) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->checkFillLogin();
        sleep(5);
        $this->checkFill2FAPushNotification();
    }

    $mes =  strtolower($this->exts->extract('div h2', null, 'innerText'));
    $this->exts->waitTillPresent('a[class*="signInLink"]', 15);
    $this->exts->log($mes);
    if (strpos($mes, 'oops, this page doesn') !== false && $this->exts->exists('a[class*="signInLink"]')) {
        $this->exts->moveToElementAndClick('a[class*="signInLink"]');
    }
    $this->checkFillTwoFactor();
    $mes =  strtolower($this->exts->extract('div h2', null, 'innerText'));
    $this->exts->log($mes);
    if (strpos($mes, 'oops, this page doesn') !== false && $this->exts->exists('a[class*="signInLink"]')) {
        $this->exts->moveToElementAndClick('a[class*="signInLink"]');
        sleep(30);
    }

    if ($this->exts->urlContains('app.ahrefs.com/dashboard')) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');

        if ($this->exts->urlContains('verification-required')) {
            $this->exts->account_not_ready();
        }

        $this->exts->log($this->exts->extract($this->check_login_failed_selector));

        if (stripos($this->exts->extract($this->check_login_failed_selector), 'password') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector, 20);
    if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
        $this->exts->log('Username is not a valid email address.');
        $this->exts->loginFailure(1);
    }
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
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $this->exts->log('Çheck-Fill-TwoFactor');
    if ($this->exts->getElement('.//div[contains(text(),"To keep your account secure")]') != null) {

        $two_factor_message_selector = './/div[contains(text(),"To keep your account secure")]';
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[0]->getAttribute('innerText');
            $this->exts->two_factor_notif_msg_en = str_replace('Please click the confirmation link in the email', '', $this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' Pls copy that link then paste here';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $this->exts->notification_uid = '';
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Open url: ." . $two_factor_code);
            $this->exts->openUrl($two_factor_code);
            sleep(25);
            $this->exts->capture("after-open-url-two-factor");
        } else {
            $this->exts->log("Not received two factor code");
        }
    } else {
        $two_factor_selector = 'input[autocomplete="one-time-code"]';
        $two_factor_message_selector = '//div[contains(text(),"digit verification code")]';
        $two_factor_submit_selector = '//div[contains(text(),"Continue")]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";

                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[0]->getAttribute('innerText');

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
                $resultCodes = str_split($two_factor_code);
                $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
                foreach ($code_inputs as $key => $code_input) {

                    if (array_key_exists($key, $resultCodes)) {
                        $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                        if ($key == 3) {
                            $this->exts->moveToElementAndType('input[autocomplete="one-time-code"]:nth-child(5)', $resultCodes[3]);
                        } else {
                            if ($key > 3) {
                                $this->exts->moveToElementAndType('input[autocomplete="one-time-code"]:nth-child(' . ($key + 2) . ')', $resultCodes[$key]);
                            } else {
                                $this->exts->moveToElementAndType('input[autocomplete="one-time-code"]:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                            }
                        }

                        // $code_input->sendKeys($resultCodes[$key]);
                    } else {
                        $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                    }
                }
                sleep(2);
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.3-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->getElement($two_factor_submit_selector) != null) {
                    $this->exts->moveToElementAndClick($two_factor_submit_selector);
                    sleep(2);
                }
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
}

private function checkFill2FAPushNotification()
{
    $this->exts->log('Process-CheckFill-2FA-Notification');
    $two_factor_message_selector = 'div[id="root"] > div > div > div > div > div';
    $two_factor_submit_selector = '';
    $this->exts->waitTillPresent($two_factor_message_selector, 15);
    if ($this->exts->querySelector($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . ' Please input "OK" when finished!!';
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $two_factor_code = trim(strtolower($this->exts->fetchTwoFactorCode()));
        if (!empty($two_factor_code) && trim($two_factor_code) == 'ok') {
            $this->exts->log("checkFillTwoFactorForMobileAcc: Entering two_factor_code." . $two_factor_code);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            sleep(15);
            if ($this->exts->querySelector($two_factor_message_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->notification_uid = '';
                $this->exts->two_factor_attempts++;
                $this->checkFill2FAPushNotification();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}