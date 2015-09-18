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
require_model('direccion_cliente.php');
require_model('subcuenta.php');
require_model('subcuenta_cliente.php');

/**
 * El cliente. Puede tener una o varias direcciones y subcuentas asociadas.
 */
class cliente extends fs_model
{
   /**
    * Clave primaria. Varchar (6).
    * @var type 
    */
   public $codcliente;
   
   /**
    * Nombre por el que conocemos al cliente, no necesariamente el oficial.
    * @var type 
    */
   public $nombre;
   
   /**
    * Razón social del cliente, es decir, el nombre oficial. El que aparece en las facturas.
    * @var type
    */
   public $razonsocial;
   
   /**
    * Identificador fiscal del cliente.
    * @var type 
    */
   public $cifnif;
   public $telefono1;
   public $telefono2;
   public $fax;
   public $email;
   public $web;
   
   /**
    * Serie predeterminada para este cliente.
    * @var type 
    */
   public $codserie;
   
   /**
    * Divisa predeterminada para este cliente.
    * @var type 
    */
   public $coddivisa;
   
   /**
    * Forma de pago predeterminada para este cliente.
    * @var type 
    */
   public $codpago;
   
   /**
    * Empleado/agente asignado al cliente.
    * @var type 
    */
   public $codagente;
   
   /**
    * Grupo al que pertenece el cliente.
    * @var type 
    */
   public $codgrupo;
   
   /**
    * TRUE -> el cliente ya no nos compra o no queremos nada con él.
    * @var type 
    */
   public $debaja;
   
   /**
    * Fecha en la que se dió de baja al cliente.
    * @var type 
    */
   public $fechabaja;
   
   /**
    * Fecha en la que se dió de alta al cliente.
    * @var type 
    */
   public $fechaalta;
   
   public $observaciones;
   
   /**
    * Régimen de fiscalidad del cliente. Por ahora solo están implementados
    * general y exento.
    * @var type 
    */
   public $regimeniva;
   
   /**
    * TRUE -> al cliente se le aplica recargo de equivalencia.
    * @var type 
    */
   public $recargo;
   
   private static $regimenes_iva;

   public function __construct($c=FALSE)
   {
      parent::__construct('clientes', 'plugins/facturacion_base/');
      if($c)
      {
         $this->codcliente = $c['codcliente'];
         $this->nombre = $c['nombre'];
         
         if( is_null($c['razonsocial']) )
         {
            $this->razonsocial = $c['nombrecomercial'];
         }
         else
         {
            $this->razonsocial = $c['razonsocial'];
         }
         
         $this->cifnif = $c['cifnif'];
         $this->telefono1 = $c['telefono1'];
         $this->telefono2 = $c['telefono2'];
         $this->fax = $c['fax'];
         $this->email = $c['email'];
         $this->web = $c['web'];
         $this->codserie = $c['codserie'];
         $this->coddivisa = $c['coddivisa'];
         $this->codpago = $c['codpago'];
         $this->codagente = $c['codagente'];
         $this->codgrupo = $c['codgrupo'];
         $this->debaja = $this->str2bool($c['debaja']);
         
         $this->fechabaja = NULL;
         if($c['fechabaja'])
         {
            $this->fechabaja = date('d-m-Y', strtotime($c['fechabaja']));
         }
         
         $this->fechaalta = date('d-m-Y', strtotime($c['fechaalta']));
         $this->observaciones = $this->no_html($c['observaciones']);
         $this->regimeniva = $c['regimeniva'];
         $this->recargo = $this->str2bool($c['recargo']);
      }
      else
      {
         $this->codcliente = NULL;
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
         $this->codagente = NULL;
         $this->codgrupo = NULL;
         $this->debaja = FALSE;
         $this->fechabaja = NULL;
         $this->fechaalta = date('d-m-Y');
         $this->observaciones = NULL;
         $this->regimeniva = 'General';
         $this->recargo = FALSE;
      }
   }
   
