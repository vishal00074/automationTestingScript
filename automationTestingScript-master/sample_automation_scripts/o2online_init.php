public $baseUrl = 'https://o2online.de';
public $dslLoginUrl = 'https://dsl.o2online.de/selfcare/content/segment/kundencenter/';
public $mobileLoginUrl = 'https://www.o2online.de/ecare/';
public $dsl_username_selector = 'input#username';
public $dsl_password_selector = 'form#loginFormular input[name="password"]';
public $dsl_submit_login_selector = 'form#loginFormular a[onclick*="submit"]';
public $mobile_username_selector = '#idToken4_od , input#IDToken1';
public $mobile_verification_number = 'input[data-test-id="login-uservalidation-input"]';
public $mobile_password_selector = 'input#IDToken2';
public $mobile_submit_login_selector = 'form[name="Login"] button[type="submit"]';
public $password_selector = 'one-cluster one-input[type="password"]';
public $check_login_success_selector = '.navigation-item-logged-in a[href*="auth/logout"] .glyphicon-user, a[href*="/logout"] span.logoutUser, li a[data-testid="menu-item-billing"], a[href*="/logout"]';
public $check_reset_password_selector = 'a[href*="/auth/passwordForgotten"]';
public $isNoInvoice = true;
public $usernameTemp = '';
public $user_mobile_number = '';

