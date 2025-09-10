public $baseUrl = "https://www.westwingnow.de/customer/order/index/";
public $loginUrl = "https://www.westwing.de/customer/account/";
public $invoicePageUrl = "https://www.westwingnow.de/customer/order/index/";
public $username_selector = 'input#loginEmail';
public $password_selector = 'input#loginPassword';
public $submit_button_selector = 'button[data-testid="login-button"]';

public $login_tryout = 0;
public $restrictPages = 3;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        $this->exts->openUrl($this->baseUrl);
        sleep(15);

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
        }
    } else {
        $this->exts->openUrl($this->loginUrl);
    }

    if (!$isCookieLoginSuccess) {
        sleep(10);
        if ($this->exts->getElement('button#onetrust-accept-btn-handler') != null) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(5);
        }
        $this->fillForm(0);
        sleep(2);

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            if (stripos(strtolower($this->exts->extract('div[data-testid="error-notification-undefined"]')), 'passwor') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->invoicePage();
    }
}

public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        sleep(1);
        if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
            sleep(1);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            if (!$this->isValidEmail($this->username)) {
                $this->exts->log('>>>>>>>>>>>>>>>>>>>Invalid email........');
                $this->exts->loginFailure(1);
            }

            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(5);

            if (stripos(strtolower($this->exts->extract('div[data-testid="error-notification-undefined"]')), 'passwor') !== false) {
                $this->exts->account_not_ready();
            } else if (stripos($this->exts->extract('div.qa-login-passwordField-error'), 'passwor') !== false) {
                $this->exts->log($this->exts->extract('div.qa-login-passwordField-error'));
                $this->exts->loginFailure(1);
            }
        }

        sleep(20);
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

public function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $this->exts->capture('4-checkLogin');
    $isLoggedIn = false;
    try {
        if ($this->exts->exists('a.sel-menu-orders[href*="/order/"]')) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        } else if ($this->exts->exists('a[href="/customer/account/logout/"]')) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        } else {
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            if ($this->exts->exists('iframe[data-testid="alice-orders-iframe"]')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else if ($this->exts->exists('a.sel-menu-orders[href*="/order/"]')) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

public function invoicePage()
{
    if (!empty($this->exts->config_array['allow_login_success_request'])) {
        $this->exts->triggerLoginSuccess();
    }
    $this->exts->success();
}