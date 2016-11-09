<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('cuenta_banco_proveedor.php');
require_model('divisa.php');
require_model('forma_pago.php');
require_model('pais.php');
require_model('proveedor.php');
require_model('serie.php');

class compras_proveedor extends fs_controller
{
   public $allow_delete;
   public $cuenta_banco;
   public $divisa;
   public $forma_pago;
   public $pais;
   public $proveedor;
   public $serie;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Proveedor', 'compras', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->ppage = $this->page->get('compras_proveedores');
      $this->cuenta_banco = new cuenta_banco_proveedor();
      $this->divisa = new divisa();
      $this->forma_pago = new forma_pago();
      $this->pais = new pais();
      $this->serie = new serie();
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      /// cargamos el proveedor
      $proveedor = new proveedor();
      $this->proveedor = FALSE;
      if( isset($_POST['codproveedor']) )
      {
         $this->proveedor = $proveedor->get($_POST['codproveedor']);
      }
      else if( isset($_GET['cod']) )
      {
         $this->proveedor = $proveedor->get($_GET['cod']);
      }
      
      if($this->proveedor)
      {
         $this->page->title = $this->proveedor->codproveedor;
         
         /// ¿Hay que hacer algo más?
         if( isset($_GET['delete_cuenta']) ) /// eliminar una cuenta bancaria
         {
            $cuenta = $this->cuenta_banco->get($_GET['delete_cuenta']);
            if($cuenta)
            {
               if( $cuenta->delete() )
               {
                  $this->new_message('Cuenta bancaria eliminada correctamente.');
               }
               else
                  $this->new_error_msg('Imposible eliminar la cuenta bancaria.');
            }
            else
               $this->new_error_msg('Cuenta bancaria no encontrada.');
         }
         else if( isset($_GET['delete_dir']) ) /// eliminar una dirección
         {
            $dir = new direccion_proveedor();
            $dir0 = $dir->get($_GET['delete_dir']);
            if($dir0)
            {
               if( $dir0->delete() )
               {
                  $this->new_message('Dirección eliminada correctamente.');
               }
               else
                  $this->new_error_msg('Imposible eliminar la dirección.');
            }
            else
               $this->new_error_msg('Dirección no encontrada.');
         }
         else if( isset($_POST['coddir']) ) /// añadir/modificar una dirección
         {
            $direccion = new direccion_proveedor();
            if($_POST['coddir'] != '')
            {
               $direccion = $direccion->get($_POST['coddir']);
            }
            $direccion->apartado = $_POST['apartado'];
            $direccion->ciudad = $_POST['ciudad'];
            $direccion->codpais = $_POST['pais'];
            $direccion->codpostal = $_POST['codpostal'];
            $direccion->codproveedor = $this->proveedor->codproveedor;
            $direccion->descripcion = $_POST['descripcion'];
            $direccion->direccion = $_POST['direccion'];
            $direccion->direccionppal = isset($_POST['direccionppal']);
            $direccion->provincia = $_POST['provincia'];
            if( $direccion->save() )
            {
               $this->new_message("Dirección guardada correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible guardar la dirección!");
         }
         else if( isset($_POST['iban']) ) /// añadir/modificar una cuenta bancaria
         {
            if( isset($_POST['codcuenta']) )
            {
               $cuentab = $this->cuenta_banco->get($_POST['codcuenta']);
            }
            else
            {
               $cuentab = new cuenta_banco_proveedor();
               $cuentab->codproveedor = $this->proveedor->codproveedor;
            }
            
            $cuentab->descripcion = $_POST['descripcion'];
            $cuentab->iban = $_POST['iban'];
            $cuentab->swift = $_POST['swift'];
            $cuentab->principal = isset($_POST['principal']);
            
            if( $cuentab->save() )
            {
               $this->new_message('Cuenta bancaria guardada correctamente.');
            }
            else
               $this->new_error_msg('Imposible guardar la cuenta bancaria.');
         }
         else if( isset($_POST['codproveedor']) ) /// modificar el proveedor
         {
            $this->proveedor->nombre = $_POST['nombre'];
            $this->proveedor->razonsocial = $_POST['razonsocial'];
            $this->proveedor->tipoidfiscal = $_POST['tipoidfiscal'];
            $this->proveedor->cifnif = $_POST['cifnif'];
            $this->proveedor->telefono1 = $_POST['telefono1'];
            $this->proveedor->telefono2 = $_POST['telefono2'];
            $this->proveedor->fax = $_POST['fax'];
            $this->proveedor->email = $_POST['email'];
            $this->proveedor->web = $_POST['web'];
            $this->proveedor->observaciones = $_POST['observaciones'];
            $this->proveedor->codpago = $_POST['codpago'];
            $this->proveedor->coddivisa = $_POST['coddivisa'];
            $this->proveedor->regimeniva = $_POST['regimeniva'];
            $this->proveedor->acreedor = isset($_POST['acreedor']);
            $this->proveedor->personafisica = isset($_POST['personafisica']);
            
            $this->proveedor->codserie = NULL;
            if($_POST['codserie'] != '')
            {
               $this->proveedor->codserie = $_POST['codserie'];
            }
            
            if( $this->proveedor->save() )
            {
               $this->new_message('Datos del proveedor modificados correctamente.');
            }
            else
               $this->new_error_msg('¡Imposible modificar los datos del proveedor!');
         }
      }
      else
      {
         $this->new_error_msg("¡Proveedor no encontrado!", 'error', FALSE, FALSE);
      }
   }
   
