public $baseUrl = 'https://www.alditalk-kundenbetreuung.de';
public $loginUrl = 'https://www.alditalk-kundenbetreuung.de';
public $invoicePageUrl = 'https://www.alditalk-kundenportal.de/portal/auth/postfach';

public $username_selector = '[id="idToken3_od"]';
public $password_selector = 'one-input[type="password"]';
public $remember_me_selector = '[id="remember_od"]';
public $submit_login_selector = '[id="IDToken5_4_od_2"]';

public $check_login_failed_selector = 'div#one-messages one-stack, [id="errorMsg"]';
public $check_login_success_selector = 'div#accountInfo';

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
    sleep(10);
    $this->exts->execute_javascript('
    var shadow = document.querySelector("#usercentrics-root");
    if(shadow){
        shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
    }
');

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->fillForm(0);
        sleep(10);
    }
    $this->exts->execute_javascript('
    var shadow = document.querySelector("#usercentrics-root");
    if(shadow){
        shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
    }
');

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwort') !== false) {
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
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            sleep(1);
            $this->exts->type_text_by_xdotool($this->username);

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(1);
            $this->exts->type_text_by_xdotool($this->password);
            // $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->isExists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }

            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(2); // Portal itself has one second delay after showing toast
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function isExists($selector = '')
{
    $safeSelector = addslashes($selector);
    $this->exts->log('Element:: ' . $safeSelector);
    $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
    if ($isElement) {
        $this->exts->log('Element Found');
        return true;
    } else {
        $this->exts->log('Element not Found');
        return false;
    }
}


/**

    * Method to Check where user is logged in or not

    * return boolean true/false

    */
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        sleep(5);
        if ($this->isExists($this->check_login_success_selector) || count($this->exts->queryXpathAll("//one-button[@variant='outline' and @color='default' and @size='medium' and text()='Abmelden']")) != 0) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}

public function waitFor($selector, $seconds = 7)
{
    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
        $this->exts->log('Waiting for Selectors.....');
        sleep($seconds);
    }
}