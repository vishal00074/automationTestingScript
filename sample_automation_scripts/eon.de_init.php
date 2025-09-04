public $baseUrl = 'https://www.eon.de/de/meineon/meine-uebersicht.html';
public $download_all_documents = 0;
public $username_selector = 'login-form__text-input-email:not(.hidden) input#username, input#text_input';
public $password_selector = 'input#pwdTxt, input#password';
public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    $this->check_solve_blocked_page();

    $this->exts->capture('cloudeflare-check');

    $this->exts->execute_javascript('
        var cookieAccept = document.querySelector("#usercentrics-root").shadowRoot.querySelector("button[data-testid=uc-accept-all-button]")
        if(cookieAccept != null) cookieAccept.click();
    ');
    sleep(2);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->checkLoginSuccess()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->baseUrl);

        sleep(10);
        $this->waitFor('#usercentrics-root');

        $this->exts->execute_javascript('
            var cookieAccept = document.querySelector("#usercentrics-root").shadowRoot.querySelector("button[data-testid=uc-accept-all-button]")
            if(cookieAccept != null) cookieAccept.click();
        ');

        if ($this->exts->exists("#usercentrics-root")) {
            $this->exts->executeSafeScript(
                'document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'div[data-testid="uc-footer"]\').querySelector(\'button[data-testid="uc-accept-all-button"]\').click();'
            );
            sleep(2);
        }

        $this->exts->capture('1-pre-login-page');

        $this->checkFillLogin();

        $this->check_solve_blocked_page();

        if ($this->exts->exists("#usercentrics-root")) {
            $this->exts->executeSafeScript(
                'document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'div[data-testid="uc-footer"]\').querySelector(\'button[data-testid="uc-accept-all-button"]\').click();'
            );
            sleep(5);
        }

        $this->checkFillTwoFactor();
    }
    $this->doAfterLogin();
}

private function checkLoginSuccess()
{
    $this->waitFor('[aria-label="Logout-icon"]', 45);
    return $this->exts->exists('a[aria-label="Logout-icon"], a .eon-de-react-icon--logout-2, eon-ui-navigation-main-icon-link[icon=logout], eon-ui-website-navigation-main-link[icon="logout"], eon-ui-website-navigation-main-link[data-testid="main-link-/profile"]');
}

private function checkFillLogin()
{
    $this->check_solve_blocked_page();
    $this->waitFor('.login-form__text-input-email:not(.hidden) input#username', 30);
    if ($this->exts->exists('.login-form__text-input-email:not(.hidden) input#username')) {
        $this->exts->capture("2-login-page");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->click_by_xdotool('button#login-button');
        sleep(5);
    }

    if ($this->exts->exists('input#text_input')) {
        sleep(3);
        $this->exts->capture("2-login-page");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
    }

    if ($this->exts->exists('input#pwdTxt, input#password') != null) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);
        $this->exts->capture("2-login-page-filled");
        $this->exts->click_by_xdotool('[class*="logon"] button[type="submit"], form[action*="login/"] button[type="submit"]');
        sleep(2);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = '[id*="MFAPage:pageForm"] #verifyTotpDiv [id*=totpCode-input], input[onkeydown="manageKeydown(this);"]';
    $two_factor_message_selector = '';
    $two_factor_content_selector = 'div[data-test="twofactor-form"] div.message-2fa,document.querySelector("eon-ui-rte-renderer").shadowRoot.querySelector(".eonui-renderer-content.eonui-rte-source-aem p")';
    $two_factor_submit_selector = 'button[onclick*=verifyTotpFunction]';
    $this->waitFor($two_factor_submit_selector, 20);
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

        $twoFaMessage = $this->exts->executeSafeScript('document.querySelector("eon-ui-rte-renderer").shadowRoot.querySelector(".eonui-renderer-content.eonui-rte-source-aem p").innerText');

        if ($twoFaMessage != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_content_selector, null, 'content');
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
            $code_inputs = $this->exts->getElements($two_factor_selector);

            foreach ($code_inputs as $key => $code_input) {
                if (array_key_exists($key, $resultCodes)) {
                    $this->exts->log('checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                    $this->exts->moveToElementAndType($code_input, $resultCodes[$key]);
                } else {
                    $this->exts->log('checkFillTwoFactor: Have no char for input #');
                }
            }

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

private function check_solve_blocked_page()
{
    sleep(10);
    $this->waitFor('div#turnstile-wrapper', 30);
    $this->exts->capture("blocked-page-checking");
    if ($this->exts->exists('div#turnstile-wrapper')) {
        $this->exts->capture("blocked-by-cloudflare");
        $attempts = 5;
        $delay = 30;

        for ($i = 0; $i < $attempts; $i++) {
            $this->exts->click_by_xdotool('div#turnstile-wrapper', 35, 35);
            sleep($delay);
            if (!$this->exts->exists('div#turnstile-wrapper')) {
                break;
            }
        }
    }
}


function doAfterLogin()
{
    // then check user logged in or not
    if ($this->checkLoginSuccess()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->waitFor('.advertise-agreement__modal.in .close');
        if ($this->exts->exists('.advertise-agreement__modal.in .close')) {
            $this->exts->moveToElementAndClick('.advertise-agreement__modal.in .close');
        }
        $this->waitFor('eon-ui-modal');
        $this->exts->execute_javascript('
            var modal = document.querySelector("eon-ui-modal").shadowRoot.querySelector(".eonui-icon-closing-x")
            if(modal != null) modal.click();
        ');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        if (
            strpos(strtolower($this->exts->extract('.logon__form-error #errorMsg')), 'passwort sind nicht korrekt') !== false ||
            strpos(strtolower($this->exts->extract('.logon__form-error #errorMsg')), 'bitte geben sie ihre login-daten ein') !== false
        ) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('input[name*="zip-input-"]')) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('div.forgot_password-labeltext, .modal.in input[name*="zip"]')) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->urlContains('/maintenance/fehler.html')) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->urlContains('force.com/_nc_external/identity/sso/ui/AuthorizationError')) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function waitFor($selector, $seconds = 30)
{
    for ($i = 1; $i <= $seconds && $this->exts->querySelector($selector) == null; $i++) {
        $this->exts->log('Waiting for Selector (' . $i . '): ' . $selector);
        sleep(1);
    }
}