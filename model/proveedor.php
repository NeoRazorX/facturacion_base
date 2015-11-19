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

require_model('cuenta.php');
require_model('direccion_proveedor.php');
require_model('subcuenta.php');
require_model('subcuenta_proveedor.php');

/**
 * Un proveedor. Puede estar relacionado con varias direcciones o subcuentas.
 */
class proveedor extends fs_model
{
   /**
    * Clave primaria. Varchar (6).
    * @var type 
    */
   public $codproveedor;
   
   /**
    * Nombre por el que se conoce al proveedor, puede ser el nombre oficial o no.
    * @var type 
    */
   public $nombre;
   
   /**
    * Razón social del proveedor, es decir, el nombre oficial, el que se usa en
    * las facturas.
    * @var type 
    */
   public $razonsocial;
   
   /**
    * Identificador fiscal del proveedor.
    * @var type
    */
   public $cifnif;
   public $telefono1;
   public $telefono2;
   public $fax;
   public $email;
   public $web;
   
   /**
    * Serie predeterminada para este proveedor.
    * @var type
    */
   public $codserie;
   
   /**
    * Divisa predeterminada para este proveedor.
    * @var type
    */
   public $coddivisa;
   
   /**
    *
    * @var type Forma de pago predeterminada para este proveedor.
    */
   public $codpago;
   public $observaciones;
   
   /**
    * Tipo de identificador fiscal, todavía sin uso.
    * @var type 
    */
   public $tipoidfiscal;
   
   /**
    * Régimen de fiscalidad del proveedor. Por ahora solo están implementados
    * general y exento.
    * @var type 
    */
   public $regimeniva;
   
   /**
    * TRUE -> el proveedor es un acreedor, es decir, no le compramos mercancia,
    * le compramos servicios, etc.
    * @var type
    */
   public $acreedor;
   
   private static $regimenes_iva;
   
   public function __construct($p=FALSE)
   {
      parent::__construct('proveedores', 'plugins/facturacion_base/');
      if($p)
      {
         $this->codproveedor = $p['codproveedor'];
         $this->nombre = $p['nombre'];
         
         if( is_null($p['razonsocial']) )
         {
            $this->razonsocial = $p['nombrecomercial'];
         }
         else
         {
            $this->razonsocial = $p['razonsocial'];
         }
         
         $this->cifnif = $p['cifnif'];
         $this->telefono1 = $p['telefono1'];
         $this->telefono2 = $p['telefono2'];
         $this->fax = $p['fax'];
         $this->email = $p['email'];
         $this->web = $p['web'];
         $this->codserie = $p['codserie'];
         $this->coddivisa = $p['coddivisa'];
         $this->codpago = $p['codpago'];
         $this->observaciones = $this->no_html($p['observaciones']);
         $this->tipoidfiscal = $p['tipoidfiscal'];
         $this->regimeniva = $p['regimeniva'];
         $this->acreedor = $this->str2bool($p['acreedor']);
      }
      else
      {
         $this->codproveedor = NULL;
         $this->nombre = '';
         $this->razonsocial = '';
         $this->cifnif = '';
         $this->telefono1 = '';
         $this->telefono2 = '';
         $this->fax = '';
         $this->email = '';
         $this->web = '';
         $this->codserie = $this->default_items->codserie();
         $this->coddivisa = $this->default_items->coddivisa();
         $this->codpago = $this->default_items->codpago();
         $this->observaciones = '';
         $this->tipoidfiscal = 'NIF';
         $this->regimeniva = 'General';
         $this->acreedor = FALSE;
      }
   }
   
   protected function install()
   {
      $this->clean_cache();
      return '';
   }
   
   /**
    * Devuelve un array con los regimenes de iva disponibles.
    * @return type
    */
   public function regimenes_iva()
   {
      if( !isset(self::$regimenes_iva) )
      {
         /// Si hay usa lista personalizada en fs_vars, la usamos
         $fsvar = new fs_var();
         $data = $fsvar->simple_get('proveedor::regimenes_iva');
         if($data)
         {
            self::$regimenes_iva = array();
            foreach( explode(',', $data) as $d )
            {
               self::$regimenes_iva[] = trim($d);
            }
         }
         else
         {
            /// sino usamos estos
            self::$regimenes_iva = array('General', 'Exento');
         }
         
         /// además de los que haya en la base de datos
         $data = $this->db->select("SELECT DISTINCT regimeniva FROM proveedores ORDER BY regimeniva ASC;");
         if($data)
         {
            foreach($data as $d)
            {
               if( !in_array($d['regimeniva'], self::$regimenes_iva) )
               {
                  self::$regimenes_iva[] = $d['regimeniva'];
               }
            }
         }
      }
      
      return self::$regimenes_iva;
   }
   
   public function observaciones_resume()
   {
      if($this->observaciones == '')
      {
         return '-';
      }
      else if( strlen($this->observaciones) < 60 )
      {
         return $this->observaciones;
      }
      else
         return substr($this->observaciones, 0, 50).'...';
   }
   
