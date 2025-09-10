public $baseUrl = "https://network.financequality.net/login";
public $loginUrl = "https://network.financequality.net/login";
public $homePageUrl = "https://network.financequality.net/login";
public $username_selector = "input#login";
public $password_selector = "input[name=\"pass\"], input#pass";
public $submit_button_selector = "form[action=\"/login\"] button, .login-btn";
public $check_login_failed_selector = ".wrong-password";
public $login_tryout = 0;


/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        $this->exts->openUrl($this->homePageUrl);
        sleep(15);
        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        }
    }

    if (!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");
        sleep(5);
        $this->fillForm(0);
        sleep(10);
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                    $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
        } else {

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);

            if (stripos($error_text, strtolower('Usernamen oder ein falsches Passwort eingegeben')) !== false) {
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
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);

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
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->getElement("a[href*='logout.do'], li.logout a[href*=\"/logout\"]") != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}
