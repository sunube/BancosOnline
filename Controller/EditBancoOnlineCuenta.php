<?php

namespace FacturaScripts\Plugins\BancosOnline\Controller;

use FacturaScripts\Core\Lib\ExtendedController\PanelController;

class EditBancoOnlineCuenta extends PanelController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'cuenta-bancaria';
        $data['menu'] = 'accounting';
        $data['icon'] = 'fa-solid fa-credit-card';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews()
    {
        $this->addEditView('EditBancoOnlineCuenta', 'BancoOnlineCuenta', 'cuenta-bancaria', 'fa-solid fa-credit-card');
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditBancoOnlineCuenta':
                $code = $this->request->get('code', '');
                $view->loadData($code);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
