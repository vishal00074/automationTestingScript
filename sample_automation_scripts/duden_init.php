public $baseUrl = 'https://www.duden.de/';
public $invoicePageUrl = 'https://www.duden.de/rechnungen';
public $loginUrl = 'http://duden.de/user/authenticate';

public $username_selector = 'form#loginForm input[id="loginForm:username"]';
public $password_selector = 'form#loginForm input[id="loginForm:password"]';
public $submit_login_selector = 'form#loginForm input[id="loginForm:loginButton"]';

public $check_login_failed_selector = 'form#loginForm ul[style*="color:red"] li';
public $check_login_success_selector = 'div.listview__item a[href*="/user/expire"], div#user-info, a[href="/rechnungen"].upright-menu__link';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    $this->exts->waitTillAnyPresent(explode(',', $this->check_login_success_selector), 20);
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');

        $this->exts->waitTillPresent('button[title="AKZEPTIEREN UND WEITER"]');
        if ($this->exts->exists('button[title="AKZEPTIEREN UND WEITER"]')) {
            $this->exts->click_element('button[title="AKZEPTIEREN UND WEITER"]');
        }
        // $this->exts->clearCookies();
        // $loginUrl = $this->exts->extract('div.listview__item a[href*="/user/authenticate"]', null, 'href');
        $this->exts->openUrl($this->loginUrl);
        $this->checkFillLogin();
        // sleep(15);
    }
    $this->exts->waitTillAnyPresent(explode(',', $this->check_login_success_selector), 20);
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'und/oder passwort') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector);
    if ($this->exts->querySelector($this->password_selector) != null) {
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