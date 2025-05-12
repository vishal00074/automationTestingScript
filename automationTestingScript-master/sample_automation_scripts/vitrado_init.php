public $baseUrl = 'https://app.vitrado.de';
public $loginUrl = 'https://app.vitrado.de/v2/login';
public $invoicePageUrl = 'https://app.vitrado.de/v2/partner-profile/receipts';

public $username_selector = 'form.loginForm input#username';
public $password_selector = 'form.loginForm input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button[value="Login"], form.loginForm input[type="submit"]';

public $check_login_failed_selector = 'div.callout-danger p';
public $check_login_success_selector = 'li a[href*="/partner-profile"]';

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

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);


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
        
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector)), strtolower('Ein Fehler ist aufgetreten')) !== false) {
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
    $this->exts->waitTillPresent($this->username_selector, 15);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->check_solve_cloudflare_page();

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("1-login-page-filled");
            sleep(5);
            $this->check_solve_cloudflare_page();
            sleep(5);

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function check_solve_cloudflare_page() {
    $unsolved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) <= 0]';
    $solved_cloudflare_input_xpath = '//input[starts-with(@name, "cf") and contains(@name, "response") and string-length(@value) > 0]';
    $this->exts->capture("cloudflare-checking");
    if(
        !$this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath]) && 
        $this->exts->exists('#cf-please-wait > p:not([style*="display: none"]):not([style*="display:none"])')
    ){
        for ($waiting=0; $waiting < 10; $waiting++) {
            sleep(2);
            if($this->exts->oneExists([$solved_cloudflare_input_xpath, $unsolved_cloudflare_input_xpath])){
                sleep(3);
                break;
            }
        }
    }

    if($this->exts->exists($unsolved_cloudflare_input_xpath)){
        $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
        sleep(5);
        $this->exts->capture("cloudflare-clicked-1", true);
        sleep(3);
        if($this->exts->exists($unsolved_cloudflare_input_xpath)){
            $this->exts->click_by_xdotool('*:has(>input[name^="cf"][name$="response"])', 30, 28);
            sleep(5);
            $this->exts->capture("cloudflare-clicked-2", true);
            sleep(15);
        }
        if($this->exts->exists($unsolved_cloudflare_input_xpath)){
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
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}