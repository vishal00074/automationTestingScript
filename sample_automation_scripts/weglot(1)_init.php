public $baseUrl = "https://dashboard.weglot.com/";
public $loginUrl = "https://dashboard.weglot.com/login";
public $invoicePageUrl = "";
public $username_selector = 'input#username';
public $password_selector = 'input#password';
public $submit_button_selector = 'button[name="login"]';
public $login_tryout = 0;
public $restrictPages = 3;
public $totalFiles = 0;
public $check_login_failed_selector = 'p[class="text-danger"]';
public $check_login_success_selector = 'a[href*="/logout"]';


/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    $this->exts->capture("Home-page-without-cookie");
    if (!$this->checkLogin()) {
        $this->clearChrome();
        $this->exts->openUrl($this->loginUrl);
        sleep(2);
        $this->fillform(0);
        $this->check_solve_blocked_page();

        // try again for form submission
        if ($this->exts->exists($this->username_selector)) {
            $this->fillform(1);
        }
    }


    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), "Wrong credentials.") !== false) {
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
    $this->exts->waitTillPresent($this->username_selector);
    try {
        sleep(seconds: 1);
        if ($this->exts->exists($this->username_selector)) {
            sleep(1);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(20); // added wait before form submission 

            $this->exts->capture("login-fill-form");

            if ($this->exts->exists($this->submit_button_selector)) {
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(10);
            }


            // check login in failure case
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), "Wrong credentials.") !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}
public function checkLogin()
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

/**
    * Clears Chrome browser history, cookies, and cache.
    * This method automates navigating to Chrome's "Clear Browsing Data" settings page 
    * and selecting the necessary options using keyboard inputs.
    */
private function clearChrome()
{

    $this->exts->log("Clearing browser history, cookies, and cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);

    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(1);

    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(10);
    $this->exts->capture("after-clear");
}

private function check_solve_blocked_page()
{
    $this->exts->capture_by_chromedevtool("blocked-page-checking");

    for ($i = 0; $i < 5; $i++) {
        if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
            $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
            $this->exts->refresh();
            sleep(10);

            $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
            sleep(15);

            if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                break;
            }
        } else {
            break;
        }
    }
}