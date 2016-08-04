<?php

/*
 * @author Carlos García Gómez      neorazorx@gmail.com
 * @copyright 2016, Carlos García Gómez. All Rights Reserved.
 */

namespace FacturaScripts\model;

/**
 * Description of linea_transferencia_stock
 *
 * @author carlos
 */
class linea_transferencia_stock extends \fs_model
{
   /// clave primaria. integer
   public $idlinea;
   
   public $idtrans;
   public $referencia;
   public $cantidad;
   public $descripcion;
   
   public function __construct($d=FALSE)
   {
      parent::__construct('lineastransstock');
      if($d)
      {
         $this->idlinea = $this->intval($d['idlinea']);
         $this->idtrans = $this->intval($d['idtrans']);
         $this->referencia = $d['referencia'];
         $this->cantidad = $d['cantidad'];
         $this->descripcion = $d['descripcion'];
      }
      else
      {
         /// valores predeterminados
         $this->idlinea = NULL;
         $this->idtrans = NULL;
         $this->referencia = NULL;
         $this->cantidad = 0;
         $this->descripcion = NULL;
      }
   }

   public function install()
   {
      return '';
   }

   public function exists()
   {
      if( is_null($this->idlinea) )
      {
         return FALSE;
      }
      else
      {
         return $this->db->select('SELECT * FROM lineastransstock WHERE idlinea = '.$this->var2str($this->idlinea).';');
      }
   }

   public function save()
   {
      if( $this->exists() )
      {
         $sql = "UPDATE lineastransstock SET idtrans = ".$this->var2str($this->idtrans)
                 . ", referencia = ".$this->var2str($this->referencia)
                 . ", cantidad = ".$this->var2str($this->cantidad)
                 . ", descripcion = ".$this->var2str($this->descripcion)
                 . "  WHERE idlinea = ".$this->var2str($this->idlinea).";";
         
         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO lineastransstock (idtrans,referencia,cantidad,descripcion) VALUES "
                 . "(".$this->var2str($this->idtrans)
                 . ",".$this->var2str($this->referencia)
                 . ",".$this->var2str($this->cantidad)
                 . ",".$this->var2str($this->descripcion).");";
         
         if( $this->db->exec($sql) )
         {
            $this->idlinea = $this->db->lastval();
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
      return $this->db->exec('DELETE FROM lineastransstock WHERE idlinea = '.$this->var2str($this->idlinea).';');
   }
   
   public function all_from_transferencia($id)
   {
      $list = array();
      
      $data = $this->db->select("SELECT * FROM lineastransstock WHERE idtrans = ".$this->var2str($id)." ORDER BY referencia ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            $list[] = new \linea_transferencia_stock($d);
         }
      }
      
      return $list;
   }
}
