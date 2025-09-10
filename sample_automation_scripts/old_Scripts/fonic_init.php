public $baseUrl = 'https://www.fonic.de/';
public $loginUrl = 'https://mein.fonic.de/login';
public $invoicePageUrl = 'https://www.fonic.de/selfcare/gespraechsuebersicht';

public $username_selector = 'form[name=authForm] one-input';
public $password_selector = 'form[name=authForm] one-input[type=password]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[name="loginButton"]';

public $check_login_failed_selector = 'div.alert.alert--error';
public $check_login_success_selector = 'use[href*="logout"]';

public $isNoInvoice = true;
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    $this->exts->execute_javascript('
        var shadow = document.querySelector("#usercentrics-root").shadowRoot;
        var button = shadow.querySelector(\'button[data-testid="uc-accept-all-button"]\')
        if(button){
            button.click();
        }
    ');
        sleep(4);
    $this->exts->capture_by_chromedevtool('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->checkLoggedIn()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->moveToElementAndClick('one-nav-bar-item[href*="auth/uebersicht"]:not(.hidden)');
        sleep(10);
        $this->checkFillLogin();
        sleep(15);
        if ($this->exts->exists('div.change_password')) {
            $this->exts->account_not_ready();
        }
    }

    // then check user logged in or not
    if ($this->checkLoggedIn()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Kennwort ist nicht korrekt.') !== false) {
            $this->exts->loginFailure(1);
        } elseif ($this->exts->exists('div.change_password')) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->username_selector) != null) {
        $this->exts->log("Enter Username");
        $this->exts->click_by_xdotool($this->username_selector, 30, 40);
        sleep(1);
        $this->exts->type_text_by_xdotool($this->username);
        sleep(2);
        $this->exts->click_by_xdotool('form[name=authForm] one-button');
        sleep(5);
        $this->exts->log("Enter Password");
        $this->exts->click_by_xdotool($this->password_selector, 30, 40);
        sleep(1);
        $this->exts->type_text_by_xdotool($this->password);
        sleep(2);
        $this->exts->click_by_xdotool('form[name=authForm] one-button[data-type="main-action"]');
        sleep(2);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkLoggedIn()
{
    $isLoggedIn = false;
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        $isLoggedIn = true;
    }
    if ($this->exts->getElementByText('one-stack[class="full-width-on-mobile"]', ['Ausloggen', 'Log out'], null, false) != null) {
        $isLoggedIn = true;
    }

    return $isLoggedIn;
}