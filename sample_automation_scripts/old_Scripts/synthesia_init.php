public $baseUrl = 'https://app.synthesia.io/';
public $loginUrl = 'https://app.synthesia.io/';
public $invoicePageUrl = '';

public $username_selector = 'input#email';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button[data-testid="login-submit-button"]';

public $check_login_failed_selector = 'div[role="alert"]';
public $check_login_success_selector = 'div[data-userflowid="workspace-picker"]';

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
    for ($wait = 0; $wait < 5 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->username_selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for login.....');
        sleep(5);
    }
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->fillForm(0);
    }


    for ($wait = 0; $wait < 5 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for login.....');
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


function fillForm($count)
{
    // Wait Till Present and exists does not solve this as elements are in DOM but not accesible. Throwws client readout time exception

    $this->exts->log("Begin fillForm " . $count);
    // sleep(15);
    // $this->exts->waitTillPresent($this->password_selector);
    // for ($wait = 0; $wait < 15 && !$this->exts->exists($this->password_selector); $wait++) {
    //     $this->exts->log('Waiting for login.....');
    //     sleep(10);
    // }

    for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->username_selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for selector.....');
        sleep(10);
    }

    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(5);
            for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->submit_login_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            $this->exts->execute_javascript("arguments[0].click();", [$this->exts->querySelector($this->submit_login_selector)]);
            // $this->exts->click_element($this->submit_login_selector);
            // sleep(5);
            // for ($wait = 0; $wait < 15 && !$this->exts->exists($this->password_selector); $wait++) {
            //     $this->exts->log('Waiting for login.....');
            //     sleep(10);
            // }
            // sleep(5);
            for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->password_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(2);
            $this->exts->type_text_by_xdotool($this->password);
            // $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }

            for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->submit_login_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            $this->exts->execute_javascript("arguments[0].click();", [$this->exts->querySelector($this->submit_login_selector)]);
            sleep(2); // Portal itself has one second delay after showing toast
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
        // sleep(15);
        // for ($wait = 0; $wait < 15 && !$this->exts->exists($this->check_login_success_selector) && !$this->exts->exists($this->username_selector); $wait++) {
        //     $this->exts->log('Waiting for login.....');
        //     sleep(10);
        // }

        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        } else if ($this->exts->getElement("//button[normalize-space(text())='Log out']", null, 'xpath')) {
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}