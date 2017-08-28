<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

class ventas_facturas extends fbase_controller
{

    public $agente;
    public $almacenes;
    public $articulo;
    public $buscar_lineas;
    public $cliente;
    public $codagente;
    public $codalmacen;
    public $codgrupo;
    public $codpago;
    public $codserie;
    public $desde;
    public $estado;
    public $factura;
    public $forma_pago;
    public $grupo;
    public $hasta;
    public $huecos;
    public $lineas;
    public $mostrar;
    public $num_resultados;
    public $offset;
    public $order;
    public $resultados;
    public $serie;
    public $total_resultados;
    public $total_resultados_comision;
    public $total_resultados_txt;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Facturas', 'ventas');
    }

    protected function private_core()
    {
        parent::private_core();

        $this->agente = new agente();
        $this->almacenes = new almacen();
        $this->factura = new factura_cliente();
        $this->forma_pago = new forma_pago();
        $this->grupo = new grupo_clientes();
        $this->huecos = array();
        $this->serie = new serie();

        $this->mostrar = 'todo';
        if (isset($_GET['mostrar'])) {
            $this->mostrar = $_GET['mostrar'];
            setcookie('ventas_fac_mostrar', $this->mostrar, time() + FS_COOKIES_EXPIRE);
        } else if (isset($_COOKIE['ventas_fac_mostrar'])) {
            $this->mostrar = $_COOKIE['ventas_fac_mostrar'];
        }

        $this->offset = 0;
        if (isset($_REQUEST['offset'])) {
            $this->offset = intval($_REQUEST['offset']);
        }

        $this->order = 'fecha DESC';
        if (isset($_GET['order'])) {
            $orden_l = $this->orden();
            if (isset($orden_l[$_GET['order']])) {
                $this->order = $orden_l[$_GET['order']]['orden'];
            }

            setcookie('ventas_fac_order', $this->order, time() + FS_COOKIES_EXPIRE);
        } else if (isset($_COOKIE['ventas_fac_order'])) {
            $this->order = $_COOKIE['ventas_fac_order'];
        }

        if (isset($_POST['buscar_lineas'])) {
            $this->buscar_lineas();
        } else if (isset($_REQUEST['buscar_cliente'])) {
            $this->fbase_buscar_cliente($_REQUEST['buscar_cliente']);
        } else if (isset($_GET['ref'])) {
            $this->template = 'extension/ventas_facturas_articulo';

            $articulo = new articulo();
            $this->articulo = $articulo->get($_GET['ref']);

            $linea = new linea_factura_cliente();
            $this->resultados = $linea->all_from_articulo($_GET['ref'], $this->offset);
        } else {
            $this->share_extension();
            $this->huecos = $this->factura->huecos();
            $this->cliente = FALSE;
            $this->codagente = '';
            $this->codalmacen = '';
            $this->codgrupo = '';
            $this->codpago = '';
            $this->codserie = '';
            $this->desde = '';
            $this->estado = '';
            $this->hasta = '';
            $this->num_resultados = '';
            $this->total_resultados = array();
            $this->total_resultados_comision = 0;
            $this->total_resultados_txt = '';

            if (isset($_GET['delete'])) {
                $this->delete_factura();
            } else {
                if (!isset($_GET['mostrar']) && ( $this->query != '' || isset($_REQUEST['codagente']) || isset($_REQUEST['codcliente']) || isset($_REQUEST['codserie']))) {
                    /**
                     * si obtenermos un codagente, un codcliente o un codserie pasamos direcatemente
                     * a la pestaña de búsqueda, a menos que tengamos un mostrar, que
                     * entonces nos indica donde tenemos que estar.
                     */
                    $this->mostrar = 'buscar';
                }

                if (isset($_REQUEST['codcliente']) && $_REQUEST['codcliente'] != '') {
                    $cli0 = new cliente();
                    $this->cliente = $cli0->get($_REQUEST['codcliente']);
                }

                if (isset($_REQUEST['codagente'])) {
                    $this->codagente = $_REQUEST['codagente'];
                }

                if (isset($_REQUEST['codalmacen'])) {
                    $this->codalmacen = $_REQUEST['codalmacen'];
                }

                if (isset($_REQUEST['codgrupo'])) {
                    $this->codgrupo = $_REQUEST['codgrupo'];
                }

                if (isset($_REQUEST['codpago'])) {
                    $this->codpago = $_REQUEST['codpago'];
                }

                if (isset($_REQUEST['codserie'])) {
                    $this->codserie = $_REQUEST['codserie'];
                }

                if (isset($_REQUEST['desde'])) {
                    $this->desde = $_REQUEST['desde'];
                    $this->hasta = $_REQUEST['hasta'];
                    $this->estado = $_REQUEST['estado'];
                }
            }

            /// añadimos segundo nivel de ordenación
            $order2 = '';
            if (substr($this->order, -4) == 'DESC') {
                $order2 = ', hora DESC, numero DESC';
            } else {
                $order2 = ', hora ASC, numero ASC';
            }

            if ($this->mostrar == 'sinpagar') {
                $this->resultados = $this->factura->all_sin_pagar($this->offset, FS_ITEM_LIMIT, $this->order . $order2);

                if ($this->offset == 0) {
                    /// calculamos el total, pero desglosando por divisa
                    $this->total_resultados = array();
                    $this->total_resultados_txt = 'Suma total de esta página:';
                    foreach ($this->resultados as $fac) {
                        if (!isset($this->total_resultados[$fac->coddivisa])) {
                            $this->total_resultados[$fac->coddivisa] = array(
                                'coddivisa' => $fac->coddivisa,
                                'total' => 0
                            );
                        }

                        $this->total_resultados[$fac->coddivisa]['total'] += $fac->total;
                    }
                }
            } else if ($this->mostrar == 'buscar') {
                $this->buscar($order2);
            } else {
                $this->resultados = $this->factura->all($this->offset, FS_ITEM_LIMIT, $this->order . $order2);
            }
        }
    }

    public function url($busqueda = FALSE)
    {
        if ($busqueda) {
            $codcliente = '';
            if ($this->cliente) {
                $codcliente = $this->cliente->codcliente;
            }

            $url = parent::url() . "&mostrar=" . $this->mostrar
                . "&query=" . $this->query
                . "&codagente=" . $this->codagente
                . "&codalmacen=" . $this->codalmacen
                . "&codcliente=" . $codcliente
                . "&codgrupo=" . $this->codgrupo
                . "&codpago=" . $this->codpago
                . "&codserie=" . $this->codserie
                . "&desde=" . $this->desde
                . "&estado=" . $this->estado
                . "&hasta=" . $this->hasta;

            return $url;
        }

        return parent::url();
    }

    public function paginas()
    {
        if ($this->mostrar == 'sinpagar') {
            $total = $this->total_sinpagar();
        } else if ($this->mostrar == 'buscar') {
            $total = $this->num_resultados;
        } else {
            $total = $this->total_registros();
        }

        return $this->fbase_paginas($this->url(TRUE), $total, $this->offset);
    }

    public function buscar_lineas()
    {
        /// cambiamos la plantilla HTML
        $this->template = 'ajax/ventas_lineas_facturas';

        $this->buscar_lineas = $_POST['buscar_lineas'];
        $linea = new linea_factura_cliente();

        if (isset($_POST['codcliente'])) {
            $this->lineas = $linea->search_from_cliente2($_POST['codcliente'], $this->buscar_lineas, $_POST['buscar_lineas_o']);
        } else {
            $this->lineas = $linea->search($this->buscar_lineas);
        }
    }

    private function share_extension()
    {
        /// añadimos las extensiones para clientes, agentes y artículos
        $extensiones = array(
            array(
                'name' => 'facturas_cliente',
                'page_from' => __CLASS__,
                'page_to' => 'ventas_cliente',
                'type' => 'button',
                'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas',
                'params' => ''
            ),
            array(
                'name' => 'facturas_agente',
                'page_from' => __CLASS__,
                'page_to' => 'admin_agente',
                'type' => 'button',
                'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas de cliente',
                'params' => ''
            ),
            array(
                'name' => 'facturas_articulo',
                'page_from' => __CLASS__,
                'page_to' => 'ventas_articulo',
                'type' => 'tab_button',
                'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas de cliente',
                'params' => ''
            ),
        );
        foreach ($extensiones as $ext) {
            $fsext0 = new fs_extension($ext);
            if (!$fsext0->save()) {
                $this->new_error_msg('Imposible guardar los datos de la extensión ' . $ext['name'] . '.');
            }
        }
    }

    public function total_sinpagar()
    {
        return $this->fbase_sql_total('facturascli', 'idfactura', 'WHERE pagada = false');
    }

    private function total_registros()
    {
        return $this->fbase_sql_total('facturascli', 'idfactura');
    }

    private function buscar($order2)
    {
        $this->resultados = array();
        $this->num_resultados = 0;
        $sql = " FROM facturascli ";
        $where = 'WHERE ';

        if ($this->query) {
            $query = $this->agente->no_html(mb_strtolower($this->query, 'UTF8'));
            $sql .= $where;
            if (is_numeric($query)) {
                $sql .= "(codigo LIKE '%" . $query . "%' OR numero2 LIKE '%" . $query . "%' "
                    . "OR observaciones LIKE '%" . $query . "%' OR cifnif LIKE '" . $query . "%')";
            } else {
                $sql .= "(lower(codigo) LIKE '%" . $query . "%' OR lower(numero2) LIKE '%" . $query . "%' "
                    . "OR lower(cifnif) LIKE '" . $query . "%' "
                    . "OR lower(observaciones) LIKE '%" . str_replace(' ', '%', $query) . "%')";
            }
            $where = ' AND ';
        }

        if ($this->cliente) {
            $sql .= $where . "codcliente = " . $this->agente->var2str($this->cliente->codcliente);
            $where = ' AND ';
        }

        if ($this->codagente != '') {
            $sql .= $where . "codagente = " . $this->agente->var2str($this->codagente);
            $where = ' AND ';
        }

        if ($this->codalmacen != '') {
            $sql .= $where . "codalmacen = " . $this->agente->var2str($this->codalmacen);
            $where = ' AND ';
        }

        if ($this->codgrupo != '') {
            $sql .= $where . "codcliente IN (SELECT codcliente FROM clientes WHERE codgrupo = " . $this->agente->var2str($this->codgrupo) . ")";
            $where = ' AND ';
        }

        if ($this->codpago != '') {
            $sql .= $where . "codpago = " . $this->agente->var2str($this->codpago);
            $where = ' AND ';
        }

        if ($this->codserie != '') {
            $sql .= $where . "codserie = " . $this->agente->var2str($this->codserie);
            $where = ' AND ';
        }

        if ($this->desde) {
            $sql .= $where . "fecha >= " . $this->agente->var2str($this->desde);
            $where = ' AND ';
        }

        if ($this->hasta) {
            $sql .= $where . "fecha <= " . $this->agente->var2str($this->hasta);
            $where = ' AND ';
        }

        if ($this->estado == 'pagadas') {
            $sql .= $where . "pagada";
            $where = ' AND ';
        } else if ($this->estado == 'impagadas') {
            $sql .= $where . "pagada = false";
            $where = ' AND ';
        } else if ($this->estado == 'anuladas') {
            $sql .= $where . "anulada = true";
            $where = ' AND ';
        } else if ($this->estado == 'sinasiento') {
            $sql .= $where . "idasiento IS NULL";
            $where = ' AND ';
        }

        $data = $this->db->select("SELECT COUNT(idfactura) as total" . $sql);
        if ($data) {
            $this->num_resultados = intval($data[0]['total']);

            $data2 = $this->db->select_limit("SELECT *" . $sql . " ORDER BY " . $this->order . $order2, FS_ITEM_LIMIT, $this->offset);
            if ($data2) {
                foreach ($data2 as $d) {
                    $this->resultados[] = new factura_cliente($d);
                }
            }

            $data2 = $this->db->select("SELECT coddivisa,SUM(total) as total" . $sql . " GROUP BY coddivisa");
            if ($data2) {
                $this->total_resultados_txt = 'Suma total de los resultados:';

                foreach ($data2 as $d) {
                    $this->total_resultados[] = array(
                        'coddivisa' => $d['coddivisa'],
                        'total' => floatval($d['total'])
                    );
                }
            }

            if ($this->codagente !== '') {
                /// calculamos la comisión del empleado
                $data2 = $this->db->select("SELECT SUM(neto*porcomision/100) as total" . $sql);
                if ($data2) {
                    $this->total_resultados_comision = floatval($data2[0]['total']);
                }
            }
        }
    }

    private function delete_factura()
    {
        $fact = $this->factura->get($_GET['delete']);
        if ($fact) {
            /// obtenemos las líneas de la factura antes de eliminar
            $lineas = $fact->get_lineas();

            if ($fact->delete()) {
                if (!$fact->anulada) {
                    /// Restauramos el stock
                    $art0 = new articulo();
                    foreach ($lineas as $linea) {
                        /// si la línea pertenece a un albarán no descontamos stock
                        if (is_null($linea->idalbaran)) {
                            $articulo = $art0->get($linea->referencia);
                            if ($articulo) {
                                $articulo->sum_stock($fact->codalmacen, $linea->cantidad, FALSE, $linea->codcombinacion);
                            }
                        }
                    }
                }

                /// ¿Esta factura es rectificativa de otra?
                if ($fact->idfacturarect) {
                    $original = $this->factura->get($fact->idfacturarect);
                    if ($original) {
                        $original->anulada = FALSE;
                        $original->save();
                    }
                }

                $this->clean_last_changes();
            } else {
                $this->new_error_msg("¡Imposible eliminar la factura!");
            }
        } else {
            $this->new_error_msg("Factura no encontrada.");
        }
    }

    public function orden()
    {
        return array(
            'fecha_desc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes-alt" aria-hidden="true"></span>',
                'texto' => 'Fecha',
                'orden' => 'fecha DESC'
            ),
            'fecha_asc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes" aria-hidden="true"></span>',
                'texto' => 'Fecha',
                'orden' => 'fecha ASC'
            ),
            'vencimiento_desc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes-alt" aria-hidden="true"></span>',
                'texto' => 'Vencimiento',
                'orden' => 'vencimiento DESC'
            ),
            'vencimiento_asc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes" aria-hidden="true"></span>',
                'texto' => 'Vencimiento',
                'orden' => 'vencimiento ASC'
            ),
            'codigo_desc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes-alt" aria-hidden="true"></span>',
                'texto' => 'Código',
                'orden' => 'codigo DESC'
            ),
            'codigo_asc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes" aria-hidden="true"></span>',
                'texto' => 'Código',
                'orden' => 'codigo ASC'
            ),
            'total_desc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes-alt" aria-hidden="true"></span>',
                'texto' => 'Total',
                'orden' => 'total DESC'
            )
        );
    }
}
