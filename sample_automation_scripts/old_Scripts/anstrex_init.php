public $baseUrl = "https://app.anstrex.com/login";
public $loginUrl = "https://app.anstrex.com/login";
public $homePageUrl = "https://native.anstrex.com/listing";
public $username_selector = "input[name=email]";
public $password_selector = "input[name=password], input.p-password-input";
public $submit_button_selector = '#loginPage .btn-login, form[action="/login"] button[type="submit"]';
public $login_confirm_selector = '.member-name, a[href*="logout"], li[aria-label="Log Out"]';
public $billingPageUrl = "https://my.leadpages.net/#/my-pages";
public $account_selector = "a[href=\"/my-account/\"]";
public $billing_selector = "a[href=\"https://app.anstrex.com/subscription_info\"]";
public $billing_history_selector = "a[href=\"/my-account/billing-history/\"]";
public $dropdown_selector = "#img_DropDownIcon";
public $dropdown_item_selector = "#di_billCycleDropDown";
public $more_bill_selector = ".view-more-bills-btn";
public $login_tryout = 0;
public $isNoInvoice = true;
public $numberInvoices = 0;


/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->loadCookiesFromFile();
    $this->check_solve_blocked_page();

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();

        $this->exts->openUrl($this->baseUrl);
        $this->check_solve_blocked_page();
        $this->fillForm(0);
        sleep(15);
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
            if ($this->exts->urlContains('subscription_info_new') && $this->exts->exists('#checkoutChargebeeForm button[name="submit"]')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->getElementByText('.alert-danger li', 'user does not exist', null, false) != null) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->getElementByText('.alert-danger li', 'credentials do not match our records', null, false) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    }
}

private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        $this->exts->type_key_by_xdotool("F5");
        sleep(7);
        $this->check_solve_blocked_page();
        if ($this->exts->querySelector($this->password_selector) != null) {
            // $this->exts->capture_by_chromedevtool("2-login-page");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->username);
            sleep(5);
            // $this->exts->capture_by_chromedevtool("2-login-page-filled");
            $this->exts->log("Click submit button");
            $this->exts->click_by_xdotool($this->submit_button_selector);
            sleep(1);

            if ($this->exts->querySelector('div#swal2-html-container') != null) {
                $this->exts->loginFailure(1);
            }
            sleep(1);
            if ($this->exts->querySelector('div#swal2-html-container') != null) {
                $this->exts->loginFailure(1);
            }
            sleep(1);
            if ($this->exts->querySelector('div#swal2-html-container') != null) {
                $this->exts->loginFailure(1);
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function check_solve_blocked_page()
{
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if (
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) &&
        $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ) {
        for ($waiting = 0; $waiting < 10; $waiting++) {
            sleep(2);
            if ($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])) {
                sleep(3);
                break;
            }
        }
    }

    if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-2", true);
            sleep(15);
        }
        if ($this->exts->exists($unsolved_cloudflare_input_xpath)) {
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-3", true);
            sleep(15);
        }
    }
}

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->exists('div.p-avatar-clickable')) {
            $this->exts->moveToElementAndClick('div.p-avatar-clickable');
            sleep(2);
        }
        if ($this->exts->exists($this->login_confirm_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}