public $baseUrl = 'https://login.easybell.de/rechnungen';
public $loginUrl = 'https://login.easybell.de/login';
public $invoicePageUrl = 'https://login.easybell.de/rechnungen';
public $username_selector = 'input[autocomplete="username"]';
public $password_selector = 'input[autocomplete="current-password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[data-test="submit"]';

public $check_login_failed_selector = 'SELECTOR_error';
public $check_login_success_selector = 'div.desktop-menu button[data-test="desktop-menu-button"]';

public $isNoInvoice = true;

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
    if ($this->exts->querySelector('#dontShowAgainCheck') != null) {
        $this->exts->moveToElementAndClick('#dontShowAgainCheck');
        $this->exts->moveToElementAndClick('button.eb-button.eb-button--text.eb-button--base');
        sleep(10);
    }
    if ($this->exts->querySelector('button[data-test="toggleDesktopMenu"]') != null) {
        $this->exts->moveToElementAndClick('button[data-test="toggleDesktopMenu"]');
        sleep(3);
    }
    sleep(15);
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        $this->checkFillLogin();
        sleep(25);
        if (stripos(strtolower($this->exts->extract('.alert.error', null, 'innerText')), 'ihr account ist zur zeit gesperrt') !== false) {
            $this->exts->log('account currently blocked');
            $this->exts->account_not_ready();
        }
        if (stripos(strtolower($this->exts->extract('.alert.error', null, 'innerText')), 'sie haben falsche zugangsdaten eingegeben') !== false) {
            $this->exts->log('account currently blocked');
            $this->exts->loginFailure(1);
        }
        //2FA check
        if ($this->exts->exists('form fieldset  div[class*="eb-input"] input') && $this->exts->exists('header h2[class="text-sm font-regular"]')) {
            $this->checkFillTwoFactor();
        }

        //dontShowAgainCheck
        if ($this->exts->exists('#dontShowAgainCheck')) {
            $this->exts->moveToElementAndClick('#dontShowAgainCheck');
            $this->exts->moveToElementAndClick('button.eb-button.eb-button--text.eb-button--base');
            sleep(10);
        }
        if ($this->exts->exists('button[data-test="toggleDesktopMenu"]')) {
            $this->exts->moveToElementAndClick('button[data-test="toggleDesktopMenu"]');
            sleep(3);
        }
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
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector);
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(3);

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
    $two_factor_selector = 'form fieldset  div[class*="eb-input"] input';
    $two_factor_message_selector = 'header h2[class="text-sm font-regular"]';
    $two_factor_submit_selector = 'form button[class*="eb-button eb-button--primar"]';
    $two_factor_resend_selector = 'form button[class*="eb-button eb-button--text"]';

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

            $cha_arr = str_split($two_factor_code);
            $two_factor_els = count($this->exts->getElements($two_factor_selector));
            $this->exts->log("checkFillTwoFactor: Number of digit." . $two_factor_els);

            for ($i = 0; $i < $two_factor_els; $i++) {

                $two_factor_el = $this->exts->getElements($two_factor_selector)[$i];
                if ($i < count($cha_arr))
                    $this->exts->moveToElementAndType($two_factor_el, $cha_arr[$i]);
            }

            //$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
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
}