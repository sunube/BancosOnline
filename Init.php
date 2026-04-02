<?php

namespace FacturaScripts\Plugins\BancosOnline;

use FacturaScripts\Core\Template\InitClass;

final class Init extends InitClass
{
    public function init(): void
    {
        // No se cargan extensiones en la v1
    }

    public function update(): void
    {
        // Las tablas se crean automaticamente por los XML en Table/
    }

    public function uninstall(): void
    {
        // Las tablas se eliminan automaticamente
    }
}
