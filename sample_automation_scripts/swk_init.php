public $baseUrl = 'https://www.swk.de/privatkunden/kundenportal';
public $loginUrl = 'https://www.swk.de/privatkunden/kundenportal';
public $invoiceUrl = 'https://www.swk.de/privatkunden/de/kundenportal/postfach?documentType=BILL';
public $check_login_success_selector = 'a[href*="/abmelden"], div[data-component-name="UserAvatar"]';
public $submit_btn = "//button[normalize-space(text())='Einloggen']";
public $username_selector = 'form input[name="username"] , #loginform input[type="text"]';
public $password_selector = 'form input[name="password"] , #loginform input[type="password"]';
public $logout_link = '#logout';
public $restrictPages = 0;
public $isNoInvoice = true;

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->exts->openUrl($this->baseUrl);

    sleep(4);
    $this->exts->capture("Home-page-without-cookie");
    $this->exts->clearCookies();
    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture("Home-page-with-cookie");
        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->clearCookies();
            $this->exts->log("initPortal::cookie is useless now. clear it");
        }
    }


    $this->exts->execute_javascript('
        var shadow = document.querySelector("#usercentrics-root");
        if(shadow){
            shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
        }
    ');

    if (!$isCookieLoginSuccess) {
        $this->exts->openUrl($this->loginUrl);
        sleep(2);
        $this->fillForm(0);
        sleep(10);
        $this->exts->execute_javascript('
        var shadow = document.querySelector("#usercentrics-root");
        if(shadow){
            shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
        }
    ');
        $this->exts->waitTillPresent("//button[.//span[normalize-space(text())='Kundenportal']]", 15);
        if ($this->exts->exists("//button[.//span[normalize-space(text())='Kundenportal']]")) {
            $this->exts->click_element("//button[.//span[normalize-space(text())='Kundenportal']]");
        }
        sleep(5);

        $this->exts->capture("after-login");
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
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
public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->capture("pre-fill-login");
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
        }

        if ($this->exts->querySelector($this->password_selector) != null) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
        }
        sleep(5);
        $this->exts->capture("post-fill-login");
        $this->exts->moveToElementAndClick($this->submit_btn);
        sleep(10);
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        sleep(10);
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}