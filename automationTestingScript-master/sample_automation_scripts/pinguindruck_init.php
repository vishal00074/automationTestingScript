public $baseUrl = 'https://pinguindruck.de/';
public $loginUrl = 'https://pinguindruck.de/';
public $invoicePageUrl = 'https://pinguindruck.de/user/show-order';

public $username_selector = '#login_form input#login_username, .login-form input#login_username';
public $password_selector = '#login_form input#login_password, .login-form input#login_password';
public $remember_me_selector = '';
public $submit_login_selector = '#login_form button#login_submit, .login-form button[name="submit"]';

public $check_login_failed_selector = 'div#login_error';
public $check_login_success_selector = 'li.logout a[href*="/user/logout"]';

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
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->exts->moveToElementAndClick('div.login-button, div.login.button');
        sleep(10);
        $this->checkFillLogin();
    }

    if ($this->checkLogin()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (
            strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'password is invalid') !== false
            || strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwort ist ung') !== false
        ) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    $this->switchToFrame('iframe[src*="/login"]');
    sleep(2);
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
        sleep(20);
        $this->exts->switchToDefault();
        sleep(2);
        if ($this->exts->getElement('iframe[src*="/login"]') != null) {
            $this->switchToFrame('iframe[src*="/login"]');
            sleep(2);
            if (
                strpos(strtolower($this->exts->extract('div.errors', null, 'innerText')), strtolower('Das Passwort ist ungültig.')) !== false ||
                strpos(strtolower($this->exts->extract('div.errors', null, 'innerText')), strtolower('Das Kundenkonto wurde nicht gefunden.')) !== false
            ) {
                $this->exts->loginFailure(1);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
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

    /**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
public  function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}