public $baseUrl = 'https://easyaccess.o2business.de/';
public $loginUrl = 'https://easyaccess.o2business.de/eCare/s/Rechnungsubersicht';
public $invoicePageUrl = 'https://easyaccess.o2business.de/eCare/s/Rechnungsubersicht';

public $username_selector = 'input[type="text"]';
public $password_selector = 'input[type="password"]';
public $submit_login_selector = '.eCareLoginBox .buttonBoxEcare button';

public $check_login_failed_selector = '.eCareLoginBox .loginErrorMessage';
public $check_login_success_selector = '#userNavItemId li a#userInfoBtnId';

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
   

    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->loginUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->checkFillLoginUndetected();
        sleep(10);
    }
   
    $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    $this->exts->capture("check-login-status");

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {

            $this->exts->triggerLoginSuccess();
        }
        
    } else {
        $isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Die E-Mail-Adresse oder das Kennwort sind falsch. Bitte prüfen Sie Ihre Eingaben. BOS Login Daten sind nicht mehr gültig, bitte registrieren Sie sich für BEA neu.")');

        $this->exts->log('isErrorMessage:'. $isErrorMessage);

        $this->exts->capture("login-failed");
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract('div.alert-message')), 'an error processing the form') !== false) {
            $this->exts->loginFailure(1);
        } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->log("Username is not a valid email address");
            $this->exts->loginFailure(1);
        } else if($isErrorMessage) {
            $this->exts->log("Die E-Mail-Adresse oder das Kennwort sind falsch. Bitte prüfen Sie Ihre Eingaben. BOS Login Daten sind nicht mehr gültig, bitte registrieren Sie sich für BEA neu.");
            $this->exts->loginFailure(1);
        } 
        else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLoginUndetected()
{
    $this->exts->capture('login-page');
    $this->exts->openUrl($this->loginUrl);
    sleep(15);
    for ($i = 0; $i < 8; $i++) {
        $this->exts->type_key_by_xdotool("Tab");
        sleep(2);
    }
    $this->exts->capture('login-page-1');

    $this->exts->log("Enter Username");
    $this->exts->type_text_by_xdotool($this->username);
    $this->exts->capture("enter-username");
    $this->exts->type_key_by_xdotool("Return");
    sleep(5);
    $this->exts->type_key_by_xdotool("Tab");
    sleep(1);
    $this->exts->log("Enter Password");
    $this->exts->type_text_by_xdotool($this->password);
    $this->exts->capture("enter-password");
    sleep(5);
    $this->exts->log("Submit Login Form");
    $this->exts->moveToElementAndClick($this->submit_login_selector);
    $this->exts->capture("submit-login");
}