public $baseUrl = 'https://geschaeftskunden.dhl.de';
public $username_selector = 'input[name*="username"]';
public $password_selector = 'input[name*="password"]';
public $submit_login_selector = 'button.submit.login, button#button-loginSubmit, div#kc-form-buttons';

public $check_login_failed_selector = '[id*="pt:dmaIl:pglError"] > div.dhl-errors div, .af_message_detail, div[data-testid="password-error"]';
public $check_login_success_selector = '.username-container + .af_panelList li [id*="admin"], [data-testid="myAccount.logout"]';

public $restrictPages = 3;
public $daily_closing_list = 0;
public $isNoInvoice = true;
public $download_report = 0;
public $shipment_tracking = 0;
public $only_download_report = 0;

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->daily_closing_list = isset($this->exts->config_array["daily_closing_list"]) ? (int) @$this->exts->config_array["daily_closing_list"] : $this->daily_closing_list;
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : $this->restrictPages;
    $this->download_report = isset($this->exts->config_array["download_report"]) ? (int) @$this->exts->config_array["download_report"] : $this->download_report;
    $this->shipment_tracking = isset($this->exts->config_array["shipment_tracking"]) ? (int) @$this->exts->config_array["shipment_tracking"] : $this->shipment_tracking;
    $this->only_download_report = isset($this->exts->config_array["only_download_report"]) ? (int) @$this->exts->config_array["only_download_report"] : $this->only_download_report;

    $this->exts->log('restrictPages '. $this->restrictPages);
    $this->exts->log('daily_closing_list '. $this->daily_closing_list);
    $this->exts->log('download_report '. $this->download_report);
    $this->exts->log('shipment_tracking '. $this->shipment_tracking);
    $this->exts->log('only_download_report '. $this->only_download_report);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    if ($this->exts->exists('button#accept-recommended-btn-handler')) {
        $this->exts->click_by_xdotool('button#accept-recommended-btn-handler');
        sleep(3);
    }
    if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
        $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
        sleep(3);
    }
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        for ($i = 0; $i < 3; $i++) {
            if (count($this->exts->getElements('body div')) == 0) {
                $this->exts->refresh();
                sleep(10);
            } else {
                break;
            }
        }
        if ($this->exts->exists('button#accept-recommended-btn-handler')) {
            $this->exts->click_by_xdotool('button#accept-recommended-btn-handler');
            sleep(3);
        }

        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
            sleep(3);
        }

        if ($this->exts->exists('div.login-module-container button[data-testid="noName"]')) {
            $this->exts->click_by_xdotool('div.login-module-container button[data-testid="noName"]');
            sleep(10);
        }

        if ($this->exts->exists('iframe.keycloakLogin')) {
            $this->switchToFrame('iframe.keycloakLogin');
            sleep(5);
        }

        // sometimes the page can't load login frame, refresh page
        for ($i = 0; $i < 2; $i++) {
            if (!$this->exts->exists($this->password_selector)) {
                $this->exts->refresh();
                sleep(10);
                if ($this->exts->exists('div.login-module-container button[data-testid="noName"]')) {
                    $this->exts->click_by_xdotool('div.login-module-container button[data-testid="noName"]');
                    sleep(10);
                }
            } else {
                break;
            }
        }

        $this->exts->capture('after-reload-page');
        if (!$this->exts->exists($this->password_selector)) {
            $this->exts->switchToDefault();
        }
        // end refresh

        $this->checkFillLogin();
        sleep(20);

        if ($this->exts->exists($this->password_selector)) {
            $this->checkFillLogin();
            sleep(30);
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
        $this->exts->log(__FUNCTION__ . '::Use login failed' . $this->exts->getUrl());
        $this->exts->log(__FUNCTION__ . '::Use login failed' . $this->exts->extract('.af_showDetailFrame_content div.form'));
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"], div.alert-error')), 'deaktiviert') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"], div.alert-error')), 'ist abgelaufen') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p, div.dhl-errors div, div.alert-error')), 'benutzername und/oder passwort ung') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p, div.dhl-errors div, div.alert-error')), 'invalid username and/or password') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p')), 'ist abgelaufen') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-warning-textoutput"] p')), 'valid username and password combination') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p')), 'da sie diesen seit mehr als 120 tagen nicht verwendet haben') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p')), 'ihr benutzer ist aufgrund') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('.af_showDetailFrame_content div.form')), 'neues passwort festlegen') !== false) {
            $this->exts->account_not_ready();
        } else if (strpos(strtolower($this->exts->extract('div[data-testid="login-messages-warning-textoutput"] p')), 'sie haben zum') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-warning-textoutput"] p')), 'mal keine g') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-warning-textoutput"] p')), 'ltige kombination f') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-warning-textoutput"] p')), 'r benutzername und passwort eingegeben') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('[data-testid="login-messages-warning-textoutput"] p span')), 'benutzer gesperrt') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p')), 'ystembenutzer anzumelden') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('.alert-error .pf-c-alert__title.kc-feedback-text')), 'bitte beachten sie, dass systembenutzer nicht für eine anmeldung zugelassen sind.') !== false) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        $this->exts->capture("2-login-page-filled");
        $this->exts->click_by_xdotool($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#otp';
    $two_factor_message_selector = 'span.kc-feedback-text';
    $two_factor_submit_selector = 'button#kc-login';
    $this->exts->waitTillPresent($two_factor_selector, 10);
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
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
            $this->exts->click_by_xdotool($two_factor_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


            $this->exts->click_by_xdotool($two_factor_submit_selector);
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

public function switchToFrame($query_string)
{
    $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
    $frame = null;
    if (is_string($query_string)) {
        $frame = $this->exts->queryElement($query_string);
    }

    if ($frame != null) {
        $frame_context = $this->exts->get_frame_excutable_context($frame);
        if ($frame_context != null) {
            $this->exts->current_context = $frame_context;
            return true;
        }
    } else {
        $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
    }

    return false;
}