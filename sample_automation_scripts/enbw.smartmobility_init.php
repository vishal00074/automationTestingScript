public $baseUrl = 'https://smartmobility.enbw.com/stats';
public $loginUrl = 'https://smartmobility.enbw.com/welcome';
public $invoicePageUrl = 'https://smartmobility.enbw.com/profile';

public $username_selector = 'input#emailinput';
public $password_selector = 'input#passwordinput';
public $remember_me_selector = '';
public $submit_login_selector = 'button[id="loginbtn"]';

public $check_login_failed_selector = 'div.flying-wrapper__error-message';
public $check_login_success_selector = 'div.es-profile-dropdown-content-bottom a';

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
    if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
        $this->exts->click_element('button#onetrust-accept-btn-handler');
    }
    sleep(1);
    $this->exts->click_element('button.login-button');
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(1);
        $this->exts->click_element('button.login-button');
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->click_element('button#onetrust-accept-btn-handler');
        }
        $this->fillForm(0);
    }
    sleep(5);
    $this->exts->waitTillPresent('//span[text()="Profil"]', 20);
    $this->exts->click_element('//span[text()="Profil"]');
    sleep(5);

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


function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }
            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
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