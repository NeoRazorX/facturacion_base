<?php

/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2015  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Description of articulo_proveedor
 *
 * @author carlos
 */
class articulo_proveedor extends fs_model
{
   /**
    * Clave primaria.
    * @var type 
    */
   public $id;
   
   /**
    * Referencia del artículo en nuestro catálogo. Puede no estar actualmente.
    * @var type 
    */
   public $referencia;
   
   /**
    * Código del proveedor asociado.
    * @var type 
    */
   public $codproveedor;
   
   /**
    * Referencia del artículo para el proveedor.
    * @var type 
    */
   public $refproveedor;
   
   public $descripcion;
   
   /**
    * Precio neto al que nos ofrece el proveedor este producto.
    * @var type 
    */
   public $precio;
   
   /**
    * Descuento sobre el precio que nos hace el proveedor.
    * @var type 
    */
   public $dto;
   
   /**
    * Impuesto asignado. Clase impuesto.
    * @var type 
    */
   public $codimpuesto;
   
   /**
    * Stock del artículo en el almacén del proveedor.
    * @var type 
    */
   public $stock;
   
   /**
    * TRUE -> el artículo no ofrece stock.
    * @var type 
    */
   public $nostock;
   
   /**
    * % IVA del impuesto asignado.
    * @var type 
    */
   private $iva;
   
   private static $impuestos;
   private static $nombres;
   
   public function __construct($a = FALSE)
   {
      parent::__construct('articulosprov', 'plugins/facturacion_base/');
      
      if( !isset(self::$impuestos) )
      {
         self::$impuestos = array();
      }
      
      if( !isset(self::$nombres) )
      {
         self::$nombres = array();
      }
      
      if($a)
      {
         $this->id = $this->intval($a['id']);
         $this->referencia = $a['referencia'];
         $this->codproveedor = $a['codproveedor'];
         $this->refproveedor = $a['refproveedor'];
         $this->descripcion = $a['descripcion'];
         
         /// En algunos módulos de eneboo se usa coste como precio
         if( is_null($a['precio']) AND isset($a['coste']) )
         {
            $this->precio = floatval($a['coste']);
         }
         else
            $this->precio = floatval($a['precio']);
         
         $this->dto = floatval($a['dto']);
         $this->codimpuesto = $a['codimpuesto'];
         $this->stock = floatval($a['stock']);
         $this->nostock = $this->str2bool($a['nostock']);
      }
      else
      {
         $this->id = NULL;
         $this->referencia = NULL;
         $this->codproveedor = NULL;
         $this->refproveedor = NULL;
         $this->descripcion = NULL;
         $this->precio = 0;
         $this->dto = 0;
         $this->codimpuesto = NULL;
         $this->stock = 0;
         $this->nostock = TRUE;
      }
      
      $this->iva = NULL;
   }
   
   protected function install()
   {
      return '';
   }
   
   public function nombre_proveedor()
   {
      if( isset(self::$nombres[$this->codproveedor]) )
      {
         return self::$nombres[$this->codproveedor];
      }
      else
      {
         $data = $this->db->select("SELECT nombre FROM proveedores WHERE codproveedor = ".$this->var2str($this->codproveedor).";");
         if($data)
         {
            self::$nombres[$this->codproveedor] = $data[0]['nombre'];
            return $data[0]['nombre'];
         }
         else
            return '-';
      }
   }
   
   public function url_proveedor()
   {
      return 'index.php?page=compras_proveedor&cod='.$this->codproveedor;
   }
   
   /**
    * Devuelve el % de IVA del artículo.
    * Si $reload es TRUE, vuelve a consultarlo en lugar de usar los datos cargados.
    * @param type $reload
    * @return type
    */
   public function get_iva($reload = TRUE)
   {
      if($reload)
      {
         $this->iva = NULL;
      }
      
      if( is_null($this->iva) )
      {
         $this->iva = 0;
         
         if( !is_null($this->codimpuesto) )
         {
            $encontrado = FALSE;
            foreach(self::$impuestos as $i)
            {
               if($i->codimpuesto == $this->codimpuesto)
               {
                  $this->iva = $i->iva;
                  $encontrado = TRUE;
                  break;
               }
            }
            if(!$encontrado)
            {
               $imp = new impuesto();
               $imp0 = $imp->get($this->codimpuesto);
               if($imp0)
               {
                  $this->iva = $imp0->iva;
                  self::$impuestos[] = $imp0;
               }
            }
         }
      }
      
      return $this->iva;
   }
   
   /**
    * Devuelve el precio final, aplicando descuento e impuesto.
    * @return type
    */
   public function total_iva()
   {
      return $this->precio * (100-$this->dto) / 100 * (100+$this->get_iva()) / 100;
   }
   
