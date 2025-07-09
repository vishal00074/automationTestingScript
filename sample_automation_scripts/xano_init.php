public $baseUrl = 'https://app.xano.com/login';
public $loginUrl = 'https://app.xano.com/login';
public $invoicePageUrl = 'https://app.xano.com/billing';

public $username_selector = 'input[name="account"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'div.toast-header';
public $check_login_success_selector = 'a[data-pw="nav-admin-billing"],a[class="dropdown-toggle nav-link"] ';
public $isNoInvoice = true;

private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    sleep(3);
    $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->type_key_by_xdotool('F5');
        sleep(5);
        $this->fillForm(0);
        $this->exts->waitTillAnyPresent([$this->check_login_success_selector, $this->check_login_failed_selector, 'input[name="code_2fa"]']);
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'access denied') !== false) {
            $this->exts->log("Site blocked");
            $this->exts->capture('2-access-denied');
            $this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            $this->exts->type_key_by_xdotool('F5');
            sleep(5);
            $this->fillForm(0);
        }
        $this->checkFillTwoFactor();
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#pages").querySelector("#clearFromBasic").shadowRoot.querySelector("#dropdownMenu").value = 4;');
    sleep(1);
    $this->exts->capture("clear-page");
    $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#clearButton").click();');
    sleep(15);
    $this->exts->capture("after-clear");
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        if ($this->exts->check_exist_by_chromedevtool($this->username_selector) != null) {

            $this->exts->capture_by_chromedevtool("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            $this->exts->type_key_by_xdotool('Ctrl+a');
            $this->exts->type_key_by_xdotool('Delete');
            $this->exts->type_text_by_xdotool($this->username);
            // $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(4);

            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            $this->exts->type_key_by_xdotool('Ctrl+a');
            $this->exts->type_key_by_xdotool('Delete');
            $this->exts->type_text_by_xdotool($this->password);
            // $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(4);


            $this->exts->capture_by_chromedevtool("1-login-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(5);
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[name="code_2fa"]';
    $two_factor_message_selector = 'p.intro';
    $two_factor_submit_selector = 'button[type="submit"]';

    if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(5);

            if ($this->exts->getElement($two_factor_selector) == null) {
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

function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }


    return $isLoggedIn;
}