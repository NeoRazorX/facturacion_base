<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2013-2018 Carlos Garcia Gomez <neorazorx@gmail.com>
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
 * La cantidad en inventario de un artículo en un almacén concreto.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class stock extends \fs_extended_model
{

    /**
     *
     * @var float
     */
    public $cantidad;

    /**
     *
     * @var float
     */
    public $cantidadultreg;

    /**
     *
     * @var string
     */
    public $codalmacen;

    /**
     *
     * @var float
     */
    public $disponible;

    /**
     *
     * @var string
     */
    public $fechaultreg;

    /**
     *
     * @var string
     */
    public $horaultreg;

    /**
     * Clave primaria.
     *
     * @var int
     */
    public $idstock;

    /**
     *
     * @var string
     */
    public $nombre;

    /**
     *
     * @var float
     */
    public $pterecibir;

    /**
     *
     * @var string
     */
    public $referencia;

    /**
     *
     * @var float
     */
    public $reservada;

    /**
     *
     * @var float
     */
    public $stockmax;

    /**
     *
     * @var float
     */
    public $stockmin;

    /**
     *
     * @var string
     */
    public $ubicacion;

    /**
     * 
     * @param array|bool $data
     */
    public function __construct($data = false)
    {
        parent::__construct('stocks', $data);
    }

    public function clear()
    {
        parent::clear();
        $this->cantidad = 0.0;
        $this->cantidadultreg = 0.0;
        $this->disponible = 0.0;
        $this->fechaultreg = date('d-m-Y');
        $this->horaultreg = date('H:i');
        $this->nombre = '';
        $this->pterecibir = 0.0;
        $this->reservada = 0.0;
        $this->stockmax = 0.0;
        $this->stockmin = 0.0;
    }

    /**
     * 
     * @return string
     */
    protected function install()
    {
        /**
         * La tabla stocks tiene claves ajenas a artículos y almacenes,
         * por eso creamos un objeto de cada uno, para forzar la comprobación
         * de las tablas.
         */
        new \almacen();
        new \articulo();

        return '';
    }

    /**
     * 
     * @return string
     */
    public function model_class_name()
    {
        return 'stock';
    }

    /**
     * 
     * @return string
     */
    public function nombre()
    {
        $al0 = new \almacen();
        $almacen = $al0->get($this->codalmacen);
        if ($almacen) {
            $this->nombre = $almacen->nombre;
        }

        return $this->nombre;
    }

    /**
     * 
     * @return string
     */
    public function primary_column()
    {
        return 'idstock';
    }

    /**
     * 
     * @param float $c
     */
    public function set_cantidad($c = 0.0)
    {
        $this->cantidad = floatval($c);

        if ($this->cantidad < 0 && !FS_STOCK_NEGATIVO) {
            $this->cantidad = 0.0;
        }

        $this->disponible = $this->cantidad - $this->reservada;
    }

    /**
     * 
     * @param float $c
     */
    public function sum_cantidad($c = 0.0)
    {
        /// convertimos a flot por si acaso nos ha llegado un string
        $this->cantidad += floatval($c);

        if ($this->cantidad < 0 && !FS_STOCK_NEGATIVO) {
            $this->cantidad = 0.0;
        }

        $this->disponible = $this->cantidad - $this->reservada;
    }

    /**
     * 
     * @param string $ref
     * @param string $codalmacen
     *
     * @return \stock|boolean
     */
    public function get_by_referencia($ref, $codalmacen = FALSE)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE referencia = " . $this->var2str($ref) . ";";
        if ($codalmacen) {
            $sql = "SELECT * FROM " . $this->table_name . " WHERE referencia = " . $this->var2str($ref)
                . " AND codalmacen = " . $this->var2str($codalmacen) . ";";
        }

        $data = $this->db->select($sql);
        if ($data) {
            return new \stock($data[0]);
        }

        return FALSE;
    }

    /**
     * 
     * @return bool
     */
    public function save()
    {
        $this->cantidad = round($this->cantidad, 3);
        $this->reservada = round($this->reservada, 3);
        $this->disponible = $this->cantidad - $this->reservada;

        return parent::save();
    }

    /**
     * 
     * @param string $ref
     *
     * @return \stock
     */
    public function all_from_articulo($ref)
    {
        $stocklist = [];

        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE referencia = " . $this->var2str($ref) . " ORDER BY codalmacen ASC;");
        if ($data) {
            foreach ($data as $s) {
                $stocklist[] = new \stock($s);
            }
        }

        return $stocklist;
    }

    /**
     * 
     * @param string $ref
     * @param string $codalmacen
     *
     * @return float
     */
    public function total_from_articulo($ref, $codalmacen = FALSE)
    {
        $num = 0.0;
        $sql = "SELECT SUM(cantidad) as total FROM " . $this->table_name . " WHERE referencia = " . $this->var2str($ref);

        if ($codalmacen) {
            $sql .= " AND codalmacen = " . $this->var2str($codalmacen);
        }

        $data = $this->db->select($sql);
        if ($data) {
            $num = round(floatval($data[0]['total']), 3);
        }

        return $num;
    }

    /**
     * 
     * @param string $column
     *
     * @return int
     */
    public function count($column = 'idstock')
    {
        $data = $this->db->select("SELECT COUNT(idstock) as total FROM " . $this->table_name . ";");
        if ($data) {
            return intval($data[0]['total']);
        }

        return 0;
    }

    /**
     * 
     * @return int
     */
    public function count_by_articulo()
    {
        return $this->count('DISTINCT referencia');
    }

    /**
     * Aplicamos algunas correcciones a la tabla.
     */
    public function fix_db()
    {
        /**
         * Esta consulta produce un error si no hay datos erroneos, pero da igual
         */
        $this->db->exec("DELETE FROM stocks s WHERE NOT EXISTS "
            . "(SELECT referencia FROM articulos a WHERE a.referencia = s.referencia);");
    }
}
