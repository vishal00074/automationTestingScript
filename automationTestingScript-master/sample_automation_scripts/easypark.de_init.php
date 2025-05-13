public $baseUrl = 'https://easypark.de/history/de';

public $username_selector = 'input#phonenumber, input#userName';
public $password_selector = 'input#password';
public $submit_login_selector = 'form#signinform #submit, button#buttonLogin';

public $check_login_failed_selector = '.swal2-shown .swal2-animate-error-icon';
public $check_login_success_selector = 'li#menu-item-signout, a.logout-button, a[href*="/logout"], a.APICA-TEST-USER-AVATAR, .APICA-TEST-SIGNOUT';

public $isNoInvoice = true;

public $common_invoice_url = 'https://easypark.de/history/de';
public $admin_user_specific_url = 'https://easypark.de/business/admin/billing/de';
public $restrictPages = 3;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

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
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');

        $this->exts->moveToElementAndClick('a[href*="/auth"]');
        sleep(5);
        $login_tab = $this->exts->findTabMatchedUrl(['/auth']);
        $this->exts->switchToTab($login_tab);
        // check if account must logout
        if ($this->exts->getElement($this->password_selector) == null) {
            if ($this->exts->exists('p') && strpos(strtolower($this->exts->extract('p', null, 'innerText')), 'angemeldet als') !== false) {
                if ($this->exts->exists('button') && strpos(strtolower($this->exts->extract('button', null, 'innerText')), 'abmelden') !== false) {
                    $this->exts->log('------Click Abmelden button---');
                    $this->exts->moveToElementAndClick('button');
                    sleep(10);
                }
            }
        }
        $this->exts->waitTillPresent($this->password_selector);
        $this->checkFillLogin();
        sleep(20);

        if ($this->exts->exists('button[data-testid="send-sms-button"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="send-sms-button"]');
            sleep(10);
        } elseif ($this->exts->exists('button[data-testid="send-email-button"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="send-email-button"]');
            sleep(10);
        }

        $this->checkFillTwoFactor();
    }


    // then check user logged in or not
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
        $mes = strtolower($this->exts->extract('form#signinform div.MuiTypography-alignLeft, p#password-helper-text', null, 'innerText'));
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else if (stripos($mes, 'passwor') !== false || strpos($mes, 'wrong username or password') !== false || strpos($mes, 'wrong phone number or password') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->password_selector) != null) {
        $this->exts->capture("2-login-page");
        $temp_username = str_replace('+', '', $this->username);
        //check username is phonenumber. if username is not a phonenumber, click login with username
        if ($this->exts->exists('button[value="phone"][aria-pressed="true"]') && !is_numeric($temp_username)) {
            $this->exts->moveToElementAndClick('button[value="username"][aria-pressed="false"]');
            sleep(10);
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log("Enter Username");
            // $this->exts->moveToElementAndType($this->username_selector, $this->username);
            $this->exts->click_by_xdotool($this->username_selector);
            sleep(1);
            if (strpos($this->username, '+') !== false) {
                $this->exts->type_key_by_xdotool('BackSpace');
                sleep(1);
                $this->exts->type_key_by_xdotool('BackSpace');
                sleep(1);
                $this->exts->type_key_by_xdotool('BackSpace');
                sleep(1);
            }
            $this->exts->type_text_by_xdotool($this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    sleep(2);
    $two_factor_selector = 'form[name="sendTokenForm"] input[name="otp"],form[name="sendTokenForm"] input[inputmode="numeric"], input[name="otp-input"]';
    $two_factor_message_selector = 'form[name="sendTokenForm"] p, p[class*="MuiTypography-body"]';
    $two_factor_submit_selector = 'form[name="sendTokenForm"] button[type="submit"]';
    $two_factor_resend_selector = '';

    if ($this->exts->getElement($two_factor_selector) != null) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

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
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            // $resultCodes = str_split($two_factor_code);
            // $code_inputs = $this->exts->getElements($two_factor_selector);
            // foreach ($code_inputs as $key => $code_input) {
            //     if(array_key_exists($key, $resultCodes)){
            //         $this->exts->log('"checkFillTwoFactor: Entering key '. $resultCodes[$key] . 'to input #');
            //         $code_input->sendKeys($resultCodes[$key]);
            //     } else {
            //         $this->exts->log('"checkFillTwoFactor: Have no char for input #');
            //     }
            // }
            //$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            $this->exts->click_by_xdotool($two_factor_selector);
            sleep(1);
            $this->exts->type_text_by_xdotool($two_factor_code);
            sleep(1);
            if ($this->exts->exists($two_factor_resend_selector)) {
                $this->exts->moveToElementAndClick($two_factor_resend_selector);
                sleep(1);
            }
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