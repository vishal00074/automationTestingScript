<?php

use GmiChromeManager;

class DisableChromeExtension
{
    protected $exts;

    public function __construct()
    {
        $this->exts = new GmiChromeManager();
    }

    /**
     * Disables unexpected Chrome extensions by navigating to their settings pages
     * and toggling their enable/disable switches.
     *
     * Extensions Disabled:
     * - "cjpalhdlnbpafiamejdnhcphjbkeiagm" (likely uBlock Origin)
     * - "ifibfemgeogfhoebkmokieepdoobkbpo" (another unidentified extension)
     *
     * The function opens each extension's settings page and attempts to disable it
     * by interacting with the Chrome Extensions Manager UI.
     */
    public function disable_unexpected_extensions()
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
        $this->exts->openUrl('chrome://extensions/?id=ifibfemgeogfhoebkmokieepdoobkbpo');
        sleep(1);
        $this->exts->execute_javascript("if (document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]') != null) {
            document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]').click();
        }");
        sleep(2);
    }
}