   public function url()
   {
      if( is_null($this->codproveedor) )
      {
         return "index.php?page=compras_proveedores";
      }
      else
         return "index.php?page=compras_proveedor&cod=".$this->codproveedor;
   }
   
   /**
    * @deprecated since version 50
    * @return type
    */
   public function is_default()
   {
      return FALSE;
   }
   
   /**
    * Devuelve un proveedor a partir de su codproveedor
    * @param type $cod
    * @return boolean|\proveedor
    */
   public function get($cod)
   {
      $prov = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codproveedor = ".$this->var2str($cod).";");
      if($prov)
      {
         return new proveedor($prov[0]);
      }
      else
         return FALSE;
   }
   
   /**
    * Devuelve un proveedor a partir de su cifnif
    * @param type $cifnif
    * @return boolean|\proveedor
    */
   public function get_by_cifnif($cifnif)
   {
      $prov = $this->db->select("SELECT * FROM ".$this->table_name." WHERE cifnif = ".$this->var2str($cifnif).";");
      if($prov)
      {
         return new proveedor($prov[0]);
      }
      else
         return FALSE;
   }
   
   /**
    * Devuelve un nuevo código que se usará como clave primaria/identificador único para este proveedor.
    * @return string
    */
   public function get_new_codigo()
   {
      $cod = $this->db->select("SELECT MAX(".$this->db->sql_to_int('codproveedor').") as cod FROM ".$this->table_name.";");
      if($cod)
      {
         return sprintf('%06s', (1 + intval($cod[0]['cod'])));
      }
      else
         return '000001';
   }
   
   /**
    * Devuelve las subcuentas asociadas al proveedor, una para cada ejercicio.
    * @return type
    */
   public function get_subcuentas()
   {
      $sublist = array();
      $subcp = new subcuenta_proveedor();
      foreach($subcp->all_from_proveedor($this->codproveedor) as $s)
      {
         $s2 = $s->get_subcuenta();
         if($s2)
         {
            $sublist[] = $s2;
         }
         else
            $s->delete();
      }
      
      return $sublist;
   }
   
   /**
    * Devuelve la subcuenta asignada al proveedor para el ejercicio $eje,
    * si no hay una subcuenta asignada, intenta crearla.  Si falla devuelve FALSE.
    * @param type $eje
    * @return subcuenta
    */
   public function get_subcuenta($eje)
   {
      $subcuenta = FALSE;
      
      foreach($this->get_subcuentas() as $s)
      {
         if($s->codejercicio == $eje)
         {
            $subcuenta = $s;
            break;
         }
      }
      
      if(!$subcuenta)
      {
         /// intentamos crear la subcuenta y asociarla
         $continuar = TRUE;
         $cuenta = new cuenta();
         
         if($this->acreedor)
         {
            $cpro = $cuenta->get_cuentaesp('ACREED', $eje);
            if(!$cpro)
            {
               $cpro = $cuenta->get_by_codigo('410', $eje);
            }
         }
         else
            $cpro = $cuenta->get_cuentaesp('PROVEE', $eje);
         
         if($cpro)
         {
            $subc0 = $cpro->new_subcuenta($this->codproveedor);
            $subc0->descripcion = $this->nombre;
            if( !$subc0->save() )
            {
               $this->new_error_msg('Imposible crear la subcuenta para el proveedor '.$this->codproveedor);
               $continuar = FALSE;
            }
            
            if($continuar)
            {
               $scpro = new subcuenta_proveedor();
               $scpro->codejercicio = $eje;
               $scpro->codproveedor = $this->codproveedor;
               $scpro->codsubcuenta = $subc0->codsubcuenta;
               $scpro->idsubcuenta = $subc0->idsubcuenta;
               if( $scpro->save() )
               {
                  $subcuenta = $subc0;
               }
               else
                  $this->new_error_msg('Imposible asociar la subcuenta para el proveedor '.$this->codproveedor);
            }
         }
         else
            $this->new_error_msg('No se encuentra ninguna cuenta especial para proveedores.');
      }
      
      return $subcuenta;
   }
   
   /**
    * Devuelve las direcciones asociadas al proveedor.
    * @return type
    */
   public function get_direcciones()
   {
      $dir = new direccion_proveedor();
      return $dir->all_from_proveedor($this->codproveedor);
   }
   
   public function exists()
   {
      if( is_null($this->codproveedor) )
      {
         return FALSE;
      }
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE codproveedor = ".$this->var2str($this->codproveedor).";");
   }
   
   public function test()
   {
      $status = FALSE;
      
      $this->codproveedor = trim($this->codproveedor);
      $this->nombre = $this->no_html($this->nombre);
      $this->razonsocial = $this->no_html($this->razonsocial);
      
      if( !preg_match("/^[A-Z0-9]{1,6}$/i", $this->codproveedor) )
      {
         $this->new_error_msg("Código de proveedor no válido.");
      }
      else if( strlen($this->nombre) < 1 OR strlen($this->nombre) > 100 )
      {
         $this->new_error_msg("Nombre de proveedor no válido.");
      }
      else if( strlen($this->razonsocial) < 1 OR strlen($this->razonsocial) > 100 )
      {
         $this->new_error_msg("Razón social del proveedor no válida.");
      }
      else
         $status = TRUE;
      
      return $status;
   }
   
