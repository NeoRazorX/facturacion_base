<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2014-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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
 * Una dirección de un cliente. Puede tener varias.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class direccion_cliente extends \fs_model
{
   /**
    * Clave primaria.
    * @var type 
    */
   public $id;
   
   /**
    * Código del cliente asociado.
    * @var type 
    */
   public $codcliente;
   
   public $codpais;
   public $apartado;
   public $provincia;
   public $ciudad;
   public $codpostal;
   public $direccion;
   
   /**
    * TRUE -> esta dirección es la principal para envíos.
    * @var type 
    */
   public $domenvio;
   
   /**
    * TRUE -> esta dirección es la principal para facturación.
    * @var type 
    */
   public $domfacturacion;
   
   public $descripcion;
   
   /**
    * Fecha de última modificación.
    * @var type 
    */
   public $fecha;
   
   public function __construct($d=FALSE)
   {
      parent::__construct('dirclientes');
      if($d)
      {
         $this->id = $this->intval($d['id']);
         $this->codcliente = $d['codcliente'];
         $this->codpais = $d['codpais'];
         $this->apartado = $d['apartado'];
         $this->provincia = $d['provincia'];
         $this->ciudad = $d['ciudad'];
         $this->codpostal = $d['codpostal'];
         $this->direccion = $d['direccion'];
         $this->domenvio = $this->str2bool($d['domenvio']);
         $this->domfacturacion = $this->str2bool($d['domfacturacion']);
         $this->descripcion = $d['descripcion'];
         $this->fecha = date('d-m-Y', strtotime($d['fecha']));
      }
      else
      {
         $this->id = NULL;
         $this->codcliente = NULL;
         $this->codpais = NULL;
         $this->apartado = NULL;
         $this->provincia = NULL;
         $this->ciudad = NULL;
         $this->codpostal = NULL;
         $this->direccion = NULL;
         $this->domenvio = TRUE;
         $this->domfacturacion = TRUE;
         $this->descripcion = 'Principal';
         $this->fecha = date('d-m-Y');
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   public function get($id)
   {
      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE id = ".$this->var2str($id).";");
      if($data)
      {
         return new \direccion_cliente($data[0]);
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
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE id = ".$this->var2str($this->id).";");
   }
   
   public function save()
   {
      $this->apartado = $this->no_html($this->apartado);
      $this->ciudad = $this->no_html($this->ciudad);
      $this->codpostal = $this->no_html($this->codpostal);
      $this->descripcion = $this->no_html($this->descripcion);
      $this->direccion = $this->no_html($this->direccion);
      $this->provincia = $this->no_html($this->provincia);
      
      /// actualizamos la fecha de modificación
      $this->fecha = date('d-m-Y');
      
      /// ¿Desmarcamos las demás direcciones principales?
      $sql = "";
      if($this->domenvio)
      {
         $sql .= "UPDATE ".$this->table_name." SET domenvio = false"
                 . " WHERE codcliente = ".$this->var2str($this->codcliente).";";
      }
      if($this->domfacturacion)
      {
         $sql .= "UPDATE ".$this->table_name." SET domfacturacion = false"
                 . " WHERE codcliente = ".$this->var2str($this->codcliente).";";
      }
      
      if( $this->exists() )
      {
         $sql .= "UPDATE ".$this->table_name." SET codcliente = ".$this->var2str($this->codcliente)
                 .", codpais = ".$this->var2str($this->codpais)
                 .", apartado = ".$this->var2str($this->apartado)
                 .", provincia = ".$this->var2str($this->provincia)
                 .", ciudad = ".$this->var2str($this->ciudad)
                 .", codpostal = ".$this->var2str($this->codpostal)
                 .", direccion = ".$this->var2str($this->direccion)
                 .", domenvio = ".$this->var2str($this->domenvio)
                 .", domfacturacion = ".$this->var2str($this->domfacturacion)
                 .", descripcion = ".$this->var2str($this->descripcion)
                 .", fecha = ".$this->var2str($this->fecha)
                 ."  WHERE id = ".$this->var2str($this->id).";";
         
         return $this->db->exec($sql);
      }
      else
      {
         $sql .= "INSERT INTO ".$this->table_name." (codcliente,codpais,apartado,provincia,ciudad,codpostal,
            direccion,domenvio,domfacturacion,descripcion,fecha) VALUES (".$this->var2str($this->codcliente)
                 .",".$this->var2str($this->codpais)
                 .",".$this->var2str($this->apartado)
                 .",".$this->var2str($this->provincia)
                 .",".$this->var2str($this->ciudad)
                 .",".$this->var2str($this->codpostal)
                 .",".$this->var2str($this->direccion)
                 .",".$this->var2str($this->domenvio)
                 .",".$this->var2str($this->domfacturacion)
                 .",".$this->var2str($this->descripcion)
                 .",".$this->var2str($this->fecha).");";
         
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
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE id = ".$this->var2str($this->id).";");
   }
   
   public function all($offset = 0)
   {
      $dirlist = array();
      
      $data = $this->db->select_limit("SELECT * FROM ".$this->table_name." ORDER BY id ASC", FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
         {
            $dirlist[] = new \direccion_cliente($d);
         }
      }
      
      return $dirlist;
   }
   
   public function all_from_cliente($cod)
   {
      $dirlist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE codcliente = ".$this->var2str($cod)
              ." ORDER BY id DESC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $dirlist[] = new \direccion_cliente($d);
         }
      }
      
      return $dirlist;
   }
}
