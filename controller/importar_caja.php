<?php

require_model('caja.php');

/**
 * Created by IntelliJ IDEA.
 * User: ggarcia
 * Date: 24/10/2016
 * Time: 08:15 PM
 */
class importar_caja extends fs_controller {

    public function __construct() {
        parent::__construct(__CLASS__, 'Importar Caja', 'contabilidad', false, false);
    }

    protected function private_core() {
        $this->template = 'ajax/importar_caja';

        $obj = new caja();

        $this->cajas = $obj->findCajasSinAsiento();
    }


}