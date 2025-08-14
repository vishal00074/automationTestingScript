public $baseUrl = 'https://immowelt-customerportal.de/Pages/invoiceportal/default.aspx';
public $loginUrl = 'https://immowelt-customerportal.de/Pages/invoiceportal/default.aspx';
public $invoicePageUrl = 'https://immowelt-customerportal.de/Pages/invoiceportal/default.aspx';

public $username_selector = 'div[class*="signin-"] input[id*="_UserName"]';
public $password_selector = 'div[class*="signin-"] input[id*="_password"]';
public $remember_me = 'div[class*="signin-"] input[id*="_signInControl_Checkbox1"]';
public $submit_login_selector = 'div[class*="signin-"] input[id*="_signInControl_login"]';

public $check_login_failed_selector = '.eCareLoginBox .loginErrorMessage';
public $check_login_success_selector = 'div.navbar-header ul li a[href="/_layouts/CustomLogout/SignOut.aspx"]';

public $isNoInvoice = true;
public $restrictPages = 3;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->log('restrictPages:: ' . $this->restrictPages);

    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->loginUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->checkFillLogin();
        sleep(20);
    }


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
        if (strpos(strtolower($this->exts->extract('div.alert-message')), 'an error processing the form') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('span[id*="signInControl_FailureText"] center')), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->log("Username is not a valid email address");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{
    if ($this->exts->getElement($this->password_selector) != null) {

        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me != '') {
            $this->exts->moveToElementAndClick($this->remember_me);
            sleep(2);
        }

        $this->exts->capture("2-login-page-filled");
        sleep(5);
        $this->exts->moveToElementAndClick($this->submit_login_selector);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}