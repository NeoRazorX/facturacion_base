<?php

/*
 * This file is part of FacturaScripts
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

namespace FacturaScripts\model;

/**
 * Esta clase sirve para guardar la información de trazabilidad del artículo.
 * Números de serie, de lote y albaranes y facturas relacionadas.
 *
 * @author Luismipr <luismipr@gmail.com>
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class articulo_traza extends \fs_model
{
   /**
    * Clave primaria
    * @var type 
    */
   public $id;
   
   /**
    * Referencia del artículo
    * @var type varchar 
    */
   public $referencia;
   
   /**
    * Numero de serie
    * Clave primaria.
    * @var type varchar 
    */
   public $numserie;
   
   /**
    * Número o identificador del lote
    * @var type 
    */
   public $lote;
   
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
   
   public function __construct($n = FALSE)
   {
      parent::__construct('articulo_trazas');
      if($n)
      {
         $this->id = $this->intval($n['id']);
         $this->referencia = $n['referencia'];
         $this->numserie = $n['numserie'];
         $this->lote = $n['lote'];
         $this->idlalbventa = $this->intval($n['idlalbventa']);
         $this->idlfacventa = $this->intval($n['idlfacventa']);
         $this->idlalbcompra = $this->intval($n['idlalbcompra']);
         $this->idlfaccompra = $this->intval($n['idlfaccompra']);
      }
      else
      {
         $this->id = NULL;
         $this->referencia = NULL;
         $this->numserie = NULL;
         $this->lote = NULL;
         $this->idlalbventa = NULL;
         $this->idlfacventa = NULL;
         $this->idlalbcompra = NULL;
         $this->idlfaccompra = NULL;
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
         return new \articulo_traza($data[0]);
      }
      else
      {
         return FALSE;
      }
   }
   
   public function get_by_numserie($numserie)
   {
      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE numserie = ".$this->var2str($numserie).";");
      if($data)
      {
         return new \articulo_traza($data[0]);
      }
      else
      {
         return FALSE;
      }
   }
   
   public function exists()
   {
      if( is_null($this->id) )
      {
         return FALSE;
      }
      else
      {
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE id = ".$this->var2str($this->id).";");
      }
   }
   
   public function save()
   {
      if( $this->exists() )
      {
         $sql = "UPDATE ".$this->table_name." SET referencia = ".$this->var2str($this->referencia)
                 . ", numserie = ".$this->var2str($this->numserie)
                 . ", lote = ".$this->var2str($this->lote)
                 . ", idlalbventa = ".$this->var2str($this->idlalbventa)
                 . ", idlfacventa = ".$this->var2str($this->idlfacventa)
                 . ", idlalbcompra = ".$this->var2str($this->idlalbcompra)
                 . ", idlfaccompra = ".$this->var2str($this->idlfaccompra)
                 . "  WHERE id = ".$this->var2str($this->id).";";
         
         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO ".$this->table_name." (referencia,numserie,lote,idlalbventa,"
                 . "idlfacventa,idlalbcompra,idlfaccompra) VALUES "
                 . "(".$this->var2str($this->referencia)
                 . ",".$this->var2str($this->numserie)
                 . ",".$this->var2str($this->lote)
                 . ",".$this->var2str($this->idlalbventa)
                 . ",".$this->var2str($this->idlfacventa)
                 . ",".$this->var2str($this->idlalbcompra)
                 . ",".$this->var2str($this->idlfaccompra).");";
         
         if( $this->db->exec($sql) )
         {
            $this->id = $this->db->lastval();
            return TRUE;
         }
         else
         {
            return FALSE;
         }
      }
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE id = ".$this->var2str($this->id).";");
   }
   
   public function all()
   {
      $lista = array();
      
      $data = $this->db->select("SELECT * FROM ".$this->table_name." ORDER BY id DESC;");
      if($data)
      {
         foreach($data as $d)
         {
            $lista[] = new \articulo_traza($d);
         }
      }
      
      return $lista;
   }
   
   public function all_from_ref($ref, $sololibre = FALSE)
   {
      $lista = array();
      
      $sql = "SELECT * FROM ".$this->table_name." WHERE referencia = ".$this->var2str($ref);
      if($sololibre)
      {
         $sql .= " AND idlalbventa IS NULL AND idlfacventa IS NULL";
      }
      $sql .= " ORDER BY id ASC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $lista[] = new \articulo_traza($d);
         }
      }
      
      return $lista;
   }
   
   public function search($query)
   {
      $numlist = array();
      $query = mb_strtolower( $this->no_html($query), 'UTF8' );
      
      $consulta = "SELECT * FROM ".$this->table_name." WHERE numserie LIKE '%".$query."%'"
              . " OR lote LIKE '%".$query."%' ORDER BY id DESC;";
      
      $data = $this->db->select($consulta);
      if($data)
      {
         foreach($data as $d)
         {
            $numlist[] = new \articulo_traza($d);
         }
      }
      
      return $numlist;
   }
   
   public function all_from_linea($documento, $idlinea)
   {
      $lista = array();
      
      $sql = "SELECT * FROM ".$this->table_name." WHERE ".$documento." = ".$idlinea." ORDER BY id DESC;";
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $d)
         {
            $lista[] = new \articulo_traza($d);
         }
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
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE $documento = ".$idlinea.";");
   }
   
   public function can_delete($documento, $idlinea)
   {
      $data = $this->db->select("SELECT count(vendido) as total FROM ".$this->table_name." WHERE ".$documento." = ".$idlinea.";");
      if($data)
      {
         if( intval($data[0]['total']) > 0)
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
      $sql = "UPDATE ".$this->table_name." SET $documento = NULL WHERE ".$documento." = ".$idlinea.";";
      return $this->db->exec($sql);
   }
}
