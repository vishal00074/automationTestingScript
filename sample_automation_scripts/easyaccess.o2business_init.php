public $baseUrl = 'https://easyaccess.o2business.de/';
public $loginUrl = 'https://easyaccess.o2business.de/eCare/s/Rechnungsubersicht';
public $invoicePageUrl = 'https://easyaccess.o2business.de/eCare/s/Rechnungsubersicht';

public $username_selector = 'lightning-primitive-input-simple input[id="input-16"], lightning-input > lightning-primitive-input-simple[exportparts*="input-text"] >div >div >input[id=input-16], .eCareLoginBox  .slds-form-element__control input[type=text], .eCareLoginBox input[type="text"],lightning-input #input-16';
public $password_selector = 'lightning-primitive-input-simple input[id="input-17"], lightning-input > lightning-primitive-input-simple[exportparts*="input-text"] >div >div >input[id=input-17],  .eCareLoginBox  .slds-form-element__control input[type=password], .eCareLoginBox input[type="password"], lightning-input #input-17';
public $submit_login_selector = '.eCareLoginBox .buttonBoxEcare button';

public $check_login_failed_selector = '.eCareLoginBox .loginErrorMessage';
public $check_login_success_selector = '#userNavItemId li a#userInfoBtnId, div.cECareOnlineInvoice';

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
        $this->exts->openUrl($this->loginUrl);
        sleep(5);


        $this->checkFillLoginUndetected();
        sleep(10);
    }

    $this->exts->waitTillPresent($this->check_login_success_selector, 20);

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        $this->exts->capture('3-Current-page');
        $this->exts->log(__FUNCTION__ . '::Use login failed');

        $isErrorMessage = $this->exts->execute_javascript('document.body.innerHTML.includes("Die E-Mail-Adresse oder das Kennwort sind falsch. Bitte prüfen Sie Ihre Eingaben. BOS Login Daten sind nicht mehr gültig, bitte registrieren Sie sich für BEA neu.")');

        $this->exts->log('isErrorMessage:' . $isErrorMessage);

        if (trim($this->exts->extract('span.loginErrorMessage[data-aura-rendered-by="795:0"]')) != null) {
            $this->exts->log("---INVALID CREDENTIALS---");
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('div.alert-message')), 'an error processing the form') !== false) {
            $this->exts->loginFailure(1);
        } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
            $this->exts->log("Username is not a valid email address");
            $this->exts->loginFailure(1);
        } else if ($isErrorMessage) {
            $this->exts->log("Die E-Mail-Adresse oder das Kennwort sind falsch. Bitte prüfen Sie Ihre Eingaben. BOS Login Daten sind nicht mehr gültig, bitte registrieren Sie sich für BEA neu.");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLoginUndetected()
{
    $this->exts->log("Enter Username");
    $this->exts->execute_javascript("
    var input = document
                .querySelector('div[data-aura-rendered-by] lightning-input')
                ?.shadowRoot?.querySelector('lightning-primitive-input-simple')
                ?.shadowRoot?.querySelector('input[type=\"text\"]') || null;
                
        if (input) {
            input.value = '" . $this->username . "';
            input.dispatchEvent(new Event('input', { bubbles: true, composed: true }));
        }
    ");

    sleep(1);
    $this->exts->log("Enter Password");
    $this->exts->execute_javascript("
    var input = document
                .querySelector('div[data-aura-rendered-by]:nth-child(2) lightning-input')
                ?.shadowRoot?.querySelector('lightning-primitive-input-simple')
                ?.shadowRoot?.querySelector('input[type=\"password\"]') || null;
                
    if (input) {
            input.value = '" . $this->password . "';
            input.dispatchEvent(new Event('input', { bubbles: true, composed: true }));
        }
    ");

    $this->exts->capture('2-Form-Filled');
    sleep(5);

    $this->exts->log("Submit Login Form");
    $this->exts->moveToElementAndClick($this->submit_login_selector);

    sleep(10);
}