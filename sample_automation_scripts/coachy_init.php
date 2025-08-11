public $baseUrl = 'https://coachy.net/';
public $loginUrl = 'https://my.coachy.net/';
public $username_selector = 'form input[name="email"]';
public $password_selector = 'form input[name="pass"]';
public $submit_login_selector = 'form button[type="submit"]';
public $check_login_failed_selector = 'div.message.error';
public $check_login_success_selector = 'a[href*="/abmelden"]';
public $isNoInvoice = true;
public $restrictPages = 3;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->waitFor('div#cookiebanner span.save.all', 10);
        if ($this->exts->exists('div#cookiebanner span.save.all')) {
            $this->exts->click_by_xdotool('div#cookiebanner span.save.all');
            sleep(5);
        }
        $this->waitFor('button[id*=AllowAll]', 10);
        if ($this->exts->exists('button[id*=AllowAll]')) {
            $this->exts->click_element('button[id*=AllowAll]');
        }
        $this->exts->click_by_xdotool('li.show-for-medium a[href*="/anmelden/"]');
        $this->checkFillLogin();
    }

    $this->waitFor($this->check_login_success_selector, 10);

    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'anmeldung fehlgeschlagen') !== false || stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'not found') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}

private function checkFillLogin()
{
    $this->waitFor($this->username_selector, 10);
    if ($this->exts->querySelector($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->click_by_xdotool($this->submit_login_selector);
        sleep(5);
        if ($this->exts->exists('form input[type="submit"]:not([value="Neuer Mitgliederbereich"])')) {
            $this->exts->click_by_xdotool('form input[type="submit"]:not([value="Neuer Mitgliederbereich"])');
            sleep(5);
        }
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