   public function save()
   {
      if( $this->test() )
      {
         $this->clean_cache();
         
         if( $this->exists() )
         {
            $sql = "UPDATE ".$this->table_name." SET nombre = ".$this->var2str($this->nombre).
                    ", razonsocial = ".$this->var2str($this->razonsocial).
                    ", cifnif = ".$this->var2str($this->cifnif).
                    ", telefono1 = ".$this->var2str($this->telefono1).
                    ", telefono2 = ".$this->var2str($this->telefono2).
                    ", fax = ".$this->var2str($this->fax).
                    ", email = ".$this->var2str($this->email).
                    ", web = ".$this->var2str($this->web).
                    ", codserie = ".$this->var2str($this->codserie).
                    ", coddivisa = ".$this->var2str($this->coddivisa).
                    ", codpago = ".$this->var2str($this->codpago).
                    ", observaciones = ".$this->var2str($this->observaciones).
                    ", tipoidfiscal = ".$this->var2str($this->tipoidfiscal).
                    ", regimeniva = ".$this->var2str($this->regimeniva).
                    ", acreedor = ".$this->var2str($this->acreedor).
                    " WHERE codproveedor = ".$this->var2str($this->codproveedor).";";
         }
         else
         {
            $sql = "INSERT INTO ".$this->table_name." (codproveedor,nombre,razonsocial,cifnif,telefono1,telefono2,
                    fax,email,web,codserie,coddivisa,codpago,observaciones,tipoidfiscal,regimeniva,acreedor)
                    VALUES (".$this->var2str($this->codproveedor).
                    ",".$this->var2str($this->nombre).
                    ",".$this->var2str($this->razonsocial).
                    ",".$this->var2str($this->cifnif).
                    ",".$this->var2str($this->telefono1).
                    ",".$this->var2str($this->telefono2).
                    ",".$this->var2str($this->fax).
                    ",".$this->var2str($this->email).
                    ",".$this->var2str($this->web).
                    ",".$this->var2str($this->codserie).
                    ",".$this->var2str($this->coddivisa).
                    ",".$this->var2str($this->codpago).
                    ",".$this->var2str($this->observaciones).
                    ",".$this->var2str($this->tipoidfiscal).
                    ",".$this->var2str($this->regimeniva).
                    ",".$this->var2str($this->acreedor).");";
         }
         
         return $this->db->exec($sql);
      }
      else
         return FALSE;
   }
   
   public function delete()
   {
      $this->clean_cache();
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE codproveedor = ".$this->var2str($this->codproveedor).";");
   }
   
   private function clean_cache()
   {
      $this->cache->delete('m_proveedor_all');
   }
   
   public function all($offset = 0, $solo_acreedores = FALSE)
   {
      $provelist = array();
      $sql = "SELECT * FROM ".$this->table_name." ORDER BY nombre ASC";
      if($solo_acreedores)
      {
         $sql = "SELECT * FROM ".$this->table_name." WHERE acreedor ORDER BY nombre ASC";
      }
      
      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $p)
            $provelist[] = new proveedor($p);
      }
      
      return $provelist;
   }
   
   public function all_full()
   {
      $provelist = $this->cache->get_array('m_proveedor_all');
      if( !$provelist )
      {
         $data = $this->db->select("SELECT * FROM ".$this->table_name." ORDER BY nombre ASC;");
         if($data)
         {
            foreach($data as $d)
               $provelist[] = new proveedor($d);
         }
         $this->cache->set('m_proveedor_all', $provelist);
      }
      return $provelist;
   }
   
   public function search($query, $offset=0)
   {
      $prolist = array();
      $query = mb_strtolower( $this->no_html($query) );
      
      $consulta = "SELECT * FROM ".$this->table_name." WHERE ";
      if( is_numeric($query) )
      {
         $consulta .= "codproveedor LIKE '%".$query."%' OR cifnif LIKE '%".$query."%'"
                 . " OR telefono1 LIKE '".$query."%' OR telefono2 LIKE '".$query."%'"
                 . " OR observaciones LIKE '%".$query."%'";
      }
      else
      {
         $buscar = str_replace(' ', '%', $query);
         $consulta .= "lower(nombre) LIKE '%".$buscar."%' OR lower(razonsocial) LIKE '%".$buscar."%'"
                 . " OR lower(cifnif) LIKE '%".$buscar."%' OR lower(email) LIKE '%".$buscar."%'"
                 . " OR lower(observaciones) LIKE '%".$buscar."%'";
      }
      $consulta .= " ORDER BY nombre ASC";
      
      $data = $this->db->select_limit($consulta, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
            $prolist[] = new proveedor($d);
      }
      
      return $prolist;
   }
}
