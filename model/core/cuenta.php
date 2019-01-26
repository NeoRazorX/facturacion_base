<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2014-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\model;

/**
 * Elemento de tercer nivel del plan contable.
 * Está relacionada con un único ejercicio y epígrafe,
 * pero puede estar relacionada con muchas subcuentas.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class cuenta extends \fs_extended_model
{

    /**
     * Código de la cuenta.
     * @var string
     */
    public $codcuenta;

    /**
     * Código del ejercicio de esta cuenta.
     * @var string 
     */
    public $codejercicio;

    /**
     *
     * @var string
     */
    public $codepigrafe;

    /**
     *
     * @var string
     */
    public $descripcion;

    /**
     * Clave primaria.
     * @var integer
     */
    public $idcuenta;

    /**
     *
     * @var string
     */
    public $idcuentaesp;

    /**
     *
     * @var int
     */
    public $idepigrafe;

    /**
     * 
     * @param array|bool $data
     */
    public function __construct($data = FALSE)
    {
        parent::__construct('co_cuentas', $data);
    }

    /**
     * 
     * @param int $offset
     *
     * @return \cuenta[]
     */
    public function all($offset = 0)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " ORDER BY codejercicio DESC, codcuenta ASC";
        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * 
     * @param int    $id
     * @param string $codejercicio
     *
     * @return \cuenta[]
     */
    public function all_from_cuentaesp($id, $codejercicio)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE idcuentaesp = " . $this->var2str($id)
            . " AND codejercicio = " . $this->var2str($codejercicio) . " ORDER BY codcuenta ASC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    /**
     * 
     * @param string $codejercicio
     * @param int    $offset
     *
     * @return \cuenta[]
     */
    public function all_from_ejercicio($codejercicio, $offset = 0)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE codejercicio = " . $this->var2str($codejercicio) .
            " ORDER BY codcuenta ASC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * 
     * @param string $codejercicio
     *
     * @return \cuenta[]
     */
    public function full_from_ejercicio($codejercicio)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE codejercicio = " . $this->var2str($codejercicio)
            . " ORDER BY codcuenta ASC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    /**
     * 
     * @param int $id
     *
     * @return \cuenta[]
     */
    public function full_from_epigrafe($id)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE idepigrafe = " . $this->var2str($id)
            . " ORDER BY codcuenta ASC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    /**
     * 
     * @param string $cod
     * @param string $codejercicio
     *
     * @return bool|\cuenta
     */
    public function get_by_codigo($cod, $codejercicio)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE codcuenta = " . $this->var2str($cod) .
            " AND codejercicio = " . $this->var2str($codejercicio) . ";";

        $data = $this->db->select($sql);
        if (!empty($data)) {
            return new \cuenta($data[0]);
        }

        return FALSE;
    }

    /**
     * Obtiene la primera cuenta especial seleccionada.
     *
     * @param string $id
     * @param string $codejercicio
     *
     * @return bool|\cuenta
     */
    public function get_cuentaesp($id, $codejercicio)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE idcuentaesp = " . $this->var2str($id) .
            " AND codejercicio = " . $this->var2str($codejercicio) . " ORDER BY codcuenta ASC;";

        $data = $this->db->select($sql);
        if (!empty($data)) {
            return new \cuenta($data[0]);
        }

        return FALSE;
    }

    /**
     * 
     * @return \ejercicio
     */
    public function get_ejercicio()
    {
        $eje = new \ejercicio();
        return $eje->get($this->codejercicio);
    }

    /**
     * 
     * @return \subcuenta[]
     */
    public function get_subcuentas()
    {
        $subcuenta = new \subcuenta();
        return $subcuenta->all_from_cuenta($this->idcuenta);
    }

    /**
     * 
     * @return string
     */
    protected function install()
    {
        /// forzamos la creación de la tabla epigrafes
        new \epigrafe();

        return '';
    }

    /**
     * 
     * @return string
     */
    public function model_class_name()
    {
        return 'cuenta';
    }

    /**
     * 
     * @param int $suma_codigo
     *
     * @return \subcuenta|bool
     */
    public function new_subcuenta($suma_codigo)
    {
        $ejercicio_model = new \ejercicio();
        $subcuenta_model = new \subcuenta();

        $ejercicio = $ejercicio_model->get($this->codejercicio);
        if (!$ejercicio) {
            return false;
        }

        $codsubcuenta = floatval(sprintf('%-0' . $ejercicio->longsubcuenta . 's', $this->codcuenta)) + $suma_codigo;
        $subcuenta = $subcuenta_model->get_by_codigo($codsubcuenta, $this->codejercicio);
        if ($subcuenta) {
            return $subcuenta;
        }

        $subcuenta = new \subcuenta();
        $subcuenta->codcuenta = $this->codcuenta;
        $subcuenta->idcuenta = $this->idcuenta;
        $subcuenta->codejercicio = $this->codejercicio;
        $subcuenta->codsubcuenta = (string) $codsubcuenta;
        return $subcuenta;
    }

    /**
     * 
     * @return string
     */
    public function primary_column()
    {
        return 'idcuenta';
    }

    /**
     * 
     * @return bool
     */
    public function save()
    {
        return $this->test() ? parent::save() : false;
    }

    /**
     * 
     * @param string $query
     * @param int    $offset
     *
     * @return \cuenta[]
     */
    public function search($query, $offset = 0)
    {
        $query = mb_strtolower($this->no_html($query), 'UTF8');
        $sql = "SELECT * FROM " . $this->table_name() .
            " WHERE codcuenta LIKE '" . $query . "%' OR lower(descripcion) LIKE '%" . $query . "%'" .
            " ORDER BY codejercicio DESC, codcuenta ASC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * 
     * @return bool
     */
    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);

        if (strlen($this->codcuenta) > 0 && strlen($this->descripcion) > 0) {
            return TRUE;
        }

        $this->new_error_msg('Faltan datos en la cuenta');
        return FALSE;
    }

    /**
     * 
     * @param string $type
     *
     * @return string
     */
    public function url($type = 'auto')
    {
        if (is_null($this->idcuenta) || $type == 'list') {
            return 'index.php?page=contabilidad_cuentas';
        }

        return 'index.php?page=contabilidad_cuenta&id=' . $this->idcuenta;
    }

    /**
     * 
     * @param array $data
     *
     * @return \cuenta[]
     */
    private function all_from_data(&$data)
    {
        $cuenlist = [];
        if (!empty($data)) {
            foreach ($data as $c) {
                $cuenlist[] = new \cuenta($c);
            }
        }

        return $cuenlist;
    }
}
