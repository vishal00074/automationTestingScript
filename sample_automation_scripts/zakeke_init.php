public $baseUrl = 'https://admin.zakeke.com/';
public $loginUrl = 'https://admin.zakeke.com/';
public $invoicePageUrl = 'https://admin.zakeke.com/en-US/Admin/User/?tab=payments';

public $username_selector = 'form input[type="text"]';
public $password_selector = 'form input[type="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form button[type="submit"]';

public $check_login_failed_selector = 'div.rnc__notification';
public $check_login_success_selector = 'a[href*="/Admin/Settings"]';

public $isNoInvoice = true;

public $isFailedLogin = false;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

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
        if ($this->isFailedLogin) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else if ($this->exts->urlContains('Login?error=')) {
                $this->exts->capture("LoginFailed");
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function waitFor($selector, $seconds = 10)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}


private function isExists($selector = '')
{
    $safeSelector = addslashes($selector);
    $this->exts->log('Element:: ' . $safeSelector);
    $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
    if ($isElement) {
        $this->exts->log('Element Found');
        return true;
    } else {
        $this->exts->log('Element not Found');
        return false;
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->waitFor($this->username_selector, 10);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->isExists($this->submit_login_selector)) {
                $this->exts->click_element($this->submit_login_selector);
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
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {

        $this->waitFor($this->check_login_failed_selector, 5);
        if ($this->isExists($this->check_login_failed_selector)) {
            $this->isFailedLogin = true;
        }

        $this->waitFor($this->check_login_success_selector, 15);
        if ($this->isExists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}