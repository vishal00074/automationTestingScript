public $baseUrl = 'https://www.dbschenker.com/app/dashboard/';
public $loginUrl = 'https://www.dbschenker.com/nges-portal/api/login';
public $invoicePageUrl = '';

public $username_selector = 'input[name="identifier"]';
public $password_selector = 'input[name="userpassword"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form button[name="login"]';

public $check_login_failed_selector = 'div.ping-error';
public $check_login_success_selector = 'a[id="sims-menu-desktop-signout"]';

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
    if($this->exts->exists('menu.privacy-actions')){
        $this->exts->moveToElementAndClick('menu.privacy-actions button[data-accept-all-privacy-settings]');
        sleep(7);
    }

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        // Accecpt cookies
        if($this->exts->exists('menu.privacy-actions')){
            $this->exts->moveToElementAndClick('menu.privacy-actions button[data-accept-all-privacy-settings]');
            sleep(7);
        }
        $this->fillForm(0);

    }

    $this->exts->waitTillPresent('div.privacy-modal button.primary span.mdc-button__label');

    if($this->exts->exists('div.privacy-modal button.primary span.mdc-button__label')){
        $this->exts->moveToElementAndClick('div.privacy-modal button.primary span.mdc-button__label');
        sleep(7);
    }


    $this->exts->openUrl('https://sims.dbschenker.com/');
    sleep(5);


    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

    } else {
        if (stripos($this->exts->extract($this->check_login_failed_selector), 'Sorry! Your sign in data is incorrect. Please try again.') !== false) {
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

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(5);
            }
            $this->exts->waitTillPresent('li[class="device password"]');
            if ($this->exts->exists('li[class="device password"]')) {
                $this->exts->click_by_xdotool('li[class="device password"]');
                sleep(2);
            }
            

            $this->exts->waitTillPresent($this->password_selector);
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