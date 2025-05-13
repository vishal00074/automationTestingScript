public $baseUrl = 'https://www.wir-machen-druck.de/konto_tracking_list.htm';
public $loginUrl = 'https://www.wir-machen-druck.de/konto_tracking_list.htm';
public $invoicePageUrl = 'https://www.wir-machen-druck.de/konto_tracking_list.htm';

public $username_selector = 'input[name="kundennummer"]';
public $password_selector = 'input#passwort';
public $remember_me_selector = '';
public $submit_login_selector = 'button[name=Submit]';

public $username_alt_selector = '.rightsidebar-section form.login-form input[name="kundennr"]';
public $password_alt_selector = '.rightsidebar-section form.login-form input[name="kundenpasswort"]';
public $submit_login_alt_selector = '.rightsidebar-section form.login-form input[name="kundenholensubmit"]';

public $check_login_failed_selector = 'div[class*=msg-box]';
public $check_login_success_selector = 'input#LogOut, input[name="LogOut"]';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->disable_uBlock_extensions();
    $this->exts->openUrl($this->baseUrl);


    // Load cookies
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

    $this->exts->capture('1-init-page');
    $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        // if($this->exts->exists('button.uc-btn-accept')) {
        //     $this->exts->moveToElementAndClick('button.uc-btn-accept');
        //     sleep(1);
        // }
        // $this->exts->moveToElementAndClick('i.fa-user');

        $this->exts->execute_javascript('
        var shadow = document.querySelector("#usercentrics-root");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
            }
        ');


        $this->checkFillLogin();
    }

    $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {

            $this->exts->triggerLoginSuccess();
        }

    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'text')), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        }
        if ($this->exts->getElementByText('li.warning-item', ['Kundendaten gefunden werden', 'no valid customer data'], null, false) != null) {
            $this->exts->loginFailure(1);
        }
        if ($this->exts->urlContains('resetpassword=1')) {
            $this->exts->account_not_ready();
        }
        $this->exts->loginFailure();
    }
}


private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector, 30);
    if ($this->exts->querySelector($this->password_selector) != null) {

        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(4);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(3);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);


        // if($this->exts->querySelector($this->password_selector) !== null && $this->exts->querySelector($this->password_alt_selector) !== null) {
        //     $this->exts->capture("2.1-login-page");

        //     $this->exts->log("Enter Username");
        //     $this->exts->moveToElementAndType($this->username_alt_selector, $this->username);
        //     sleep(1);

        //     $this->exts->log("Enter Password");
        //     $this->exts->moveToElementAndType($this->password_alt_selector, $this->password);
        //     sleep(1);

        //     if($this->remember_me_selector != '')
        //         $this->exts->moveToElementAndClick($this->remember_me_selector);
        //     sleep(2);

        //     $this->exts->capture("2-login-page-filled");
        //     $this->exts->moveToElementAndClick($this->submit_login_alt_selector);
        //     sleep(10);
        // }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function disable_uBlock_extensions()
{
    $this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm'); // disable Block origin extension
    sleep(2);
    $this->exts->execute_javascript("
	if(document.querySelector('extensions-manager') != null) {
		if(document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view')  != null){
			var disable_button = document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]');
			if(disable_button != null){
				disable_button.click();
			}
		}
	}
");
    sleep(1);
}

private function getInnerTextByJS($element)
{
    return $this->exts->evaluate("return arguments[0].innerText", [$element]);
}