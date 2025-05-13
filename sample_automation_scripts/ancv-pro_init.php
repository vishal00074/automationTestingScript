public $baseUrl = "https://espace-ptl.ancv.com/espacePrestataire/accueil-ptl";
public $loginUrl = "https://espace-ptl.ancv.com/user/login";
public $invoicePageUrl = 'https://espace-ptl.ancv.com/espacePrestataire/remb/suivi';
public $username_selector = 'input[id="edit-name"]';
public $password_selector = 'input[id="edit-pass"]';
public $submit_button_selector = 'input[id="edit-submit"]';
public $check_login_failed_selector = 'div.messages.error';
public $check_login_success_selector = 'div.content > a.lienDeconnexion';
public $login_tryout = 0;
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

    if ($this->exts->exists('button.agree-button')) {
        $this->exts->moveToElementAndClick("button.agree-button");
        sleep(10);
    }



    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(7);
        if ($this->exts->exists('button.agree-button')) {
            $this->exts->moveToElementAndClick("button.agree-button");
            sleep(10);
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
        $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
        $this->exts->log("::Error Text" .  $error_text);

        if (stripos($error_text, strtolower("Votre numéro de convention et/ou votre mot de passe est incorrect. Pour davantage d'information, se référer aux bulles d'aide.")) !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->log("Failed due to unknown reasons");
            $this->exts->loginFailure();
        }
    }
}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 10);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(2);
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