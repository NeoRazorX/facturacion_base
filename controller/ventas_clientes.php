<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2018  Carlos Garcia Gomez  neorazorx@gmail.com
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

class ventas_clientes extends fbase_controller
{

    public $ciudad;
    public $cliente;
    public $codgrupo;
    public $codpais;
    public $debaja;
    public $grupo;
    public $grupos;
    public $nocifnif;
    public $nuevocli_setup;
    public $offset;
    public $orden;
    public $pais;
    public $provincia;
    public $resultados;
    public $serie;
    public $tarifa;
    public $tarifas;
    public $total_resultados;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Clientes', 'ventas');
    }

    protected function private_core()
    {
        parent::private_core();

        $this->cliente = new cliente();
        $this->grupo = new grupo_clientes();
        $this->pais = new pais();
        $this->serie = new serie();
        $this->tarifa = new tarifa();
        $this->tarifas = $this->tarifa->all();

        $this->cargar_config();

        if (isset($_GET['delete_grupo'])) { /// eliminar un grupo
            $this->eliminar_grupo();
        } else if (isset($_POST['codgrupo'])) { /// añadir/modificar un grupo
            $this->nuevo_grupo();
        } else if (isset($_GET['delete'])) { /// eliminar un cliente
            $this->eliminar_cliente();
        } else if (isset($_POST['cifnif'])) { /// añadir un nuevo cliente
            $this->nuevo_cliente();
        }

        $this->ini_filters();
        $this->buscar();
        $this->grupos = $this->grupo->all();
    }

    private function cargar_config()
    {
        $fsvar = new fs_var();
        $this->nuevocli_setup = $fsvar->array_get(
            array(
            'nuevocli_cifnif_req' => 0,
            'nuevocli_direccion' => 1,
            'nuevocli_direccion_req' => 0,
            'nuevocli_codpostal' => 1,
            'nuevocli_codpostal_req' => 0,
            'nuevocli_pais' => 0,
            'nuevocli_pais_req' => 0,
            'nuevocli_provincia' => 1,
            'nuevocli_provincia_req' => 0,
            'nuevocli_ciudad' => 1,
            'nuevocli_ciudad_req' => 0,
            'nuevocli_telefono1' => 0,
            'nuevocli_telefono1_req' => 0,
            'nuevocli_telefono2' => 0,
            'nuevocli_telefono2_req' => 0,
            'nuevocli_email' => 0,
            'nuevocli_email_req' => 0,
            'nuevocli_codgrupo' => '',
            ), FALSE
        );
    }

    private function ini_filters()
    {
        $this->offset = 0;
        if (isset($_GET['offset'])) {
            $this->offset = intval($_GET['offset']);
        }

        $this->ciudad = '';
        if (isset($_REQUEST['ciudad'])) {
            $this->ciudad = $this->empresa->no_html($_REQUEST['ciudad']);
        }

        $this->provincia = '';
        if (isset($_REQUEST['provincia'])) {
            $this->provincia = $this->empresa->no_html($_REQUEST['provincia']);
        }

        $this->codpais = '';
        if (isset($_REQUEST['codpais'])) {
            $this->codpais = $_REQUEST['codpais'];
        }

        $this->codgrupo = '';
        if (isset($_REQUEST['bcodgrupo'])) {
            $this->codgrupo = $_REQUEST['bcodgrupo'];
        }

        $this->orden = 'lower(nombre) ASC';
        if (isset($_REQUEST['orden'])) {
            $this->orden = $_REQUEST['orden'];
        }

        $this->nocifnif = isset($_REQUEST['nocifnif']);
        $this->debaja = isset($_REQUEST['debaja']);
    }

    public function paginas()
    {
        $url = $this->url() . "&query=" . $this->query
            . "&ciudad=" . $this->ciudad
            . "&provincia=" . $this->provincia
            . "&codpais=" . $this->codpais
            . "&bcodgrupo=" . $this->codgrupo
            . "&orden=" . $this->orden;

        if ($this->nocifnif) {
            $url .= '&nocifnif=TRUE';
        }

        if ($this->debaja) {
            $url .= '&debaja=TRUE';
        }

        return $this->fbase_paginas($url, $this->total_resultados, $this->offset);
    }

    public function nombre_grupo($cod)
    {
        $nombre = '-';

        foreach ($this->grupos as $g) {
            if ($g->codgrupo == $cod) {
                $nombre = $g->nombre;
                break;
            }
        }

        return $nombre;
    }

    public function ciudades()
    {
        return $this->fbase_sql_distinct('dirclientes', 'ciudad', 'provincia', $this->provincia);
    }

    public function provincias()
    {
        return $this->fbase_sql_distinct('dirclientes', 'provincia', 'codpais', $this->codpais);
    }

    public function orden()
    {
        return array(
            'lower(nombre) ASC' => 'Orden: nombre',
            'lower(nombre) DESC' => 'Orden: nombre descendente',
            'cifnif ASC' => 'Orden: ' . FS_CIFNIF,
            'cifnif DESC' => 'Orden: ' . FS_CIFNIF . ' descendente',
            'fechaalta ASC' => 'Orden: fecha',
            'fechaalta DESC' => 'Orden: fecha descendente',
            'codcliente ASC' => 'Orden: código',
            'codcliente DESC' => 'Orden: código descendente'
        );
    }

    private function buscar()
    {
        $this->total_resultados = 0;
        $query = mb_strtolower($this->cliente->no_html($this->query), 'UTF8');
        $sql = " FROM clientes";
        $and = ' WHERE ';

        if (is_numeric($query)) {
            $sql .= $and . "(nombre LIKE '%" . $query . "%'"
                . " OR razonsocial LIKE '%" . $query . "%'"
                . " OR codcliente LIKE '%" . $query . "%'"
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

        if ($this->ciudad != '' || $this->provincia != '' || $this->codpais != '') {
            $sql .= $and . " codcliente IN (SELECT codcliente FROM dirclientes WHERE ";
            $and2 = '';

            if ($this->ciudad != '') {
                $sql .= $and2 . "lower(ciudad) = " . $this->cliente->var2str(mb_strtolower($this->ciudad, 'UTF8'));
                $and2 = ' AND ';
            }

            if ($this->provincia != '') {
                $sql .= $and2 . "lower(provincia) = " . $this->cliente->var2str(mb_strtolower($this->provincia, 'UTF8'));
                $and2 = ' AND ';
            }

            if ($this->codpais != '') {
                $sql .= $and2 . "codpais = " . $this->cliente->var2str($this->codpais);
            }

            $sql .= ")";
            $and = ' AND ';
        }

        if ($this->codgrupo != '') {
            $sql .= $and . "codgrupo = " . $this->cliente->var2str($this->codgrupo);
            $and = ' AND ';
        }

        if ($this->nocifnif) {
            $sql .= $and . "cifnif = ''";
            $and = ' AND ';
        }

        if ($this->debaja) {
            $sql .= $and . "debaja = true";
            $and = ' AND ';
        } else {
            $sql .= $and . "(debaja = false OR debaja IS NULL)";
            $and = ' AND ';
        }

        $data = $this->db->select("SELECT COUNT(codcliente) as total" . $sql . ';');
        if ($data) {
            $this->total_resultados = intval($data[0]['total']);

            $data2 = $this->db->select_limit("SELECT *" . $sql . " ORDER BY " . $this->orden, FS_ITEM_LIMIT, $this->offset);
            if ($data2) {
                foreach ($data2 as $d) {
                    $this->resultados[] = new cliente($d);
                }
            }
        }
    }

    private function nuevo_cliente()
    {
        $cliente = new cliente();
        $cliente->codcliente = $cliente->get_new_codigo();
        if (isset($_POST['codigo']) && !empty($_POST['codigo'])) {
            $cliente->codcliente = $_POST['codigo'];
        }

        $cliente->nombre = $_POST['nombre'];
        $cliente->razonsocial = $_POST['nombre'];
        $cliente->tipoidfiscal = $_POST['tipoidfiscal'];
        $cliente->cifnif = $_POST['cifnif'];
        $cliente->personafisica = isset($_POST['personafisica']);

        if (isset($_POST['email'])) {
            $cliente->email = $_POST['email'];
        }

        if (isset($_POST['scodgrupo']) && $_POST['scodgrupo'] != '') {
            $cliente->codgrupo = $_POST['scodgrupo'];
        }

        if (isset($_POST['telefono1'])) {
            $cliente->telefono1 = $_POST['telefono1'];
        }

        if (isset($_POST['telefono2'])) {
            $cliente->telefono2 = $_POST['telefono2'];
        }

        if ($cliente->exists()) {
            $this->new_error_msg("El cliente ya existe.");
        } elseif ($cliente->save()) {
            $dircliente = new direccion_cliente();
            $dircliente->codcliente = $cliente->codcliente;
            $dircliente->codpais = $this->empresa->codpais;
            $dircliente->provincia = $this->empresa->provincia;
            $dircliente->ciudad = $this->empresa->ciudad;
            $dircliente->descripcion = 'Principal';

            if (isset($_POST['pais'])) {
                $dircliente->codpais = $_POST['pais'];
            }

            if (isset($_POST['provincia'])) {
                $dircliente->provincia = $_POST['provincia'];
            }

            if (isset($_POST['ciudad'])) {
                $dircliente->ciudad = $_POST['ciudad'];
            }

            if (isset($_POST['codpostal'])) {
                $dircliente->codpostal = $_POST['codpostal'];
            }

            if (isset($_POST['direccion'])) {
                $dircliente->direccion = $_POST['direccion'];
            }

            if ($dircliente->save()) {
                if ($this->empresa->contintegrada) {
                    /// forzamos la creación de la subcuenta
                    $cliente->get_subcuenta($this->empresa->codejercicio);
                }

                /// redireccionamos a la página del cliente
                header('location: ' . $cliente->url());
            } else {
                $this->new_error_msg("¡Imposible guardar la dirección del cliente!");
            }
        } else {
            $this->new_error_msg("¡Imposible guardar los datos del cliente!");
        }
    }

    private function eliminar_cliente()
    {
        $cliente = $this->cliente->get($_GET['delete']);
        if ($cliente) {
            if (FS_DEMO) {
                $this->new_error_msg('En el modo demo no se pueden eliminar clientes. Otros usuarios podrían necesitarlos.');
            } else if (!$this->allow_delete) {
                $this->new_error_msg('No tienes permiso para eliminar en esta página.');
            } else if ($cliente->delete()) {
                $this->new_message('Cliente eliminado correctamente.');
            } else {
                $this->new_error_msg('Ha sido imposible eliminar el cliente.');
            }
        } else {
            $this->new_error_msg('Cliente no encontrado.');
        }
    }

    private function nuevo_grupo()
    {
        $grupo = $this->grupo->get($_POST['codgrupo']);
        if ($grupo) {
            $this->new_error_msg('El grupo con código ' . $_POST['codgrupo'] . ' ya existe.');
        } else {
            $grupo = new grupo_clientes();
            $grupo->codgrupo = $_POST['codgrupo'];
            $grupo->nombre = $_POST['nombre'];

            $grupo->codtarifa = NULL;
            if ($_POST['codtarifa'] != '---') {
                $grupo->codtarifa = $_POST['codtarifa'];
            }

            if ($grupo->save()) {
                $this->new_message('Grupo guardado correctamente.');
                header('Location: ' . $grupo->url());
            } else {
                $this->new_error_msg('Imposible guardar el grupo.');
            }
        }
    }

    private function eliminar_grupo()
    {
        $grupo = $this->grupo->get($_GET['delete_grupo']);
        if ($grupo) {
            if (!$this->allow_delete) {
                $this->new_error_msg('No tienes permiso para eliminar en esta página.');
            } else if ($grupo->delete()) {
                $this->new_message('Grupo ' . $grupo->codgrupo . ' eliminado correctamente.');
            } else {
                $this->new_error_msg('Imposible eliminar el grupo.');
            }
        } else {
            $this->new_error_msg('Grupo no encontrado.');
        }
    }
}
