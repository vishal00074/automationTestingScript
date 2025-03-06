public $baseUrl = 'https://client.mobility.totalenergies.com/group/france/invoices';
public $loginUrl = 'https://client.mobility.totalenergies.com/web/guest/home';
public $invoicePageUrl = 'https://client.mobility.totalenergies.com/group/france/invoices';

public $continue_login_button = 'form input#tec';

public $username_selector = 'form.gigya-passwordless-login-form input#fixed-username';
public $password_selector = 'form#gigya-password-auth-method-form input[type="password"]';
public $remember_me_selector = '';
public $submit_login_email_selector = 'form.gigya-passwordless-login-form input#submitLoginPasswordLess';



public $submit_login_selector = 'form#gigya-password-auth-method-form input#passwd-submit';

public $check_login_failed_selector1 = 'form#gigya-register-form input#register-firstname';
public $check_login_failed_selector2 = 'form#gigya-password-auth-method-form div.gigya-form-error-msg';


public $check_login_success_selector = 'span[class*="user-profile-img user-profile-initial"], table#cardProAccountList';

public $accounts_selector = 'table#invoiceListTable tbody tr, table#cardProAccountList tbody tr';

public $isNoInvoice = true;
/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
   
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->loadCookiesFromFile();
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();

        $this->exts->waitTillPresent($this->continue_login_button, 10);
        if ($this->exts->exists($this->continue_login_button)) {
            $this->exts->click_element($this->continue_login_button);
        }

        $this->fillForm(0);
        sleep(5);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        if($this->exts->exists($this->continue_login_button)){
             $this->exts->click_element($this->continue_login_button);
        }
        sleep(10);
    }

   

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {

            $this->exts->triggerLoginSuccess();
        }

    } else {
        if ($this->exts->exists($this->check_login_failed_selector1) || $this->exts->exists($this->check_login_failed_selector2)) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

function fillForm($count)
{
    sleep(10);
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 20);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            sleep(5);
            if ($this->exts->exists($this->submit_login_email_selector)) {
                $this->exts->click_element($this->submit_login_email_selector);
            }
            sleep(5);

            if ($this->exts->querySelector($this->username_selector) != null) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(5);
                $this->exts->click_element($this->submit_login_email_selector);
            }

            sleep(35);


            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(4);

            $this->exts->click_element('input[type="checkbox"][id="passwd-gotoprofile"]');
            sleep(4);


            $this->exts->capture("1-login-page-filled");
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_element($this->submit_login_selector);
            }
            sleep(5);
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
        $this->exts->waitTillAnyPresent([$this->check_login_success_selector], 20);
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}



