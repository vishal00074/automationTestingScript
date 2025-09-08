public $baseUrl = 'https://kundencenter.n0q.de/index.php?load=rechnungen';
public $loginUrl = 'https://kundencenter.n0q.de/index.php?load=rechnungen';
public $invoicePageUrl = 'https://kundencenter.n0q.de/index.php?load=rechnungen';

public $username_selector = '#loginbox input#logincnotext, #loginwrapper input#logincnotext';
public $password_selector = '#loginbox input#loginpass, #loginwrapper input#loginpass';
public $remember_me_selector = '';
public $submit_login_selector = '#loginbox input#loginbutton, #loginwrapper button#loginbutton';

public $check_login_failed_selector = 'div.alert-danger span#showInactiveText';
public $check_login_success_selector = 'a#logout, a[title="Abmelden"]';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page-before-loadcookies');
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');
    $this->waitFor($this->check_login_success_selector, 3);
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->clearChrome();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->checkFillLogin();
        sleep(20);
    }
    $this->waitFor($this->check_login_success_selector);
    if ($this->exts->getElement($this->check_login_success_selector) != null) {
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

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if (stripos($error_text, strtolower('passwor')) !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->exists($this->password_selector)) {
        sleep(3);
        $this->exts->capture_by_chromedevtool("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->click_by_xdotool($this->username_selector);
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->click_by_xdotool($this->password_selector);
        sleep(1);
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->password);
        sleep(1);

        $this->exts->capture_by_chromedevtool("2-login-page-filled");
        $this->exts->click_by_xdotool($this->submit_login_selector);

        $login_response = $this->getLoginResponse($this->username, $this->password);

        if ($login_response == 'DENIED') {
            $this->exts->loginFailure(1);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function getLoginResponse($usernameVal, $passwordVal)
{
    $usernameValue = base64_encode($usernameVal);
    $passwordValue = base64_encode($passwordVal);

    $login_response = $this->exts->executeSafeScript('
        var username = atob("' . $usernameValue . '");
        var password = atob("' . $passwordValue . '");
        
        var form_data = new FormData();
        form_data.append("logincno", username);
        form_data.append("loginpass", password);
        
        // Send login request
        var xhr = new XMLHttpRequest();
        
        xhr.open("POST", "https://kundencenter.n0q.de/ajax/login.php", false);
        
        xhr.send(form_data);

        var response_data =xhr.response;
        return  response_data;
    ');

    $this->exts->log('lOGIN RESPONSE : ' . $login_response);

    return $login_response;
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(1);
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
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
