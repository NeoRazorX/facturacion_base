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

require_model('articulos.php');
require_model('empresa.php');
require_model('almacen.php');
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
class informe_analisisarticulos extends fs_controller {

    public $resultados;
    public $resultados_almacen;
    public $total_resultados;
    public $articulos;
    public $fecha_inicio;
    public $fecha_fin;
    public $almacen;
    public $stock;
    public $lista_almacenes;
    public function __construct() {
        parent::__construct(__CLASS__, "Análisis de Stock", 'informes', FALSE, TRUE);
    }

    protected function private_core() {
        $this->fecha_inicio = \date('01-m-Y');
        $this->fecha_fin = \date('t-m-Y');
        $this->reporte = '';
        $this->total_resultados = 0;
        $this->resultados_almacen = '';
        $tiporeporte = \filter_input(INPUT_POST, 'tipo-reporte');
        if(!empty($tiporeporte)){
            $inicio = \filter_input(INPUT_POST, 'inicio');
            $fin = \filter_input(INPUT_POST, 'fin');
            $this->fecha_inicio = $inicio;
            $this->fecha_fin = $fin;
            $this->reporte = $tiporeporte;
            switch ($tiporeporte){
                case 'valorizado-almacen':
                    $this->valorizado_almacen();
                    break;
                case 'valorizado-familia':
                    $this->valorizado_familia();
                    break;
                case 'valorizado-articulo':
                    $this->valorizado_articulo();
                    break;
                default :
                    break;
            }
        }
    }

    public function valorizado_almacen(){
        $almacenes = new almacen();
        $this->lista_almacenes = $almacenes->all();
        $resumen = array();
        foreach($almacenes->all() as $almacen){
            $resumen = array_merge($resumen, $this->stock_query($almacen));
        }
        $this->resultados_almacen = $resumen;
        //$this->lista_almacenes->codalmacen;
        //$this->lista_almacenes->nombre;
        //$this->lista_almacenes->is_default();
    }

    public function stock_query($almacen){
        $lista = array();
        $sql = "select codalmacen,fc.fecha,fc.idfactura,referencia,descripcion,sum(cantidad) as cantidad, sum(pvptotal) as monto
                from facturascli as fc
                join lineasfacturascli as l ON (fc.idfactura=l.idfactura)
                where codalmacen = '".  stripcslashes(strip_tags(trim($almacen->codalmacen)))."' AND fecha between '".$this->fecha_inicio."' and '".$this->fecha_fin."' and anulada=FALSE and idalbaran is null
                group by codalmacen,fc.fecha,fc.idfactura,referencia,descripcion
                order by codalmacen,referencia,fecha;";
        //echo $sql;
        $data = $this->db->select($sql);
        if($data){
            $counter = 0;
            foreach($data as $linea){
                //var_dump($linea);
                $resultados = new stdClass();
                $resultados->codalmacen = $linea['codalmacen'];
                $resultados->nombre = $almacen->nombre;
                $resultados->fecha = $linea['fecha'];
                $resultados->idfactura = $linea['idfactura'];
                $resultados->referencia = $linea['referencia'];
                $resultados->descripcion = $linea['descripcion'];
                $resultados->salida_cantidad = ($linea['cantidad']>=0)?$linea['cantidad']:0;
                $resultados->ingreso_cantidad = ($linea['cantidad']<=0)?$linea['cantidad']:0;
                $resultados->salida_monto = ($linea['monto']>=0)?$linea['monto']:0;
                $resultados->ingreso_monto = ($linea['monto']<=0)?$linea['monto']:0;
                $resultados->salida_cantidad_saldo += ($linea['cantidad']>=0)?$linea['cantidad']:0;
                $resultados->ingreso_cantidad_saldo += ($linea['cantidad']<=0)?$linea['cantidad']:0;
                $resultados->salida_monto_saldo += ($linea['monto']>=0)?$linea['monto']:0;
                $resultados->ingreso_monto_saldo += ($linea['monto']<=0)?$linea['monto']:0;
                $counter++;
                $lista[$resultados->codalmacen][$resultados->referencia][] = $resultados;
                $this->total_resultados++;
            }
        }
        return $lista;
    }


}
