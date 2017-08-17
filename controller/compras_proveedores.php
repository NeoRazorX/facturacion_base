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

class compras_proveedores extends fbase_controller
{

    public $debaja;
    public $num_resultados;
    public $offset;
    public $orden;
    public $pais;
    public $proveedor;
    public $resultados;
    public $tipo;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Proveedores / Acreedores', 'compras');
    }

    protected function private_core()
    {
        parent::private_core();

        $this->pais = new pais();
        $this->proveedor = new proveedor();

        if (isset($_GET['delete'])) {
            $this->eliminar_proveedor();
        } else if (isset($_POST['cifnif'])) {
            $this->nuevo_proveedor();
        }

        $this->ini_filters();
        $this->buscar();
    }

    private function ini_filters()
    {
        $this->offset = 0;
        if (isset($_GET['offset'])) {
            $this->offset = intval($_GET['offset']);
        }

        $this->orden = 'lower(nombre) ASC';
        if (isset($_REQUEST['orden'])) {
            $this->orden = $_REQUEST['orden'];
        }

        $this->tipo = '';
        if (isset($_REQUEST['tipo'])) {
            $this->tipo = $_REQUEST['tipo'];
        }

        $this->debaja = isset($_REQUEST['debaja']);
    }

    private function nuevo_proveedor()
    {
        $proveedor = new proveedor();
        $proveedor->codproveedor = $proveedor->get_new_codigo();
        $proveedor->nombre = $_POST['nombre'];
        $proveedor->razonsocial = $_POST['nombre'];
        $proveedor->tipoidfiscal = $_POST['tipoidfiscal'];
        $proveedor->cifnif = $_POST['cifnif'];
        $proveedor->acreedor = isset($_POST['acreedor']);
        $proveedor->personafisica = isset($_POST['personafisica']);

        if ($proveedor->save()) {
            $dirproveedor = new direccion_proveedor();
            $dirproveedor->codproveedor = $proveedor->codproveedor;
            $dirproveedor->descripcion = "Principal";
            $dirproveedor->codpais = $_POST['pais'];
            $dirproveedor->provincia = $_POST['provincia'];
            $dirproveedor->ciudad = $_POST['ciudad'];
            $dirproveedor->codpostal = $_POST['codpostal'];
            $dirproveedor->direccion = $_POST['direccion'];
            $dirproveedor->apartado = $_POST['apartado'];

            if ($dirproveedor->save()) {
                if ($this->empresa->contintegrada) {
                    /// forzamos crear la subcuenta
                    $proveedor->get_subcuenta($this->empresa->codejercicio);
                }

                /// redireccionamos a la página del proveedor
                header('location: ' . $proveedor->url());
            } else
                $this->new_error_msg("¡Imposible guardar la dirección el proveedor!");
        } else
            $this->new_error_msg("¡Imposible guardar el proveedor!");
    }

    private function eliminar_proveedor()
    {
        $proveedor = $this->proveedor->get($_GET['delete']);
        if ($proveedor) {
            if (FS_DEMO) {
                $this->new_error_msg('En el modo demo no se pueden eliminar proveedores.
               Otros usuarios podrían necesitarlos.');
            } else if (!$this->allow_delete) {
                $this->new_error_msg('No tienes permiso para eliminar en esta página.');
            } else if ($proveedor->delete()) {
                $this->new_message('Proveedor eliminado correctamente.');
            } else
                $this->new_error_msg('Ha sido imposible borrar el proveedor.');
        } else
            $this->new_message('Proveedor no encontrado.');
    }

    private function buscar()
    {
        $this->total_resultados = 0;
        $query = mb_strtolower($this->proveedor->no_html($this->query), 'UTF8');
        $sql = " FROM proveedores";
        $and = ' WHERE ';

        if (is_numeric($query)) {
            $sql .= $and . "(nombre LIKE '%" . $query . "%'"
                . " OR razonsocial LIKE '%" . $query . "%'"
                . " OR codproveedor LIKE '%" . $query . "%'"
                . " OR cifnif LIKE '%" . $query . "%'"
                . " OR telefono1 LIKE '" . $query . "%'"
                . " OR telefono2 LIKE '" . $query . "%'"
                . " OR observaciones LIKE '%" . $query . "%')";
            $and = ' AND ';
        } else if ($query != '') {
            $buscar = str_replace(' ', '%', $query);
            $sql .= $and . "(lower(nombre) LIKE '%" . $buscar . "%'"
                . " OR lower(razonsocial) LIKE '%" . $buscar . "%'"
                . " OR lower(cifnif) LIKE '%" . $buscar . "%'"
                . " OR lower(observaciones) LIKE '%" . $buscar . "%'"
                . " OR lower(email) LIKE '%" . $buscar . "%')";
            $and = ' AND ';
        }

        if ($this->tipo == 'acreedores') {
            $sql .= $and . "acreedor = true";
            $and = ' AND ';
        } else if ($this->tipo == 'noacreedores') {
            $sql .= $and . "acreedor = false";
            $and = ' AND ';
        }

        if ($this->debaja) {
            $sql .= $and . "debaja = true";
            $and = ' AND ';
        } else {
            $sql .= $and . "(debaja = false OR debaja IS NULL)";
            $and = ' AND ';
        }

        $data = $this->db->select("SELECT COUNT(codproveedor) as total" . $sql . ';');
        if ($data) {
            $this->num_resultados = intval($data[0]['total']);

            $data2 = $this->db->select_limit("SELECT *" . $sql . " ORDER BY " . $this->orden, FS_ITEM_LIMIT, $this->offset);
            if ($data2) {
                foreach ($data2 as $d) {
                    $this->resultados[] = new proveedor($d);
                }
            }
        }
    }

    public function orden()
    {
        return array(
            'lower(nombre) ASC' => 'Orden: nombre',
            'lower(nombre) DESC' => 'Orden: nombre descendente',
            'cifnif ASC' => 'Orden: ' . FS_CIFNIF,
            'cifnif DESC' => 'Orden: ' . FS_CIFNIF . ' descendente',
            'codproveedor ASC' => 'Orden: código',
            'codproveedor DESC' => 'Orden: código descendente'
        );
    }

    public function paginas()
    {
        $url = $this->url() . "&query=" . $this->query
            . "&tipo=" . $this->tipo
            . "&orden=" . $this->orden;

        if ($this->debaja) {
            $url .= '&debaja=TRUE';
        }

        return $this->fbase_paginas($url, $this->num_resultados, $this->offset);
    }
}
