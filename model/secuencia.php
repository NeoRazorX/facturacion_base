<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2015  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('ejercicio.php');
require_model('serie.php');

/**
 * Estos tres modelos (secuencia, secuencia_contabilidad y secuencia_ejercicio)
 * existen para mantener compatibilidad con eneboo, porque maldita la gana que
 * yo tengo de usar TRES tablas para algo tan simple...
 */
class secuencia extends fs_model
{
   /**
    * Clave primaria.
    * @var type 
    */
   public $idsec;
   public $id;
   public $valorout;
   public $valor;
   public $descripcion;
   public $nombre;
   
   public function __construct($s = FALSE)
   {
      parent::__construct('secuencias', 'plugins/facturacion_base/');
      if($s)
      {
         $this->idsec = $this->intval($s['idsec']);
         $this->id = $this->intval($s['id']);
         $this->valorout = $this->intval($s['valorout']);
         $this->valor = $this->intval($s['valor']);
         $this->descripcion = $s['descripcion'];
         $this->nombre = $s['nombre'];
      }
      else
      {
         $this->idsec = NULL;
         $this->id = NULL;
         $this->valorout = 0;
         $this->valor = 1;
         $this->descripcion = NULL;
         $this->nombre = NULL;
      }
   }
   
   protected function install()
   {
      $sece = new secuencia_ejercicio();
      return '';
   }
   
   public function get($idsec)
   {
      $sec = $this->db->select("SELECT * FROM ".$this->table_name." WHERE idsec = ".$this->var2str($idsec).";");
      if($sec)
      {
         return new secuencia($sec[0]);
      }
      else
         return FALSE;
   }
   
   public function get_by_params($id, $nombre)
   {
      $sec = $this->db->select("SELECT * FROM ".$this->table_name." WHERE id = ".$this->var2str($id)." AND nombre = ".$this->var2str($nombre).";");
      if($sec)
      {
         return new secuencia($sec[0]);
      }
      else
         return FALSE;
   }
   
   public function get_by_params2($eje, $serie, $nombre)
   {
      $sece = new secuencia_ejercicio();
      $sece->check();
      $aux = $sece->get_by_params($eje, $serie);
      if($aux)
      {
         $sec = $this->get_by_params($aux->id, $nombre);
         if($sec)
         {
            return $sec;
         }
         else
         {
            $newsec = new secuencia();
            $newsec->id = $aux->id;
            $newsec->nombre = $nombre;
            $newsec->descripcion = 'Secuencia del ejercicio '.$eje.' y la serie '.$serie;
            return $newsec;
         }
      }
      else
      {
         $this->new_error_msg("¡Secuencia de ejercicio no encontrada!");
         return FALSE;
      }
   }

   public function exists()
   {
      if( is_null($this->idsec) )
      {
         return FALSE;
      }
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE idsec = ".$this->var2str($this->idsec).";");
   }
   
   public function save()
   {
      if( $this->exists() )
      {
         $sql = "UPDATE ".$this->table_name." SET id = ".$this->var2str($this->id).
                 ", valorout = ".$this->var2str($this->valorout).
                 ", valor = ".$this->var2str($this->valor).
                 ", descripcion = ".$this->var2str($this->descripcion).
                 ", nombre = ".$this->var2str($this->nombre).
                 "  WHERE idsec = ".$this->var2str($this->idsec).";";
         
         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO ".$this->table_name." (id,valorout,valor,descripcion,nombre) VALUES
                  (".$this->var2str($this->id).
                 ",".$this->var2str($this->valorout).
                 ",".$this->var2str($this->valor).
                 ",".$this->var2str($this->descripcion).
                 ",".$this->var2str($this->nombre).");";
         
         if( $this->db->exec($sql) )
         {
            $this->idsec = $this->db->lastval();
            return TRUE;
         }
         else
            return FALSE;
      }
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE idsec = ".$this->var2str($this->idsec).";");
   }
}

/**
 * Clase que permite la compatibilidad con Eneboo.
 */
class secuencia_contabilidad extends fs_model
{
   /**
    * Clave primaria.
    * @var type 
    */
   public $idsecuencia;
   public $valorout;
   public $valor;
   public $descripcion;
   public $nombre;
   public $codejercicio;
   
