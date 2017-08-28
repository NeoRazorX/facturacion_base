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

/**
 * Description of ventas_grupo
 *
 * @author Carlos Garcia Gomez
 */
class ventas_grupo extends fbase_controller
{

    public $ciudad;
    public $clientes;
    public $codpais;
    public $direccion;
    public $grupo;
    public $mostrar;
    public $orden;
    public $pais;
    public $provincia;
    public $resultados;
    public $tarifa;
    public $total_clientes;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Grupo', 'ventas', FALSE, FALSE);
    }

    protected function private_core()
    {
        parent::private_core();

        $this->pais = new pais();
        $this->tarifa = new tarifa();

        $this->mostrar = 'clientes';
        if (isset($_REQUEST['mostrar'])) {
            $this->mostrar = $_REQUEST['mostrar'];
        }

        $this->grupo = FALSE;
        if (isset($_REQUEST['cod'])) {
            $grupo = new grupo_clientes();
            $this->grupo = $grupo->get($_REQUEST['cod']);
        }

        if ($this->grupo) {
            if (isset($_POST['nombre'])) {
                $this->modificar();
            } else if (isset($_POST['anyadir'])) {
                $this->anyadir_clientes();
            } else if (isset($_GET['quitar'])) {
                $this->quitar_cliente();
            }

            /// El listado de clientes lo cargamos siempre, así tenemos el número
            $this->clientes = $this->clientes_from_grupo($this->grupo->codgrupo);

            if ($this->mostrar == 'agregar_clientes') {
                $this->ini_filters();
                $this->buscar_clientes();
            }
        } else {
            $this->new_error_msg('Grupo no encontrado.', 'error', FALSE, FALSE);
        }
    }

    public function url()
    {
        if ($this->grupo) {
            return $this->grupo->url();
        } else {
            return parent::url();
        }
    }

    private function ini_filters()
    {
        $this->direccion = '';
        if (isset($_POST['direccion'])) {
            $this->direccion = $_POST['direccion'];
        }

        $this->codpais = FALSE;
        if (isset($_POST['codpais'])) {
            $this->codpais = $_POST['codpais'];
        }

        $this->provincia = FALSE;
        if (isset($_POST['provincia'])) {
            $this->provincia = $_POST['provincia'];
        }

        $this->ciudad = FALSE;
        if (isset($_POST['ciudad'])) {
            $this->ciudad = $_POST['ciudad'];
        }

        $this->orden = 'lower(nombre) ASC';
        if (isset($_POST['orden'])) {
            $this->orden = $_POST['orden'];
        }
    }

    private function modificar()
    {
        $this->grupo->nombre = $_POST['nombre'];

        $this->grupo->codtarifa = NULL;
        if ($_POST['codtarifa']) {
            $this->grupo->codtarifa = $_POST['codtarifa'];
        }

        if ($this->grupo->save()) {
            $this->new_message('Datos guardados correctamente.');
        } else {
            $this->new_error_msg('Error al guardar los datos.');
        }
    }

    private function clientes_from_grupo($cod)
    {
        $clist = array();
        $sql = "FROM clientes WHERE codgrupo = " . $this->grupo->var2str($cod);

        $data = $this->db->select('SELECT COUNT(*) as total ' . $sql);
        if ($data) {
            $this->total_clientes = intval($data[0]['total']);

            $data = $this->db->select('SELECT * ' . $sql . " ORDER BY nombre ASC;");
            if ($data) {
                foreach ($data as $d) {
                    $clist[] = new cliente($d);
                }
            }
        }

        return $clist;
    }

    private function buscar_clientes()
    {
        $query = mb_strtolower($this->grupo->no_html($this->query), 'UTF8');
        $sql = " FROM clientes WHERE (debaja = false OR debaja IS NULL) AND "
            . "(codgrupo IS NULL OR codgrupo != " . $this->grupo->var2str($this->grupo->codgrupo) . ')';

        if (is_numeric($query)) {
            $sql .= " AND (nombre LIKE '%" . $query . "%'"
                . " OR razonsocial LIKE '%" . $query . "%'"
                . " OR codcliente LIKE '%" . $query . "%'"
                . " OR cifnif LIKE '%" . $query . "%'"
                . " OR telefono1 LIKE '" . $query . "%'"
                . " OR telefono2 LIKE '" . $query . "%'"
                . " OR observaciones LIKE '%" . $query . "%')";
        } else if ($query != '') {
            $buscar = str_replace(' ', '%', $query);
            $sql .= " AND (lower(nombre) LIKE '%" . $buscar . "%'"
                . " OR lower(razonsocial) LIKE '%" . $buscar . "%'"
                . " OR lower(cifnif) LIKE '%" . $buscar . "%'"
                . " OR lower(observaciones) LIKE '%" . $buscar . "%'"
                . " OR lower(email) LIKE '%" . $buscar . "%')";
        }

        if ($this->ciudad != '' OR $this->provincia != '' OR $this->codpais != '') {
            $sql .= " AND codcliente IN (SELECT codcliente FROM dirclientes WHERE 1=1 ";

            if ($this->ciudad != '') {
                $sql .= " AND lower(ciudad) = " . $this->grupo->var2str(mb_strtolower($this->ciudad, 'UTF8'));
            }

            if ($this->provincia != '') {
                $sql .= " AND lower(provincia) = " . $this->grupo->var2str(mb_strtolower($this->provincia, 'UTF8'));
            }

            if ($this->codpais != '') {
                $sql .= " AND codpais = " . $this->grupo->var2str($this->codpais);
            }

            $sql .= ")";
        }

        $data = $this->db->select_limit("SELECT *" . $sql . " ORDER BY " . $this->orden, 100, 0);
        if ($data) {
            foreach ($data as $d) {
                $this->resultados[] = new cliente($d);
            }
        }
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
            'fechaalta DESC' => 'Orden: fecha descendente'
        );
    }

    private function anyadir_clientes()
    {
        $errores = 0;
        $modificados = 0;

        $cli0 = new cliente();
        foreach ($_POST['anyadir'] as $codcliente) {
            $cliente = $cli0->get($codcliente);
            if ($cliente) {
                $cliente->codgrupo = $this->grupo->codgrupo;
                if ($cliente->save()) {
                    $modificados++;
                } else {
                    $errores++;
                }
            }
        }

        $this->new_message($modificados . ' clientes añadidos, ' . $errores . ' errores.');
    }

    private function quitar_cliente()
    {
        $cli0 = new cliente();
        $cliente = $cli0->get($_GET['quitar']);
        if ($cliente) {
            $cliente->codgrupo = NULL;

            if ($cliente->save()) {
                $this->new_message('Datos guardados correctamente. El cliente ' . $cliente->codcliente
                    . ' ya no pertenece al grupo ' . $this->grupo->codgrupo . '.');
            } else {
                $this->new_error_msg('Error al quitar al cliente ' . $cliente->codcliente
                    . ' del grupo ' . $this->grupo->codgrupo . '.');
            }
        } else {
            $this->new_error_msg('Cliente no encontrado.');
        }
    }
}
