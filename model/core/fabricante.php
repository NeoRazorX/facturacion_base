<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2013-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
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
 * Un fabricante de artículos.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class fabricante extends \fs_model
{

    /**
     * Clave primaria.
     *
     * @var string
     */
    public $codfabricante;

    /**
     *
     * @var string
     */
    public $nombre;

    public function __construct($data = FALSE)
    {
        parent::__construct('fabricantes');
        if ($data) {
            $this->codfabricante = $data['codfabricante'];
            $this->nombre = $data['nombre'];
        } else {
            $this->codfabricante = NULL;
            $this->nombre = '';
        }
    }

    /**
     * 
     * @return string
     */
    protected function install()
    {
        $this->clean_cache();
        return "INSERT INTO " . $this->table_name . " (codfabricante,nombre) VALUES ('OEM','OEM');";
    }

    /**
     * 
     * @return string
     */
    public function url()
    {
        if (is_null($this->codfabricante)) {
            return "index.php?page=ventas_fabricantes";
        }

        return "index.php?page=ventas_fabricante&cod=" . urlencode($this->codfabricante);
    }

    /**
     * 
     * @param int $len
     *
     * @return string
     */
    public function nombre($len = 12)
    {
        if (mb_strlen($this->nombre) > $len) {
            return substr($this->nombre, 0, $len) . '...';
        }

        return $this->nombre;
    }

    public function get($cod)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codfabricante = " . $this->var2str($cod) . ";");
        if ($data) {
            return new \fabricante($data[0]);
        }

        return FALSE;
    }

    public function get_articulos($offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $articulo = new \articulo();
        return $articulo->all_from_fabricante($this->codfabricante, $offset, $limit);
    }

    /**
     * 
     * @return bool
     */
    public function exists()
    {
        if (is_null($this->codfabricante)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codfabricante = " . $this->var2str($this->codfabricante) . ";");
    }

    /**
     * 
     * @return bool
     */
    public function test()
    {
        $this->codfabricante = $this->no_html($this->codfabricante);
        $this->nombre = $this->no_html($this->nombre);

        if (mb_strlen($this->codfabricante) < 1 || mb_strlen($this->codfabricante) > 8) {
            $this->new_error_msg("Código de fabricante no válido. Deben ser entre 1 y 8 caracteres.");
        } else if (mb_strlen($this->nombre) < 1 || mb_strlen($this->nombre) > 100) {
            $this->new_error_msg("Descripción de fabricante no válida.");
        } else {
            return true;
        }

        return false;
    }

    /**
     * 
     * @return bool
     */
    public function save()
    {
        if ($this->test()) {
            $this->clean_cache();

            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET nombre = " . $this->var2str($this->nombre) .
                    " WHERE codfabricante = " . $this->var2str($this->codfabricante) . ";";
            } else {
                $sql = "INSERT INTO " . $this->table_name . " (codfabricante,nombre) VALUES " .
                    "(" . $this->var2str($this->codfabricante) .
                    "," . $this->var2str($this->nombre) . ");";
            }

            return $this->db->exec($sql);
        }

        return FALSE;
    }

    /**
     * 
     * @return bool
     */
    public function delete()
    {
        $this->clean_cache();

        $sql = "DELETE FROM " . $this->table_name . " WHERE codfabricante = " . $this->var2str($this->codfabricante) . ";";
        return $this->db->exec($sql);
    }

    private function clean_cache()
    {
        $this->cache->delete('m_fabricante_all');
    }

    /**
     * Devuelve un array con todos los fabricantes
     *
     * @return \fabricante[]
     */
    public function all()
    {
        /// leemos la lista de la caché
        $fablist = $this->cache->get_array('m_fabricante_all');
        if (!$fablist) {
            /// si la lista no está en caché, leemos de la base de datos
            $data = $this->db->select("SELECT * FROM " . $this->table_name . " ORDER BY nombre ASC;");
            if ($data) {
                foreach ($data as $d) {
                    $fablist[] = new \fabricante($d);
                }
            }

            /// guardamos la lista en caché
            $this->cache->set('m_fabricante_all', $fablist);
        }

        return $fablist;
    }

    public function search($query)
    {
        $fablist = [];
        $query = $this->no_html(mb_strtolower($query, 'UTF8'));
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE lower(nombre) LIKE '%" . $query . "%' ORDER BY nombre ASC;");
        if ($data) {
            foreach ($data as $f) {
                $fablist[] = new \fabricante($f);
            }
        }

        return $fablist;
    }
}
