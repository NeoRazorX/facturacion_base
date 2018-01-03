<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2015-2018    Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2017         Itaca Software Libre contacta@itacaswl.com
 * Copyright (C) 2017         Francesc Pineda Segarra francesc.pìneda@x-netdigital.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'plugins/facturacion_base/extras/fbase_controller.php';
require_once 'plugins/facturacion_base/extras/fs_pdf.php';
require_once 'extras/xlsxwriter.class.php';

class informe_albaranes extends fbase_controller
{

    public $agente;
    public $almacen;
    public $cliente;
    public $codagente;
    public $codalmacen;
    public $coddivisa;
    public $codpago;
    public $codserie;
    public $desde;
    public $divisa;
    public $forma_pago;
    public $hasta;
    public $proveedor;
    public $serie;
    protected $nombre_docs;
    protected $table_compras;
    protected $table_ventas;
    protected $where_compras;
    protected $where_compras_nf;
    protected $where_ventas;
    protected $where_ventas_nf;

    /**
     * Este controlador lo usaremos de ejemplo en otros, así que debemos permitir usar su constructor.
     * @param system $name
     * @param type $title
     * @param string $folder
     * @param type $admin
     * @param type $shmenu
     * @param type $important
     */
    public function __construct($name = '', $title = 'home', $folder = '', $admin = FALSE, $shmenu = TRUE, $important = FALSE)
    {
        if ($name == '') {
            /**
             * si no se proporciona un $name es que estamos usando este mismo controlador,
             * por lo que establecemos los valores.
             */
            $name = __CLASS__;
            $title = ucfirst(FS_ALBARANES);
            $folder = 'informes';
        }

        parent::__construct($name, $title, $folder, $admin, $shmenu, $important);
    }

    protected function private_core()
    {
        parent::private_core();

        /// declaramos los objetos sólo para asegurarnos de que existen las tablas
        new albaran_cliente();
        new albaran_proveedor();

        $this->agente = new agente();
        $this->almacen = new almacen();
        $this->divisa = new divisa();
        $this->forma_pago = new forma_pago();
        $this->serie = new serie();

        if (!isset($this->nombre_docs)) {
            $this->nombre_docs = FS_ALBARANES;
            $this->table_compras = 'albaranesprov';
            $this->table_ventas = 'albaranescli';
        }

        if (isset($_REQUEST['buscar_cliente'])) {
            $this->fbase_buscar_cliente($_REQUEST['buscar_cliente']);
        } else if (isset($_REQUEST['buscar_proveedor'])) {
            $this->fbase_buscar_proveedor($_REQUEST['buscar_proveedor']);
        } else {
            $this->ini_filters();
            $this->set_where();

            if (isset($_POST['generar'])) {
                if ($_POST['generar'] == 'pdf_cli') {
                    $this->generar_pdf('venta');
                } else if ($_POST['generar'] == 'xls_cli') {
                    $this->generar_xls('venta');
                } else if ($_POST['generar'] == 'csv_cli') {
                    $this->generar_csv('venta');
                } else if ($_POST['generar'] == 'pdf_prov') {
                    $this->generar_pdf('compra');
                } else if ($_POST['generar'] == 'xls_prov') {
                    $this->generar_xls('compra');
                } else if ($_POST['generar'] == 'csv_prov') {
                    $this->generar_csv('compra');
                } else {
                    $this->generar_extra();
                }
            }
        }
    }

    protected function generar_extra()
    {
        /// a completar en el informe de facturas
    }

    /**
     * Obtenemos los valores de los filtros del formulario.
     */
    protected function ini_filters()
    {
        $this->desde = Date('01-m-Y', strtotime('-14 months'));
        if (isset($_REQUEST['desde'])) {
            $this->desde = $_REQUEST['desde'];
        }

        $this->hasta = Date('t-m-Y');
        if (isset($_REQUEST['hasta'])) {
            $this->hasta = $_REQUEST['hasta'];
        }

        $this->codserie = FALSE;
        if (isset($_REQUEST['codserie'])) {
            $this->codserie = $_REQUEST['codserie'];
        }

        $this->codpago = FALSE;
        if (isset($_REQUEST['codpago'])) {
            $this->codpago = $_REQUEST['codpago'];
        }

        $this->codagente = FALSE;
        if (isset($_REQUEST['codagente'])) {
            $this->codagente = $_REQUEST['codagente'];
        }

        $this->codalmacen = FALSE;
        if (isset($_REQUEST['codalmacen'])) {
            $this->codalmacen = $_REQUEST['codalmacen'];
        }

        $this->coddivisa = $this->empresa->coddivisa;
        if (isset($_REQUEST['coddivisa'])) {
            $this->coddivisa = $_REQUEST['coddivisa'];
        }

        $this->cliente = FALSE;
        if (isset($_REQUEST['codcliente'])) {
            if ($_REQUEST['codcliente'] != '') {
                $cli0 = new cliente();
                $this->cliente = $cli0->get($_REQUEST['codcliente']);
            }
        }

        $this->proveedor = FALSE;
        if (isset($_REQUEST['codproveedor'])) {
            if ($_REQUEST['codproveedor'] != '') {
                $prov0 = new proveedor();
                $this->proveedor = $prov0->get($_REQUEST['codproveedor']);
            }
        }
    }

