public $baseUrl = 'https://www.hpsmart.com/';
public $loginUrl = 'https://www.hpsmart.com/';
public $invoicePageUrl = '';
public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';
public $check_login_failed_selector = 'p#password-helper-text';
public $check_login_success_selector = 'button[data-testid*="avatar_menu"], div[data-testid*="organizationList"]';
public $isNoInvoice = true;

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    // $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    sleep(5);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        // $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
        }
        sleep(5);
        $this->exts->execute_javascript('
        var btn = document.querySelector("button#sign-in-link");
        if(btn){
            btn.click();
        }
    ');
        $this->fillForm(0);
    }

    $this->waitFor("button[data-testid*='continue_button']");

    if ($this->exts->exists("button[data-testid*='continue_button']")) {
        $this->exts->click_element("button[data-testid*='continue_button']");
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

function fillForm($count = 1)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            $this->exts->click_element($this->submit_login_selector);
            $this->exts->waitTillPresent($this->password_selector, 10);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }

            $this->exts->click_element($this->submit_login_selector);
            sleep(2); // Portal itself has one second delay after showing toast

        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

public  function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

private function waitFor($selector, $iterationNumber = 2)
{
    for ($wait = 0; $wait < $iterationNumber && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selector.....');
        sleep(10);
    }
}


