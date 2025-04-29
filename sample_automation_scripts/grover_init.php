public $baseUrl = 'https://www.grover.com/business-de/for-business';
public $loginUrl = 'https://www.grover.com/de-de/auth';
public $paymentPageUrl = 'https://www.grover.com/business-de/your-payments?status=PAID';

public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"], form button.eUjiwK, form button[class*="clickable"]';

public $check_login_success_selector = 'div[data-testid="header-dashboard-links"] a:nth-child(3), div[data-testid="account-menu-button"], a[href*="your-profile"]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    // Load cookies
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    $this->exts->waitTillPresent('.snackbar-enter-done button[role="button"]');
    if ($this->exts->exists('.snackbar-enter-done button[role="button"]')) {
        $this->exts->click_by_xdotool('.snackbar-enter-done button[role="button"]');
        sleep(1);
    }
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElementByText($this->check_login_success_selector, ['Konto', 'Account'], null, true) == null || $this->exts->exists($this->check_login_success_selector)) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->exts->waitTillPresent('svg > path[clip-rule="evenodd"][fill="#333333"]');
        if ($this->exts->exists('svg > path[clip-rule="evenodd"][fill="#333333"]')) {
            $this->exts->click_by_xdotool('svg > path[clip-rule="evenodd"][fill="#333333"]');
            sleep(3);
        }

        if ($this->exts->exists('div[data-testid="country_redirection_close_button"]')) {
            $this->exts->click_by_xdotool('div[data-testid="country_redirection_close_button"]');
            sleep(3);
        }
        sleep(10);
        if ($this->exts->exists('.snackbar-enter-done button[role="button"]')) {
            $this->exts->click_by_xdotool('.snackbar-enter-done button[role="button"]');
            sleep(1);
        }

        if ($this->exts->exists('div[data-testid="snackbar"] > div >div > button')) {
            $this->exts->click_by_xdotool('div[data-testid="snackbar"] > div >div > button');
            sleep(3);
        }
        sleep(10);
        if ($this->exts->exists('div[data-testid="country_redirection_close_button"]')) {
            $this->exts->click_by_xdotool('div[data-testid="country_redirection_close_button"]');
            sleep(3);
        }

        if ($this->exts->exists('div.CountryRedirectionContent .bMOKHR')) {
            $this->exts->click_by_xdotool('div.CountryRedirectionContent .bMOKHR');
            sleep(1);
        }
        $this->checkFillLogin();
        $this->checkFillTwoFactor();

        if (strpos($this->exts->getUrl(), '/auth') !== false && $this->exts->exists('div.step-content-enter-done')) {
            $mes_check_login = '';
            $mes_els = $this->exts->getElements('div.step-content-enter-done');
            foreach ($mes_els as $mes_el) {
                $mes_check_login .= $mes_el->getAttribute('innerText');
            }
            $mes_check_login = strtolower($mes_check_login);
            $this->exts->log($mes_check_login);
            if (strpos($mes_check_login, 'passwort zur') !== false && strpos($mes_check_login, 'wie du dein neues passwort einrichten willst') !== false) {
                $this->exts->account_not_ready();
            }
        }
        $this->exts->capture('1-afterlogin-page');
        if ($this->exts->getElement('div.CountryRedirectionContent') != null) {
            $this->exts->log(__FUNCTION__ . '::redirect to base');
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
        }
        if ($this->exts->exists('button[data-testid="country_redirection_confirm_button"]')) {
            $this->exts->click_by_xdotool('button[data-testid="country_redirection_confirm_button"]');
            sleep(15);
        }
        $this->exts->waitTillPresent($this->check_login_success_selector);
    }
    // then check user logged in or not
    if ($this->exts->getElementByText($this->check_login_success_selector, ['Konto', 'Account'], null, true) != null || $this->exts->exists($this->check_login_success_selector)) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if ($this->exts->exists('div[id*="AUTH_FLOW"]')) {
            $this->exts->account_not_ready();
        }
        if (strpos($this->exts->extract('form'), 'Passwor') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


private function checkFillTwoFactor()
{
    $two_factor_selector = 'label[name="twoFactorAuthCode"] input';
    $two_factor_message_selector = 'form h5 > font, form div[dir="auto"] > font, form h5';
    //$two_factor_submit_selector = 'form button.btn-primary';
    $this->exts->waitTillPresent($two_factor_selector, 20);
    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute("innerText") . "\n";
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

            //$this->exts->click_by_xdotool($two_factor_submit_selector);
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

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector);
    if ($this->exts->getElement($this->password_selector) != null) {
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->click_by_xdotool($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        // $this->exts->click_by_xdotool($this->submit_login_selector);
        $tab_buttons = $this->exts->getElements('form button');
        $this->exts->log('Finding Completted trips button...');
        foreach ($tab_buttons as $key => $tab_button) {
            $tab_name = trim($tab_button->getAttribute('innerText'));
            if (stripos($tab_name, 'Einloggen') !== false) {
                $this->exts->log('Completted trips button found');
                try {
                    $this->exts->log('Click button');
                    $tab_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$tab_button]);
                }
                break;
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}