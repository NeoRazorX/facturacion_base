<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2014-2016    Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2014         GISBEL JOSE          gpg841@gmail.com
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

require_model('cuenta_banco.php');
require_model('forma_pago.php');

class contabilidad_formas_pago extends fs_controller
{
   public $allow_delete;
   public $button_plazos;
   public $cuentas_banco;
   public $forma_pago;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Formas de Pago', 'contabilidad');
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      /**
       * Si hay alguna extensión de tipo config y texto button_plazos,
       * mostramos un botón con enlace a la página de la extensión.
       */
      $this->button_plazos = FALSE;
      foreach($this->extensions as $ext)
      {
         if($ext->type == 'config' AND $ext->text == 'button_plazos')
         {
            $this->button_plazos = $ext->from;
            break;
         }
      }
      
      $cuentab = new cuenta_banco();
      $this->cuentas_banco = $cuentab->all_from_empresa();
      
      $this->forma_pago = new forma_pago();
      
      if( isset($_POST['codpago']) )
      {
         /// crear/modificar forma de pago
         $nueva = FALSE;
         
         $fp0 = $this->forma_pago->get($_POST['codpago']);
         if(!$fp0)
         {
            $fp0 = new forma_pago();
            $fp0->codpago = $_POST['codpago'];
            $nueva = TRUE;
         }
         $fp0->descripcion = $_POST['descripcion'];
         $fp0->genrecibos = $_POST['genrecibos'];
         $fp0->vencimiento = $_POST['vencimiento'];
         
         $fp0->codcuenta = NULL;
         if($_POST['codcuenta'] != '')
         {
            $fp0->codcuenta = $_POST['codcuenta'];
         }
         
         $fp0->domiciliado = FALSE;
         if( isset($_POST['domiciliado']) )
         {
            if( is_null($fp0->codcuenta) )
            {
               $this->new_error_msg('Para marcar una forma de pago como domiciliada,'
                       . ' también tienes que seleccionar una cuenta bancaria.');
            }
            else
            {
               $fp0->domiciliado = TRUE;
            }
         }
         
         if($fp0->codpago == '' OR $fp0->descripcion == '')
         {
            $this->new_error_msg('Debes poner algún nombre a la forma de pago.');
         }
         else if( $fp0->save() )
         {
            $this->new_message('Forma de pago '.$fp0->codpago.' guardada correctamente.');
            
            if($nueva AND $this->button_plazos AND $fp0->genrecibos == 'Emitidos')
            {
               header('Location: index.php?page='.$this->button_plazos.'&cod='.$fp0->codpago.'&nueva=TRUE');
            }
         }
         else
            $this->new_error_msg('Error al guardar la forma pago.');
      }
      else if( isset($_GET['delete']) )
      {
         $fp0 = $this->forma_pago->get($_GET['delete']);
         if($fp0)
         {
            if( !$this->user->admin )
            {
               $this->new_error_msg('Sólo un administrador puede eliminar formas de pago.');
            }
            else if( $fp0->delete() )
            {
               $this->new_message('Forma de pago '.$fp0->codpago.' eliminada correctamente.');
            }
            else
               $this->new_error_msg('Error al eliminar la forma de pago '.$fp0->codpago.'.');
         }
         else
            $this->new_error_msg('Forma de pago no encontrada.');
      }
   }
   
   public function vencimientos()
   {
      return array(
          '+1day' => '1 día',
          '+1week' => '1 semana',
          '+2week' => '2 semanas',
          '+3week' => '3 semanas',
          '+1month' => '1 mes',
          '+2month' => '2 meses',
          '+3month' => '3 meses',
          '+4month' => '4 meses',
          '+5month' => '5 meses',
          '+6month' => '6 meses',
          '+7month' => '7 meses',
          '+8month' => '8 meses',
          '+9month' => '9 meses',
          '+10month' => '10 meses',
          '+11month' => '11 meses',
          '+12month' => '12 meses',
      );
   }
}
