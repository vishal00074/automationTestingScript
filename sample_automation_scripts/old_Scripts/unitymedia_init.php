public $baseUrl = 'https://www.unitymedia.de/kundencenter/meine-rechnungen/alle-rechnungen/';
public $loginUrl = 'https://www.unitymedia.de/benutzerkonto/login/zugangsdaten';
public $invoicePageUrl = 'https://www.unitymedia.de/kundencenter/meine-rechnungen/alle-rechnungen/';
public $username_selector = 'form.lgi-form.lgi-oim-form input[name="userId"], input#txtUsername';
public $password_selector = 'form.lgi-form.lgi-oim-form input[name="password"], input#txtPassword';
public $remember_me_selector = '';
public $submit_login_selector = 'form.lgi-form.lgi-oim-form button.upc_button6, .login-onelogin button[type="submit"]';
public $check_login_failed_selector = 'form > div.login-onelogin p.notification-text';
public $check_login_success_selector = '.indicator[style="display: block;"], a[href="/logout"]';
public $capt_image_selector = "form ol-captcha img";
public $capt_input_selector = "input#captchaField";
public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int) $this->exts->config_array["login_with_google"] : $this->login_with_google;
    $this->login_with_apple = isset($this->exts->config_array["login_with_apple"]) ? (int) $this->exts->config_array["login_with_apple"] : $this->login_with_apple;

    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    // $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->exists('button#dip-consent-summary-accept-all')) {
            $this->exts->moveToElementAndClick('button#dip-consent-summary-accept-all');
            sleep(5);
        } else {
            $this->exts->log('Not found selector button#dip-consent-summary-accept-all');
        }
        $this->checkSolveCaptcha();
        $this->checkFillLogin();

        sleep(15);
        $this->checkSolveCaptcha();
        if ($this->exts->urlContains('cprx/captcha')) {
            $this->exts->refresh();
            sleep(10);

            $this->exts->moveToElementAndClick('a.open-overlay-my-vf');
            sleep(10);
            $this->checkFillLogin();
            sleep(15);
            $this->checkSolveCaptcha();
        }



        $this->checkFillTwoFactor();
    }

    // then check user logged in or not
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos($this->exts->extract($this->check_login_failed_selector), 'bitte Deine Eingabe') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent('form.lgi-form.lgi-oim-form button.upc_button6, .login-onelogin button[type="submit"]', 100);
    if ($this->exts->querySelector($this->password_selector) != null) {
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
    $two_factor_selector = 'input#totpcontrol';
    $two_factor_message_selector = 'p[automation-id="totpcodeTxt_tv"]';
    $two_factor_submit_selector = '[automation-id="SUBMITCODEBTN_btn"] button.login-btn[type="submit"]';

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
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

public function checkSolveCaptcha()
{
    for ($i = 0; $i < 30 && $this->exts->querySelector('form[action*="/captcha"] input#captchaField') != null && $this->exts->urlContains('/captcha');  $i++) {
        $this->exts->processCaptcha('form[action*="/captcha"] img.captcha', 'form[action*="/captcha"] input#captchaField');
        $this->exts->capture('captcha-filled');
        $this->exts->moveToElementAndClick('form[action*="/captcha"] [type="submit"]');
        sleep(10);
    }
}