<?php

/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2016 Luismipr <luismipr@gmail.com>.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * Lpublished by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * LeGNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Description of numserie
 *
 * @author Luismipr <luismipr@gmail.com>
 */

require_model('articulo.php');

class numero_serie extends fs_model
{
   /**
    * Numero de serie
    * Clave primaria.
    * @var type varchar 
    */
   public $numserie;
   
   /**
    * Referencia del artículo
    * @var type varchar 
    */
   public $referencia;
   
   /**
    * Id linea albaran venta
    * @var type serial
    */
   public $idlalbventa;
   
   /**
    * id linea factura venta
    * @var type serial
    */
   public $idlfacventa;
   
   /**
    * Id linea albaran compra
    * @var type serial
    */
   public $idlalbcompra;
   
   /**
    * Id linea factura compra
    * @var type serial
    */
   public $idlfaccompra;
   
   /**
    * Estado del numero de serie
    * @var type boolean
    */
   public $vendido;
   
   public function __construct($n = FALSE)
   {
      parent::__construct('numeros_serie');
      if($n)
      {
         $this->numserie = $n['numserie'];
         $this->referencia = $n['referencia'];
         $this->idlalbventa = $n['idlalbventa'];
         $this->idlfacventa = $n['idlfacventa'];
         $this->idlalbcompra = $n['idlalbcompra'];
         $this->idlfaccompra = $n['idlfaccompra'];
         $this->vendido = $n['vendido'];
      }
      else
      {
         $this->numserie = NULL;
         $this->referencia = NULL;
         $this->idlalbventa = NULL;
         $this->idlfacventa = NULL;
         $this->idlalbcompra = NULL;
         $this->idlfaccompra = NULL;
         $this->vendido = FALSE;
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   /**
    * Devuelve la url del numero de serie para poder editar
    * @return type url
    */
   public function url()
   {
      return 'index.php?page=trazabilidad&numserie='.$this->numserie;
   }
   
   
   /**
    * Devuelve los datos de un numero de serie dado
    * @param type $numserie
    * @return new numserie
    */
   public function get($numserie)
   {
      $data = $this->db->select("SELECT * FROM numeros_serie WHERE numserie = ".$this->var2str($numserie).";");
      if($data)
      {
         return new numero_serie($data[0]);
      }
      else
      {
         return FALSE;
      }
   }
   
   
   /**
    * Devuelve true si existe ya el número de serie
    * @return boolean
    */
   public function exists()
   {
      if( is_null($this->numserie) )
      {
         return FALSE;
      }
      else
      {
         return $this->db->select("SELECT * FROM numeros_serie WHERE numserie = ".$this->var2str($this->numserie).";");
      }
   }
   
   /**
    * Guarda los datos en la db
    * 
    * @return type
    */
   public function save()
   {
      
      if( $this->exists() )
      {
         $sql = "UPDATE numeros_serie SET "
                 . "referencia = ".$this->var2str($this->referencia).","
                 . "idlalbventa = ".$this->var2str($this->idlalbventa).","
                 . "idlfacventa = ".$this->var2str($this->idlfacventa).","
                 . "idlalbcompra = ".$this->var2str($this->idlalbcompra).","
                 . "idlfaccompra = ".$this->var2str($this->idlfaccompra).","
                 . "vendido = ".$this->var2str($this->vendido)
                 ." WHERE numserie = ".$this->var2str($this->numserie).";";
      }
      else
      {
         $sql = "INSERT INTO numeros_serie (numserie,referencia,idlalbventa,idlfacventa,idlalbcompra,idlfaccompra) VALUES ("
                 .$this->var2str($this->numserie).","
                 .$this->var2str($this->referencia).","
                 .$this->var2str($this->idlalbventa).","
                 .$this->var2str($this->idlfacventa).","
                 .$this->var2str($this->idlalbcompra).","
                 .$this->var2str($this->idlfaccompra).");";
      }
      
      return $this->db->exec($sql);
   }
   
   /**
    * Elimina un numero de serie
    * @return type
    */
   public function delete()
   {
      return $this->db->exec("DELETE FROM numeros_serie WHERE numserie = ".$this->var2str($this->numserie).";");
   }
   
   /**
    * Devuelve un array con todos los números de serie
    * @return \numero_serie
    */
   public function all()
   {
      $lista = array();
      
      $data = $this->db->select("SELECT * FROM numeros_serie ORDER BY referencia DESC;");
      if($data)
      {
         foreach($data as $d)
         {
            $lista[] = new numero_serie($d);
         }
      }
      return $lista;
   }
   
   public function all_from_ref($ref)
   {
      $lista = array();
      
      $sql = "SELECT * FROM numeros_serie WHERE referencia = ".$this->var2str($ref)
              ." ORDER BY numserie ASC;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $lista[] = new numero_serie($d);
         }
      }
      
      return $lista;
   }
   
   public function all_from_ref_libre($ref)
   {
      $lista = array();
      
      $sql = "SELECT * FROM numeros_serie WHERE referencia = ".$this->var2str($ref). " AND vendido IS NOT TRUE "
              ." ORDER BY numserie ASC;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $lista[] = new numero_serie($d);
         }
      }
      
      return $lista;
   }
   
   public function search($query, $offset=0)
   {
      $numlist = array();
      $query = mb_strtolower( $this->no_html($query), 'UTF8' );
      
      $consulta = "SELECT * FROM ".$this->table_name." WHERE vendido = FALSE AND numserie LIKE '%".$query."%' ORDER BY numserie ASC;";
      $data = $this->db->select_limit($consulta, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
            $numlist[] = new numero_serie($d);
      } 
      return $numlist;
   }
   
   public function all_from_linea($documento,$idlinea)
   {
    
      $lista = array();
      
      $sql = "SELECT numserie FROM ".$this->table_name." WHERE ".$documento." = ".$idlinea." ORDER BY numserie;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $key => $value)
         {
            $lista[]= $value['numserie'];
         }
      }
      return $lista;
   }
   
   public function all_from_linea_completo($documento,$idlinea)
   {
    
      $lista = array();
      
      $sql = "SELECT * FROM ".$this->table_name." WHERE ".$documento." = ".$idlinea." ORDER BY numserie;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
            $lista[] = new numero_serie($d);
      } 
      return $lista;
   }

   public function total_por_linea($documento, $idlinea)
   {
      $data = $this->db->select("SELECT COUNT(numserie) as total FROM ".$this->table_name." WHERE ".$documento." = ".$idlinea.";");
      if ($data)
      {
         return intval($data[0]['total']);
      }
      else
         return 0;
   }
   
   public function delete_idlinea($documento, $idlinea)
   {
      return $this->db->exec("DELETE FROM numeros_serie WHERE $documento = ".$idlinea.";");
   }
   
   public function can_delete($documento, $idlinea)
   {
      $data = $this->db->select("SELECT count(vendido) as total FROM ".$this->table_name." WHERE ".$documento." = ".$idlinea.";");
      if ($data)
      {
         if($data[0]['total'] > 0)
         {
            return FALSE;
         }
         else
            return TRUE;
      }
      else
         return TRUE;
   }
   
  public function limpiar_linea($documento, $idlinea)
   {
      $sql = "UPDATE numeros_serie SET $documento = NULL,vendido = 'FALSE' WHERE ".$documento." = ".$idlinea.";";
      
      return $this->db->exec($sql);
   }

}
