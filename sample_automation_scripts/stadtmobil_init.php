public $baseUrl = 'https://mein.stadtmobil.de/';
public $loginUrl = 'https://mein.stadtmobil.de/';
public $invoicePageUrl = 'https://mein.stadtmobil.de/';
public $username_selector = 'input#login_username';
public $password_selector = 'input#login_password';
public $remember_me_selector = 'input#cbx_storeLogin';
public $submit_login_selector = 'button.login__submit';
public $check_login_failed_selector = 'input#login_password.field__error';
public $check_login_success_selector = "li[class*='logout']:not([style*='display: none'])";
public $isNoInvoice = true;
public $restrictPages = 3;
public $totalInvoices = 0;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(1);

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    $this->waitForSelectors($this->check_login_success_selector, 20, 2);
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->waitForSelectors("button.menu__button--login", 10, 2);
        if ($this->exts->querySelector('button.menu__button--login') != null) {
            $this->exts->moveToElementAndClick('button.menu__button--login');
            sleep(2);
        }
        $this->checkFillLogin();
    }

    // then check user logged in or not
    if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->loginFailure(1);
    }
    sleep(20);
    $this->waitForSelectors($this->check_login_success_selector, 20, 2);
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        $this->exts->loginFailure();
    }
}

private function changeSelectbox($select_box = '', $option_value = '')
{
    $this->exts->waitTillPresent($select_box, 10);
    if ($this->exts->exists($select_box)) {
        $option = $select_box . ' option[value="' . $option_value . '"]';
        $this->exts->log('Option Box : ' . $option);
        $this->exts->click_element($select_box);
        sleep(1);
        if ($this->exts->exists($option)) {
            $this->exts->log('Select box Option exists');
            try {
                $this->exts->execute_javascript(
                    'var select = document.querySelector("' . $select_box . '"); 
                if (select) {
                    select.value = "' . $option_value . '";
                    select.dispatchEvent(new Event("change", { bubbles: true }));
                }'
                );
            } catch (\Exception $e) {
                $this->exts->log('JavaScript selection failed, error: ' . $e->getMessage());
            }

            sleep(3);
        } else {
            $this->exts->log('Select box Option does not exist');
        }
    } else {
        $this->exts->log('Select box does not exist');
    }
}


private function checkFillLogin()
{
    $this->waitForSelectors($this->password_selector, 10, 2);
    if ($this->exts->querySelector($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);
        $client_region = isset($this->exts->config_array["client_region"]) ? (int)@$this->exts->config_array["client_region"] : 88;
        if ($this->exts->querySelector('select.login__select') != null) {
            $this->changeSelectbox('select.login__select', $client_region, 2);
        }
        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);

        $this->exts->waitTillPresent($this->check_login_failed_selector, 10);
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->loginFailure(1);
        }

        if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        }
        if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        }
        if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}




private function waitForSelectors($selector, $max_attempt, $sec)
{
    for (
        $wait = 0;
        $wait < $max_attempt && $this->exts->executeSafeScript("return !!document.querySelector(\"" . $selector . "\");") != 1;
        $wait++
    ) {
        $this->exts->log('Waiting for Selectors!!!!!!');
        sleep($sec);
    }
}