   protected function install()
   {
      $this->clean_cache();
      return '';
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
      if( is_null($this->codcliente) )
      {
         return "index.php?page=ventas_clientes";
      }
      else
         return "index.php?page=ventas_cliente&cod=".$this->codcliente;
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
    * Devuelve un array con los regimenes de iva disponibles.
    * @return type
    */
   public function regimenes_iva()
   {
      if( !isset(self::$regimenes_iva) )
      {
         /// Si hay usa lista personalizada en fs_vars, la usamos
         $fsvar = new fs_var();
         $data = $fsvar->simple_get('cliente::regimenes_iva');
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
         
         /// además de añadir los que haya en la base de datos
         $data = $this->db->select("SELECT DISTINCT regimeniva FROM clientes ORDER BY regimeniva ASC;");
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
   
   /**
    * Devuelve un cliente a partir del codcliente
    * @param type $cod
    * @return \cliente|boolean
    */
   public function get($cod)
   {
      $cli = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codcliente = ".$this->var2str($cod).";");
      if($cli)
      {
         return new cliente($cli[0]);
      }
      else
         return FALSE;
   }
   
   /**
    * Devuelve un cliente a partir del cifnif
    * @param type $cifnif
    * @return \cliente|boolean
    */
   public function get_by_cifnif($cifnif)
   {
      $cli = $this->db->select("SELECT * FROM ".$this->table_name." WHERE cifnif = ".$this->var2str($cifnif).";");
      if($cli)
      {
         return new cliente($cli[0]);
      }
      else
         return FALSE;
   }
   
   /**
    * Devuelve un array con las direcciones asociadas al cliente.
    * @return type
    */
   public function get_direcciones()
   {
      $dir = new direccion_cliente();
      return $dir->all_from_cliente($this->codcliente);
   }
   
   /**
    * Devuelve un array con todas las subcuentas asociadas al cliente.
    * Una para cada ejercicio.
    * @return type
    */
   public function get_subcuentas()
   {
      $subclist = array();
      $subc = new subcuenta_cliente();
      foreach($subc->all_from_cliente($this->codcliente) as $s)
      {
         $s2 = $s->get_subcuenta();
         if($s2)
         {
            $subclist[] = $s2;
         }
         else
            $s->delete();
      }
      
      return $subclist;
   }
   
   /**
    * Devuelve la subcuenta asociada al cliente para el ejercicio $eje.
    * Si no existe intenta crearla. Si falla devuelve FALSE.
    * @param type $ejercicio
    * @return subcuenta
    */
   public function get_subcuenta($ejercicio)
   {
      $subcuenta = FALSE;
      
      foreach($this->get_subcuentas() as $s)
      {
         if($s->codejercicio == $ejercicio)
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
         $ccli = $cuenta->get_cuentaesp('CLIENT', $ejercicio);
         if($ccli)
         {
            $subc0 = $ccli->new_subcuenta($this->codcliente);
            $subc0->descripcion = $this->nombre;
            if( !$subc0->save() )
            {
               $this->new_error_msg('Imposible crear la subcuenta para el cliente '.$this->codcliente);
               $continuar = FALSE;
            }
            
            if($continuar)
            {
               $sccli = new subcuenta_cliente();
               $sccli->codcliente = $this->codcliente;
               $sccli->codejercicio = $ejercicio;
               $sccli->codsubcuenta = $subc0->codsubcuenta;
               $sccli->idsubcuenta = $subc0->idsubcuenta;
               if( $sccli->save() )
               {
                  $subcuenta = $subc0;
               }
               else
                  $this->new_error_msg('Imposible asociar la subcuenta para el cliente '.$this->codcliente);
            }
         }
         else
            $this->new_error_msg('No se encuentra ninguna cuenta especial para clientes.');
      }
      
      return $subcuenta;
   }
   
   public function exists()
   {
      if( is_null($this->codcliente) )
      {
         return FALSE;
      }
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE codcliente = ".$this->var2str($this->codcliente).";");
   }
   
   /**
    * Devuelve un código que se usará como clave primaria/identificador único para este cliente.
    * @return string
    */
   public function get_new_codigo()
   {
      $cod = $this->db->select("SELECT MAX(".$this->db->sql_to_int('codcliente').") as cod FROM ".$this->table_name.";");
      if($cod)
      {
         return sprintf('%06s', (1 + intval($cod[0]['cod'])));
      }
      else
         return '000001';
   }
   
   public function test()
   {
      $status = FALSE;
      
      $this->codcliente = trim($this->codcliente);
      $this->nombre = $this->no_html($this->nombre);
      $this->razonsocial = $this->no_html($this->razonsocial);
      $this->cifnif = $this->no_html($this->cifnif);
      $this->observaciones = $this->no_html($this->observaciones);
      
      if($this->debaja)
      {
         if( is_null($this->fechabaja) )
         {
            $this->fechabaja = date('d-m-Y');
         }
      }
      else
      {
         $this->fechabaja = NULL;
      }
      
      if( !preg_match("/^[A-Z0-9]{1,6}$/i", $this->codcliente) )
      {
         $this->new_error_msg("Código de cliente no válido.");
      }
      else if( strlen($this->nombre) < 1 OR strlen($this->nombre) > 100 )
      {
         $this->new_error_msg("Nombre de cliente no válido.");
      }
      else if( strlen($this->razonsocial) < 1 OR strlen($this->razonsocial) > 100 )
      {
         $this->new_error_msg("Razón social del cliente no válida.");
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
            $sql = "UPDATE ".$this->table_name." SET nombre = ".$this->var2str($this->nombre)
                    .", razonsocial = ".$this->var2str($this->razonsocial)
                    .", cifnif = ".$this->var2str($this->cifnif)
                    .", telefono1 = ".$this->var2str($this->telefono1)
                    .", telefono2 = ".$this->var2str($this->telefono2)
                    .", fax = ".$this->var2str($this->fax)
                    .", email = ".$this->var2str($this->email)
                    .", web = ".$this->var2str($this->web)
                    .", codserie = ".$this->var2str($this->codserie)
                    .", coddivisa = ".$this->var2str($this->coddivisa)
                    .", codpago = ".$this->var2str($this->codpago)
                    .", codagente = ".$this->var2str($this->codagente)
                    .", codgrupo = ".$this->var2str($this->codgrupo)
                    .", debaja = ".$this->var2str($this->debaja)
                    .", fechabaja = ".$this->var2str($this->fechabaja)
                    .", fechaalta = ".$this->var2str($this->fechaalta)
                    .", observaciones = ".$this->var2str($this->observaciones)
                    .", regimeniva = ".$this->var2str($this->regimeniva)
                    .", recargo = ".$this->var2str($this->recargo)
                    ."  WHERE codcliente = ".$this->var2str($this->codcliente).";";
         }
         else
         {
            $sql = "INSERT INTO ".$this->table_name." (codcliente,nombre,razonsocial,cifnif,telefono1,
               telefono2,fax,email,web,codserie,coddivisa,codpago,codagente,codgrupo,debaja,fechabaja,
               fechaalta,observaciones,regimeniva,recargo) VALUES (".$this->var2str($this->codcliente)
                    .",".$this->var2str($this->nombre)
                    .",".$this->var2str($this->razonsocial)
                    .",".$this->var2str($this->cifnif)
                    .",".$this->var2str($this->telefono1)
                    .",".$this->var2str($this->telefono2)
                    .",".$this->var2str($this->fax)
                    .",".$this->var2str($this->email)
                    .",".$this->var2str($this->web)
                    .",".$this->var2str($this->codserie)
                    .",".$this->var2str($this->coddivisa)
                    .",".$this->var2str($this->codpago)
                    .",".$this->var2str($this->codagente)
                    .",".$this->var2str($this->codgrupo)
                    .",".$this->var2str($this->debaja)
                    .",".$this->var2str($this->fechabaja)
                    .",".$this->var2str($this->fechaalta)
                    .",".$this->var2str($this->observaciones)
                    .",".$this->var2str($this->regimeniva)
                    .",".$this->var2str($this->recargo).");";
         }
         
         return $this->db->exec($sql);
      }
      else
         return FALSE;
   }
   
   public function delete()
   {
      $this->clean_cache();
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE codcliente = ".$this->var2str($this->codcliente).";");
   }
   
   private function clean_cache()
   {
      $this->cache->delete('m_cliente_all');
   }
   
   public function all($offset=0)
   {
      $clientlist = array();
      
      $data = $this->db->select_limit("SELECT * FROM ".$this->table_name." ORDER BY nombre ASC", FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
            $clientlist[] = new cliente($d);
      }
      
      return $clientlist;
   }
   
   public function all_full()
   {
      $clientlist = $this->cache->get_array('m_cliente_all');
      if( !$clientlist )
      {
         $data = $this->db->select("SELECT * FROM ".$this->table_name." ORDER BY nombre ASC;");
         if($data)
         {
            foreach($data as $d)
               $clientlist[] = new cliente($d);
         }
         $this->cache->set('m_cliente_all', $clientlist);
      }
      return $clientlist;
   }
   
   public function search($query, $offset=0)
   {
      $clilist = array();
      $query = mb_strtolower( $this->no_html($query) );
      
      $consulta = "SELECT * FROM ".$this->table_name." WHERE debaja = FALSE AND ";
      if( is_numeric($query) )
      {
         $consulta .= "(codcliente LIKE '%".$query."%' OR cifnif LIKE '%".$query."%'"
                 . " OR telefono1 LIKE '".$query."%' OR telefono2 LIKE '".$query."%'"
                 . " OR observaciones LIKE '%".$query."%')";
      }
      else
      {
         $buscar = str_replace(' ', '%', $query);
         $consulta .= "(lower(nombre) LIKE '%".$buscar."%' OR lower(razonsocial) LIKE '%".$buscar."%'"
                 . " OR lower(cifnif) LIKE '%".$buscar."%' OR lower(observaciones) LIKE '%".$buscar."%'"
                 . " OR lower(email) LIKE '%".$buscar."%')";
      }
      $consulta .= " ORDER BY nombre ASC";
      
      $data = $this->db->select_limit($consulta, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
            $clilist[] = new cliente($d);
      }
      
      return $clilist;
   }
   
   /**
    * Busca por cifnif.
    * @param type $dni
    * @param type $offset
    * @return \cliente
    */
   public function search_by_dni($dni, $offset=0)
   {
      $clilist = array();
      $query = strtolower( $this->no_html($dni) );
      $consulta = "SELECT * FROM ".$this->table_name." WHERE debaja = FALSE "
              . "AND lower(cifnif) LIKE '".$query."%' ORDER BY nombre ASC";
      
      $data = $this->db->select_limit($consulta, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
            $clilist[] = new cliente($d);
      }
      
      return $clilist;
   }
}
