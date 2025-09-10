public $baseUrl = "https://www.banggood.com/login.html";
public $loginUrl = "https://www.banggood.com/login.html";
public $homePageUrl = "https://www.banggood.com/index.php?com=account&t=ordersList";
public $username_selector = "input#login-email";
public $password_selector = "input#login-pwd";
public $submit_button_selector = "input#login-submit";
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
        $this->exts->openUrl($this->homePageUrl);
        sleep(15);
        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
        }
    }

    if (!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");

        $this->fillForm(0);
        sleep(10);
        $error_text = strtolower($this->exts->extract('p.login-email-tip'));
        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if (stripos($error_text, strtolower('No match for E-Mail Address and/or Password')) !== false) {
            $this->exts->loginFailure(1);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }
        
        $this->exts->success();
    }
}

public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        sleep(5);
        if ($this->exts->getElement($this->username_selector) != null || $this->exts->getElement($this->password_selector) != null) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            if ($this->exts->getElement($this->username_selector) != null) {
                $this->exts->log("Enter Username");

                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
            }

            if ($this->exts->getElement($this->password_selector) != null) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(5);
            }
            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(10);
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
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->getElement('a[href*="logout"]') != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}