<?php

namespace FacturaScripts\Plugins\BancosOnline\Controller;

use FacturaScripts\Core\Lib\ExtendedController\PanelController;

class EditBancoOnlineBanco extends PanelController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'banco';
        $data['menu'] = 'admin';
        $data['icon'] = 'fa-solid fa-university';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews()
    {
        $this->addEditView('EditBancoOnlineBanco', 'BancoOnlineBanco', 'banco', 'fa-solid fa-university');
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditBancoOnlineBanco':
                $code = $this->request->get('code', '');
                $view->loadData($code);
                break;

            default:
                parent::loadData($viewName, $view);
                break;
        }
    }
}
