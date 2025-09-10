public $baseUrl = 'https://shop.deutschepost.de/shop/login_page.jsp';
public $invoicePageUrl = 'https://shop.deutschepost.de/shop/kundenkonto/auftragshistorie.jsp';

public $username_selector = '#content input#username';
public $password_selector = '#content input#password';
public $remember_me_selector = '';
public $submit_login_selector = '#content button';

public $check_login_failed_selector = '.clue--error div';
public $check_login_success_selector = 'form[name="LogoutForm"]';

public $validate_form_selector = 'form[name="standardform"]';
public $security_code_selector = '#standardform input#authenticationCode';
public $confirm_code_btn = '#standardform a[onclick*="submitLoginRequestPageForm"]';

public $isNoInvoice = true;

/**
 * Entry Method thats called for a portal
 *
 * @param int $count Number of times portal is retried.
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
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->clearChrome();
        $this->exts->openUrl($this->baseUrl);
        sleep(15);

        for ($i = 0; $i < 3; $i++) {
            $msg1 = strtolower($this->exts->extract('div#main-frame-error p[jsselect="summary"]', null, 'innerText'));
            if (strpos($msg1, 'the connection was reset') !== false || strpos($msg1, 'die verbindung wurde') !== false) {
                $this->exts->refresh();
                sleep(15);
                $this->exts->capture('after-refresh-cant-be-reach-' . $i);
            } else {
                break;
            }
        }

        if ($this->exts->exists('button#accept-recommended-btn-handler')) {
            $this->exts->click_by_xdotool('button#accept-recommended-btn-handler');
            sleep(5);
        }

        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
            sleep(5);
        }


        $this->checkFillLogin($count);
        sleep(10);
        $this->exts->waitTillPresent($this->check_login_success_selector, 10);

        for ($i = 1; $i < 5; $i++) {
            if ($this->exts->getElement($this->check_login_success_selector) == null) {
                //$this->exts->clearCookies();
                $this->checkFillLogin($i);
                $this->exts->waitTillPresent($this->check_login_success_selector, 7);
            }

            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null)), 'pass') !== false) {
                $this->exts->log('Credentials are invalid.');
                $this->exts->loginFailure(1);
            } elseif (strpos(strtolower($this->exts->extract('div.clue__message', null, 'innerText')), 'der sicherheitscode konnte leider nicht validiert werden') !== false) {
                $this->exts->log('Security code could not be validated.');
                $this->exts->loginFailure(1);
            } elseif (strpos(strtolower($this->exts->extract('div.clue__message', null, 'innerText')), 'steht momentan leider nicht zur') !== false) {
                $this->exts->account_not_ready();
            }

            if ($this->exts->getElement($this->check_login_success_selector) != null) {
                break;
            }
        }
    }

    // then check user logged in or not
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture('3-login-success');

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->capture('LoginFailed_after_no_login_sucess');
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null)), 'pass') !== false) {
            $this->exts->log('Credentials are invalid.');
            $this->exts->loginFailure(1);
        } elseif (strpos(strtolower($this->exts->extract('div.clue__message', null, 'innerText')), 'der sicherheitscode konnte leider nicht validiert werden') !== false) {
            $this->exts->log('Security code could not be validated.');
            $this->exts->loginFailure(1);
        } elseif (strpos(strtolower($this->exts->extract('div.clue__message', null, 'innerText')), 'steht momentan leider nicht zur') !== false) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(1);
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

private function checkFillLogin($count = 1)
{
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture('2-login-page');
        if ($this->exts->exists('#content input[name*="captchaValue"][type="text"]')) {
            $this->exts->processCaptcha('#content img.captcha__content--image', '#content input[name*="captchaValue"][type="text"]');
        }

        $this->exts->log('Enter Username');

        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log('Enter Password');
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me_selector != '') {
            $this->exts->click_by_xdotool($this->remember_me_selector);
        }
        sleep(2);

        $this->exts->capture('2-login-page-filled-' . $count);
        $this->exts->click_by_xdotool($this->submit_login_selector);
        sleep(10);
        $this->exts->capture('submit-login-page');


        $error_text = strtolower($this->exts->extract('div.clue__message'));
        $this->exts->log('Error text:: ' . $error_text);

        if (strpos($error_text, 'fehler bei der eingabe des logins oder passwortes.') !== false) {
            $this->exts->loginFailure(1);
        }

        if (strpos($error_text, 'ihr account ist gesperrt. bitte verwenden sie unsere "passwort-vergessen" funktionalität, um ihren account wieder freizuschalten.') !== false) {
            $this->exts->loginFailure(1);
        }

        $this->checkFillTwoFactor();
        sleep(7);

        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(4);
        $this->exts->openUrl($this->baseUrl);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture('2-login-page-not-found');
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = '#standardform input#authenticationCode';
    $two_factor_message_selector = 'form#standardform p';
    $two_factor_submit_selector = 'a[onclick*="submitLoginRequestPageForm();"]';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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
            //$this->exts->getElement($two_factor_selector)->sendKeys($two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->click_by_xdotool($two_factor_submit_selector);
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