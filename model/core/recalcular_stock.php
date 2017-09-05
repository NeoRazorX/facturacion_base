<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2017  Carlos Garcia Gomez  neorazorx@gmail.com
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
namespace FacturaScripts\model;

/**
 * Description of recalcular_stock
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class recalcular_stock
{

    protected $db;
    protected $linea_transferencia_stock;
    protected $regularizacion_stock;

    public function __construct()
    {
        $this->db = new \fs_db2();
        $this->linea_transferencia_stock = new \linea_transferencia_stock();
        $this->regularizacion_stock = new \regularizacion_stock();

        /// forzamos la comprobación de las tablas de albaranes
        new albaran_cliente();
        new linea_albaran_cliente();
        new albaran_proveedor();
        new linea_albaran_proveedor();
    }

    public function get_movimientos($ref, $codalmacen = '', $desde = '', $hasta = '', $codagente = '')
    {
        $mlist = array();

        $this->get_regularizaciones($mlist, $ref, $codalmacen, $desde, $hasta);
        if ($codalmacen) {
            $this->get_transferencias($mlist, $ref, $codalmacen, $desde, $hasta);
        }

        $this->get_movimientos_compra($mlist, $ref, $codalmacen, $desde, $hasta, $codagente);
        $this->get_movimientos_venta($mlist, $ref, $codalmacen, $desde, $hasta, $codagente);

        /// ordenamos por fecha y hora
        usort($mlist, function($a, $b) {
            if (strtotime($a['fecha'] . ' ' . $a['hora']) == strtotime($b['fecha'] . ' ' . $b['hora'])) {
                return 0;
            } else if (strtotime($a['fecha'] . ' ' . $a['hora']) < strtotime($b['fecha'] . ' ' . $b['hora'])) {
                return -1;
            }

            return 1;
        });

        /// recalculamos
        $inicial = 0;
        foreach ($mlist as $i => $value) {
            $inicial += $value['movimiento'];
            $mlist[$i]['final'] = $inicial;
        }

        return $mlist;
    }

    protected function get_regularizaciones(&$mlist, $ref, $codalmacen = '', $desde = '', $hasta = '')
    {
        foreach ($this->regularizacion_stock->all_from_articulo($ref, $codalmacen, $desde, $hasta) as $reg) {
            $mlist[] = array(
                'referencia' => $ref,
                'codalmacen' => $reg->codalmacendest,
                'origen' => 'Regularización',
                'url' => 'index.php?page=ventas_articulo&ref=' . $ref . '#stock',
                'clipro' => '-',
                'movimiento' => $reg->cantidadfin - $reg->cantidadini,
                'precio' => 0,
                'dto' => 0,
                'inicial' => $reg->cantidadini,
                'final' => $reg->cantidadfin,
                'fecha' => $reg->fecha,
                'hora' => $reg->hora
            );
        }
    }

    protected function get_transferencias(&$mlist, $ref, $codalmacen, $desde = '', $hasta = '')
    {
        /**
         * Transferencias de stock con origen $codalmacen
         */
        foreach ($this->linea_transferencia_stock->all_from_referencia($ref, $codalmacen, '', $desde, $hasta) as $lin) {
            $mlist[] = array(
                'referencia' => $lin->referencia,
                'codalmacen' => $codalmacen,
                'origen' => 'Transferencia por salida ' . $lin->idtrans,
                'url' => 'index.php?page=editar_transferencia_stock&id=' . $lin->idtrans,
                'clipro' => '',
                'movimiento' => 0 - $lin->cantidad,
                'precio' => 0,
                'dto' => 0,
                'inicial' => 0,
                'final' => 0,
                'fecha' => $lin->fecha(),
                'hora' => $lin->hora()
            );
        }

        /**
         * Transferencias de stock con destino $codalmacen
         */
        foreach ($this->linea_transferencia_stock->all_from_referencia($ref, '', $codalmacen, $desde, $hasta) as $lin) {
            $mlist[] = array(
                'referencia' => $lin->referencia,
                'codalmacen' => $codalmacen,
                'origen' => 'Transferencia por ingreso ' . $lin->idtrans,
                'url' => 'index.php?page=editar_transferencia_stock&id=' . $lin->idtrans,
                'clipro' => '',
                'movimiento' => $lin->cantidad,
                'precio' => 0,
                'dto' => 0,
                'inicial' => 0,
                'final' => 0,
                'fecha' => $lin->fecha(),
                'hora' => $lin->hora()
            );
        }
    }

    private function get_movimientos_compra(&$mlist, $ref, $codalmacen = '', $desde = '', $hasta = '', $codagente = '')
    {
        $sql_extra = $this->set_sql_extra($codalmacen, $desde, $hasta, $codagente);

        /// buscamos el artículo en albaranes de compra
        $sql = "SELECT a.codigo,l.cantidad,l.pvpunitario,l.dtopor,a.fecha,a.hora"
            . ",a.codalmacen,a.idalbaran,a.codproveedor,a.nombre"
            . " FROM albaranesprov a, lineasalbaranesprov l"
            . " WHERE a.idalbaran = l.idalbaran AND l.referencia = " . $this->regularizacion_stock->var2str($ref) . $sql_extra;

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $mlist[] = array(
                    'referencia' => $ref,
                    'codalmacen' => $d['codalmacen'],
                    'origen' => 'Albaran compra ' . $d['codigo'],
                    'url' => 'index.php?page=compras_albaran&id=' . $d['idalbaran'],
                    'clipro' => $d['codproveedor'] . ' - ' . $d['nombre'],
                    'movimiento' => floatval($d['cantidad']),
                    'precio' => floatval($d['pvpunitario']),
                    'dto' => floatval($d['dtopor']),
                    'inicial' => 0,
                    'final' => 0,
                    'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                    'hora' => $d['hora']
                );
            }
        }

        /// buscamos el artículo en facturas de compra
        $sql = "SELECT f.codigo,l.cantidad,l.pvpunitario,l.dtopor,f.fecha,f.hora"
            . ",f.codalmacen,f.idfactura,f.codproveedor,f.nombre"
            . " FROM facturasprov f, lineasfacturasprov l"
            . " WHERE f.idfactura = l.idfactura AND l.idalbaran IS NULL"
            . " AND f.anulada = false AND l.referencia = " . $this->regularizacion_stock->var2str($ref) . $sql_extra;

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $mlist[] = array(
                    'referencia' => $ref,
                    'codalmacen' => $d['codalmacen'],
                    'origen' => 'Factura compra ' . $d['codigo'],
                    'url' => 'index.php?page=compras_factura&id=' . $d['idfactura'],
                    'clipro' => $d['codproveedor'] . ' - ' . $d['nombre'],
                    'movimiento' => floatval($d['cantidad']),
                    'precio' => floatval($d['pvpunitario']),
                    'dto' => floatval($d['dtopor']),
                    'inicial' => 0,
                    'final' => 0,
                    'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                    'hora' => $d['hora']
                );
            }
        }
    }

    private function get_movimientos_venta(&$mlist, $ref, $codalmacen = '', $desde = '', $hasta = '', $codagente = '')
    {
        $sql_extra = $this->set_sql_extra($codalmacen, $desde, $hasta, $codagente);

        /// buscamos el artículo en albaranes de venta
        $sql = "SELECT a.codigo,l.cantidad,l.pvpunitario,l.dtopor,a.fecha,a.hora"
            . ",a.codalmacen,a.idalbaran,a.codcliente,a.nombrecliente"
            . " FROM albaranescli a, lineasalbaranescli l"
            . " WHERE a.idalbaran = l.idalbaran AND l.referencia = " . $this->regularizacion_stock->var2str($ref) . $sql_extra;

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $mlist[] = array(
                    'referencia' => $ref,
                    'codalmacen' => $d['codalmacen'],
                    'origen' => 'Albaran venta ' . $d['codigo'],
                    'url' => 'index.php?page=ventas_albaran&id=' . $d['idalbaran'],
                    'clipro' => $d['codcliente'] . ' - ' . $d['nombrecliente'],
                    'movimiento' => 0 - floatval($d['cantidad']),
                    'precio' => floatval($d['pvpunitario']),
                    'dto' => floatval($d['dtopor']),
                    'inicial' => 0,
                    'final' => 0,
                    'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                    'hora' => $d['hora']
                );
            }
        }

        /// buscamos el artículo en facturas de venta
        $sql = "SELECT f.codigo,l.cantidad,l.pvpunitario,l.dtopor,f.fecha,f.hora"
            . ",f.codalmacen,f.idfactura,f.codcliente,f.nombrecliente"
            . " FROM facturascli f, lineasfacturascli l"
            . " WHERE f.idfactura = l.idfactura AND l.idalbaran IS NULL"
            . " AND f.anulada = false AND l.referencia = " . $this->regularizacion_stock->var2str($ref) . $sql_extra;

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $mlist[] = array(
                    'referencia' => $ref,
                    'codalmacen' => $d['codalmacen'],
                    'origen' => 'Factura venta ' . $d['codigo'],
                    'url' => 'index.php?page=ventas_factura&id=' . $d['idfactura'],
                    'clipro' => $d['codcliente'] . ' - ' . $d['nombrecliente'],
                    'movimiento' => 0 - floatval($d['cantidad']),
                    'precio' => floatval($d['pvpunitario']),
                    'dto' => floatval($d['dtopor']),
                    'inicial' => 0,
                    'final' => 0,
                    'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                    'hora' => $d['hora']
                );
            }
        }
    }

    protected function set_sql_extra($codalmacen = '', $desde = '', $hasta = '', $codagente = '')
    {
        $sql_extra = '';

        if ($codalmacen) {
            $sql_extra .= " AND codalmacen = " . $this->regularizacion_stock->var2str($codalmacen);
        }

        if ($desde) {
            $sql_extra .= " AND fecha >= " . $this->regularizacion_stock->var2str($desde);
        }

        if ($hasta) {
            $sql_extra .= " AND fecha <= " . $this->regularizacion_stock->var2str($hasta);
        }

        if ($codagente) {
            $sql_extra .= " AND codagente = " . $this->regularizacion_stock->var2str($codagente);
        }

        return $sql_extra;
    }
}
