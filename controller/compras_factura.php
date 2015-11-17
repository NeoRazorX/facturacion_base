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

require_model('articulo.php');
require_model('asiento.php');
require_model('asiento_factura.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('factura_proveedor.php');
require_model('forma_pago.php');
require_model('partida.php');
require_model('proveedor.php');
require_model('serie.php');
require_model('subcuenta.php');

class compras_factura extends fs_controller
{
   public $agente;
   public $allow_delete;
   public $divisa;
   public $ejercicio;
   public $factura;
   public $forma_pago;
   public $mostrar_boton_pagada;
   public $proveedor;
   public $rectificada;
   public $rectificativa;
   public $serie;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Factura de proveedor', 'compras', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $this->ppage = $this->page->get('compras_facturas');
      $this->agente = FALSE;
      $this->divisa = new divisa();
      $this->ejercicio = new ejercicio();
      $factura = new factura_proveedor();
      $this->factura = FALSE;
      $this->forma_pago = new forma_pago();
      $this->proveedor = FALSE;
      $this->rectificada = FALSE;
      $this->rectificativa = FALSE;
      $this->serie = new serie();
      
      /**
       * Si hay alguna extensión de tipo config y texto no_button_pagada,
       * desactivamos el botón de pagada/sin pagar.
       */
      $this->mostrar_boton_pagada = TRUE;
      foreach($this->extensions as $ext)
      {
         if($ext->type == 'config' AND $ext->text == 'no_button_pagada')
         {
            $this->mostrar_boton_pagada = FALSE;
            break;
         }
      }
      
      if( isset($_POST['idfactura']) )
      {
         $this->factura = $factura->get($_POST['idfactura']);
         $this->modificar();
      }
      else if( isset($_GET['id']) )
      {
         $this->factura = $factura->get($_GET['id']);
      }
      
      if($this->factura)
      {
         $this->page->title = $this->factura->codigo;
         
         /// cargamos el agente
         if( !is_null($this->factura->codagente) )
         {
            $agente = new agente();
            $this->agente = $agente->get($this->factura->codagente);
         }
         
         /// cargamos el proveedor
         $proveedor = new proveedor();
         $this->proveedor = $proveedor->get($this->factura->codproveedor);
         
         if( isset($_GET['gen_asiento']) AND isset($_GET['petid']) )
         {
            if( $this->duplicated_petition($_GET['petid']) )
            {
               $this->new_error_msg('Petición duplicada. Evita hacer doble clic sobre los botones.');
            }
            else
            {
               $this->generar_asiento($this->factura);
            }
         }
         else if( isset($_REQUEST['pagada']) )
         {
            $this->factura->pagada = ($_REQUEST['pagada'] == 'TRUE');
            if( $this->factura->save() )
            {
               $this->new_message("Factura modificada correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible modificar la factura!");
         }
         else if( isset($_POST['anular']) )
         {
            $this->anular_factura();
         }
         
         if($this->factura->idfacturarect)
         {
            $this->rectificada = $factura->get($this->factura->idfacturarect);
         }
         else
         {
            $this->get_factura_rectificativa();
         }
         
         /// comprobamos la factura
         $this->factura->full_test();
      }
      else
         $this->new_error_msg("¡Factura de proveedor no encontrada!");
   }
   
   public function url()
   {
      if( !isset($this->factura) )
      {
         return parent::url();
      }
      else if($this->factura)
      {
         return $this->factura->url();
      }
      else
         return $this->page->url();
   }
   
   private function modificar()
   {
      $this->factura->numproveedor = $_POST['numproveedor'];
      $this->factura->observaciones = $_POST['observaciones'];
      $this->factura->codpago = $_POST['forma_pago'];
      $this->factura->nombre = $_POST['nombre'];
      $this->factura->cifnif = $_POST['cifnif'];
      
      /// obtenemos el ejercicio para poder acotar la fecha
      $eje0 = $this->ejercicio->get( $this->factura->codejercicio );
      if($eje0)
      {
         $this->factura->fecha = $eje0->get_best_fecha($_POST['fecha'], TRUE);
         $this->factura->hora = $_POST['hora'];
      }
      else
         $this->new_error_msg('No se encuentra el ejercicio asociado a la factura.');
      
      if( $this->factura->save() )
      {
         $asiento = $this->factura->get_asiento();
         if($asiento)
         {
            $asiento->fecha = $this->factura->fecha;
            if( !$asiento->save() )
            {
               $this->new_error_msg("Imposible modificar la fecha del asiento.");
            }
         }
         $this->new_message("Factura modificada correctamente.");
         $this->new_change('Factura Proveedor '.$this->factura->codigo, $this->factura->url());
      }
      else
         $this->new_error_msg("¡Imposible modificar la factura!");
   }
   
   private function generar_asiento(&$factura)
   {
      if( $factura->get_asiento() )
      {
         $this->new_error_msg('Ya hay un asiento asociado a esta factura.');
      }
      else
      {
         $asiento_factura = new asiento_factura();
         $asiento_factura->soloasiento = TRUE;
         if( $asiento_factura->generar_asiento_compra($factura) )
         {
            $this->new_message("<a href='".$asiento_factura->asiento->url()."'>Asiento</a> generado correctamente.");
         }
         
         foreach($asiento_factura->errors as $err)
         {
            $this->new_error_msg($err);
         }
         
         foreach($asiento_factura->messages as $msg)
         {
            $this->new_message($msg);
         }
      }
   }
   
   private function anular_factura()
   {
      /// generamos una factura rectificativa a partir de la actual
      $factura = clone $this->factura;
      $factura->idfactura = NULL;
      $factura->numero = NULL;
      $factura->numero2 = NULL;
      $factura->codigo = NULL;
      $factura->idasiento = NULL;
      
      $factura->idfacturarect = $this->factura->idfactura;
      $factura->codigorect = $this->factura->codigo;
      $factura->codserie = $_POST['codserie'];
      $factura->fecha = $this->today();
      $factura->hora = $this->hour();
      $factura->observaciones = $_POST['motivo'];
      $factura->neto = 0 - $factura->neto;
      $factura->totalirpf = 0 - $factura->totalirpf;
      $factura->totaliva = 0 - $factura->totaliva;
      $factura->totalrecargo = 0 - $factura->totalrecargo;
      $factura->total = $factura->neto + $factura->totaliva + $factura->totalrecargo - $factura->totalirpf;
      
      if( $factura->save() )
      {
         $articulo = new articulo();
         $error = FALSE;
         
         /// copiamos las líneas en negativo
         foreach($this->factura->get_lineas() as $lin)
         {
            /// actualizamos el stock
            $art = $articulo->get($lin->referencia);
            if($art)
            {
               $art->sum_stock($factura->codalmacen, $lin->cantidad);
            }
            
            $lin->idlinea = NULL;
            $lin->idalbaran = NULL;
            $lin->idfactura = $factura->idfactura;
            $lin->cantidad = 0 - $lin->cantidad;
            $lin->pvpsindto = $lin->pvpunitario * $lin->cantidad;
            $lin->pvptotal = $lin->pvpunitario * (100 - $lin->dtopor)/100 * $lin->cantidad;
            
            if( !$lin->save() )
            {
               $error = TRUE;
            }
         }
         
         if($error)
         {
            $factura->delete();
            $this->new_error_msg('Se han producido errores al crear la '.FS_FACTURA_RECTIFICATIVA);
         }
         else
         {
            $this->new_message( '<a href="'.$factura->url().'">'.ucfirst(FS_FACTURA_RECTIFICATIVA).'</a> creada correctamenmte.' );
            $this->generar_asiento($factura);
         }
      }
      else
      {
         $this->new_error_msg('Error al anular la factura.');
      }
   }
   
   private function get_factura_rectificativa()
   {
      $sql = "SELECT * FROM facturasprov WHERE idfacturarect = ".$this->factura->var2str($this->factura->idfactura);
      
      $data = $this->db->select($sql);
      if($data)
      {
         $this->rectificativa = new factura_proveedor($data[0]);
      }
   }
}
