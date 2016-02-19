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
require_model('familias.php');
require_model('articulo.php');
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
require_once 'plugins/facturacion_base/extras/xlsxwriter.class.php';
/**
 * Description of informe_resumenarticulos
 *
 * @author Joe Nilson <joenilson@gmail.com>
 */
class informe_analisisarticulos extends fs_controller {

    public $resultados;
    public $resultados_almacen;
    public $total_resultados;
    public $familia;
    public $familias;
    public $articulo;
    public $articulos;
    public $fecha_inicio;
    public $fecha_fin;
    public $almacen;
    public $almacenes;
    public $stock;
    public $lista_almacenes;
    public $fileName;
    public $writer;

    public function __construct() {
        parent::__construct(__CLASS__, "Análisis de Stock", 'informes', FALSE, TRUE);
    }

    protected function private_core() {
        $this->familias = new familia();
        $this->articulos = new articulo();
        $this->almacenes = new almacen();
        $this->share_extension();
        $this->fecha_inicio = \date('01-m-Y');
        $this->fecha_fin = \date('t-m-Y');
        $this->reporte = '';
        $this->total_resultados = 0;
        $this->resultados_almacen = '';
        $this->fileName = '';
        $tiporeporte = \filter_input(INPUT_POST, 'procesar-reporte');

        if(!empty($tiporeporte)){
            $inicio = \date('Y-m-d', strtotime(\filter_input(INPUT_POST, 'inicio')));
            $fin = \date('Y-m-d', strtotime(\filter_input(INPUT_POST, 'fin')));
            $almacen = \filter_input(INPUT_POST, 'almacen');
            $familia = \filter_input(INPUT_POST, 'familia');
            $articulo = \filter_input(INPUT_POST, 'articulo');
            $this->fecha_inicio = $inicio;
            $this->fecha_fin = $fin;
            $this->reporte = $tiporeporte;
            $this->almacen = ($almacen!='null')?$this->comma_separated_to_array($almacen):NULL;
            $this->familia = ($familia!='null')?$this->comma_separated_to_array($familia):NULL;
            $this->articulo = ($articulo!='null')?$this->comma_separated_to_array($articulo):NULL;
            $this->kardex_almacen();
        }
    }

