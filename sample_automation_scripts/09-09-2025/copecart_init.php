public $baseUrl = 'https://www.copecart.com/users/sign_in';
public $loginUrl = 'https://www.copecart.com/users/sign_in';
public $invoiceUrl = 'https://www.copecart.com/payouts';
public $username_selector = '#user_email';
public $password_selector = 'input#user_password';
public $submit_btn = 'input[name="commit"]';
public $login_link_selector = 'a[href*="user/signin"]';
public $logout_link = 'a[href*="users/sign_out"]';
public $login_error_selector = '.dc-control--error';
public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
    $this->exts->openUrl($this->baseUrl);
    sleep(4);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    sleep(7);
    $isCookieLoginSuccess = false;

    if ($this->checkLogin()) {
        $isCookieLoginSuccess = true;
    }

    if (!$isCookieLoginSuccess) {

        $this->exts->capture("Home-page-without-cookie");
        $this->exts->clearCookies();

        $login_link = $this->exts->querySelector($this->login_link_selector);
        if ($login_link != null) {
            $this->exts->moveToElementAndClick($this->login_link_selector);
        } else {
            $this->exts->log("initPortal:: could not click on login link, try opening login URL");
            $this->exts->openUrl($this->loginUrl);
            sleep(2);
        }

        $this->fillForm(0);
        sleep(5);

        $this->exts->capture("after-login");
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
        } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->log("Username is not a valid email address");
            $this->exts->loginFailure(1);
        } else if ($this->exts->querySelector($this->login_error_selector) != null) {
            $this->exts->log("Email or password incorrect!!!");
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('form.edit_user')) {
            //New fee structure
            $this->exts->account_not_ready();
        } else {
            $this->exts->log(">>>>>>>>>>>>>> after-login check failed!!!!");
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    } else {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful with cookie!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
        $this->exts->success();
    }
}

/**
    * Method to fill login form
    * @param Integer $count Number of times portal is retried.
    */

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->capture("pre-fill-login");
    $this->exts->querySelector($this->username_selector);
    try {
        if ($this->exts->exists($this->username_selector)) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
        }
        if ($this->exts->exists($this->password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
        }
        sleep(8);
        $this->exts->capture("post-fill-login");
        $this->exts->moveToElementAndClick($this->submit_btn);
        sleep(10);
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */

function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {

        $isLoginForm = $this->exts->querySelector($this->username_selector);
        if (!$isLoginForm) {
            if ($this->exts->querySelector($this->logout_link) != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful 1!!!!");
                $isLoggedIn = true;
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}