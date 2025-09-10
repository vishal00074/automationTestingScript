public $baseUrl = 'https://my.nordaccount.com/dashboard';
public $invoicePageUrl = 'https://my.nordaccount.com/billing/billing-history/';

public $username_selector = 'input[name="identifier"]';
public $password_selector = 'input[name="password"]';
public $check_login_success_selector = 'a[href*="billing-history"], img[src*="logout.svg"], button[data-testid="Desktop__navigation-bar-user-email"]';

public $isNoInvoice = true;
public $billing_address = '';
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->billing_address = isset($this->exts->config_array["billing_address"]) ? trim($this->exts->config_array["billing_address"]) : '';
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    $this->check_solve_blocked_page();
    $this->check_solve_blocked_page();
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if (!$this->exts->exists($this->check_login_success_selector)) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        sleep(8);
        $this->check_solve_blocked_page();
        $this->checkFillLogin();
        if ($this->exts->querySelector($this->username_selector) != null) {
            $this->checkFillLogin();
        }
        sleep(5);
        $this->check_solve_blocked_page();
        $this->checkFillTwoFactor();
        $this->check_solve_blocked_page();
    }

    // then check user logged in or not
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
        $errorText = strtolower($this->exts->extract('p[role="alert"]'));

        $this->exts->log('::Error Text ' . $errorText);

        if (strpos(strtolower($this->exts->extract('form div.text-red')), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        } else if (
            stripos($errorText, 'password you entered is incorrect') !== false ||
            stripos($errorText, 'please enter a valid email address.') !== false
        ) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
private function checkFillLogin($count = 0)
{
    if ($this->exts->exists('form[action*="account/switch"] button.w-full')) {
        $this->exts->moveToElementAndClick('form[action*="account/switch"] button.w-full');
        sleep(2);
    }
    $this->exts->capture("2-login-page");
    if ($this->exts->exists($this->username_selector)) {
        sleep(1);
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->capture("2-username-filled");
        $this->exts->moveToElementAndClick('button[type="submit"]');
        sleep(3);
    }
    $this->check_solve_blocked_page();
    sleep(15);

    // Sometimes after cloudflare user is redirected to username page again.
    if ($this->exts->exists($this->username_selector)) {
        sleep(1);
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->capture("2-username-filled");
        $this->exts->moveToElementAndClick('button[type="submit"]');
        sleep(3);
        $this->check_solve_blocked_page();
        sleep(15);
    }

    if ($this->exts->exists($this->password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);
        $this->exts->capture("2-password-filled");
        $this->exts->moveToElementAndClick('button[type="submit"]');
        sleep(10);
    } else {
        $this->exts->capture("2-password-not-found");
    }

    if ($this->exts->exists($this->password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);
        $this->exts->capture("2-password-filled");
        $this->exts->moveToElementAndClick('button[type="submit"]');
        sleep(10);
    }

    if ($this->exts->exists('a[data-ga-slug="Back to login"]') && $count < 3) {
        sleep(7);
        $this->clearChrome();
        $count++;
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->checkFillLogin();
    }
    $this->exts->capture("filled-password-page");
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookies, and cache");

    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10); // Wait for the page to load

    // Capture screenshot of the clear browsing data page
    $this->exts->capture("clear-page");

    // Navigate using tab key (moving through UI elements)
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }

    // Press Tab again to focus on the dropdown menu (Time range)
    $this->exts->type_key_by_xdotool('Tab');
    sleep(1);

    // Press Enter to open the dropdown
    $this->exts->type_key_by_xdotool('Return');
    sleep(1);

    // Select "All time" option by pressing 'a' (assuming shortcut selection)
    $this->exts->type_key_by_xdotool('a');
    sleep(1);

    // Confirm selection by pressing Enter
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);

    // Capture screenshot after selection
    $this->exts->capture("clear-page");

    // Navigate further using Tab to reach the "Clear Data" button
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    // Press Enter to confirm and clear the browsing data
    $this->exts->type_key_by_xdotool('Return');
    sleep(10);
    $this->exts->capture("after-clear");
}
private function checkFillTwoFactor()
{
    $two_factor_selector = 'form>fieldset input[data-id][inputmode="numeric"]';
    $two_factor_message_selector = '.nord-container h1+p.nord-text';
    $two_factor_submit_selector = 'form button.btn-primary';

    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                $infoMessage = $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getAttribute('innerText');
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $infoMessage . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = '';
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $resultCodes = str_split($two_factor_code);
            $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
            foreach ($code_inputs as $key => $code_input) {
                if (array_key_exists($key, $resultCodes)) {
                    $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                    $code_input->sendKeys($resultCodes[$key]);
                    // $this->exts->moveToElementAndType('input:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);;

                    sleep(1);
                } else {
                    $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                }
            }
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            // $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->checkFillTwoFactor();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
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