    public function kardex_almacen(){
        $resumen = array();
        $this->fileName = 'tmp/'.FS_TMP_NAME.'/Kardex'."_".$this->user->nick.".xlsx";
        if(file_exists($this->fileName)){
            unlink($this->fileName);
        }
        $header = array(
            'Fecha'=>'date',
            'Documento'=>'string',
            'Número'=>'string',
            'Código'=>'string',
            'Artículo'=>'string',
            'Salida'=>'#,###,###.##',
            'Ingreso'=>'#,###,###.##',
            'Saldo'=>'#,###,###.##',
            'Salida Valorizada'=>'#,###,###.##',
            'Ingreso Valorizado'=>'#,###,###.##',
            'Saldo Valorizado'=>'#,###,###.##');
        $this->writer = new XLSXWriter();
        foreach($this->almacen as $index=>$codigo)
        {
            $almacen0 = $this->almacenes->get($codigo);
            $this->writer->writeSheetHeader($almacen0->nombre, $header );
            $resumen = array_merge($resumen, $this->stock_query($almacen0));
        }
        $this->writer->writeToFile($this->fileName);
        gc_collect_cycles();
        $this->resultados_almacen = $resumen;
        $data['rows'] = $resumen;
        $data['filename'] = $this->fileName;
        $this->template = false;
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public function stock_query($almacen){
        //Validamos el listado de Familias seleccionadas
        $codfamilia = ($this->familia)?" and codfamilia IN ({$this->familia_data()})":" ";

        //Validamos el listado de Productos seleccionados
        $referencia = ($this->articulo)?" and referencia IN ({$this->articulo_data()})":" ";

        //Generamos el select para la subconsulta
        $productos = "SELECT referencia FROM articulos where bloqueado = false and controlstock = false $codfamilia $referencia";
        $lista = array();
        /*
         * Generamos la informacion de las regularizaciones que se hayan hecho a los stocks
         */
        $sql_regstocks = "select codalmacen, fecha, l.idstock, l.referencia, motivo, sum(cantidadfin) as cantidad, descripcion, costemedio
               from lineasregstocks AS ls
               JOIN stocks as s ON(ls.idstock = s.idstock)
               JOIN articulos as a ON(a.referencia = l.referencia)
               where codalmacen = '".stripcslashes(strip_tags(trim($almacen->codalmacen)))."' AND fecha between '".$this->fecha_inicio."' and '".$this->fecha_fin."'
               and s.referencia IN ($productos)
               group by l.codalmacen, fecha, s.idstock, l.referencia, motivo, descripcion, costemedio
               order by codalmacen,referencia,fecha;";
        $data = $this->db->select($sql_regstocks);
        if($data){
            foreach($data as $linea){
                $resultados['codalmacen'] = $linea['codalmacen'];
                $resultados['nombre'] = $almacen->nombre;
                $resultados['fecha'] = $linea['fecha'];
                $resultados['tipo_documento'] = "Regularización";
                $resultados['documento'] = $linea['idstock'];
                $resultados['referencia'] = $linea['referencia'];
                $resultados['descripcion'] = $linea['descripcion'];
                $resultados['salida_cantidad'] = ($linea['cantidad']<=0)?$linea['cantidad']:0;
                $resultados['ingreso_cantidad'] = ($linea['cantidad']>=0)?$linea['cantidad']:0;
                $resultados['salida_monto'] = ($linea['cantidad']<=0)?($linea['costemedio']*$linea['cantidad']):0;
                $resultados['ingreso_monto'] = ($linea['cantidad']>=0)?($linea['costemedio']*$linea['cantidad']):0;
                $lista[$linea['fecha']][] = $resultados;
                $this->total_resultados++;
            }
        }
        /*
         * Generamos la informacion de los albaranes de proveedor asociados a facturas no anuladas
         */
        $sql_albaranes = "select codalmacen,ac.fecha,ac.idalbaran,referencia,descripcion,sum(cantidad) as cantidad, sum(pvptotal) as monto
                from albaranesprov as ac
                join lineasalbaranesprov as l ON (ac.idalbaran=l.idalbaran)
                JOIN articulos as a ON(a.referencia = l.referencia)
                where codalmacen = '".stripcslashes(strip_tags(trim($almacen->codalmacen)))."' AND fecha between '".$this->fecha_inicio."' and '".$this->fecha_fin."'
                and idfactura is not null
                and l.referencia in ($productos)
                group by codalmacen,ac.fecha,ac.idalbaran,referencia,descripcion
                order by codalmacen,referencia,fecha;";
        $data = $this->db->select($sql_albaranes);
        if($data){
            foreach($data as $linea){
                $resultados['codalmacen'] = $linea['codalmacen'];
                $resultados['nombre'] = $almacen->nombre;
                $resultados['fecha'] = $linea['fecha'];
                $resultados['tipo_documento'] = ucfirst(FS_ALBARAN)." compra";
                $resultados['documento'] = $linea['idalbaran'];
                $resultados['referencia'] = $linea['referencia'];
                $resultados['descripcion'] = $linea['descripcion'];
                $resultados['salida_cantidad'] = ($linea['cantidad']<=0)?$linea['cantidad']:0;
                $resultados['ingreso_cantidad'] = ($linea['cantidad']>=0)?$linea['cantidad']:0;
                $resultados['salida_monto'] = ($linea['monto']<=0)?$linea['monto']:0;
                $resultados['ingreso_monto'] = ($linea['monto']>=0)?$linea['monto']:0;
                $lista[$linea['fecha']][] = $resultados;
                $this->total_resultados++;
            }
        }

        /*
         * Generamos la informacion de las facturas de proveedor ingresadas
         * que no esten asociadas a un albaran de proveedor
         */
        $sql_facturasprov = "select codalmacen,fc.fecha,fc.idfactura,referencia,descripcion,sum(cantidad) as cantidad, sum(pvptotal) as monto
                from facturasprov as fc
                join lineasfacturasprov as l ON (fc.idfactura=l.idfactura)
                where codalmacen = '".stripcslashes(strip_tags(trim($almacen->codalmacen)))."' AND fecha between '".$this->fecha_inicio."' and '".$this->fecha_fin."'
                and anulada=FALSE and idalbaran is null
                and l.referencia in ($productos)
                group by codalmacen,fc.fecha,fc.idfactura,referencia,descripcion
                order by codalmacen,referencia,fecha;";
        $data = $this->db->select($sql_facturasprov);
        if($data){
            foreach($data as $linea){
                $resultados['codalmacen'] = $linea['codalmacen'];
                $resultados['nombre'] = $almacen->nombre;
                $resultados['fecha'] = $linea['fecha'];
                $resultados['tipo_documento'] = ucfirst(FS_FACTURA)." compra";
                $resultados['documento'] = $linea['idfactura'];
                $resultados['referencia'] = $linea['referencia'];
                $resultados['descripcion'] = $linea['descripcion'];
                $resultados['salida_cantidad'] = ($linea['cantidad']<=0)?$linea['cantidad']:0;
                $resultados['ingreso_cantidad'] = ($linea['cantidad']>=0)?$linea['cantidad']:0;
                $resultados['salida_monto'] = ($linea['monto']<=0)?$linea['monto']:0;
                $resultados['ingreso_monto'] = ($linea['monto']>=0)?$linea['monto']:0;
                $lista[$linea['fecha']][] = $resultados;
                $this->total_resultados++;
            }
        }

        /*
         * Generamos la informacion de los albaranes asociados a facturas no anuladas
         */
        $sql_albaranes = "select codalmacen,ac.fecha,ac.idalbaran,referencia,descripcion,sum(cantidad) as cantidad, sum(pvptotal) as monto
                from albaranescli as ac
                join lineasalbaranescli as l ON (ac.idalbaran=l.idalbaran)
                where codalmacen = '".stripcslashes(strip_tags(trim($almacen->codalmacen)))."' AND fecha between '".$this->fecha_inicio."' and '".$this->fecha_fin."'
                and idfactura is not null
                and l.referencia in ($productos)
                group by codalmacen,ac.fecha,ac.idalbaran,referencia,descripcion
                order by codalmacen,referencia,fecha;";
        $data = $this->db->select($sql_albaranes);
        if($data){
            foreach($data as $linea){
                $resultados['codalmacen'] = $linea['codalmacen'];
                $resultados['nombre'] = $almacen->nombre;
                $resultados['fecha'] = $linea['fecha'];
                $resultados['tipo_documento'] = ucfirst(FS_ALBARAN)." venta";
                $resultados['documento'] = $linea['idalbaran'];
                $resultados['referencia'] = $linea['referencia'];
                $resultados['descripcion'] = $linea['descripcion'];
                $resultados['salida_cantidad'] = ($linea['cantidad']>=0)?$linea['cantidad']:0;
                $resultados['ingreso_cantidad'] = ($linea['cantidad']<=0)?$linea['cantidad']:0;
                $resultados['salida_monto'] = ($linea['monto']>=0)?$linea['monto']:0;
                $resultados['ingreso_monto'] = ($linea['monto']<=0)?$linea['monto']:0;
                $lista[$linea['fecha']][] = $resultados;
                $this->total_resultados++;
            }
        }

        /*
         * Generamos la informacion de las facturas que se han generado sin albaran
         */
        $sql_facturas = "select codalmacen,fc.fecha,fc.idfactura,referencia,descripcion,sum(cantidad) as cantidad, sum(pvptotal) as monto
                from facturascli as fc
                join lineasfacturascli as l ON (fc.idfactura=l.idfactura)
                where codalmacen = '".stripcslashes(strip_tags(trim($almacen->codalmacen)))."' AND fecha between '".$this->fecha_inicio."' and '".$this->fecha_fin."'
                and anulada=FALSE and idalbaran is null
                and l.referencia in ($productos)
                group by codalmacen,fc.fecha,fc.idfactura,referencia,descripcion
                order by codalmacen,referencia,fecha;";
        $data = $this->db->select($sql_facturas);
        if($data){
            foreach($data as $linea){
                $resultados['codalmacen'] = $linea['codalmacen'];
                $resultados['nombre'] = $almacen->nombre;
                $resultados['fecha'] = $linea['fecha'];
                $resultados['tipo_documento'] = ucfirst(FS_FACTURA)." venta";
                $resultados['documento'] = $linea['idfactura'];
                $resultados['referencia'] = $linea['referencia'];
                $resultados['descripcion'] = $linea['descripcion'];
                $resultados['salida_cantidad'] = ($linea['cantidad']>=0)?$linea['cantidad']:0;
                $resultados['ingreso_cantidad'] = ($linea['cantidad']<=0)?$linea['cantidad']:0;
                $resultados['salida_monto'] = ($linea['monto']>=0)?$linea['monto']:0;
                $resultados['ingreso_monto'] = ($linea['monto']<=0)?$linea['monto']:0;
                $lista[$linea['fecha']][] = $resultados;
                $this->total_resultados++;
            }
        }
        return $this->generar_resultados($lista,$almacen);
    }

    public function generar_resultados($lista,$almacen){
        $linea_resultado = array();
        $lista_resultado = array();
        $cabecera_export = array();
        $lista_export = array();
        $resumen = array();
        ksort($lista);
        foreach($lista as $fecha){
            foreach($fecha as $value){
                if(!isset($resumen[$value['codalmacen']][$value['referencia']]['saldo_cantidad'])){
                    $resumen[$value['codalmacen']][$value['referencia']]['saldo_cantidad'] = 0;
                }
                if(!isset($resumen[$value['codalmacen']][$value['referencia']]['saldo_monto'])){
                    $resumen[$value['codalmacen']][$value['referencia']]['saldo_monto'] = 0;
                }
                if(!isset($lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['saldo_monto'])){
                    $lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['saldo_monto'] = 0;
                }
                if(!isset($lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['salida_monto'])){
                    $lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['salida_monto'] = 0;
                }
                if(!isset($lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['salida_cantidad'])){
                    $lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['salida_cantidad'] = 0;
                }
                if(!isset($lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['ingreso_monto'])){
                    $lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['ingreso_monto'] = 0;
                }
                if(!isset($lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['ingreso_cantidad'])){
                    $lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['ingreso_cantidad'] = 0;
                }
                $value['ingreso_cantidad'] = ($value['ingreso_cantidad'] < 0)?$value['ingreso_cantidad']*-1:$value['ingreso_cantidad'];
                $value['salida_cantidad'] = ($value['salida_cantidad'] < 0)?$value['salida_cantidad']*-1:$value['salida_cantidad'];
                $value['ingreso_monto'] = ($value['ingreso_monto'] < 0)?$value['ingreso_monto']*-1:$value['ingreso_monto'];
                $value['salida_monto'] = ($value['salida_monto'] < 0)?$value['salida_monto']*-1:$value['salida_monto'];
                $resumen[$value['codalmacen']][$value['referencia']]['saldo_cantidad'] += ($value['ingreso_cantidad']-$value['salida_cantidad']);
                $resumen[$value['codalmacen']][$value['referencia']]['saldo_monto'] += ($value['ingreso_monto']-$value['salida_monto']);
                $linea_resultado = $value;
                $linea_resultado['saldo_cantidad'] =  $resumen[$value['codalmacen']][$value['referencia']]['saldo_cantidad'];
                $linea_resultado['saldo_monto'] = $resumen[$value['codalmacen']][$value['referencia']]['saldo_monto'];
                $lista_resultado[] = $linea_resultado;
                $cabecera_export[$value['referencia']]=$value['descripcion'];
                $lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['ingreso_cantidad'] += $value['ingreso_cantidad'];
                $lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['ingreso_monto'] += $value['ingreso_monto'];
                $lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['salida_cantidad'] += $value['salida_cantidad'];
                $lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['salida_monto'] += $value['salida_monto'];
                $lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['saldo_cantidad'] = $linea_resultado['saldo_cantidad'];
                $lista_export[$value['referencia']][$value['fecha']][$value['tipo_documento']][$value['documento']]['saldo_monto'] = $linea_resultado['saldo_monto'];
            }
        }

        foreach($lista_export as $referencia=>$listafecha){
            $lineas = 0;
            $sumaSalidasQda[$referencia]=0;
            $sumaSalidasMonto[$referencia]=0;
            $sumaIngresosQda[$referencia]=0;
            $sumaIngresosMonto[$referencia]=0;
            foreach($listafecha as $fecha=>$tipo_documentos){
                foreach($tipo_documentos as $tipo_documento=>$documentos){
                    foreach($documentos as $documento=>$movimiento){
                        if($lineas == 0){
                            $this->writer->writeSheetRow($almacen->nombre,
                                array('', '', '', '', $cabecera_export[$referencia], '', '', '', '', '', '')
                            );
                        }
                        $this->writer->writeSheetRow($almacen->nombre,
                            array(
                                $fecha,
                                $tipo_documento,
                                $documento,
                                $referencia,
                                $cabecera_export[$referencia],
                                $movimiento['salida_cantidad'],
                                $movimiento['ingreso_cantidad'],
                                $movimiento['saldo_cantidad'],
                                $movimiento['salida_monto'],
                                $movimiento['ingreso_monto'],
                                $movimiento['saldo_monto']
                            )
                        );
                        $sumaSalidasQda[$referencia] +=$movimiento['salida_cantidad'];
                        $sumaSalidasMonto[$referencia] +=$movimiento['salida_monto'];
                        $sumaIngresosQda[$referencia] +=$movimiento['ingreso_cantidad'];
                        $sumaIngresosMonto[$referencia] +=$movimiento['ingreso_monto'];
                        $lineas++;
                    }
                }
            }
            $this->writer->writeSheetRow($almacen->nombre,
                array('', '', '', '', 'Saldo Final', $sumaSalidasQda[$referencia], $sumaIngresosQda[$referencia], ($sumaIngresosQda[$referencia]-$sumaSalidasQda[$referencia]), $sumaSalidasMonto[$referencia], $sumaIngresosMonto[$referencia], ($sumaIngresosMonto[$referencia]-$sumaSalidasMonto[$referencia]))
            );
            $this->writer->writeSheetRow($almacen->nombre,
                array('', '', '', '', '', '', '', '', '', '', '')
            );
        }



        return $lista_resultado;
    }

    private function share_extension() {
        $extensiones = array(
            array(
                'name' => 'analisisarticulos_css001',
                'page_from' => __CLASS__,
                'page_to' => 'informe_analisisarticulos',
                'type' => 'head',
                'text' => '<link rel="stylesheet" type="text/css" media="screen" href="plugins/facturacion_base/view/css/ui.jqgrid-bootstrap.css"/>',
                'params' => ''
            ),
            array(
                'name' => 'analisisarticulos_css002',
                'page_from' => __CLASS__,
                'page_to' => 'informe_analisisarticulos',
                'type' => 'head',
                'text' => '<link rel="stylesheet" type="text/css" media="screen" href="plugins/facturacion_base/view/css/bootstrap-select.min.css"/>',
                'params' => ''
            ),
            array(
                'name' => 'analisisarticulos_js001',
                'page_from' => __CLASS__,
                'page_to' => 'informe_analisisarticulos',
                'type' => 'head',
                'text' => '<script src="plugins/facturacion_base/view/js/locale/grid.locale-es.js" type="text/javascript"></script>',
                'params' => ''
            ),
            array(
                'name' => 'analisisarticulos_js002',
                'page_from' => __CLASS__,
                'page_to' => 'informe_analisisarticulos',
                'type' => 'head',
                'text' => '<script src="plugins/facturacion_base/view/js/plugins/jquery.jqGrid.min.js" type="text/javascript"></script>',
                'params' => ''
            ),
            array(
                'name' => 'analisisarticulos_js003',
                'page_from' => __CLASS__,
                'page_to' => 'informe_analisisarticulos',
                'type' => 'head',
                'text' => '<script src="plugins/facturacion_base/view/js/bootstrap-select.min.js" type="text/javascript"></script>',
                'params' => ''
            )
        );

        foreach ($extensiones as $ext) {
            $fsext0 = new fs_extension($ext);
            if (!$fsext0->save()) {
                $this->new_error_msg('Imposible guardar los datos de la extensión ' . $ext['name'] . '.');
            }
        }
    }

    private function familia_data()
    {
        $result = "'";
        foreach($this->familia as $key=>$value){
            $result .= $value."',";
        }
        return substr($result, 0, strlen($result)-1);
    }

    private function articulo_data()
    {
        $result = "'";
        foreach($this->articulo as $key=>$value){
            $result .= $value."',";
        }
        return substr($result, 0, strlen($result)-1);
    }

    /**
    * @url http://snippets.khromov.se/convert-comma-separated-values-to-array-in-php/
    * @param $string - Input string to convert to array
    * @param string $separator - Separator to separate by (default: ,)
    *
    * @return array
    */
    private function comma_separated_to_array($string, $separator = ',')
    {
        //Explode on comma
        $vals = explode($separator, $string);

        //Trim whitespace
        foreach($vals as $key => $val) {
            $vals[$key] = trim($val);
        }
        //Return empty array if no items found
        //http://php.net/manual/en/function.explode.php#114273
        return array_diff($vals, array(""));
    }
}
