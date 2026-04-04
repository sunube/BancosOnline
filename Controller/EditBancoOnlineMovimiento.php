<?php

namespace FacturaScripts\Plugins\BancosOnline\Controller;

use FacturaScripts\Core\Lib\ExtendedController\PanelController;

class EditBancoOnlineMovimiento extends PanelController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'movimiento-bancario';
        $data['menu'] = 'accounting';
        $data['icon'] = 'fa-solid fa-exchange-alt';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews()
    {
        $this->addEditView('EditBancoOnlineMovimiento', 'BancoOnlineMovimiento', 'movimiento-bancario', 'fa-solid fa-exchange-alt');
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditBancoOnlineMovimiento':
                $code = $this->request->get('code', '');
                $view->loadData($code);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
