public $baseUrl = 'https://myoffice.lrworld.com/';
public $loginUrl = 'https://sso.lrworld.com/cas/login?d=h&service=https%3A%2F%2Fmyoffice.lrworld.com%2Fsso';
public $invoicePageUrl = '';

public $username_selector = 'input[id="username"]';
public $password_selector = 'input[id="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[name="submit"]';

public $check_login_failed_selector = 'div#loginErrorsPanel p';
public $check_login_success_selector = 'a[href="/logout"]';

public $isNoInvoice = true;

/**
* Entry Method thats called for a portal
* @param Integer $count Number of times portal is retried.
*/
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->loadCookiesFromFile();

    sleep(10);
    // Accecpt cookies
    if($this->exts->exists('div#ccc-notify button.ccc-button-solid')){
        $this->exts->moveToElementAndClick('div#ccc-notify button.ccc-button-solid');
        sleep(7);
    }
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        // Accecpt cookies
        if($this->exts->exists('div#ccc-notify button.ccc-button-solid')){
            $this->exts->moveToElementAndClick('div#ccc-notify button.ccc-button-solid');
            sleep(7);
        }
        $this->fillForm(0);

    }
    if ($this->checkLogin()) {

        if($this->exts->exists('div#ccc-notify button.ccc-button-solid')){
            $this->exts->moveToElementAndClick('div#ccc-notify button.ccc-button-solid');
            sleep(7);
        }

        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

    } else {
        if (stripos($this->exts->extract($this->check_login_failed_selector), 'Login failed. Please check your user name and/or password.') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("1-login-page-filled");
           
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(10);
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
        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}