   public function __construct($s = FALSE)
   {
      parent::__construct('co_secuencias', 'plugins/facturacion_base/');
      if($s)
      {
         $this->codejercicio = $s['codejercicio'];
         $this->descripcion = $s['descripcion'];
         $this->idsecuencia = $this->intval($s['idsecuencia']);
         $this->nombre = $s['nombre'];
         $this->valor = $this->intval($s['valor']);
         $this->valorout = $this->intval($s['valorout']);
      }
      else
      {
         $this->codejercicio = NULL;
         $this->descripcion = NULL;
         $this->idsecuencia = NULL;
         $this->nombre = NULL;
         $this->valor = NULL;
         $this->valorout = 1;
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   public function get_by_params($eje, $nombre)
   {
      $secuencias = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codejercicio = ".
              $this->var2str($eje)." AND nombre = ".$this->var2str($nombre).";");
      
      if($secuencias)
      {
         return new secuencia_contabilidad($secuencias[0]);
      }
      else
         return FALSE;
   }
   
   public function get_by_params2($eje, $nombre)
   {
      $sec = $this->get_by_params($eje, $nombre);
      if($sec)
      {
         return $sec;
      }
      else
      {
         $newsec = new secuencia_contabilidad();
         $newsec->codejercicio = $eje;
         $newsec->descripcion = 'Creado por FacturaScripts';
         $newsec->nombre = $nombre;
         return $newsec;
      }
   }
   
   public function exists()
   {
      if( is_null($this->idsecuencia) )
      {
         return FALSE;
      }
      else
      {
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE idsecuencia = ".
                 $this->var2str($this->idsecuencia).";");
      }
   }
   
   public function save()
   {
      if( $this->exists() )
      {
         $sql = "UPDATE ".$this->table_name." SET codejercicio = ".$this->var2str($this->codejercicio).
                 ", descripcion = ".$this->var2str($this->descripcion).
                 ", nombre = ".$this->var2str($this->nombre).
                 ", valor = ".$this->var2str($this->valor).
                 ", valorout = ".$this->var2str($this->valorout).
                 "  WHERE idsecuencia = ".$this->var2str($this->idsecuencia).";";
         
         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO ".$this->table_name." (codejercicio,descripcion,nombre,valor,valorout) VALUES "
                 . "(".$this->var2str($this->codejercicio)
                 . ",".$this->var2str($this->descripcion)
                 . ",".$this->var2str($this->nombre)
                 . ",".$this->var2str($this->valor)
                 . ",".$this->var2str($this->valorout).");";
         
         if( $this->db->exec($sql) )
         {
            $this->idsecuencia = $this->db->lastval();
            return TRUE;
         }
         else
            return FALSE;
      }
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE idsecuencia = ".$this->var2str($this->idsecuencia).";");
   }
}

/**
 * Clase que permite la compatibilidad con Eneboo.
 */
class secuencia_ejercicio extends fs_model
{
   /**
    * Clave primaria.
    * @var type 
    */
   public $id;
   public $nfacturacli;
   public $nalbarancli;
   public $npedidocli;
   public $npresupuestocli;
   public $nfacturaprov;
   public $nalbaranprov;
   public $npedidoprov;
   public $codejercicio;
   public $codserie;
   
