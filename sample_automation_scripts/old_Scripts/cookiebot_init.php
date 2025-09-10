public $baseUrl = 'https://manage.cookiebot.com/de/manage';
public $loginUrl = 'https://manage.cookiebot.com/de/login';
public $invoicePageUrl = 'https://manage.cookiebot.com/de/invoices';

public $username_selector = '#logincontainer input#pageloginemail, .login input#pageloginemail';
public $password_selector = '#logincontainer input#pageloginpassword, .login input#pageloginpassword';
public $remember_me_selector = '#logincontainer input#persistentLogin, .login input#persistentLogin';
public $submit_login_btn = '#logincontainer a#pagesubmitLoginButton, .login a#pagesubmitLoginButton';

public $checkLoginFailedSelector = '#logincontainer input#pageloginpassword';
public $checkLoggedinSelector = 'a[href="javascript: userLogout();"].enabled';

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->exts->temp_keep_useragent = $this->exts->send_websocket_event(
        $this->exts->current_context->webSocketDebuggerUrl,
        "Network.setUserAgentOverride",
        '',
        ["userAgent" => "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36"]
    );
    sleep(2);

    $this->exts->openUrl($this->baseUrl);
    sleep(16);
    $this->exts->capture("Home-page-without-cookie");

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    // after load cookies and open base url, check if user logged in

    // Wait for selector that make sure user logged in
    sleep(10);
    if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
        // If user has logged in via cookies, call waitForLogin
        $this->exts->log('Logged in from initPortal');
        $this->exts->capture('0-init-portal-loggedin');
        // login with cookie, invoice can not load
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(15);
        if (strpos($this->exts->extract('span#lblEmptyData'), 'Sie wurden keine Rechnungen erstellt') !== false) {
            $this->exts->clearCookies();
            sleep(1);
            $this->exts->openUrl($this->loginUrl);
            $this->waitForLoginPage();
        } else {
            $this->waitForLogin();
        }
    } else {
        // If user hase not logged in, open the login url and wait for login form
        $this->exts->log('NOT logged in from initPortal');
        $this->exts->capture('0-init-portal-not-loggedin');
        $this->exts->clearCookies();

        $this->exts->openUrl($this->loginUrl);
        $this->waitForLoginPage();
    }
}

private function waitForLoginPage($count = 1)
{
    sleep(15);
    if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
        $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
        sleep(5);
    }
    if ($this->exts->getElement($this->password_selector) != null) {
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("1-filled-login");

        $alertPopup = $this->exts->evaluate('
        window.alert = function(msg) {
            console.log("Alert detected:", msg);
            window._alertTriggered = true;
        };');


        $this->exts->moveToElementAndClick($this->submit_login_btn);
        sleep(15);

        $alertBox = $this->exts->execute_javascript('window._alertTriggered;');

        $this->exts->log("print alertBox ----->" . $alertBox);

        if ($alertBox) {
            $this->exts->log('Incorrect username password');
            $this->exts->loginFailure(1);
        }

        if ($count < 5) {
            $count++;
            $this->waitForLoginPage($count);
        }
    } else {
        if ($count < 5) {
            $count++;
            $this->waitForLoginPage($count);
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }
}

private function waitForLogin($count = 1)
{
    sleep(5);

    if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
        sleep(3);
        $this->exts->log('User logged in.');
        $this->exts->capture("2-post-login");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if ($count < 5) {
            $count = $count + 1;
            $this->waitForLogin($count);
        } else {
            $this->exts->log('Timeout waitForLogin');
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }
}