    /**
     * Contruimos sentencias where para las consultas sql.
     */
    protected function set_where()
    {
        $this->where_compras = " WHERE fecha >= " . $this->empresa->var2str($this->desde)
            . " AND fecha <= " . $this->empresa->var2str($this->hasta);

        /// nos guardamos un where sin fechas
        $this->where_compras_nf = " WHERE 1 = 1";

        if ($this->codserie) {
            $this->where_compras .= " AND codserie = " . $this->empresa->var2str($this->codserie);
            $this->where_compras_nf .= " AND codserie = " . $this->empresa->var2str($this->codserie);
        }

        if ($this->codagente) {
            $this->where_compras .= " AND codagente = " . $this->empresa->var2str($this->codagente);
            $this->where_compras_nf .= " AND codagente = " . $this->empresa->var2str($this->codagente);
        }

        if ($this->codalmacen) {
            $this->where_compras .= " AND codalmacen = " . $this->empresa->var2str($this->codalmacen);
            $this->where_compras_nf .= " AND codalmacen = " . $this->empresa->var2str($this->codalmacen);
        }

        if ($this->coddivisa) {
            $this->where_compras .= " AND coddivisa = " . $this->empresa->var2str($this->coddivisa);
            $this->where_compras_nf .= " AND coddivisa = " . $this->empresa->var2str($this->coddivisa);
        }

        if ($this->codpago) {
            $this->where_compras .= " AND codpago = " . $this->empresa->var2str($this->codpago);
            $this->where_compras_nf .= " AND codpago = " . $this->empresa->var2str($this->codpago);
        }

        $this->where_ventas = $this->where_compras;
        $this->where_ventas_nf = $this->where_compras_nf;

        if ($this->cliente) {
            $this->where_ventas .= " AND codcliente = " . $this->empresa->var2str($this->cliente->codcliente);
            $this->where_ventas_nf .= " AND codcliente = " . $this->empresa->var2str($this->cliente->codcliente);
        }

        if ($this->proveedor) {
            $this->where_compras .= " AND codproveedor = " . $this->empresa->var2str($this->proveedor->codproveedor);
            $this->where_compras_nf .= " AND codproveedor = " . $this->empresa->var2str($this->proveedor->codproveedor);
        }
    }

    /**
     * Devuelve un array con las compras y ventas por día en el plazo de un mes desde hoy.
     * @return type
     */
    public function stats_last_30_days()
    {
        $stats = array();
        $stats_cli = $this->stats_last_30_days_aux($this->table_ventas);
        $stats_pro = $this->stats_last_30_days_aux($this->table_compras);
        $meses = array(
            1 => 'ene',
            2 => 'feb',
            3 => 'mar',
            4 => 'abr',
            5 => 'may',
            6 => 'jun',
            7 => 'jul',
            8 => 'ago',
            9 => 'sep',
            10 => 'oct',
            11 => 'nov',
            12 => 'dic'
        );

        foreach ($stats_cli as $i => $value) {
            $mes = $meses[intval(date('m', strtotime($value['day'])))];

            $stats[$i] = array(
                'day' => date('d', strtotime($value['day'])) . $mes, /// el identificado day será dia + mes
                'total_cli' => round($value['total'], FS_NF0),
                'total_pro' => 0
            );
        }

        foreach ($stats_pro as $i => $value) {
            $stats[$i]['total_pro'] = round($value['total'], FS_NF0);
        }

        return $stats;
    }