   public function __construct($s = FALSE)
   {
      parent::__construct('secuenciasejercicios', 'plugins/facturacion_base/');
      if($s)
      {
         $this->id = $this->intval($s['id']);
         $this->codejercicio = $s['codejercicio'];
         $this->codserie = $s['codserie'];
         $this->nalbarancli = $this->intval($s['nalbarancli']);
         $this->nalbaranprov = $this->intval($s['nalbaranprov']);
         $this->nfacturacli = $this->intval($s['nfacturacli']);
         $this->nfacturaprov = $this->intval($s['nfacturaprov']);
         $this->npedidocli = $this->intval($s['npedidocli']);
         $this->npedidoprov = $this->intval($s['npedidoprov']);
         $this->npresupuestocli = $this->intval($s['npresupuestocli']);
      }
      else
      {
         $this->id = NULL;
         $this->codejercicio = NULL;
         $this->codserie = NULL;
         $this->nalbarancli = 1;
         $this->nalbaranprov = 1;
         $this->nfacturacli = 1;
         $this->nfacturaprov = 1;
         $this->npedidocli = 1;
         $this->npedidoprov = 1;
         $this->npresupuestocli = 1;
      }
   }
   
   protected function install()
   {
      return '';
   }
   
   public function get($id)
   {
      $secs = $this->db->select("SELECT * FROM ".$this->table_name." WHERE id = ".$this->var2str($id).";");
      if($secs)
      {
         return new secuencia_ejercicio($secs[0]);
      }
      else
         return FALSE;
   }
   
   public function get_by_params($eje, $serie)
   {
      $secs = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codejercicio = ".
              $this->var2str($eje)." AND codserie = ".$this->var2str($serie).";");
      
      if($secs)
      {
         return new secuencia_ejercicio($secs[0]);
      }
      else
         return FALSE;
   }
   
   public function check()
   {
      $eje = new ejercicio();
      $serie = new serie();
      foreach($eje->all() as $e)
      {
         $secs = $this->all_from_ejercicio($e->codejercicio);
         foreach($serie->all() as $serie)
         {
            $encontrada = FALSE;
            foreach($secs as $s)
            {
               if($s->codserie == $serie->codserie)
               {
                  $encontrada = TRUE;
               }
            }
            if( !$encontrada )
            {
               $aux = new secuencia_ejercicio();
               $aux->codejercicio = $e->codejercicio;
               $aux->codserie = $serie->codserie;
               if( !$aux->save() )
               {
                  $this->new_error_msg("¡Imposible crear la secuencia para el ejercicio ".
                          $aux->codejercicio." y la serie ".$aux->codserie."!");
               }
            }
         }
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
         $sql = "UPDATE ".$this->table_name." SET codejercicio = ".$this->var2str($this->codejercicio).
                 ", codserie = ".$this->var2str($this->codserie).
                 ", nalbarancli = ".$this->var2str($this->nalbarancli).
                 ", nalbaranprov = ".$this->var2str($this->nalbaranprov).
                 ", nfacturacli = ".$this->var2str($this->nfacturacli).
                 ", nfacturaprov = ".$this->var2str($this->nfacturaprov).
                 ", npedidocli = ".$this->var2str($this->npedidocli).
                 ", npedidoprov = ".$this->var2str($this->npedidoprov).
                 ", npresupuestocli =".$this->var2str($this->npresupuestocli).
                 "  WHERE id = ".$this->var2str($this->id).";";
         
         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO ".$this->table_name." (codejercicio,codserie,nalbarancli,
            nalbaranprov,nfacturacli,nfacturaprov,npedidocli,npedidoprov,npresupuestocli)
            VALUES (".$this->var2str($this->codejercicio).
                 ",".$this->var2str($this->codserie).
                 ",".$this->var2str($this->nalbarancli).
                 ",".$this->var2str($this->nalbaranprov).
                 ",".$this->var2str($this->nfacturacli).
                 ",".$this->var2str($this->nfacturaprov).
                 ",".$this->var2str($this->npedidocli).
                 ",".$this->var2str($this->npedidoprov).
                 ",".$this->var2str($this->npresupuestocli).");";
         
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
   
   public function all_from_ejercicio($eje)
   {
      $seclist = array();
      
      $secs = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codejercicio = ".$this->var2str($eje).";");
      if($secs)
      {
         foreach($secs as $s)
            $seclist[] = new secuencia_ejercicio($s);
      }
      
      return $seclist;
   }
}
