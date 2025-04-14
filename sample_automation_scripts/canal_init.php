public $baseUrl = "https://client.canal.fr/";
public $loginUrl = "https://client.canal.fr/";
public $homePageUrl = "https://client.canal.fr/abonnement/";
public $username_selector = 'input[name="identifier"]';
public $password_selector = 'input[name="credentials.passcode"][type="password"]';
public $submit_button_selector = 'input[type="submit"]';
public $month_names_fr = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
public $login_tryout = 0;
public $restrictPages = 3;

public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $nav_driver = $this->exts->execute_javascript('(function() { return navigator.webdriver; })();');
    $this->exts->log('nav_driver: ' . json_decode($nav_driver, true)['result']['result']['value']);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $this->exts->openUrl($this->baseUrl);
    sleep(15);
    $this->exts->capture("Home-page-without-cookie");

    if ($this->exts->exists('button#didomi-notice-agree-button')) {
        $this->exts->click_by_xdotool('button#didomi-notice-agree-button');
    }

    $this->exts->loadCookiesFromFile();

    if (!$this->checkLogin()) {
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        if ($this->exts->exists('button#didomi-notice-agree-button')) {
            $this->exts->moveToElementAndClick('button#didomi-notice-agree-button');
            sleep(5);
        }

        if ($this->exts->exists('div[class*="user__popin__auth-section"] button')) {
            $this->exts->moveToElementAndClick('div[class*="user__popin__auth-section"] button');
            sleep(5);
        }

        $this->fillForm(0);
    }

    $this->exts->log($this->exts->extract('div[class*="HeaderLayout__userOptions"]', null, 'innerHTML'));

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if ($this->exts->exists('form[name="sso-form"] input[value="blockedAccount"]')) {
            $this->exts->account_not_ready();
        } else if (strpos($this->exts->extract('form.form-error .errorDiv .message'), 'votre email et votre mot de passe') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('span#error-message')), 'ake sure that you have right password') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    try {
        sleep(5);
        if ($this->exts->exists($this->password_selector)) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, '');
            $this->exts->click_by_xdotool($this->username_selector);
            sleep(2);
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, '');
            $this->exts->click_by_xdotool($this->password_selector);
            sleep(2);
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->click_by_xdotool($this->submit_button_selector);

            sleep(8);
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
        if ($this->exts->getElementByText('div.header-user-popin-button button', ['Se déconnecter', 'Sign out'], null, true) && !$this->exts->exists($this->password_selector) || $this->exts->exists('div[class="uic-header-user__trigger --authenticated"]')) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}