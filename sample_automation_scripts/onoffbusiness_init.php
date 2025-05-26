public $baseUrl = 'https://admin.onoffbusiness.com/';
public $loginUrl = 'https://admin.onoffbusiness.com/login';
public $invoicePageUrl = 'https://admin.onoffbusiness.com/settings/plan-and-billing';

public $username_selector = 'input[name="email"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[data-testid="sign-in-button"]';

public $check_login_failed_selector = 'span[role="alert"]';
public $check_login_success_selector = 'button[id="my-account-menu"]';

public $isNoInvoice = true;

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
        sleep(10);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
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
    $this->exts->waitTillPresent($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_element($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("1-login-page-filled");
            sleep(2);
            $this->exts->execute_javascript('document.getElementById("login-button").click();');
            sleep(5);

            if ($this->exts->querySelector($this->submit_login_selector) != null) {
                $this->exts->type_key_by_xdotool('Return');
                sleep(1);
                $this->exts->type_key_by_xdotool('Return');
                sleep(1);
                $this->exts->type_key_by_xdotool('Return');
                sleep(1);
                $this->exts->type_key_by_xdotool('Return');
                sleep(5);
            }

            if ($this->exts->querySelector($this->submit_login_selector) != null) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(5);
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
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}