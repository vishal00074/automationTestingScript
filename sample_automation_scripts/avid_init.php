public $baseUrl = "https://my.avid.com/account/orientation?websource=avid";
public $loginUrl = "https://my.avid.com/account/orientation?websource=avid";
public $homePageUrl = "https://my.avid.com/products/orderhistory";
public $username_selector = 'input[name="Email"]';
public $password_selector = 'input[name="Password"]';
public $submit_button_selector = 'button#login';
public $login_tryout = 0;
public $restrictPages = 3;
public $isNoInvoice = true;
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
        $this->exts->openUrl($this->homePageUrl);
        sleep(15);

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            //$this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
        }
    }

    if ($this->exts->exists('#onetrust-group-container')) {
        $this->exts->moveToElementAndClick('#onetrust-accept-btn-container > button#onetrust-accept-btn-handler');
        sleep(5);
    }


    if (!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");

        $this->fillForm(0);
        sleep(10);

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            if ($this->exts->getElement('#onetrust-group-container') != null) {
                $this->exts->moveToElementAndClick('#onetrust-accept-btn-container > button#onetrust-accept-btn-handler');
            }
            $this->invoicePage();
        } else {
            $err_msg = $this->exts->extract('span.notification-message');
            if ($err_msg != "" && $err_msg != null && $this->exts->exists($this->password_selector)) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
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
        sleep(5);
        if ($this->exts->exists($this->username_selector) || $this->exts->exists($this->password_selector)) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            $this->exts->moveToElementAndClick($this->submit_button_selector);

            sleep(8);
        }

        sleep(10);
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}


/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->exists('a[href*="/logout"]') && !$this->exts->exists($this->password_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
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
