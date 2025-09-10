public $baseUrl = 'https://www.uline.ca';
public $loginUrl = 'https://www.uline.ca/SignIn/SignIn';
public $username_selector = 'form#signinForm input[name="txtEmail"]';
public $password_selector = 'form#signinForm input[name="txtPassword"]';
public $remember_me_selector = '';
//public $submit_next_selector = '.auth-content-inner form input[type="submit"]';

public $submit_login_selector = 'form#signinForm input#btnSignIn';

public $check_login_success_selector = '.account-group.invoices-group ul li:first-child a.myulinelink';
public $check_login_failed_selector = 'form#signinForm span.messageListWarning';

public $isNoInvoice = true;
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');

        if ($this->exts->exists('div#CountrySelectionModal')) {
            $this->exts->log('click country');
            $this->exts->moveToElementAndClick('a[data-country-code="CA"]');
        }
        sleep(10);
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
        $this->checkFillLogin();
        sleep(10);
    }



    // then check user logged in or not
    if ($this->checkLogin()) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in ' . $this->exts->getUrl());
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

        $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
        if (stripos($error_text, strtolower('incorrect')) !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin()
{

    if ($this->exts->exists($this->username_selector)) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);

        sleep(1);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);


        $this->exts->capture("failed_login_screen");

        sleep(5);
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
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
