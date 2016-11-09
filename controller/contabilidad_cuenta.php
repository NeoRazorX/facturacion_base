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

require_model('cuenta.php');
require_model('cuenta_especial.php');
require_model('ejercicio.php');
require_model('subcuenta.php');

class contabilidad_cuenta extends fs_controller
{
   public $allow_delete;
   public $cuenta;
   public $ejercicio;
   public $nuevo_codsubcuenta;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Cuenta', 'contabilidad', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $this->cuenta = FALSE;
      if( isset($_POST['nsubcuenta']) )
      {
         $subc0 = new subcuenta();
         $subc0->codcuenta = $_POST['codcuenta'];
         $subc0->codejercicio = $_POST['ejercicio'];
         $subc0->codsubcuenta = $_POST['nsubcuenta'];
         $subc0->descripcion = $_POST['descripcion'];
         $subc0->idcuenta = $_POST['idcuenta'];
         
         if( $subc0->save() )
         {
            header( 'Location: '.$subc0->url() );
         }
         else
            $this->new_error_msg('Error al crear la subcuenta.');
         
         $this->cuenta = $subc0->get_cuenta();
      }
      else if( isset($_GET['deletes']) )
      {
         $this->delete_subcuenta();
      }
      else if( isset($_GET['id']) )
      {
         $cuenta = new cuenta();
         $this->cuenta = $cuenta->get($_GET['id']);
         if($this->cuenta AND isset($_POST['descripcion']))
         {
            $this->cuenta->descripcion = $_POST['descripcion'];
            if($_POST['idcuentaesp'] == '---')
            {
               $this->cuenta->idcuentaesp = NULL;
            }
            else
               $this->cuenta->idcuentaesp = $_POST['idcuentaesp'];
            
            if( $this->cuenta->save() )
            {
               $this->new_message('Cuenta modificada correctamente.');
            }
            else
               $this->new_error_msg('Error al modificar la cuenta.');
         }
      }
      
      if($this->cuenta)
      {
         /// configuramos la página previa
         $this->ppage = $this->page->get('contabilidad_epigrafes');
         if($this->ppage)
         {
            $this->ppage->title = 'Epígrafe: '.$this->cuenta->codepigrafe;
            $this->ppage->extra_url = '&epi='.$this->cuenta->idepigrafe;
         }
         
         $this->page->title = 'Cuenta: '.$this->cuenta->codcuenta;
         $this->ejercicio = $this->cuenta->get_ejercicio();
         $this->nuevo_codsubcuenta = sprintf('%-0'.$this->ejercicio->longsubcuenta.'s', $this->cuenta->codcuenta);
      }
      else
      {
         $this->new_error_msg("Cuenta no encontrada.", 'error', FALSE, FALSE);
         $this->ppage = $this->page->get('contabilidad_cuentas');
      }
   }
   
   public function url()
   {
      if( !isset($this->cuenta) )
      {
         return parent::url();
      }
      else if($this->cuenta)
      {
         return $this->cuenta->url();
      }
      else
         return $this->page->url();
   }
   
   public function cuentas_especiales()
   {
      $cuentae = new cuenta_especial();
      return $cuentae->all();
   }
   
   private function delete_subcuenta()
   {
      $subc0 = new subcuenta();
      $subc1 = $subc0->get($_GET['deletes']);
      if($subc1)
      {
         /// cargamos la cuenta
         $this->cuenta = $subc1->get_cuenta();
         
         $bloquear = FALSE;
         $ejercicio = $this->cuenta->get_ejercicio();
         if($ejercicio)
         {
            if( !$ejercicio->abierto() )
            {
               $this->new_error_msg('No se puede eliminar la subcuenta, el ejercicio '
                       .$ejercicio->nombre.' está cerrado.');
               $bloquear = TRUE;
            }
         }
         
         if($bloquear)
         {
            /// bloqueado
         }
         else if( $subc1->delete() )
         {
            $this->new_message('Subcuenta eliminada correctamente.');
         }
         else
            $this->new_error_msg('Error al eliminar la subcuenta.');
      }
      else
         $this->new_error_msg('Subcuenta no encontrada.');
   }
}
