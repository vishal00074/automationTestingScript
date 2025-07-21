public $baseUrl = "https://edfenergy.com/myaccount/login";
public $loginUrl = "https://edfenergy.com/myaccount/login";
public $homePageUrl = "https://edfenergy.com/myaccount/login";
public $login_button_selector = ".signin-interceptor";
public $billingPageUrl = "https://www.fido.ca/pages/#/my-account/view-invoice-history";
public $username_selector = "input[name=\"email\"]";
public $password_selector = "input#edit-customer-pwd, input[name='password']";
public $remember_me = "input#edit-remember-me";
public $next_button_selector = "input#edit-submit--2";
public $submit_button_selector = 'button#customer_login';
public $check_login_success_selector = '#myaccountProfile, a[href="/user/logout"], button[aria-label*="View account"]';
public $billing_selector = "a[href='/myaccount/bills-statements']";
public $more_bill_selector = ".view-more-bills-btn";
public $login_tryout = 0;
public $checkbox_coo = '';
public $current_cursor = '';


/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);


    // Load cookies

    $this->exts->loadCookiesFromFile();

    $this->exts->openUrl($this->baseUrl);
    $this->exts->waitTillPresent('button#onetrust-accept-btn-handler');

    $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
    sleep(10);

    $this->exts->click_by_xdotool('img.LPMcloseButton, div.LPMcontainer [src*="close_icons"]');
    sleep(2);

    $this->exts->capture_by_chromedevtool('1-init-page');


    for ($i = 0; $i < 6; $i++) {
        if (!$this->exts->exists($this->check_login_success_selector) && !$this->exts->exists("//*[text()='Menu']")) {
            $this->exts->log('NOT logged via cookie');
            $this->checkFillLogin();
            sleep(20);
            $this->check_solve_blocked_page();
        }
        // Check if the login was successful or failed
        if ($this->exts->getElement($this->check_login_success_selector) !== null ||  $this->exts->exists("//*[text()='Menu']")) {
            break;
        }
        $err_msg = "";
        if ($this->exts->exists("div.notification--error p, p.pswd-err , div[data-testid='disk-warning-parent']") != null || $this->exts->exists("p.ptrn-err-msg") || $this->exts->exists(".ptrn-msg-error") || $this->exts->exists("div[data-testid='disk-warning-parent']")) {
            $err_msg = trim($this->exts->extract("div.notification--error p, p.pswd-err,div#password-error-message"));
            if ($err_msg == "") {
                $err_msg = trim($this->exts->extract("p.ptrn-err-msg,div#password-error-message"));
            }
            sleep(2);
            if ($err_msg == "") {
                $err_msg = trim($this->exts->extract(".ptrn-msg-error,div#password-error-message"));
            }
        }

        if (stripos($err_msg, 'Invalid login') !== false || stripos($err_msg, "we couldn't log you in") !== false) {
            $this->exts->log("Found error message in login page : " . $err_msg);
            $this->exts->loginFailure(1);
            break;
        }
    }


    if ($this->exts->exists($this->check_login_success_selector) || $this->exts->exists("//*[text()='Menu']")) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture_by_chromedevtool("3-login-success");

        // Open invoices url and download invoice
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        sleep(15);
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $err_msg = "";
        if ($this->exts->exists("div.notification--error p, p.pswd-err , div[data-testid='disk-warning-parent']") != null || $this->exts->exists("p.ptrn-err-msg") || $this->exts->exists(".ptrn-msg-error") || $this->exts->exists("div[data-testid='disk-warning-parent']")) {
            $err_msg = trim($this->exts->extract("div.notification--error p, p.pswd-err,div#password-error-message"));
            if ($err_msg == "") {
                $err_msg = trim($this->exts->extract("p.ptrn-err-msg,div#password-error-message"));
            }
            sleep(2);
            if ($err_msg == "") {
                $err_msg = trim($this->exts->extract(".ptrn-msg-error,div#password-error-message"));
            }
        }

        if (stripos($err_msg, 'Invalid login') !== false || stripos($err_msg, "we couldn't log you in") !== false) {
            $this->exts->log("Found error message in login page : " . $err_msg);
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
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



private function checkFillLogin()
{
    if ($this->exts->exists($this->username_selector)) {
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
            sleep(3);
        }
        if ($this->exts->exists('img.LPMcloseButton, div.LPMcontainer [src*="close_icons"]')) {
            $this->exts->click_by_xdotool('img.LPMcloseButton, div.LPMcontainer [src*="close_icons"]');
            sleep(2);
        }
        $this->exts->capture_by_chromedevtool("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->click_by_xdotool($this->username_selector);
        sleep(1);
        $this->exts->type_key_by_xdotool("Delete");
        sleep(1);
        $this->exts->type_text_by_xdotool($this->username);
        sleep(2);



        $this->exts->click_by_xdotool($this->password_selector);
        sleep(1);
        $this->exts->type_key_by_xdotool("Delete");
        sleep(1);
        $this->exts->type_text_by_xdotool($this->password);
        sleep(2);
        //$this->checkFillRecaptcha();
        if ($this->exts->exists('img.LPMcloseButton, div.LPMcontainer [src*="close_icons"]')) {
            $this->exts->click_by_xdotool('img.LPMcloseButton, div.LPMcontainer [src*="close_icons"]');
            sleep(2);
        }

        $this->exts->capture_by_chromedevtool("2-username-filled");
        $this->exts->click_by_xdotool('button[type="submit"]');
        sleep(15);

        //$this->checkFillRecaptcha();

    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

