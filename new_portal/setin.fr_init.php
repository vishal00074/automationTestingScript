public $baseUrl = 'https://www.setin.fr/';
public $loginUrl = 'https://www.setin.fr/dhtml/acces.php';
public $invoicePageUrl = 'https://www.setin.fr/dhtml/factures_setin.php';

public $username_selector = 'form[action="acces.php"] input#acces_mail';
public $password_selector = 'form[action="acces.php"] input#acces_password';
public $remember_me_selector = '';
public $submit_login_selector = 'form[action="acces.php"] a#id_valider';

public $check_login_failed_selector = 'div.erreur';
public $check_login_success_selector = 'a[href="home.php?deconnect=1"][id="deconnexion"]';

public $isNoInvoice = true;

/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(7);
    $this->exts->loadCookiesFromFile();

    for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('div#divCookiesGeneral a.AcceptAllBouton');") != 1; $wait++) {
        $this->exts->log('Waiting for login.....');
        sleep(10);
    }

    if ($this->exts->exists('div#divCookiesGeneral a.AcceptAllBouton')) {
        $this->exts->moveToElementAndClick('div#divCookiesGeneral a.AcceptAllBouton');
        sleep(7);
    }

    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');

        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);

        for ($wait = 0; $wait < 3 && $this->exts->executeSafeScript("return !!document.querySelector('div#divCookiesGeneral a.AcceptAllBouton');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
        if ($this->exts->exists('div#divCookiesGeneral a.AcceptAllBouton')) {
            $this->exts->moveToElementAndClick('div#divCookiesGeneral a.AcceptAllBouton');
            sleep(7);
        }
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
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
        if (
            stripos($error_text, 'identifiant ou mot de passe incorrect !') !== false ||
            stripos($error_text, 'incorrect username or password!') !== false
        ) {

            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

public function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);

    $this->exts->waitTillPresent($this->username_selector);
    if ($this->exts->querySelector($this->username_selector) != null) {

        $this->exts->capture("1-pre-login");
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->exts->exists($this->remember_me_selector)) {
            $this->exts->click_by_xdotool($this->remember_me_selector);
            sleep(2);
        }

        $this->exts->capture("1-login-page-filled");
        if ($this->exts->exists($this->submit_login_selector)) {
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(5);
        }
    }
}
/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
public  function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for login.....');
            sleep(10);
        }
        if ($this->exts->exists($this->check_login_success_selector)) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}