public $baseUrl = "https://shop.badenova.de/account";
public $loginUrl = "https://shop.badenova.de/account";
public $homePageUrl = "https://shop.badenova.de/account";
public $username_selector = 'input#login, input#email';
public $password_selector = 'input#password';
public $submit_button_selector = 'div.widget-login form button[type="submit"], form[action*="user"] button[type*="submit"]';
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
        }
    }

    if (!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");


        if ($this->exts->exists('div[class*="banner tc-privacy-popin"] button[id*="popin"]')) {
            $this->exts->log("Accept cookie");
            $this->exts->moveToElementAndClick('div[class*="banner tc-privacy-popin"] button[id*="popin"]');
            sleep(15);
        }

        $this->exts->moveToElementAndClick('div[class*="header"]  div[class*="login"] a[href*="account"]');
        sleep(5);
        $this->fillForm(0);
        sleep(10);
        if ($this->exts->exists('div[class*="SurveyInvitation"]')) {
            $this->exts->log("Close survey");
            $this->exts->moveToElementAndClick('div[class*="SurveyInvitation"] button[class*="close"]');
            sleep(15);
        }
        if ($this->exts->exists('div[class*="banner tc-privacy-popin"] button[id*="popin"]')) {
            $this->exts->log("Accept cookie");
            $this->exts->moveToElementAndClick('div[class*="banner tc-privacy-popin"] button[id*="popin"]');
            sleep(15);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->log('ONLY EMAIL NEEDED AS USERNAME');
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

function fillForm($count)
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
            // $this->exts->moveToElementAndClick('div.widget-login form a[onclick*="javascript:performLogin"]');
            sleep(10);

            $err_msg = $this->exts->extract('.form-field--password div.form-error__message');

            if ($err_msg != "" && $err_msg != null && strpos(strtolower($err_msg), 'passwor') !== false) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            }
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
        if ($this->exts->exists('a#logout, a[href*="/logout"]') && !$this->exts->exists($this->password_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

function invoicePage()
{
    if (!empty($this->exts->config_array['allow_login_success_request'])) {
        $this->exts->triggerLoginSuccess();
    }
    $this->exts->success();
}