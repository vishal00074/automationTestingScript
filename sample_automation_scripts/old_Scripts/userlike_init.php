public $baseUrl = "https://www.userlike.com/de/login";
public $loginUrl = "https://www.userlike.com/de/login";
public $homePageUrl = "https://www.userlike.com/de/dashboard/company/invoice";
public $username_selector = "form input[name='username']";
public $password_selector = "form input[name='password']";
public $submit_button_selector = "form button[type='submit']";
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


    // .aptr-engagement-close-btn
    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        $this->exts->openUrl($this->homePageUrl);
        sleep(15);
        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
        }
    }

    if (!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");
        if ($this->exts->getElement('//button[contains(text(), "Abbrechen")]', null, 'xpath') != null) {
            $this->exts->getElement('//button[contains(text(), "Abbrechen")]', null, 'xpath')->click();
            sleep(2);
        }
        $this->fillForm(0);
        sleep(10);

        if (stripos($this->exts->extract('form .chakra-form__error-message', null, 'innerText'), 'the provided username or password is wrong.') !== false) {
            $this->exts->log('Error message: ' . $this->exts->extract('form .chakra-form__error-message', null, 'innerText'));
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

function fillForm($count)
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
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->getElement('a[href*="logout"], div[id*="account-menu"], #sidebar-operator-availability') != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}
