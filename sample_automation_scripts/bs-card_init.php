public $baseUrl = "https://portal.bs-card-service.com/login";
public $loginUrl = "https://portal.bs-card-service.com/login";
public $homePageUrl = "https://www.bs-service-portal.com/customer/default.aspx?Kanal=customer/Rech_Netz";
public $contract_number_selector = "form#aspnetForm input[name=\"ctl00\$CC\$ctl00\$I\$ctl00\$ctl00\$T1\"]";
public $username_selector = "form#aspnetForm input[name=\"ctl00\$CC\$ctl00\$I\$ctl00\$ctl01\$T1\"]";
public $username_selector_1 = "form[action*=\"/login?\"] input[name=\"User\"]";
public $password_selector = "form#aspnetForm input[name=\"ctl00\$CC\$ctl00\$I\$ctl00\$ctl02\$T1\"]";
public $password_selector_1 = "form[action*=\"/login?\"] input[name=\"Password\"]";
public $submit_button_selector = "form#aspnetForm input[type=\"submit\"]";
public $submit_button_selector_1 = "form[action*=\"/login?\"] button[type=\"submit\"]";
public $login_tryout = 0;
public $contract_number = "";

public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->contract_number = isset($this->exts->config_array["contract_number"]) ? (int)@$this->exts->config_array["contract_number"] : "";

    $this->exts->log($this->contract_number);

    $this->exts->openUrl($this->baseUrl);
    sleep(7);
    $this->exts->capture("Home-page-without-cookie");

    $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-root");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
            }
        ');

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        $this->exts->openUrl($this->homePageUrl);
        sleep(10);

        $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-root");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
            }
        ');
        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(7);
            $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-root");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
            }
        ');
        }
    }

    if (!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");

        $this->fillForm(0);
        sleep(10);

        $err_msg = "";
        if ($this->exts->getElement("div.wrong-login-data span.field-validation-error") != null) {
            $err_msg = trim($this->exts->getElements("div.wrong-login-data span.field-validation-error")[0]->getText());
        }

        if ($err_msg != "" && $err_msg != null) {
            $this->exts->log($err_msg);
            $this->exts->loginFailure(1);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
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
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Contract Number");
            $this->exts->moveToElementAndType($this->contract_number_selector, $this->contract_number);
            sleep(2);

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);

            $this->exts->moveToElementAndClick($this->submit_button_selector);

            sleep(10);
        } else if ($this->exts->getElement($this->username_selector_1) != null) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector_1, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector_1, $this->password);
            sleep(5);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_button_selector_1);

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
        if ($this->exts->getElement("a.mmpMenuLogout, div.user-menu a[href*=\"/Logout/\"]") != null) {
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