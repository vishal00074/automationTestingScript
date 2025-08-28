public $baseUrl = 'https://app.foreplay.co/login';
public $loginUrl = 'https://app.foreplay.co/login';
public $invoicePageUrl = 'https://app.foreplay.co/dashboard?settings=billing';
public $username_selector = 'form input[type="email"]';
public $password_selector = 'form input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';
public $check_login_failed_selector = 'div[class*="notification"]';
public $check_login_success_selector = "a[href='/dashboard']";
public $isNoInvoice = true;
public $restrictPages = 3;
public $totalInvoices = 0;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.  

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->fillForm(0);
    }


    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false  || stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'customer number') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->waitFor($this->username_selector, 5);
    $this->waitFor($this->username_selector, 5);
    $this->waitFor($this->username_selector, 5);
    $this->waitFor($this->username_selector, 5);
    $this->waitFor($this->username_selector, 5);

    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(1);
            }

            for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('div.notification');") != 1; $wait++) {
                $this->exts->log('Waiting for selector.....');
                sleep(2);
            }

            sleep(1);
            for ($i = 0; $i < 5; $i++) {
                $this->exts->log("Login Failure : " . $this->exts->extract('div.notification'));
                if (stripos($this->exts->extract('div.notification'), 'There is no user record corresponding to this identifier') !== false || stripos($this->exts->extract('div.notification'), 'The password is invalid or the user does not have a password') !== false) {
                    $this->exts->loginFailure(1);
                } else {
                    sleep(1);
                }
            }
        }
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
        for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector(\"a[href='/dashboard']\");") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(5);
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

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}