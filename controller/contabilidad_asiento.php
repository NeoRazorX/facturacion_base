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

require_model('asiento.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('impuesto.php');
require_model('partida.php');
require_model('subcuenta.php');

class contabilidad_asiento extends fs_controller
{
   public $allow_delete;
   public $asiento;
   public $divisa;
   public $ejercicio;
   public $impuesto;
   public $lineas;
   public $resultados;
   public $subcuenta;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Asiento', 'contabilidad', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->asiento = FALSE;
      $this->ppage = $this->page->get('contabilidad_asientos');
      $this->divisa = new divisa();
      $this->ejercicio = new ejercicio();
      $this->impuesto = new impuesto();
      $this->subcuenta = new subcuenta();
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      if( isset($_GET['id']) )
      {
         $asiento = new asiento();
         $this->asiento = $asiento->get($_GET['id']);
      }
      
      if( isset($_POST['fecha']) AND isset($_POST['query']) )
      {
         $this->new_search();
      }
      else if($this->asiento)
      {
         $this->page->title = 'Asiento: '.$this->asiento->numero;
         
         if( isset($_GET['bloquear']) )
         {
            $this->asiento->editable = FALSE;
            if( $this->asiento->save() )
            {
               $this->new_message('Asiento bloqueado correctamente.');
            }
            else
               $this->new_error_msg('Imposible bloquear el asiento.');
         }
         else if( isset($_GET['desbloquear']) )
         {
            $this->asiento->editable = TRUE;
            if( $this->asiento->save() )
            {
               $this->new_message('Asiento desbloqueado correctamente.');
            }
            else
               $this->new_error_msg('Imposible desbloquear el asiento.');
         }
         
         if( isset($_POST['fecha']) AND $this->asiento->editable )
         {
            $this->modificar();
         }
         
         /// comprobamos el asiento
         $this->asiento->full_test();
         
         $this->lineas = $this->get_lineas_asiento();
      }
      else
         $this->new_error_msg("Asiento no encontrado.");
   }
   
   public function url()
   {
      if( !isset($this->asiento) )
      {
         return parent::url();
      }
      else if($this->asiento)
      {
         return $this->asiento->url();
      }
      else
         return $this->ppage->url();
   }
   
   private function new_search()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/contabilidad_nuevo_asiento';
      
      $eje0 = $this->ejercicio->get_by_fecha($_POST['fecha']);
      if($eje0)
      {
         $this->resultados = $this->subcuenta->search_by_ejercicio($eje0->codejercicio, $this->query);
      }
      else
      {
         $this->resultados = array();
      }
   }
   
   private function modificar()
   {
      /// obtenemos el ejercicio para poder acotar la fecha
      $eje0 = $this->ejercicio->get($this->asiento->codejercicio);
      if($eje0)
      {
         $this->asiento->fecha = $eje0->get_best_fecha($_POST['fecha']);
      }
      else
         $this->new_error_msg('No se encuentra el ejercicio asociado al asiento.');
      
      $this->asiento->concepto = $_POST['concepto'];
      $this->asiento->importe = floatval($_POST['importe']);
      
      /// obtenemos la divisa de las partidas
      $div0 = $this->divisa->get($_POST['divisa']);
      
      if( !$eje0 OR !$div0 )
      {
         $this->new_error_msg('Imposible modificar el asiento.');
      }
      else if( $this->asiento->save() )
      {
         $continuar = TRUE;
         $numlineas = intval($_POST['numlineas']);
         
         /// eliminamos las partidas que faltan
         foreach($this->asiento->get_partidas() as $pa)
         {
            $encontrada = FALSE;
            for($i = 1; $i <= $numlineas; $i++)
            {
               if( isset($_POST['idpartida_'.$i]) )
               {
                  if( intval($_POST['idpartida_'.$i]) == $pa->idpartida )
                  {
                     $encontrada = TRUE;
                     break;
                  }
               }
            }
            if( !$encontrada )
            {
               if( !$pa->delete() )
               {
                  $this->new_error_msg('Imposible eliminar la línea debe='.$pa->debe.' haber='.$pa->haber);
                  $continuar = FALSE;
                  break;
               }
            }
         }
         
         /// añadimos y modificamos
         $npartida = new partida();
         for($i = 1; $i <= $numlineas; $i++)
         {
            if( isset($_POST['idpartida_'.$i]) )
            {
               if($_POST['idpartida_'.$i] == '-1')
               {
                  /// las nuevas líneas llevan idpartida = -1
                  $partida = new partida();
               }
               else
               {
                  $partida = $npartida->get( $_POST['idpartida_'.$i] );
                  if( !$partida )
                  {
                     $this->new_error_msg('Partida de '.$_POST['codsubcuenta_'.$i].' no encontrada.');
                     $continuar = FALSE;
                  }
               }
               
               if($continuar)
               {
                  /// añadimos
                  $sub0 = $this->subcuenta->get_by_codigo($_POST['codsubcuenta_'.$i], $eje0->codejercicio);
                  if($sub0)
                  {
                     $partida->idasiento = $this->asiento->idasiento;
                     $partida->coddivisa = $div0->coddivisa;
                     $partida->tasaconv = $div0->tasaconv;
                     $partida->idsubcuenta = $sub0->idsubcuenta;
                     $partida->codsubcuenta = $sub0->codsubcuenta;
                     $partida->debe = floatval($_POST['debe_'.$i]);
                     $partida->haber = floatval($_POST['haber_'.$i]);
                     $partida->idconcepto = $this->asiento->idconcepto;
                     $partida->concepto = $this->asiento->concepto;
                     $partida->documento = $this->asiento->documento;
                     $partida->tipodocumento = $this->asiento->tipodocumento;
                     
                     if( isset($_POST['codcontrapartida_'.$i]) )
                     {
                        if( $_POST['codcontrapartida_'.$i] != '')
                        {
                           $subc1 = $this->subcuenta->get_by_codigo($_POST['codcontrapartida_'.$i], $eje0->codejercicio);
                           if($subc1)
                           {
                              $partida->idcontrapartida = $subc1->idsubcuenta;
                              $partida->codcontrapartida = $subc1->codsubcuenta;
                              $partida->cifnif = $_POST['cifnif_'.$i];
                              $partida->iva = floatval($_POST['iva_'.$i]);
                              $partida->baseimponible = floatval($_POST['baseimp_'.$i]);
                           }
                           else
                           {
                              $this->new_error_msg('Subcuenta '.$_POST['codcontrapartida_'.$i].' no encontrada.');
                              $continuar = FALSE;
                           }
                        }
                     }
                     
                     if( !$partida->save() )
                     {
                        $this->new_error_msg('Imposible guardar la partida de la subcuenta '.$_POST['codsubcuenta_'.$i].'.');
                        $continuar = FALSE;
                     }
                  }
                  else
                  {
                     $this->new_error_msg('Subcuenta '.$_POST['codsubcuenta_'.$i].' de la línea '.$i.' no encontrada.');
                     $continuar = FALSE;
                  }
               }
               else
                  break;
            }
         }
         
         if($continuar)
         {
            $this->new_message('Asiento modificado correctamente.');
            $this->new_change('Asiento '.$this->asiento->numero, $this->asiento->url());
         }
      }
      else
         $this->new_error_msg('Imposible modificar el asiento.');
   }
   
   private function get_lineas_asiento()
   {
      $lineas = $this->asiento->get_partidas();
      $subc = new subcuenta();
      
      foreach($lineas as $i => $lin)
      {
         $subcuenta = $subc->get($lin->idsubcuenta);
         if($subcuenta)
         {
            $lineas[$i]->desc_subcuenta = $subcuenta->descripcion;
            $lineas[$i]->saldo = $subcuenta->saldo;
         }
      }
      
      return $lineas;
   }
}
