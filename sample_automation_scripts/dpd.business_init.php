public $baseUrl = "https://business.dpd.de/";
public $loginUrl = "https://business.dpd.de/";
public $invoicePageUrl = "https://business.dpd.de/profil/meinkonto/rechnung-archiv.aspx";
public $username_selector = 'div.login_banner input#txtMasterLogin, div.div_login_formular input#txtUserLogin';
public $password_selector = 'div.login_banner input#txtMasterPasswort, div.div_login_formular input#txtUserPassword';
public $remember_selector = '';
public $submit_button_selector = 'div.login_banner div > a.home_loginbutton#CPLContentSmall_btnMasterLogin, a#CPLContentLarge_lnkLogin';
public $check_login_failed_selector = 'div.check_login_failed_selector';
public $login_tryout = 0;
public $restrictPages = 3;


/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;

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
        if ($this->exts->exists('div#tc-privacy-wrapper #popin_tc_privacy_button')) {
            $this->exts->moveToElementAndClick('div#tc-privacy-wrapper #popin_tc_privacy_button');
            sleep(2);
        }
        sleep(10);
        $this->fillForm(0);
        sleep(2);

        if ($this->exts->exists('a#cphBody_btnLayerSME_OK')) {
            $this->exts->moveToElementAndClick('a#cphBody_btnLayerSME_OK');
            sleep(1);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('a#cphBody_btnLayerSME_OK')) {
                $this->exts->moveToElementAndClick('a#cphBody_btnLayerSME_OK');
                sleep(1);
            }

            $this->invoicePage();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if ($this->exts->exists('a#cphBody_btnLayerSME_OK')) {
            $this->exts->moveToElementAndClick('a#cphBody_btnLayerSME_OK');
            sleep(1);
        }

        $this->invoicePage();
    }
}

private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        sleep(1);
        if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
            sleep(1);
            $this->login_tryout = (int) $this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            if ($this->remember_selector != '')
                $this->exts->moveToElementAndClick($this->remember_selector);

            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(10);

            $err_msg = trim($this->exts->extract('span#CPLContentLarge_labLogin_Error'));
            if (stripos($err_msg, 'passwor') !== false) {
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
private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->exists('a.btnLogout')) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

private function invoicePage()
{
    if (!empty($this->exts->config_array['allow_login_success_request'])) {
        $this->exts->triggerLoginSuccess();
    }

    $this->exts->success();
}
