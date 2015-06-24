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
   public $referencia;
   public $codproveedor;
   public $refproveedor;
   public $precio;
   public $stock;
   
   private static $nombres;
   
   public function __construct($a = FALSE)
   {
      parent::__construct('articulosprov', 'plugins/facturacion_base/');
      
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
         
         /// En algunos módulos de eneboo se usa coste como precio
         if( is_null($a['precio']) AND isset($a['coste']) )
         {
            $this->precio = floatval($a['coste']);
         }
         else
            $this->precio = floatval($a['precio']);
         
         $this->stock = floatval($a['stock']);
      }
      else
      {
         $this->id = NULL;
         $this->referencia = NULL;
         $this->codproveedor = NULL;
         $this->refproveedor = NULL;
         $this->precio = 0;
         $this->stock = 0;
      }
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
   
   public function get_by($ref, $codproveedor)
   {
      $data = $this->db->select("SELECT * FROM articulosprov WHERE referencia = ".$this->var2str($ref).
              " AND codproveedor = ".$this->var2str($codproveedor).";");
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
      if( $this->exists() )
      {
         $sql = "UPDATE articulosprov SET referencia = ".$this->var2str($this->referencia).
                 ", codproveedor = ".$this->var2str($this->codproveedor).
                 ", refproveedor = ".$this->var2str($this->refproveedor).
                 ", precio = ".$this->var2str($this->precio).
                 ", stock = ".$this->var2str($this->stock).
                 " WHERE id = ".$this->var2str($this->id).";";
         
         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO articulosprov (referencia,codproveedor,refproveedor,stock) VALUES ".
                 "(".$this->var2str($this->referencia).
                 ",".$this->var2str($this->codproveedor).
                 ",".$this->var2str($this->refproveedor).
                 ",".$this->var2str($this->precio).
                 ",".$this->var2str($this->stock).");";
         
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
}
