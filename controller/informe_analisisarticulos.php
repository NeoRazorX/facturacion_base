<?php

/*
 * Copyright (C) 2016 Joe Nilson <joenilson@gmail.com>
 *
 *  * This program is free software: you can redistribute it and/or modify
 *  * it under the terms of the GNU Affero General Public License as
 *  * published by the Free Software Foundation, either version 3 of the
 *  * License, or (at your option) any later version.
 *  *
 *  * This program is distributed in the hope that it will be useful,
 *  * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See th * e
 *  * GNU Affero General Public License for more details.
 *  * 
 *  * You should have received a copy of the GNU Affero General Public License
 *  * along with this program.  If not, see <http://www.gnu.org/licenses/>.

 */
namespace facturacion_base;

require_model('articulos.php');
require_model('empresa.php');
require_model('almacenes.php');
require_model('albaran_cliente.php');
require_model('albaran_proveedor.php');
require_model('cliente.php');
require_model('factura_cliente.php');
require_model('factura_proveedor.php');
require_model('forma_pago.php');
require_model('pais.php');
require_model('proveedor.php');
require_model('serie.php');

/**
 * Description of informe_resumenarticulos
 *
 * @author Joe Nilson <joenilson@gmail.com>
 */
class informe_analisisarticulos extends \fs_controller {
    
    public $resultados;
    public $articulos;
    public $fecha_inicio;
    public $fecha_fin;
    public $almacen;
    public $stock;
    
    public function __construct() {
        parent::__construct(__CLASS__, "Análisis de Stock", 'facturacion_base', FALSE, TRUE);
    }
    
    protected function public_core() {
        
        $this->resultados = 0;
        
    }
    
    
}
