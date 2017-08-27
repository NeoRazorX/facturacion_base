<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

class admin_agentes extends fbase_controller
{

    public $agente;
    public $ciudad;
    public $offset;
    public $orden;
    public $provincia;
    public $resultados;
    public $total_resultados;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Empleados', 'admin');
    }

    protected function private_core()
    {
        parent::private_core();

        $this->agente = new agente();

        if (isset($_POST['sdnicif'])) {
            $this->nuevo_agente();
        } else if (isset($_GET['delete'])) {
            $this->eliminar_agente();
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

        $this->ciudad = '';
        if (isset($_REQUEST['ciudad'])) {
            $this->ciudad = $_REQUEST['ciudad'];
        }

        $this->provincia = '';
        if (isset($_REQUEST['provincia'])) {
            $this->provincia = $_REQUEST['provincia'];
        }

        foreach ($this->orden() as $key => $value) {
            $this->orden = $key;
            break;
        }
        if (isset($_REQUEST['orden'])) {
            $this->orden = $_REQUEST['orden'];
        }
    }

    private function nuevo_agente()
    {
        $age0 = new agente();
        $age0->codagente = $age0->get_new_codigo();
        $age0->nombre = $_POST['snombre'];
        $age0->apellidos = $_POST['sapellidos'];
        $age0->dnicif = $_POST['sdnicif'];
        $age0->telefono = $_POST['stelefono'];
        $age0->email = $_POST['semail'];

        if ($age0->save()) {
            $this->new_message("Empleado " . $age0->codagente . " guardado correctamente.");
            header('location: ' . $age0->url());
        } else {
            $this->new_error_msg("¡Imposible guardar el empleado!");
        }
    }

    private function eliminar_agente()
    {
        $age0 = $this->agente->get($_GET['delete']);
        if ($age0) {
            if (FS_DEMO) {
                $this->new_error_msg('En el modo <b>demo</b> no se pueden eliminar empleados. Otro usuario podría estar usándolo.');
            } else if (!$this->allow_delete) {
                $this->new_error_msg('No tienes permiso para eliminar en esta página.');
            } else if ($age0->delete()) {
                $this->new_message("Empleado " . $age0->codagente . " eliminado correctamente.");
            } else {
                $this->new_error_msg("¡Imposible eliminar el empleado!");
            }
        } else {
            $this->new_error_msg("¡Empleado no encontrado!");
        }
    }

    public function paginas()
    {
        $url = $this->url() . "&query=" . $this->query
            . "&ciudad=" . $this->ciudad
            . "&provincia=" . $this->provincia
            . "&orden=" . $this->orden;

        return $this->fbase_paginas($url, $this->total_resultados, $this->offset);
    }

    public function ciudades()
    {
        return $this->fbase_sql_distinct('agentes', 'ciudad', 'provincia', $this->provincia);
    }

    public function provincias()
    {
        return $this->fbase_sql_distinct('agentes', 'provincia');
    }

    public function orden()
    {
        return array(
            'lower(nombre) ASC, lower(apellidos) ASC' => 'Orden: nombre',
            'lower(nombre) DESC, lower(apellidos) DESC' => 'Orden: nombre descendente',
            'dnicif ASC' => 'Orden: ' . FS_CIFNIF,
            'dnicif DESC' => 'Orden: ' . FS_CIFNIF . ' descendente',
            'f_alta ASC' => 'Orden: fecha',
            'f_alta DESC' => 'Orden: fecha descendente',
            'codagente ASC' => 'Orden: código',
            'codagente DESC' => 'Orden: código descendente'
        );
    }

    private function buscar()
    {
        $this->total_resultados = 0;
        $query = mb_strtolower($this->agente->no_html($this->query), 'UTF8');
        $sql = " FROM agentes";
        $and = ' WHERE ';

        if (is_numeric($query)) {
            $sql .= $and . "(codagente LIKE '%" . $query . "%'"
                . " OR dnicif LIKE '%" . $query . "%'"
                . " OR telefono LIKE '" . $query . "%')";
            $and = ' AND ';
        } else if ($query != '') {
            $buscar = str_replace(' ', '%', $query);
            $sql .= $and . "(lower(nombre) LIKE '%" . $buscar . "%'"
                . " OR lower(apellidos) LIKE '%" . $buscar . "%'"
                . " OR lower(dnicif) LIKE '%" . $buscar . "%'"
                . " OR lower(email) LIKE '%" . $buscar . "%')";
            $and = ' AND ';
        }

        if ($this->ciudad != '') {
            $sql .= $and . "lower(ciudad) = " . $this->agente->var2str(mb_strtolower($this->ciudad, 'UTF8'));
            $and = ' AND ';
        }

        if ($this->provincia != '') {
            $sql .= $and . "lower(provincia) = " . $this->agente->var2str(mb_strtolower($this->provincia, 'UTF8'));
        }

        $data = $this->db->select("SELECT COUNT(codagente) as total" . $sql . ';');
        if ($data) {
            $this->total_resultados = intval($data[0]['total']);

            $data2 = $this->db->select_limit("SELECT *" . $sql . " ORDER BY " . $this->orden, FS_ITEM_LIMIT, $this->offset);
            if ($data2) {
                foreach ($data2 as $d) {
                    $this->resultados[] = new agente($d);
                }
            }
        }
    }
}
