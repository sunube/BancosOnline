<?php

namespace FacturaScripts\Plugins\BancosOnline\Controller;

use FacturaScripts\Core\Lib\ExtendedController\PanelController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\BancosOnline\Lib\EnableBankingAPI;
use FacturaScripts\Plugins\BancosOnline\Model\BancoOnlineConfig;

class ConfigBancosOnline extends PanelController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['title'] = 'bancos-online-config';
        $data['menu'] = 'admin';
        $data['icon'] = 'fa-solid fa-gear';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews()
    {
        $this->setTabsPosition('top');

        $this->addEditView('EditBancoOnlineConfig', 'BancoOnlineConfig', 'configuracion', 'fa-solid fa-gear');
        $this->addListView('ListBancoOnlineBanco', 'BancoOnlineBanco', 'bancos', 'fa-solid fa-university');

        $this->addSearchFields('ListBancoOnlineBanco', ['nombre']);
        $this->addOrderBy('ListBancoOnlineBanco', ['nombre'], 'nombre');
    }

    protected function loadData($viewName, $view)
    {
        switch ($viewName) {
            case 'EditBancoOnlineConfig':
                $config = BancoOnlineConfig::current();
                if ($config->exists()) {
                    $view->loadData($config->primaryColumnValue());
                } else {
                    $view->loadData('');
                    $view->model->idempresa = $config->idempresa;
                }
                break;

            case 'ListBancoOnlineBanco':
                $view->loadData();
                break;
        }
    }

    protected function execPreviousAction($action)
    {
        if ($action === 'test-connection') {
            $this->testConnection();
            return true;
        }

        return parent::execPreviousAction($action);
    }

    private function testConnection(): void
    {
        $config = BancoOnlineConfig::current();

        if (!$config->isConfigured()) {
            Tools::log()->warning('Primero debes guardar la configuracion de Enable Banking.');
            return;
        }

        try {
            $api = new EnableBankingAPI(
                $config->enablebanking_app_id,
                $config->enablebanking_key_path,
                $config->enablebanking_redirect_url
            );

            $result = $api->testConnection();

            if ($result['ok']) {
                Tools::log()->notice('Conexion con Enable Banking correcta.');
            } else {
                Tools::log()->warning('Error de conexion: ' . ($result['error'] ?? 'desconocido'));
            }
        } catch (\Exception $e) {
            Tools::log()->error('Error: ' . $e->getMessage());
        }
    }
}