   public function get($id)
   {
      $data = $this->db->select("SELECT * FROM articulosprov WHERE id = ".$this->var2str($id).";");
      if($data)
      {
         return new articulo_proveedor($data[0]);
      }
      else
         return FALSE;
   }
   
   /**
    * Devuelve el primer elemento que tenga $ref como referencia y $codproveedor
    * como codproveedor. Si se proporciona $refprov, entonces lo que devuelve es el
    * primer elemento que tenga $codproveedor como codproveedor y $refprov como refproveedor
    * o bien $ref como referencia.
    * @param type $ref
    * @param type $codproveedor
    * @param type $refprov
    * @return \articulo_proveedor|boolean
    */
   public function get_by($ref, $codproveedor, $refprov = FALSE)
   {
      if($refprov)
      {
         $sql = "SELECT * FROM articulosprov WHERE codproveedor = ".$this->var2str($codproveedor)
                 ." AND (refproveedor = ".$this->var2str($refprov)
                 ." OR referencia = ".$this->var2str($ref).");";
      }
      else
      {
         $sql = "SELECT * FROM articulosprov WHERE referencia = ".$this->var2str($ref)
                 ." AND codproveedor = ".$this->var2str($codproveedor).";";
      }
      
      $data = $this->db->select($sql);
      if($data)
      {
         return new articulo_proveedor($data[0]);
      }
      else
         return FALSE;
   }
   
   public function exists()
   {
      if( is_null($this->id) )
      {
         return FALSE;
      }
      else
         return $this->db->select("SELECT * FROM articulosprov WHERE id = ".$this->var2str($this->id).";");
   }
   
   public function save()
   {
      $this->descripcion = $this->no_html($this->descripcion);
      
      if($this->nostock)
      {
         $this->stock = 0;
      }
      
      if( is_null($this->refproveedor) OR strlen($this->refproveedor) < 1 OR strlen($this->refproveedor) > 25 )
      {
         $this->new_error_msg('La referencia de proveedor debe contener entre 1 y 25 caracteres.');
      }
      else if( $this->exists() )
      {
         $sql = "UPDATE articulosprov SET referencia = ".$this->var2str($this->referencia).
                 ", codproveedor = ".$this->var2str($this->codproveedor).
                 ", refproveedor = ".$this->var2str($this->refproveedor).
                 ", descripcion = ".$this->var2str($this->descripcion).
                 ", precio = ".$this->var2str($this->precio).
                 ", dto = ".$this->var2str($this->dto).
                 ", codimpuesto = ".$this->var2str($this->codimpuesto).
                 ", stock = ".$this->var2str($this->stock).
                 ", nostock = ".$this->var2str($this->nostock).
                 " WHERE id = ".$this->var2str($this->id).";";
         
         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO articulosprov (referencia,codproveedor,refproveedor,descripcion,".
                 "precio,dto,codimpuesto,stock,nostock) VALUES ".
                 "(".$this->var2str($this->referencia).
                 ",".$this->var2str($this->codproveedor).
                 ",".$this->var2str($this->refproveedor).
                 ",".$this->var2str($this->descripcion).
                 ",".$this->var2str($this->precio).
                 ",".$this->var2str($this->dto).
                 ",".$this->var2str($this->codimpuesto).
                 ",".$this->var2str($this->stock).
                 ",".$this->var2str($this->nostock).");";
         
         if( $this->db->exec($sql) )
         {
            $this->id = $this->db->lastval();
            return TRUE;
         }
         else
            return FALSE;
      }
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM articulosprov WHERE id = ".$this->var2str($this->id).";");
   }
   
   /**
    * Devuelve todos los elementos que tienen $ref como referencia.
    * @param type $ref
    * @return \articulo_proveedor
    */
   public function all_from_ref($ref)
   {
      $alist = array();
      
      $data = $this->db->select("SELECT * FROM articulosprov WHERE referencia = ".$this->var2str($ref)." ORDER BY precio ASC;");
      if($data)
      {
         foreach($data as $d)
            $alist[] = new articulo_proveedor($d);
      }
      
      return $alist;
   }
   
   /**
    * Devuelve el artículo con menor precio de los que tienen $ref como referencia.
    * @param type $ref
    * @return \articulo_proveedor
    */
   public function mejor_from_ref($ref)
   {
      $data = $this->db->select("SELECT * FROM articulosprov WHERE referencia = ".$this->var2str($ref)." ORDER BY precio ASC;");
      if($data)
      {
         return new articulo_proveedor($data[0]);
      }
      else
         return FALSE;
   }
}
