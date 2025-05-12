public $baseUrl = "https://instantink.hpconnected.com";
public $loginUrl = "https://instantink.hpconnected.com/users/signin";
public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $submit_button_selector = '#next_button, [type=submit]';

public $restrictPages = 3;
public $totalFiles = 0;
public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->exts->loadCookiesFromFile();
    sleep(1);

    //Check session is expired or not
    $this->exts->openUrl('https://instantink.hpconnected.com/api/internal/critical_scopes');
    sleep(10);
    $this->exts->capture("0-check-session-expired");
    if (stripos($this->exts->extract('body pre'), '{"error":{"code":"session_expired"}}') !== false) {
        $this->clearChrome();
        sleep(1);
    }

    $this->exts->openUrl($this->baseUrl);
    sleep(20);
    $this->exts->capture("Home-page-with-cookie");

    if ($this->isExists('button#onetrust-button-group #onetrust-accept-btn-handler')) {
        $this->exts->click_element('button#onetrust-button-group #onetrust-accept-btn-handler');
        sleep(10);
    }
    if ($this->isExists('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]')) {
        $this->exts->click_element('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]');
        sleep(30);
    }
    $isCookieLoginSuccess = false;
    if ($this->checkLogin()) {
        $isCookieLoginSuccess = true;
    } else {
        if ($this->isExists('button[data-testid="sign-in-button"]')) {
            $this->exts->click_element('button[data-testid="sign-in-button"]');
        } else {
            $this->exts->openUrl($this->loginUrl);
        }
    }

    if (!$isCookieLoginSuccess) {
        sleep(15);
        $this->fillForm();
        sleep(30);

        if ($this->isExists('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]')) {
            $this->exts->click_element('button[data-testid="clientos-react-tenant-selector-mfe__continue_button"]');
            sleep(30);
        }

        if ($this->isExists('#onetrust-accept-btn-handler')) {
            $this->exts->click_element('#onetrust-accept-btn-handler');
            sleep(30);
        }
        if ($this->isExists('#full-screen-consent-form-footer-button-continue')) {
            $this->exts->click_element('#full-screen-consent-form-footer-button-continue');
            sleep(10);
        }

        if ($this->isExists('button[name="send-email"]')) {
            $this->exts->click_element('button[name="send-email"]');
            sleep(13);
        } else if ($this->isExists('button[name="send-phone"]')) {
            $this->exts->click_element('button[name="send-phone"]');
            sleep(13);
        }
        $this->checkFillTwoFactor();

        if ($this->isExists('.onboarding-component button#full-screen-error-button')) {
            $this->exts->capture("internal-session-error");
            $this->exts->refresh();
            sleep(10);
            $this->exts->refresh();
            sleep(10);
        }
        if ($this->isExists('#full-screen-consent-form-footer-button-continue')) {
            $this->exts->click_element('#full-screen-consent-form-footer-button-continue');
            sleep(10);
        }
        if ($this->isExists('#root[style*="display: block"] [role="progressbar"]') && $this->exts->urlContains('/org-selector')) {
            // Huy added this 07-2022
            $this->exts->openUrl($this->baseUrl);
            sleep(5);
        }
        sleep(10);
        if ($this->isExists('[aria-describedby="org-selector-modal-desc"] #org-selector-modal-desc label')) {
            $this->exts->click_element('[aria-describedby="org-selector-modal-desc"] #org-selector-modal-desc label');
            sleep(5);
            $this->exts->click_element('[aria-describedby="org-selector-modal-desc"] button[type="button"]');
            sleep(15);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }
    
            $this->exts->success();
        } else {
            if (
                strpos(strtolower($this->exts->extract('.caption.text')), 'invalid username or password') !== false ||
                strpos(strtolower($this->exts->extract('.caption.text')), 'ltiger benutzername oder') !== false
            ) {
                $this->exts->loginFailure(1);
            } else if ($this->isExists('#username-helper-text a.error-link')) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
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

private function fillForm($count = 0)
{
    $this->exts->capture("1-pre-login");
    $this->waitFor($this->username_selector, 20);
    if ($this->isExists($this->username_selector)) {
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        $this->exts->click_element('input#RememberMe, .remember-me label');
        $this->exts->capture("1-username-filled");
        $this->exts->click_element($this->submit_button_selector);
        sleep(6);
        $this->exts->capture("1-username-submitted");
        $this->exts->capture("1-password-page");
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }

    if ($this->isExists($this->password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);
        $this->exts->capture("1-password-filled");
        $this->exts->click_element($this->submit_button_selector);
        sleep(15);
    }

    if ($this->isExists($this->username_selector) && $count < 3) {
        $count++;
        $this->fillForm($count);
    }
}


private function isExists($selector = '')
{
    $safeSelector = addslashes($selector);
    $this->exts->log('Element:: ' . $safeSelector);
    $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
    if ($isElement) {
        $this->exts->log('Element Found');
        return true;
    } else {
        $this->exts->log('Element not Found');
        return false;
    }
}

public function waitFor($selector, $seconds = 10)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[name="code"], input#code';
    $two_factor_message_selector = 'div.email-header p, div.sms-header p, p';
    $two_factor_submit_selector = 'button#submit-code , button#submit-auth-code';

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

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->click_element($two_factor_submit_selector);
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
private function checkLogin()
{
    for ($wait_count = 1; $wait_count <= 10 && $this->exts->querySelector('#menu-avatar-container, div[data-testid*="avatar_menu"]') == null; $wait_count++) {
        $this->exts->log('Waiting for login...');
        sleep(5);
    }
    if ($this->exts->querySelector('#menu-avatar-container, div[data-testid*="avatar_menu"]') != null) {
        return true;
    }
    return $this->isExists('#desktop-header a[href="/users/logout"], [data-testid="sign-out-button"], [data-value="Sign out"], [data-value="Abmelden"], [data-testid="avatar-container"] [aria-haspopup="true"], #menu-avatar-container, div[data-testid*="avatar_menu"]');
}