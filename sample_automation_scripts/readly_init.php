public $baseUrl = "https://de.readly.com/accounts/subscriptions";
public $loginUrl = "https://de.readly.com/accounts/login";
public $username_selector = '#login_form input[name="account[email]"]';
public $password_selector = '#login_form input[name="account[password]"]';
public $submit_button_selector = '#login_form [type="submit"]';
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
    sleep(5);

    if ($this->exts->exists('#cookie-accept-all')) {
        $this->exts->moveToElementAndClick('#cookie-accept-all');
        sleep(1);
    }

    if ($this->exts->exists('div[data-testid="cookies-dialog-accept-all"]>button')) {
        $this->exts->moveToElementAndClick('div[data-testid="cookies-dialog-accept-all"]>button');
        sleep(1);
    }
    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);

            if ($this->exts->exists('#cookie-accept-all, div[class*="CookieConsentButtonContainer"]')) {
                $this->exts->moveToElementAndClick('#cookie-accept-all, div[class*="CookieConsentButtonContainer"]');
                sleep(1);
            }
        }
    }

    if (!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");

        $this->fillForm(0);
        sleep(10);
        if ($this->exts->exists('div.alert-danger')) {
            $this->exts->log("Message after login: " . $this->exts->extract('div.alert-danger', null, 'innerText'));
            $this->exts->capture("after-login-submited");
            if ($this->exts->exists('div#cookie-accept-all, div[class*="CookieConsentButtonContainer"]')) {
                $this->exts->moveToElementAndClick('div#cookie-accept-all, div[class*="CookieConsentButtonContainer"]');
                sleep(1);
            }
            // For this user the site requires login to another url.
            $mesg = strtolower($this->exts->extract('div.alert-danger', null, 'innerText'));
            if (strpos($mesg, 'you have been redirected to the home page of the account\'s country. please log in again.') !== false || strpos($mesg, 'du wurdest auf die login-seite deines landes weitergeleitet. bitte logge dich erneut ein') !== false) {
                $this->fillForm(0);
                sleep(10);
            }
        }


        // if($this->exts->getElement('div.myAccount[style*="display: none;"]') != null) {
        // 	$this->exts->loginFailure();
        // }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            c

            $this->exts->success();
        } else {

            $err_msg1 = $this->exts->extract('form[id="login_form"]  div[class="disclaimer v2 inline-flash-container red"]');
            if ($err_msg1 !== null && strpos(strtolower($err_msg1), 'ungültige anmeldedaten') !== false) {
                if (strpos(strtolower($err_msg1), 'ungültige anmeldedaten') === 0 || strpos(strtolower($err_msg1), 'ungültige anmeldedaten') > 0) {
                    $this->exts->log($err_msg1);
                    $this->exts->loginFailure(1);
                }
            }

            if ($this->exts->exists('.disclaimer p') && strpos(strtolower($this->exts->extract('.disclaimer p')), 'passwor') !== false) {
                $this->exts->log("Login failed!!!! " . $this->exts->extract('.disclaimer p'));
                $this->exts->loginFailure(1);
            } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
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
        $this->exts->moveToElementAndClick('a.main-header-button');
        sleep(5);
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(3);
            $this->exts->capture("1-pre-login-form");
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(3);

            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(5);
        }

        sleep(5);
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
        if ($this->exts->getElement('input[id="account_email"]') != null) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}