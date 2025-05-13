public $baseUrl = 'https://portal.jobrad.org/login.html';
public $loginUrl = 'https://portal.jobrad.org/login.html';
public $invoicePageUrl = 'https://fachhandel.jobrad.org/supplier/orders';

public $username_selector = 'input[id*=eMailAddressr0]';
public $password_selector = 'input[id*=passwordr1]';
public $remember_me_selector = '';
public $submit_login_selector = 'j-form button.j-button';

public $check_login_failed_selector = 'j-group[name*=failed]';
public $check_login_success_selector = 'a[href*=logout],div[class*=logout]';

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
    if ($this->exts->exists('button[id*=AllowAll]')) {
    $this->exts->click_element('button[id*=AllowAll');
    }
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        if ($this->exts->exists('button[id*=AllowAll')) {
        $this->exts->click_element('button[id*=AllowAll');
        }
        $this->fillForm(0);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {

            $this->exts->triggerLoginSuccess();
        }

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
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }
            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                for ($i = 0; $i < 5; $i++) {
                    if ($this->exts->exists($this->username_selector)) {
                        $this->checkFillRecaptcha();
                    } else {
                        break;
                    }
                }
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                    sleep(5);
                }
                $this->exts->click_by_xdotool($this->submit_login_selector);


            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}


private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'div.j-form__re-captcha-fallback iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    $this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

        if ($isCaptchaSolved) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            if ($count < 3) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
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
    $this->exts->waitTillPresent('button[id*= "headlessui"]', 10);

    if ($this->exts->exists('button[id*= "headlessui"]')) {
        $this->exts->moveToElementAndClick('button[id*= "headlessui"]');
        sleep(4);
    }
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 10);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}