    /**
     * Función auxiliar para obtener las compras o ventas por día en el plazo de un mes desde hoy.
     * @param type $table_name
     * @return type
     */
    protected function stats_last_30_days_aux($table_name)
    {
        $stats = array();
        $hasta = date('d-m-Y');
        $desde = date('d-m-Y', strtotime($hasta . '-1 month'));

        /// inicializamos los resultados
        foreach ($this->date_range($desde, $hasta) as $date) {
            $i = strtotime($date);
            $stats[$i] = array(
                'day' => date('d-m-Y', $i),
                'total' => 0
            );
        }

        $where = $this->where_compras_nf;
        if ($table_name == $this->table_ventas) {
            $where = $this->where_ventas_nf;
        }

        $sql = "SELECT fecha as dia, SUM(neto) as total FROM " . $table_name . $where
            . " AND fecha >= " . $this->empresa->var2str($desde)
            . " AND fecha <= " . $this->empresa->var2str($hasta)
            . " GROUP BY dia ORDER BY dia ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $i = strtotime($d['dia']);
                $stats[$i]['total'] = floatval($d['total']);
            }
        }

        return $stats;
    }

    /**
     * Devuelve un array con las compras y ventas agrupadas por mes.
     * @return type
     */
    public function stats_months()
    {
        $stats = array();
        $stats_cli = $this->stats_months_aux($this->table_ventas);
        $stats_pro = $this->stats_months_aux($this->table_compras);
        $meses = array(
            1 => 'ene',
            2 => 'feb',
            3 => 'mar',
            4 => 'abr',
            5 => 'may',
            6 => 'jun',
            7 => 'jul',
            8 => 'ago',
            9 => 'sep',
            10 => 'oct',
            11 => 'nov',
            12 => 'dic'
        );

        foreach ($stats_cli as $i => $value) {
            $stats[$i] = array(
                'month' => $meses[intval($value['month'])] . $value['year'], /// el identificador del mes es mes+año
                'total_cli' => round($value['total'], FS_NF0),
                'total_pro' => 0
            );
        }

        foreach ($stats_pro as $i => $value) {
            $stats[$i]['total_pro'] = round($value['total'], FS_NF0);
        }

        return $stats;
    }

    /**
     * Función auxiliar para obtener las compras o ventas agrupadas por mes.
     * @param type $table_name
     * @return type
     */
    protected function stats_months_aux($table_name)
    {
        $stats = array();

        /// inicializamos los resultados
        foreach ($this->date_range($this->desde, $this->hasta, '+1 month') as $date) {
            $i = intval(date('my', strtotime($date)));
            $stats[$i] = array(
                'month' => date('m', strtotime($date)),
                'year' => date('y', strtotime($date)),
                'total' => 0
            );
        }

        if (strtolower(FS_DB_TYPE) == 'postgresql') {
            $sql_aux = "to_char(fecha,'FMMMYY')";
        } else {
            $sql_aux = "DATE_FORMAT(fecha, '%m%y')";
        }

        $where = $this->where_compras;
        if ($table_name == $this->table_ventas) {
            $where = $this->where_ventas;
        }

        $sql = "SELECT " . $sql_aux . " as mes, SUM(neto) as total FROM " . $table_name
            . $where . " GROUP BY " . $sql_aux . " ORDER BY mes ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $i = intval($d['mes']);
                $stats[$i]['total'] = floatval($d['total']);
            }
        }

        return $stats;
    }

    /**
     * Devuelve un array con las compras y ventas agrupadas por año.
     * @return type
     */
    public function stats_years()
    {
        $stats = array();
        $stats_cli = $this->stats_years_aux($this->table_ventas);
        $stats_pro = $this->stats_years_aux($this->table_compras);

        foreach ($stats_cli as $i => $value) {
            $stats[$i] = array(
                'year' => $value['year'],
                'total_cli' => round($value['total'], FS_NF0),
                'total_pro' => 0
            );
        }

        foreach ($stats_pro as $i => $value) {
            $stats[$i]['total_pro'] = round($value['total'], FS_NF0);
        }

        return $stats;
    }

    /**
     * Función auxiliar para obtener las compras o ventas agrupadas por año.
     * @param type $table_name
     * @param type $num
     * @return type
     */
    protected function stats_years_aux($table_name, $num = 4)
    {
        $stats = array();

        /// inicializamos los resultados
        $desde = date('1-1-Y', strtotime($this->desde));
        foreach ($this->date_range($desde, $this->hasta, '+1 year', 'Y') as $date) {
            $i = intval($date);
            $stats[$i] = array('year' => $i, 'total' => 0);
        }

        if (strtolower(FS_DB_TYPE) == 'postgresql') {
            $sql_aux = "to_char(fecha,'FMYYYY')";
        } else
            $sql_aux = "DATE_FORMAT(fecha, '%Y')";

        $where = $this->where_compras;
        if ($table_name == $this->table_ventas) {
            $where = $this->where_ventas;
        }

        $data = $this->db->select("SELECT " . $sql_aux . " as ano, sum(neto) as total FROM " . $table_name
            . $where . " GROUP BY " . $sql_aux . " ORDER BY ano ASC;");

        if ($data) {
            foreach ($data as $d) {
                $i = intval($d['ano']);
                $stats[$i]['total'] = floatval($d['total']);
            }
        }

        return $stats;
    }

    protected function date_range($first, $last, $step = '+1 day', $format = 'd-m-Y')
    {
        $dates = array();
        $current = strtotime($first);
        $last = strtotime($last);

        while ($current <= $last) {
            $dates[] = date($format, $current);
            $current = strtotime($step, $current);
        }

        return $dates;
    }

    public function stats_series($tabla)
    {
        $stats = array();

        $where = $this->where_compras;
        if ($tabla == $this->table_ventas) {
            $where = $this->where_ventas;
        }

        $sql = "select codserie,sum(neto) as total from " . $tabla;
        $sql .= $where;
        $sql .= " group by codserie order by total desc;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $serie = $this->serie->get($d['codserie']);
                if ($serie) {
                    $stats[] = array(
                        'txt' => $serie->descripcion,
                        'total' => round(floatval($d['total']), FS_NF0)
                    );
                } else {
                    $stats[] = array(
                        'txt' => $d['codserie'],
                        'total' => round(floatval($d['total']), FS_NF0)
                    );
                }
            }
        }

        return $stats;
    }

    public function stats_agentes($tabla)
    {
        $stats = array();

        $where = $this->where_compras;
        if ($tabla == $this->table_ventas) {
            $where = $this->where_ventas;
        }

        $sql = "select codagente,sum(neto) as total from " . $tabla;
        $sql .= $where;
        $sql .= " group by codagente order by total desc;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                if (is_null($d['codagente'])) {
                    $stats[] = array(
                        'txt' => 'Ninguno',
                        'total' => round(floatval($d['total']), FS_NF0)
                    );
                } else {
                    $agente = $this->agente->get($d['codagente']);
                    if ($agente) {
                        $stats[] = array(
                            'txt' => $agente->get_fullname(),
                            'total' => round(floatval($d['total']), FS_NF0)
                        );
                    } else {
                        $stats[] = array(
                            'txt' => $d['codagente'],
                            'total' => round(floatval($d['total']), FS_NF0)
                        );
                    }
                }
            }
        }

        return $stats;
    }

    public function stats_almacenes($tabla)
    {
        $stats = array();

        $where = $this->where_compras;
        if ($tabla == $this->table_ventas) {
            $where = $this->where_ventas;
        }

        $sql = "select codalmacen,sum(neto) as total from " . $tabla;
        $sql .= $where;
        $sql .= " group by codalmacen order by total desc;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $alma = $this->almacen->get($d['codalmacen']);
                if ($alma) {
                    $stats[] = array(
                        'txt' => $alma->nombre,
                        'total' => round(floatval($d['total']), FS_NF0)
                    );
                } else {
                    $stats[] = array(
                        'txt' => $d['codalmacen'],
                        'total' => round(floatval($d['total']), FS_NF0)
                    );
                }
            }
        }

        return $stats;
    }

    public function stats_formas_pago($tabla)
    {
        $stats = array();

        $where = $this->where_compras;
        if ($tabla == $this->table_ventas) {
            $where = $this->where_ventas;
        }

        $sql = "select codpago,sum(neto) as total from " . $tabla;
        $sql .= $where;
        $sql .= " group by codpago order by total desc;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $formap = $this->forma_pago->get($d['codpago']);
                if ($formap) {
                    $stats[] = array(
                        'txt' => $formap->descripcion,
                        'total' => round(floatval($d['total']), FS_NF0)
                    );
                } else {
                    $stats[] = array(
                        'txt' => $d['codpago'],
                        'total' => round(floatval($d['total']), FS_NF0)
                    );
                }
            }
        }

        return $stats;
    }

    public function stats_estados($tabla)
    {
        $stats = array();

        $where = $this->where_compras;
        if ($tabla == $this->table_ventas) {
            $where = $this->where_ventas;
        }

        /// aprobados
        $sql = "select sum(neto) as total from " . $tabla;
        $sql .= $where;
        $sql .= " and idfactura is not null order by total desc;";

        $data = $this->db->select($sql);
        if ($data) {
            if (floatval($data[0]['total'])) {
                $stats[] = array(
                    'txt' => 'facturado',
                    'total' => round(floatval($data[0]['total']), FS_NF0)
                );
            }
        }

        /// pendientes
        $sql = "select sum(neto) as total from " . $tabla;
        $sql .= $where;
        $sql .= " and idfactura is null order by total desc;";

        $data = $this->db->select($sql);
        if ($data) {
            if (floatval($data[0]['total'])) {
                $stats[] = array(
                    'txt' => 'no facturado',
                    'total' => round(floatval($data[0]['total']), FS_NF0)
                );
            }
        }

        return $stats;
    }

    /**
     * Esta función sirve para generar el javascript necesario para que la vista genere
     * las gráficas, ahorrando mucho código.
     * @param type $data
     * @param type $chart_id
     * @return string
     */
    public function generar_chart_pie_js(&$data, $chart_id)
    {
        $js_txt = '';

        if ($data) {
            echo "var " . $chart_id . "_labels = [];\n";
            echo "var " . $chart_id . "_data = [];\n";

            foreach ($data as $d) {
                echo $chart_id . '_labels.push("' . $d['txt'] . '"); ';
                echo $chart_id . '_data.push("' . abs($d['total']) . '");' . "\n";
            }

            /// hacemos el apaño para evitar el problema de charts.js con tabs en boostrap
            echo "var " . $chart_id . "_ctx = document.getElementById('" . $chart_id . "').getContext('2d');\n";
            echo $chart_id . "_ctx.canvas.height = 100;\n";

            echo "var " . $chart_id . "_chart = new Chart(" . $chart_id . "_ctx, {
            type: 'pie',
            data: {
               labels: " . $chart_id . "_labels,
               datasets: [
                  {
                     backgroundColor: default_colors,
                     data: " . $chart_id . "_data
                  }
               ]
            }
         });";
        }

        return $js_txt;
    }

    protected function get_documentos($tabla)
    {
        $doclist = array();

        $where = $this->where_compras;
        if ($tabla == $this->table_ventas) {
            $where = $this->where_ventas;
        }

        $sql = "select * from " . $tabla . $where . " order by fecha asc, codigo asc;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                if ($tabla == $this->table_ventas) {
                    $doclist[] = new albaran_cliente($d);
                } else {
                    $doclist[] = new albaran_proveedor($d);
                }
            }
        }

        return $doclist;
    }

    protected function generar_pdf($tipo = 'compra')
    {
        /// desactivamos el motor de plantillas
        $this->template = FALSE;

        $pdf_doc = new fs_pdf('a4', 'landscape', 'Courier');
        $pdf_doc->pdf->addInfo('Title', $this->nombre_docs . ' de ' . $tipo . ' del ' . $this->desde . ' al ' . $this->hasta);
        $pdf_doc->pdf->addInfo('Subject', $this->nombre_docs . ' de ' . $tipo . ' del ' . $this->desde . ' al ' . $this->hasta);
        $pdf_doc->pdf->addInfo('Author', fs_fix_html($this->empresa->nombre));

        $cliente = 'Proveedor';
        $num2 = 'Num. proveedor';
        $tabla = $this->table_compras;
        if ($tipo == 'venta') {
            $cliente = 'Cliente';
            $num2 = FS_NUMERO2;
            $tabla = $this->table_ventas;
        }

        $encabezado = fs_fix_html($this->empresa->nombre) . ' - ' . $this->nombre_docs
            . ' de ' . $tipo . ' del ' . $this->desde . ' al ' . $this->hasta;

        if ($this->codagente) {
            $encabezado .= ', empleado: ' . $this->codagente;
        }

        if ($this->codserie) {
            $encabezado .= ', serie: ' . $this->codserie;
        }

        if ($this->coddivisa) {
            $encabezado .= ', divisa: ' . $this->coddivisa;
        }

        if ($this->codpago) {
            $encabezado .= ', forma de pago: ' . $this->codpago;
        }

        if ($this->codalmacen) {
            $encabezado .= ', almacén ' . $this->codalmacen;
        }

        $documentos = $this->get_documentos($tabla);
        if (!empty($documentos)) {
            $total_lineas = count($documentos);
            $linea_actual = 0;
            $lppag = 72;
            $pagina = 1;
            $neto = $totaliva = $totalre = $totalirpf = $total = 0;

            while ($linea_actual < $total_lineas) {
                if ($linea_actual > 0) {
                    $pdf_doc->pdf->ezNewPage();
                    $pagina++;
                }

                /// encabezado
                $pdf_doc->pdf->ezText($encabezado . ".\n\n");

                /// tabla principal
                $pdf_doc->new_table();
                $pdf_doc->add_table_header(
                    array(
                        'serie' => '<b>' . strtoupper(FS_SERIE) . '</b>',
                        'doc' => '<b>Documento</b>',
                        'num2' => '<b>' . $num2 . '</b>',
                        'fecha' => '<b>Fecha</b>',
                        'cliente' => '<b>' . $cliente . '</b>',
                        'cifnif' => '<b>' . FS_CIFNIF . '</b>',
                        'neto' => '<b>Neto</b>',
                        'iva' => '<b>' . FS_IVA . '</b>',
                        're' => '<b>RE</b>',
                        'irpf' => '<b>' . FS_IRPF . '</b>',
                        'total' => '<b>Total</b>'
                    )
                );

                for ($i = 0; $i < $lppag && $linea_actual < $total_lineas; $i++) {
                    $linea = array(
                        'serie' => $documentos[$linea_actual]->codserie,
                        'doc' => $documentos[$linea_actual]->codigo,
                        'num2' => '',
                        'fecha' => $documentos[$linea_actual]->fecha,
                        'cliente' => '',
                        'cifnif' => $documentos[$linea_actual]->cifnif,
                        'neto' => $this->show_numero($documentos[$linea_actual]->neto),
                        'iva' => $this->show_numero($documentos[$linea_actual]->totaliva),
                        're' => $this->show_numero($documentos[$linea_actual]->totalrecargo),
                        'irpf' => $this->show_numero($documentos[$linea_actual]->totalirpf),
                        'total' => $this->show_numero($documentos[$linea_actual]->total),
                    );

                    if ($tipo == 'compra') {
                        $linea['num2'] = fs_fix_html($documentos[$linea_actual]->numproveedor);
                        $linea['cliente'] = fs_fix_html($documentos[$linea_actual]->nombre);
                    } else {
                        $linea['num2'] = fs_fix_html($documentos[$linea_actual]->numero2);
                        $linea['cliente'] = fs_fix_html($documentos[$linea_actual]->nombrecliente);
                    }

                    $pdf_doc->add_table_row($linea);

                    $neto += $documentos[$linea_actual]->neto;
                    $totaliva += $documentos[$linea_actual]->totaliva;
                    $totalre += $documentos[$linea_actual]->totalrecargo;
                    $totalirpf += $documentos[$linea_actual]->totalirpf;
                    $total += $documentos[$linea_actual]->total;
                    $i++;
                    $linea_actual++;
                }

                /// añadimos el subtotal
                $linea = array(
                    'serie' => '',
                    'doc' => '',
                    'num2' => '',
                    'fecha' => '',
                    'cliente' => '',
                    'cifnif' => '',
                    'neto' => '<b>' . $this->show_numero($neto) . '</b>',
                    'iva' => '<b>' . $this->show_numero($totaliva) . '</b>',
                    're' => '<b>' . $this->show_numero($totalre) . '</b>',
                    'irpf' => '<b>' . $this->show_numero($totalirpf) . '</b>',
                    'total' => '<b>' . $this->show_numero($total) . '</b>',
                );
                $pdf_doc->add_table_row($linea);

                $pdf_doc->save_table(
                    array(
                        'fontSize' => 8,
                        'cols' => array(
                            'neto' => array('justification' => 'right'),
                            'iva' => array('justification' => 'right'),
                            're' => array('justification' => 'right'),
                            'irpf' => array('justification' => 'right'),
                            'total' => array('justification' => 'right')
                        ),
                        'shaded' => 0,
                        'width' => 780
                    )
                );
            }

            $this->desglose_impuestos_pdf($pdf_doc, $tipo);
        } else {
            $pdf_doc->pdf->ezText($encabezado . '.');
            $pdf_doc->pdf->ezText("\nSin resultados.", 14);
        }

        $pdf_doc->show();
    }

    /**
     * Añade el desglose de impuestos al documento PDF.
     * @param fs_pdf $pdf_doc
     * @param type $tipo
     */
    protected function desglose_impuestos_pdf(&$pdf_doc, $tipo)
    {
        /// a completar en el informe de facturas
    }

    protected function generar_xls($tipo = 'compra')
    {
        /// desactivamos el motor de plantillas
        $this->template = FALSE;

        header("Content-Disposition: attachment; filename=\"informe_" . $this->nombre_docs . "_" . time() . ".xlsx\"");
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        $header = array(
            'serie' => 'string',
            'doc' => 'string',
            'num2' => 'string',
            'num.proveedor' => 'string',
            'fecha' => 'string',
            'cliente' => 'string',
            'proveedor' => 'string',
            FS_CIFNIF => 'string',
            'neto' => '#,##0.00;[RED]-#,##0.00',
            'iva' => '#,##0.00;[RED]-#,##0.00',
            're' => '#,##0.00;[RED]-#,##0.00',
            'irpf' => '#,##0.00;[RED]-#,##0.00',
            'total' => '#,##0.00;[RED]-#,##0.00',
        );

        if ($tipo == 'compra') {
            $tabla = $this->table_compras;
            unset($header['num2']);
            unset($header['cliente']);
        } else {
            $tabla = $this->table_ventas;
            unset($header['num.proveedor']);
            unset($header['proveedor']);
        }

        $writter = new XLSXWriter();
        $writter->setAuthor('FacturaScripts');
        $writter->writeSheetHeader($this->nombre_docs, $header);

        foreach ($this->get_documentos($tabla) as $doc) {
            $linea = array(
                'serie' => $doc->codserie,
                'doc' => $doc->codigo,
                'num2' => '',
                'num.proveedor' => '',
                'fecha' => $doc->fecha,
                'cliente' => '',
                'proveedor' => '',
                FS_CIFNIF => $doc->cifnif,
                'neto' => $doc->neto,
                'iva' => $doc->totaliva,
                're' => $doc->totalrecargo,
                'irpf' => $doc->totalirpf,
                'total' => $doc->total,
            );

            if ($tipo == 'compra') {
                $linea['num.proveedor'] = $doc->numproveedor;
                $linea['proveedor'] = $doc->nombre;
                unset($linea['num2']);
                unset($linea['cliente']);
            } else {
                $linea['num2'] = $doc->numero2;
                $linea['cliente'] = $doc->nombrecliente;
                unset($linea['num.proveedor']);
                unset($linea['proveedor']);
            }

            $writter->writeSheetRow($this->nombre_docs, $linea);
        }

        $writter->writeToStdOut();
    }

    protected function generar_csv($tipo = 'compra')
    {
        /// desactivamos el motor de plantillas
        $this->template = FALSE;

        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"informe_" . $this->nombre_docs . "_" . time() . ".csv\"");

        if ($tipo == 'compra') {
            $tabla = $this->table_compras;
            echo "serie,documento,num.proveedor,fecha,proveedor," . FS_CIFNIF . ",neto," . FS_IVA . ",re," . FS_IRPF . ",total\n";
        } else {
            $tabla = $this->table_ventas;
            echo "serie,documento," . FS_NUMERO2 . ",fecha,cliente," . FS_CIFNIF . ",neto," . FS_IVA . ",re," . FS_IRPF . ",total\n";
        }

        foreach ($this->get_documentos($tabla) as $doc) {
            $linea = array(
                'serie' => $doc->codserie,
                'doc' => $doc->codigo,
                'num2' => '',
                'fecha' => $doc->fecha,
                'cliente' => '',
                'cifnif' => $doc->cifnif,
                'neto' => $doc->neto,
                'iva' => $doc->totaliva,
                're' => $doc->totalrecargo,
                'irpf' => $doc->totalirpf,
                'total' => $doc->total,
            );

            if ($tipo == 'compra') {
                $linea['num2'] = $doc->numproveedor;
                $linea['cliente'] = fs_fix_html($doc->nombre);
            } else {
                $linea['num2'] = $doc->numero2;
                $linea['cliente'] = fs_fix_html($doc->nombrecliente);
            }

            echo '"' . join('","', $linea) . "\"\n";
        }
    }
}
