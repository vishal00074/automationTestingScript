public $baseUrl = 'https://www.otto-office.com/de/app/account/statement/main';
public $loginUrl = 'https://www.otto-office.com/de/app/account/statement/main';
public $invoicePageUrl = 'https://www.otto-office.com/de/app/account/statement/main';
public $username_selector = 'input[name*="login[login]"]';
public $password_selector = 'input[name*="login[password]"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form#anmelden button[type="submit"]';
public $check_login_failed_selector = 'span.oo-alert-text';
public $check_login_success_selector = 'li[role="menuitem"] > a[href*="logout"]';
public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    $this->exts->clearCookies();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    if ($this->exts->exists('button#oo-overlay-close')) {
        $this->exts->moveToElementAndClick('button#oo-overlay-close');
    }

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);

        if ($this->exts->exists('button#oo-overlay-close')) {
            $this->exts->moveToElementAndClick('button#oo-overlay-close');
        }

        if ($this->exts->exists('div#top-notification_accept-machmichweg-guidelines button')) {
            $this->exts->moveToElementAndClick('div#top-notification_accept-machmichweg-guidelines button');
            sleep(3);
        }
        sleep(5);
        if ($this->exts->exists('button.close-accept')) {
            $this->exts->click_element('button.close-accept');
        }

        sleep(5);
        if ($this->exts->exists('button[id*="overlay-close"]')) {
            $this->exts->click_element('button[id*="overlay-close"]');
        }
        sleep(10);
        $this->checkFillLogin();
        sleep(20);
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        if ($this->exts->exists('div#overlay_login img')) {
            $this->exts->moveToElementAndClick('div#overlay_login img');
            sleep(3);
        }
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if (stripos($error_text, strtolower('anmeldeproblemen die passwor')) !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->exists('div#top-notification_accept-machmichweg-guidelines button')) {
        $this->exts->moveToElementAndClick('div#top-notification_accept-machmichweg-guidelines button');
        sleep(5);
    }
    if ($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(10);

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