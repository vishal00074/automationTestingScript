public $baseUrl = 'https://www.laposte.fr';
public $loginUrl = 'https://lastation.laposte.fr/private-access/factures';
public $invoicePageUrl = 'https://lastation.laposte.fr/private-access/factures';

public $username_selector = 'input[name="AUTHENTICATION.LOGIN"]';
public $password_selector = 'input[name="AUTHENTICATION.PASSWORD"]';
public $remember_me_selector = '';
public $submit_login_selector = 'input[name="validateButton"]';

public $check_login_failed_selector = 'div.msgErr';
public $check_login_success_selector = 'a[href$="logout"]';

public $isNoInvoice = true;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->loginUrl);
    $this->exts->loadCookiesFromFile();

    $this->exts->waitTillPresent('button#footer_tc_privacy_button_2', 10);
    if ($this->exts->exists('button#footer_tc_privacy_button_2')) {
        $this->exts->click_element('button#footer_tc_privacy_button_2');
    }

    $this->exts->waitTillPresent('form#form-redirection2IA button', 5);
    if ($this->exts->exists('form#form-redirection2IA button')) {
        $this->exts->click_element('form#form-redirection2IA button');
    }

    if ($this->checkLogin()) {

        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

    }else{
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);

        $this->exts->waitTillPresent('button#footer_tc_privacy_button_2', 10);
        if ($this->exts->exists('button#footer_tc_privacy_button_2')) {
            $this->exts->click_element('button#footer_tc_privacy_button_2');
        }

        $this->exts->waitTillPresent('form#form-redirection2IA button', 5);
        if ($this->exts->exists('form#form-redirection2IA button')) {
            $this->exts->click_element('form#form-redirection2IA button');
        }

        $this->fillForm(0);
    

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {

                $this->exts->triggerLoginSuccess();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');

            $this->exts->capture("login-failed");

            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
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
            
            $this->exts->log("Click Validate Button");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_element($this->submit_login_selector);
            }
            sleep(5);
            $this->exts->capture("username-page-filled");
        }
        if ($this->exts->querySelector($this->password_selector) != null) { 
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);

            sleep(2);
            
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_element($this->submit_login_selector);
                sleep(5);
            }
            $this->exts->capture("1-login-page-filled");
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