/**
 * Entry Method thats called for a portal
 *
 * @param int $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->usernameTemp = $this->exts->config_array['usernametemp'] ?? $this->username;
    $this->user_mobile_number = $this->exts->config_array['user_mobile_number'] ?? $this->user_mobile_number;
    $this->user_mobile_number = trim(preg_replace('/[^\d]/', '', $this->user_mobile_number));
    $this->exts->log($this->user_mobile_number);

    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        if (stripos($this->usernameTemp, 'my') !== false) {
            $this->exts->openUrl($this->dslLoginUrl);
            if (stripos($this->exts->extract('div[id="login"]'), 'Session ist abgelaufen') !== false) {
                $this->exts->moveToElementAndClick('button[type="submit"]');
            }
            sleep(15);
            $this->checkFillDslLogin();
            sleep(20);
        } else {
            $this->exts->openUrl($this->mobileLoginUrl);
            sleep(15);
            if ($this->exts->exists('[role="dialog"] button#uc-btn-accept-banner')) {
                $this->exts->moveToElementAndClick('[role="dialog"] button#uc-btn-accept-banner');
            }
            if (stripos($this->exts->extract('div[id="login"]'), 'Session ist abgelaufen') !== false) {
                $this->exts->moveToElementAndClick('button[type="submit"]');
            }
            sleep(15);
            $this->checkCookieConfirm();
            $this->checkFillMobileLogin();
            sleep(20);
        }

        $this->exts->execute_javascript("document.querySelector('div#usercentrics-root').shadowRoot.querySelector('button[data-testid=\"uc-accept-all-button\"]').click()");
        sleep(5);

        if (count($this->exts->getElements('label[data-test-id*="mfa-msisdn"]')) > 1) {
            if ($this->user_mobile_number != '') {
                $phone_label = $this->exts->getElement('//label[contains(text(),"' . $this->user_mobile_number . '") and contains(@data-test-id, "mfa")]', null, 'xpath');
                if ($phone_label != null) {
                    try {
                        $this->exts->log(__FUNCTION__ . ' trigger click.');
                        $phone_label->click();
                    } catch (\Exception $exception) {
                        $this->exts->log(__FUNCTION__ . ' by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$phone_label]);
                    }
                    sleep(2);
                }
            }
        }

        if ($this->exts->exists('button[data-test-id="mfa-send-otp"]')) {
            $this->exts->moveToElementAndClick('button[data-test-id="mfa-send-otp"]');
            sleep(10);
        }

        $this->checkFillTwoFactor();

        if ($this->exts->getElement('form[name="enterMsisdnForm"]') != null) {
            $this->exts->moveToElementAndClick('a[href="#/verwalten/uebersicht"]');
            sleep(5);
            $this->exts->moveToElementAndClick('a[href="/"]');
            sleep(20);
        }
    }



    // then check user logged in or not
    if ($this->exts->exists($this->check_login_success_selector)) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture('LoginSuccess');
        $this->exts->capture('3-login-success');

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');

        $isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Benutzername und/oder Kennwort falsch");');
        $this->exts->log('isErrorMessage:: ' . $isErrorMessage);
        if ($isErrorMessage) {
            $this->exts->capture('incorrect-user-pass');
            $this->exts->loginFailure(1);
        }

        if (stripos($this->usernameTemp, 'my') !== false) {
            if (strpos($this->exts->extract('#aliceLogin .feedbackPanel'), 'Passwort ist nicht korrekt') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos($this->exts->extract('div[id="o2-login"]'), 'Leider konnten wir Ihre') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        } else {
            if ($this->exts->urlContains('/meta/bereichswechsel/')) {
                $this->exts->loginFailure(1);
            } elseif (strpos(strtolower($this->exts->extract('#login .alert-danger')), 'bitte geben sie ihre rufnummer ein') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos($this->exts->extract('div[id="o2-login"]'), 'Leider konnten wir Ihre') !== false) {
                $this->exts->loginFailure(1);
            } elseif (
                strpos($this->exts->extract('#login .alert-danger'), 'Nutzername ist uns') !== false ||
                strpos($this->exts->extract('#login .alert-danger'), 'Kennwort ist') !== false ||
                strpos($this->exts->extract('#login .alert-danger'), 'Sie sind noch nicht f') !== false
            ) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->urlContains('/meta/auth/logout/')) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
}

private function checkFillDslLogin()
{
    if ($this->exts->getElement($this->dsl_password_selector) != null) {
        sleep(3);
        $this->exts->capture('2-login-page');

        $this->exts->log('Enter Username');
        $this->exts->log($this->username);
        $this->exts->moveToElementAndType($this->dsl_username_selector, $this->username);
        sleep(1);

        $this->exts->log('Enter Password');
        $this->exts->log($this->password);
        $this->exts->moveToElementAndType($this->dsl_password_selector, $this->password);
        sleep(1);

        $this->exts->capture('2-login-page-filled');
        $this->exts->moveToElementAndClick($this->dsl_submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture('2-login-page-not-found');
    }
}
private function checkCookieConfirm()
{
    sleep(3);
    $this->exts->execute_javascript('
    var cookie_popup = document.querySelector("#usercentrics-root");
    if (cookie_popup != null) {
        cookie_popup.shadowRoot.querySelector("[data-testid=\"uc-accept-all-button\"]").click();
    }
');

    sleep(3);
}

private function checkFillMobileLogin()
{
    $this->exts->capture('2-login-page');
    if ($this->exts->getElement($this->mobile_username_selector) != null) {
        sleep(3);
        $this->exts->log('Enter Username');
        $this->exts->log($this->username);
        $this->exts->execute_javascript("
        (function() {
            var host1 = document.querySelector('#idToken4_od');
            if (host1 && host1.shadowRoot) {
                var input = host1.shadowRoot.querySelector('input#input-2');
                if (input) {
                    input.value = " . $this->username . ";
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        })();
    ");

        sleep(2);
        $this->exts->execute_javascript('
        var shadow = document.querySelector("one-button.loginLegacySubmitBtn");
        if(shadow){
            shadow.shadowRoot.querySelector(\'button[role="button"]\').click();
        }
    ');
        sleep(5);
    }

    if ($this->exts->getElement($this->password_selector) != null) {
        $this->exts->log('Enter Password 2');

        $this->exts->log($this->password);
        $this->exts->execute_javascript("
        (function() {
            var host1 = document.querySelector('one-input[type=\"password\"]');
            if (host1 && host1.shadowRoot) {
                var input = host1.shadowRoot.querySelector('input[type=\"password\"]');
                if (input) {
                    input.value = '" . $this->password . "';
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        })();
    ");
        sleep(2);

        $this->exts->capture('2-login-page-filled');
        $this->exts->execute_javascript('
        var shadow = document.querySelector("one-button[data-type=\'main-action\']");
        if (shadow && shadow.shadowRoot) {
            var button = shadow.shadowRoot.querySelector("button[role=\'button\']");
            if (button) {
                button.click();
            }
        }
    ');

        sleep(5);

        // if ($this->exts->exists($this->mobile_verification_number)) {
        if ($this->exts->exists('[id="select-group"] one-select')) {
            $this->exts->log('Enter Password 1');
            $this->exts->log($this->user_mobile_number);
            $this->exts->log('this is working -------------------->');
            $this->exts->execute_javascript('
            var shadowHost = document.querySelector("#select-group one-select");
            if (shadowHost && shadowHost.shadowRoot) {
                var select = shadowHost.shadowRoot.querySelector("select");
                if (select) {
                    select.value = "1";
                    select.dispatchEvent(new Event("change", { bubbles: true }));
                }
            }

            var buttonElement = document.querySelector("one-button[data-type=\'main-action\']");
            if (buttonElement) {
                buttonElement.removeAttribute("disabled");

                if (buttonElement.shadowRoot) {
                    var innerButton = buttonElement.shadowRoot.querySelector("button");
                    if (innerButton) {
                        innerButton.disabled = false;
                        innerButton.removeAttribute("disabled");
                        innerButton.click();
                    }
                } else {
                    buttonElement.click();
                }
            }
        ');

            $this->exts->capture('2-login-page-number');
            sleep(5);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture('2-login-page-not-found');
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'one-pin-input[autocomplete="one-time-code"]';
    $two_factor_message_selector = 'one-text[role="presentation"] b';
    $two_factor_submit_selector = 'one-button-group one-button[data-type="main-action"]';
    $this->exts->waitTillPresent($two_factor_selector, 20);

    $errMsg = $this->exts->extract('div[data-test-id="unified-login-error-display"]');
    $invalidUsernameMsg_gm = 'Ihr Nutzername ist uns nicht bekannt. Bitte überprüfen Sie ihre Eingabe.';
    $invalidUsernameMsg_en = 'We do not know your username. Please check your entry.';
    $this->exts->log($errMsg);
    if ((!empty($errMsg) && trim($errMsg) != '') && ($errMsg == $invalidUsernameMsg_gm || $errMsg == $invalidUsernameMsg_en || stripos($errMsg, 'Ihr Kennwort ist ungültig') !== false) || stripos($this->exts->extract('div#login h1'), 'Neues Kennwort anfordern') !== false) {
        $this->exts->loginFailure(1);
    }

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
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            $this->exts->execute_javascript("
            const host = document.querySelector('$two_factor_selector');
            if (!host || !host.shadowRoot) return 'NO_SHADOW';
            const inputs = host.shadowRoot.querySelectorAll('fieldset div[class*=\"pin-input\"] input');
            const code = arguments[0];
            if (inputs.length !== code.length) return 'OTP_LENGTH_MISMATCH';
            code.split('').forEach((digit, idx) => {
                inputs[idx].value = digit;
                inputs[idx].dispatchEvent(new Event('input', { bubbles: true }));
                inputs[idx].dispatchEvent(new Event('change', { bubbles: true }));
            });
            return 'SUCCESS';
        ", [$two_factor_code]);
            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            $this->exts->execute_javascript("
            const host = document.querySelector('$two_factor_submit_selector');
            if (host && host.shadowRoot) {
                const btn = host.shadowRoot.querySelector('button[role=\"button\"]');
                if (btn) btn.click();
            }
        ");
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