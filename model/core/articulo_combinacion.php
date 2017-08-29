<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2015-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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
 * Este modelo representa el par atributo => valor de la combinación de un artículo con atributos.
 * Ten en cuenta que lo que se almacena son estos pares atributo => valor,
 * pero la combinación del artículo es el conjunto, que comparte el mismo código.
 * 
 * Ejemplo de combinación:
 * talla => l
 * color => blanco
 * 
 * Esto se traduce en dos objetos articulo_combinación, ambos con el mismo código,
 * pero uno con nombreatributo talla y valor l, y el otro con nombreatributo color
 * y valor blanco.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class articulo_combinacion extends \fs_model
{

    /**
     * Clave primaria. Identificador de este par atributo-valor, no de la combinación.
     * @var integer 
     */
    public $id;

    /**
     * Identificador de la combinación.
     * Ten en cuenta que la combinación es la suma de todos los pares atributo-valor.
     * @var string 
     */
    public $codigo;

    /**
     * Segundo identificador para la combinación, para facilitar la sincronización
     * con woocommerce o prestashop.
     * @var string 
     */
    public $codigo2;

    /**
     * Referencia del artículos relacionado.
     * @var string 
     */
    public $referencia;

    /**
     * ID del valor del atributo.
     * @var integer 
     */
    public $idvalor;

    /**
     * Nombre del atributo.
     * @var string 
     */
    public $nombreatributo;

    /**
     * Valor del atributo.
     * @var string 
     */
    public $valor;

    /**
     * Referencia de la propia combinación.
     * @var string 
     */
    public $refcombinacion;

    /**
     * Código de barras de la combinación.
     * @var string 
     */
    public $codbarras;

    /**
     * Impacto en el precio del artículo.
     * @var double 
     */
    public $impactoprecio;

    /**
     * Stock físico de la combinación.
     * @var double 
     */
    public $stockfis;

    public function __construct($data = FALSE)
    {
        parent::__construct('articulo_combinaciones');
        if ($data) {
            $this->id = $this->intval($data['id']);
            $this->codigo = $data['codigo'];
            $this->codigo2 = $data['codigo2'];
            $this->referencia = $data['referencia'];
            $this->idvalor = $this->intval($data['idvalor']);
            $this->nombreatributo = $data['nombreatributo'];
            $this->valor = $data['valor'];
            $this->refcombinacion = $data['refcombinacion'];
            $this->codbarras = $data['codbarras'];
            $this->impactoprecio = floatval($data['impactoprecio']);
            $this->stockfis = floatval($data['stockfis']);
        } else {
            $this->id = NULL;
            $this->codigo = NULL;
            $this->codigo2 = NULL;
            $this->referencia = NULL;
            $this->idvalor = NULL;
            $this->nombreatributo = NULL;
            $this->valor = NULL;
            $this->refcombinacion = NULL;
            $this->codbarras = NULL;
            $this->impactoprecio = 0.0;
            $this->stockfis = 0.0;
        }
    }

    protected function install()
    {
        /// nos aseguramos de que existan las tablas necesarias
        new \atributo();
        new \atributo_valor();

        return '';
    }

    /**
     * Devuelve la combinación del artículo con id = $id
     * @param string $id
     * @return \articulo_combinacion|boolean
     */
    public function get($id)
    {
        $data = $this->db->select("SELECT * FROM articulo_combinaciones WHERE id = " . $this->var2str($id) . ";");
        if ($data) {
            return new \articulo_combinacion($data[0]);
        }

        return FALSE;
    }

    /**
     * Devuelve la combinación de artículo con codigo = $cod
     * @deprecated since version 110
     * @param string $cod
     * @return \articulo_combinacion|boolean
     */
    public function get_by_codigo($cod)
    {
        $data = $this->db->select("SELECT * FROM articulo_combinaciones WHERE codigo = " . $this->var2str($cod) . ";");
        if ($data) {
            return new \articulo_combinacion($data[0]);
        }

        return FALSE;
    }

    /**
     * Devuelve un nuevo código para una combinación de artículo
     * @return string
     */
    private function get_new_codigo()
    {
        $data = $this->db->select("SELECT MAX(" . $this->db->sql_to_int('codigo') . ") as cod FROM " . $this->table_name . ";");
        if ($data) {
            return (string) (1 + intval($data[0]['cod']));
        }

        return '1';
    }

    /**
     * Devuelve TRUE si la combinación de artículo existe en la base de datos
     * @return boolean
     */
    public function exists()
    {
        if (is_null($this->id)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM articulo_combinaciones WHERE id = " . $this->var2str($this->id) . ";");
    }

    /**
     * Guarda los datos en la base de datos
     * @return boolean
     */
    public function save()
    {
        if ($this->exists()) {
            $sql = "UPDATE articulo_combinaciones SET codigo = " . $this->var2str($this->codigo)
                . ", codigo2 = " . $this->var2str($this->codigo2)
                . ", referencia = " . $this->var2str($this->referencia)
                . ", idvalor = " . $this->var2str($this->idvalor)
                . ", nombreatributo = " . $this->var2str($this->nombreatributo)
                . ", valor = " . $this->var2str($this->valor)
                . ", refcombinacion = " . $this->var2str($this->refcombinacion)
                . ", codbarras = " . $this->var2str($this->codbarras)
                . ", impactoprecio = " . $this->var2str($this->impactoprecio)
                . ", stockfis = " . $this->var2str($this->stockfis)
                . "  WHERE id = " . $this->var2str($this->id) . ";";

            return $this->db->exec($sql);
        }

        if (is_null($this->codigo)) {
            $this->codigo = $this->get_new_codigo();
        }
        $sql = "INSERT INTO articulo_combinaciones (codigo,codigo2,referencia,idvalor,nombreatributo,"
            . "valor,refcombinacion,codbarras,impactoprecio,stockfis) VALUES "
            . "(" . $this->var2str($this->codigo)
            . "," . $this->var2str($this->codigo2)
            . "," . $this->var2str($this->referencia)
            . "," . $this->var2str($this->idvalor)
            . "," . $this->var2str($this->nombreatributo)
            . "," . $this->var2str($this->valor)
            . "," . $this->var2str($this->refcombinacion)
            . "," . $this->var2str($this->codbarras)
            . "," . $this->var2str($this->impactoprecio)
            . "," . $this->var2str($this->stockfis) . ");";
        if ($this->db->exec($sql)) {
            $this->id = $this->db->lastval();
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Elimina la combinación de artículo
     * @return boolean
     */
    public function delete()
    {
        return $this->db->exec("DELETE FROM articulo_combinaciones WHERE id = " . $this->var2str($this->id) . ";");
    }

    /**
     * Elimina todas las combinaciones del artículo con referencia = $ref
     * @param string $ref
     * @return boolean
     */
    public function delete_from_ref($ref)
    {
        return $this->db->exec("DELETE FROM articulo_combinaciones WHERE referencia = " . $this->var2str($ref) . ";");
    }

    private function all_from($sql)
    {
        $lista = array();
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $a) {
                $lista[] = new \articulo_combinacion($a);
            }
        }

        return $lista;
    }

    /**
     * Devuelve un array con todas las combinaciones del artículo con referencia = $ref
     * @param string $ref
     * @return \articulo_combinacion
     */
    public function all_from_ref($ref)
    {
        $sql = "SELECT * FROM articulo_combinaciones WHERE referencia = " . $this->var2str($ref)
            . " ORDER BY codigo ASC, nombreatributo ASC;";
        return $this->all_from($sql);
    }

    /**
     * Devuelve un array con todos los datos de la combinación con código = $cod,
     * ten en cuenta que lo que se almacenan son los pares atributo => valor.
     * @param string $cod
     * @return \articulo_combinacion
     */
    public function all_from_codigo($cod)
    {
        $sql = "SELECT * FROM articulo_combinaciones WHERE codigo = " . $this->var2str($cod)
            . " ORDER BY nombreatributo ASC;";
        return $this->all_from($sql);
    }

    /**
     * Devuelve un array con todos los datos de la combinación con codigo2 = $cod,
     * ten en cuenta que lo que se almacena son los pares atrubuto => valor.
     * @param string $cod
     * @return \articulo_combinacion
     */
    public function all_from_codigo2($cod)
    {
        $sql = "SELECT * FROM articulo_combinaciones WHERE codigo2 = " . $this->var2str($cod)
            . " ORDER BY nombreatributo ASC;";
        return $this->all_from($sql);
    }

    /**
     * Devuelve las combinaciones del artículos $ref agrupadas por código.
     * @param string $ref
     * @return \articulo_combinacion
     */
    public function combinaciones_from_ref($ref)
    {
        $lista = array();

        $sql = "SELECT * FROM articulo_combinaciones WHERE referencia = " . $this->var2str($ref)
            . " ORDER BY codigo ASC, nombreatributo ASC;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                if (isset($lista[$d['codigo']])) {
                    $lista[$d['codigo']][] = new \articulo_combinacion($d);
                } else {
                    $lista[$d['codigo']] = array(new \articulo_combinacion($d));
                }
            }
        }

        return $lista;
    }

    /**
     * Devuelve un array con las combinaciones que contienen $query en su referencia
     * o que coincide con su código de barras.
     * @param string $query
     * @return \articulo_combinacion
     */
    public function search($query = '')
    {
        $artilist = array();
        $query = $this->no_html(mb_strtolower($query, 'UTF8'));

        $sql = "SELECT * FROM " . $this->table_name . " WHERE referencia LIKE '" . $query . "%'"
            . " OR codbarras = " . $this->var2str($query);

        $data = $this->db->select_limit($sql, 200);
        if ($data) {
            foreach ($data as $d) {
                $artilist[] = new \articulo_combinacion($d);
            }
        }

        return $artilist;
    }
}
