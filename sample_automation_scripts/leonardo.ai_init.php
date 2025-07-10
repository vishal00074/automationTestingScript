public $baseUrl = "https://app.leonardo.ai/";
public $loginUrl = "https://app.leonardo.ai/auth/login";
public $invoicePageUrl = 'https://app.leonardo.ai/buy';
public $username_selector = 'input[type="email"]';
public $password_selector = 'input[type="password"]';
public $submit_button_selector = 'button[type="submit"]';
public $check_login_failed_selector = 'div[class="chakra-stack css-q5helz"] > p';
public $check_login_success_selector = 'a[href="/settings"]';
public $login_tryout = 0;
public $isNoInvoice = true;
/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */

private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);
    $this->acceptCookies();
    $this->solveCAPTCHA();
    if ($this->exts->exists('button.chakra-modal__close-btn')) {
        $this->exts->moveToElementAndClick('button.chakra-modal__close-btn');
    }
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        $this->acceptCookies();
        $this->fillForm(0);
    }
    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        
        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        // $this->exts->waitTillPresent($this->check_login_failed_selector, 20);
        if ($this->exts->exists($this->check_login_failed_selector)) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->log("Failed due to unknown reasons");
            $this->exts->loginFailure();
        }
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    // $this->exts->waitTillPresent($this->username_selector, 20);
    for ($i = 0; $i < 10; $i++) {
        if ($this->exts->exists($this->username_selector)) {
            break;
        }
        sleep(2);
    }
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            $this->solveCAPTCHA();
            sleep(5);
            $this->exts->click_element($this->submit_button_selector);
            sleep(5); // Portal itself has one second delay after showing toast
        }
        // $this->exts->waitTillPresent($this->password_selector, 20);
        for ($i = 0; $i < 10; $i++) {
            if ($this->exts->exists($this->password_selector)) {
                break;
            }
            sleep(2);
        }
        if ($this->exts->querySelector($this->password_selector) != null) {

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            $this->solveCAPTCHA();
            sleep(5);
            $this->exts->click_element($this->submit_button_selector);
            sleep(2); // Portal itself has one second delay after showing toast
        } else {
            $this->exts->log('Failed due to unknown reasons');
            $this->exts->loginFailure();
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
        sleep(15);

        // $this->exts->waitTillAnyPresent([$this->check_login_success_selector,$this->username_selector], 15);
        for ($i = 0; $i < 10; $i++) {
            if ($this->exts->exists($this->username_selector) || $this->exts->exists($this->check_login_success_selector)) {
                break;
            }
            sleep(2);
        }
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        } else {
            if ($this->exts->exists('button[id*="menu-button"]')) {
                $this->exts->moveToElementAndClick('button[id*="menu-button"]');
                sleep(3);
                if ($this->exts->exists('//p[contains(text(),"Logout")]')) {

                    $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                    $isLoggedIn = true;
                    $this->exts->moveToElementAndClick('button[id*="menu-button"]');
                    sleep(1);
                }
            }
        }
    } catch (TypeError $e) {

        $this->exts->log("Exception checking loggedin " . $e);
        sleep(15);

        $this->exts->waitTillAnyPresent([$this->check_login_success_selector, $this->username_selector], 15);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        } else {
            if ($this->exts->exists('button[id*="menu-button"]')) {
                $this->exts->moveToElementAndClick('button[id*="menu-button"]');
                sleep(3);
                if ($this->exts->exists('//p[contains(text(),"Logout")]')) {

                    $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                    $isLoggedIn = true;
                    $this->exts->moveToElementAndClick('button[id*="menu-button"]');
                    sleep(1);
                }
            }
        }
    }

    return $isLoggedIn;
}

private function solveCAPTCHA()
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

private function acceptCookies()
{
    sleep(2);
    // $this->exts->waitTillPresent('button[title="Accept all cookies"]', 20);
    for ($i = 0; $i < 10; $i++) {
        if ($this->exts->exists('button[title="Accept all cookies"]')) {
            break;
        }
        sleep(2);
    }
    if ($this->exts->querySelector('button[title="Accept all cookies"]') != null) {
        $this->exts->click_element('button[title="Accept all cookies"]');
    }
}