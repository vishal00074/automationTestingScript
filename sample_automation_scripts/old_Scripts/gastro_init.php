public $baseUrl = 'https://www.gastro-hero.de';
public $loginUrl = 'https://www.gastro-hero.de/hilfe-service';
public $invoicePageUrl = 'https://www.gastro-hero.de/sales/order/history/';

public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[data-testid="loginSubmit"]';

public $check_login_failed_selector = '[data-testid="notificationMessage"]';
public $check_login_success_selector = 'div[data-testid="megaNavigationAccount"] .gh-user-avatar';

public $isNoInvoice = true;
public $restrictPages = 3;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');
    sleep(5);
    $this->exts->openUrl('https://www.gastro-hero.de/customer/account');
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->checkLoggedIn()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();

        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->exts->moveToElementAndClick('div.account-menu.hidden-md > div:nth-child(3) > button, div.account-menu > div:nth-child(3) > button');
        sleep(10);
        $button_cookie = $this->exts->execute_javascript('document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\')');
        if ($button_cookie != null) {
            $this->exts->execute_javascript("arguments[0].click()", [$button_cookie]);
            sleep(5);
        }
        $this->exts->moveToElementAndClick('[data-testid="megaNavigationAccount"] .account-icon div');
        sleep(10);
        $this->checkFillLogin();
        sleep(20);
    }

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
        if ($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
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

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);
        for ($i = 0; $i < 10 && $this->exts->getElement('[data-testid="notificationMessage"], [class="notifications fixed"]') == null; $i++) {
            sleep(1);
        }
        if ($this->exts->exists('[data-testid="notificationMessage"], [class="notifications fixed"]')) {
            $error_message = $this->exts->extract('[data-testid="notificationMessage"], [class="notifications fixed"]');
            $this->exts->log("Login Failure : " . $error_message);
            if (
                strpos(strtolower($error_message), 'passwor') !== false ||
                strpos(strtolower($error_message), 'account ist vorübergehend nicht') !== false
            ) {
                $this->exts->loginFailure(1);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function checkLoggedIn()
{
    // $this->exts->openUrl('https://www.gastro-hero.de/customer/account');
    sleep(10);
    $LoggedIn = false;
    if ($this->exts->exists($this->check_login_success_selector)) {
        $LoggedIn = true;
    }
    return $LoggedIn;
}