   public function url()
   {
      if( !isset($this->proveedor) )
      {
         return parent::url();
      }
      else if($this->proveedor)
      {
         return $this->proveedor->url();
      }
      else
         return $this->ppage->url();
   }
   
   /*
    * Devuelve un array con los datos estadísticos de las compras al proveedor
    * en los cinco últimos años.
    */
   public function stats_from_prov()
   {
      $stats = array();
      $years = array();
      for($i=4; $i>=0; $i--)
      {
         $years[] = intval(Date('Y')) - $i;
      }
      
      $meses = array('Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic');
      
      foreach($years as $year)
      {
         if( $year == intval(Date('Y')) )
         {
            /// año actual
            for($i = 1; $i <= intval(Date('m')); $i++)
            {
               $stats[$year.'-'.$i]['mes'] = $meses[$i-1].' '.$year;
               $stats[$year.'-'.$i]['albaranes'] = 0;
               $stats[$year.'-'.$i]['facturas'] = 0;
            }
         }
         else
         {
            /// años anteriores
            for($i = 1; $i <= 12; $i++)
            {
               $stats[$year.'-'.$i]['mes'] = $meses[$i-1].' '.$year;
               $stats[$year.'-'.$i]['albaranes'] = 0;
               $stats[$year.'-'.$i]['facturas'] = 0;
            }
         }
         
         if( strtolower(FS_DB_TYPE) == 'postgresql')
         {
            $sql_aux = "to_char(fecha,'FMMM')";
         }
         else
            $sql_aux = "DATE_FORMAT(fecha, '%m')";
         
         $sql = "SELECT ".$sql_aux." as mes, sum(neto/tasaconv) as total FROM albaranesprov"
                 ." WHERE fecha >= ".$this->empresa->var2str(Date('1-1-'.$year))
                 ." AND fecha <= ".$this->empresa->var2str(Date('31-12-'.$year))
                 ." AND codproveedor = ".$this->empresa->var2str($this->proveedor->codproveedor)
                 ." GROUP BY mes ORDER BY mes ASC;";
         
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               if( isset($stats[$year.'-'.intval($d['mes'])]['albaranes']) )
               {
                  $total = $this->euro_convert( floatval($d['total']) );
                  $stats[$year.'-'.intval($d['mes'])]['albaranes'] = number_format($total, FS_NF0, '.', '');
               }
            }
         }
         
         $sql = "SELECT ".$sql_aux." as mes, sum(neto/tasaconv) as total FROM facturasprov"
                 ." WHERE fecha >= ".$this->empresa->var2str(Date('1-1-'.$year))
                 ." AND fecha <= ".$this->empresa->var2str(Date('31-12-'.$year))
                 ." AND codproveedor = ".$this->empresa->var2str($this->proveedor->codproveedor)
                 ." GROUP BY mes ORDER BY mes ASC;";
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               if( isset($stats[$year.'-'.intval($d['mes'])]['facturas']) )
               {
                  $total = $this->euro_convert( floatval($d['total']) );
                  $stats[$year.'-'.intval($d['mes'])]['facturas'] = number_format($total, FS_NF0, '.', '');
               }
            }
         }
      }
      
      return $stats;
   }
   
   public function tiene_facturas()
   {
      $tiene = FALSE;
      
      if( $this->db->table_exists('facturasprov') )
      {
         $sql = "SELECT * FROM facturasprov WHERE codproveedor = "
                 .$this->proveedor->var2str($this->proveedor->codproveedor);
         
         $data = $this->db->select_limit($sql, 5, 0);
         if($data)
         {
            $tiene = TRUE;
         }
      }
      
      return $tiene;
   }
}
