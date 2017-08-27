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
 * Artículo vendido por un proveedor.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class articulo_proveedor extends \fs_model
{

    /**
     * Clave primaria.
     * @var integer 
     */
    public $id;

    /**
     * Referencia del artículo en nuestro catálogo. Puede no estar actualmente.
     * @var string 
     */
    public $referencia;

    /**
     * Código del proveedor asociado.
     * @var string 
     */
    public $codproveedor;

    /**
     * Referencia del artículo para el proveedor.
     * @var string 
     */
    public $refproveedor;
    public $descripcion;

    /**
     * Precio neto al que nos ofrece el proveedor este producto.
     * @var double
     */
    public $precio;

    /**
     * Descuento sobre el precio que nos hace el proveedor.
     * @var double
     */
    public $dto;

    /**
     * Impuesto asignado. Clase impuesto.
     * @var string 
     */
    public $codimpuesto;

    /**
     * Stock del artículo en el almacén del proveedor.
     * @var double 
     */
    public $stock;

    /**
     * TRUE -> el artículo no ofrece stock.
     * @var boolean 
     */
    public $nostock;

    /**
     * % IVA del impuesto asignado.
     * @var double 
     */
    private $iva;
    public $codbarras;
    public $partnumber;
    private static $impuestos;
    private static $nombres;

    public function __construct($data = FALSE)
    {
        parent::__construct('articulosprov');

        if (!isset(self::$impuestos)) {
            self::$impuestos = array();
        }

        if (!isset(self::$nombres)) {
            self::$nombres = array();
        }

        if ($data) {
            $this->id = $this->intval($data['id']);
            $this->referencia = $data['referencia'];
            $this->codproveedor = $data['codproveedor'];
            $this->refproveedor = $data['refproveedor'];
            $this->descripcion = $data['descripcion'];

            /// En algunos módulos de eneboo se usa coste como precio
            if (is_null($data['precio']) && isset($data['coste'])) {
                $this->precio = floatval($data['coste']);
            } else
                $this->precio = floatval($data['precio']);

            $this->dto = floatval($data['dto']);
            $this->codimpuesto = $data['codimpuesto'];
            $this->stock = floatval($data['stock']);
            $this->nostock = $this->str2bool($data['nostock']);
            $this->codbarras = $data['codbarras'];
            $this->partnumber = $data['partnumber'];
        } else {
            $this->id = NULL;
            $this->referencia = NULL;
            $this->codproveedor = NULL;
            $this->refproveedor = NULL;
            $this->descripcion = NULL;
            $this->precio = 0.0;
            $this->dto = 0.0;
            $this->codimpuesto = NULL;
            $this->stock = 0.0;
            $this->nostock = TRUE;
            $this->codbarras = NULL;
            $this->partnumber = NULL;
        }

        $this->iva = NULL;
    }

    protected function install()
    {
        return '';
    }

    public function nombre_proveedor()
    {
        if (isset(self::$nombres[$this->codproveedor])) {
            return self::$nombres[$this->codproveedor];
        }

        $data = $this->db->select("SELECT razonsocial FROM proveedores WHERE codproveedor = " . $this->var2str($this->codproveedor) . ";");
        if ($data) {
            self::$nombres[$this->codproveedor] = $data[0]['razonsocial'];
            return $data[0]['razonsocial'];
        }

        return '-';
    }

    public function url_proveedor()
    {
        return 'index.php?page=compras_proveedor&cod=' . $this->codproveedor;
    }

    /**
     * Devuelve el % de IVA del artículo.
     * Si $reload es TRUE, vuelve a consultarlo en lugar de usar los datos cargados.
     * @param boolean $reload
     * @return double
     */
    public function get_iva($reload = TRUE)
    {
        if ($reload) {
            $this->iva = NULL;
        }

        if (is_null($this->iva)) {
            $this->iva = 0.0;

            if (!is_null($this->codimpuesto)) {
                $encontrado = FALSE;
                foreach (self::$impuestos as $i) {
                    if ($i->codimpuesto == $this->codimpuesto) {
                        $this->iva = $i->iva;
                        $encontrado = TRUE;
                        break;
                    }
                }
                if (!$encontrado) {
                    $imp = new \impuesto();
                    $imp0 = $imp->get($this->codimpuesto);
                    if ($imp0) {
                        $this->iva = $imp0->iva;
                        self::$impuestos[] = $imp0;
                    }
                }
            }
        }

        return $this->iva;
    }

    public function get_articulo()
    {
        if (is_null($this->referencia)) {
            return FALSE;
        }

        $art0 = new \articulo();
        return $art0->get($this->referencia);
    }

    /**
     * Devuelve el precio final, aplicando descuento e impuesto.
     * @return double
     */
    public function total_iva()
    {
        return $this->precio * (100 - $this->dto) / 100 * (100 + $this->get_iva()) / 100;
    }

    /**
     * Devuelve el artículo de proveedor solicitado, o false si no se encuentra.
     * @param string $id
     * @return \articulo_proveedor|boolean
     */
    public function get($id)
    {
        $data = $this->db->select("SELECT * FROM articulosprov WHERE id = " . $this->var2str($id) . ";");
        if ($data) {
            return new \articulo_proveedor($data[0]);
        }

        return FALSE;
    }

    /**
     * Devuelve el primer elemento que tenga $ref como referencia y $codproveedor
     * como codproveedor. Si se proporciona $refprov, entonces lo que devuelve es el
     * primer elemento que tenga $codproveedor como codproveedor y $refprov como refproveedor
     * o bien $ref como referencia.
     * @param string $ref
     * @param string $codproveedor
     * @param string $refprov
     * @return \articulo_proveedor|boolean
     */
    public function get_by($ref, $codproveedor, $refprov = FALSE)
    {
        if ($refprov !== FALSE) {
            $sql = "SELECT * FROM articulosprov WHERE codproveedor = " . $this->var2str($codproveedor)
                . " AND (refproveedor = " . $this->var2str($refprov)
                . " OR referencia = " . $this->var2str($ref) . ");";
        } else {
            $sql = "SELECT * FROM articulosprov WHERE referencia = " . $this->var2str($ref)
                . " AND codproveedor = " . $this->var2str($codproveedor) . ";";
        }

        $data = $this->db->select($sql);
        if ($data) {
            return new \articulo_proveedor($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->id)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM articulosprov WHERE id = " . $this->var2str($this->id) . ";");
    }

    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);

        if ($this->nostock) {
            $this->stock = 0.0;
        }

        if (is_null($this->refproveedor) || strlen($this->refproveedor) < 1 || strlen($this->refproveedor) > 25) {
            $this->new_error_msg('La referencia de proveedor debe contener entre 1 y 25 caracteres.');
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE articulosprov SET referencia = " . $this->var2str($this->referencia) .
                    ", codproveedor = " . $this->var2str($this->codproveedor) .
                    ", refproveedor = " . $this->var2str($this->refproveedor) .
                    ", descripcion = " . $this->var2str($this->descripcion) .
                    ", precio = " . $this->var2str($this->precio) .
                    ", dto = " . $this->var2str($this->dto) .
                    ", codimpuesto = " . $this->var2str($this->codimpuesto) .
                    ", stock = " . $this->var2str($this->stock) .
                    ", nostock = " . $this->var2str($this->nostock) .
                    ", codbarras = " . $this->var2str($this->codbarras) .
                    ", partnumber = " . $this->var2str($this->partnumber) .
                    "  WHERE id = " . $this->var2str($this->id) . ";";
                return $this->db->exec($sql);
            }

            $sql = "INSERT INTO articulosprov (referencia,codproveedor,refproveedor,descripcion," .
                "precio,dto,codimpuesto,stock,nostock,codbarras,partnumber) VALUES " .
                "(" . $this->var2str($this->referencia) .
                "," . $this->var2str($this->codproveedor) .
                "," . $this->var2str($this->refproveedor) .
                "," . $this->var2str($this->descripcion) .
                "," . $this->var2str($this->precio) .
                "," . $this->var2str($this->dto) .
                "," . $this->var2str($this->codimpuesto) .
                "," . $this->var2str($this->stock) .
                "," . $this->var2str($this->nostock) .
                "," . $this->var2str($this->codbarras) .
                "," . $this->var2str($this->partnumber) . ");";

            if ($this->db->exec($sql)) {
                $this->id = $this->db->lastval();
                return TRUE;
            }
        }

        return FALSE;
    }

    public function delete()
    {
        return $this->db->exec("DELETE FROM articulosprov WHERE id = " . $this->var2str($this->id) . ";");
    }

    private function all_from($sql)
    {
        $alist = array();
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $a) {
                $alist[] = new \articulo_proveedor($a);
            }
        }

        return $alist;
    }

    /**
     * Devuelve todos los elementos que tienen $ref como referencia.
     * @param string $ref
     * @return \articulo_proveedor
     */
    public function all_from_ref($ref)
    {
        $sql = "SELECT * FROM articulosprov WHERE referencia = " . $this->var2str($ref) . " ORDER BY precio ASC;";
        return $this->all_from($sql);
    }

    /**
     * Devuelve el artículo con menor precio de los que tienen $ref como referencia.
     * @param string $ref
     * @return \articulo_proveedor|false
     */
    public function mejor_from_ref($ref)
    {
        $sql = "SELECT * FROM articulosprov WHERE referencia = " . $this->var2str($ref)
            . " ORDER BY precio ASC;";

        return $this->all_from($sql);
    }

    /**
     * Devuelve todos los articulos que tienen asociada una referencia para actualizar.
     * @param 
     * @return \articulo_proveedor
     */
    public function all_con_ref()
    {
        $sql = "SELECT * FROM articulosprov WHERE referencia !='' ORDER BY precio ASC;";
        return $this->all_from($sql);
    }

    /**
     * Aplicamos correcciones a la tabla.
     */
    public function fix_db()
    {
        $this->db->exec("DELETE FROM articulosprov WHERE codproveedor NOT IN (SELECT codproveedor FROM proveedores);");
        $this->db->exec("UPDATE articulosprov SET refproveedor = referencia WHERE refproveedor IS NULL;");
    }
}
