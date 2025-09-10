public $baseUrl = 'https://www.lichtblick.de/';
public $loginUrl = 'https://www.lichtblick.de/';
public $invoicePageUrl = 'https://mein.lichtblick.de/Privatkunden/Vertraege/Rechnungen';

public $username_selector = 'input#Benutzername,input[name="email"],form[action="/Konto/Login"] input#Benutzername, input[id="email"]';
public $password_selector = 'input[name="Passwort"],form[action="/Konto/Login"] input#Passwort, input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'form[action="/Konto/Login"] button[type="submit"], div.m-login__action button, button#process-submit, button[form="localAccountForm"][type="submit"], form button';

public $check_login_failed_selector = 'form[action="/Konto/Login"] div.alert-danger[style*="display: block"], div.m-login__action div.errors, form#localAccountForm div.error:not([style*="display: none"])';
public $check_login_success_selector = 'button[data-testid="sub-nav-logout"],a[href*="Logout"],div#m_ver_menu';


public $isNoInvoice = true;
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->clearChrome();
    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);

        $this->waitFor('#uc-btn-accept-banner', 5);

        if ($this->exts->exists('#uc-btn-accept-banner')) {
            $this->exts->moveToElementAndClick('#uc-btn-accept-banner');
            sleep(2);
        }

        $this->waitFor('[id*=usercentrics-cmp-ui]', 5);

        if ($this->exts->exists("[id*=usercentrics-cmp-ui]")) {
            $this->exts->execute_javascript(
                'document.querySelector("[id*=usercentrics-cmp-ui]").shadowRoot.querySelector(\'footer[data-testid="uc-footer"]\').querySelector(\'button[id="accept"]\').click();'
            );
        }
        $this->waitFor("a[href*='/konto/']", 5);
        if ($this->exts->exists("a[href*='/konto/']")) {
            $this->exts->click_element("a[href*='/konto/']");
        }
        $this->checkFillLogin();
        sleep(3);
        if (stripos(strtolower($this->exts->extract("div[class*='error itemLevel'][aria-hidden=false]")), 'valid') !== false) {
            $this->exts->openUrl('https://mein.lichtblick.de/Konto/Login');
            $this->checkFillLogin();
        }
    }
    sleep(15);
    $this->exts->waitTillAnyPresent(explode(',', $this->check_login_success_selector), 10);
    if ($this->exts->getElement($this->check_login_success_selector) != null && $this->exts->getElement($this->username_selector) == null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $error_message = $this->exts->extract('[role="alert"].alert-danger', null, 'innerHTML');
        if ($error_message != null && (strpos($error_message, 'dieser benutzer ist gesperrt') != false
            || strpos($error_message, 'blocked') != false)) {
            $this->exts->account_not_ready();
        }
        $this->exts->log($this->exts->extract($this->check_login_failed_selector));
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'hast du die richtige e-mail-adresse und das richtige passwort') !== false || strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'Es gab ein Problem beim Abruf der Kundendaten. Bitte versuche es später erneut') !== false) {
            $this->exts->loginFailure(1);
        } elseif ($this->exts->getElementByText('div.validation-summary-errors', ['Das Feld "Benutzername" ist erforderlich'], null, false) != null) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->waitFor($this->username_selector, 5);
    if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        if ($this->exts->exists($this->submit_login_selector) && !$this->exts->exists($this->password_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }

        $this->waitFor($this->password_selector, 5);

        $this->exts->log("Enter Password");
        if ($this->exts->exists($this->password_selector)) {
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
        }
        sleep(2);
        if ($this->remember_me_selector != '') {
            $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(1);
        }

        $this->exts->capture("2-login-page-filled");
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        }
        sleep(1);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
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
    }
    $this->exts->type_key_by_xdotool('Tab');
    $this->exts->type_key_by_xdotool('Return');
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}