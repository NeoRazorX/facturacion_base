<?php
/*
 * This file is part of FacturaSctipts
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

require_model('articulo.php');
require_model('articulo_propiedad.php');
require_model('cliente.php');
require_model('cuenta_banco.php');
require_model('divisa.php');
require_model('empresa.php');
require_model('forma_pago.php');
require_model('impuesto.php');
require_model('proveedor.php');

/**
 * Esta clase permite genera un asiento a partir de una factura.
 *
 * @author Carlos García Gómez  neorazorx@gmail.com
 */
class asiento_factura
{
   public $asiento;
   private $cuenta_banco;
   private $divisa;
   private $ejercicio;
   private $empresa;
   private $forma_pago;
   private $impuestos;
   private $subcuenta;
   
   public $messages;
   public $errors;
   public $soloasiento;
   
   public function __construct()
   {
      $this->asiento = FALSE;
      $this->cuenta_banco = new cuenta_banco();
      $this->divisa = new divisa();
      $this->ejercicio = new ejercicio();
      $this->empresa = new empresa();
      $this->forma_pago = new forma_pago();
      $this->subcuenta = new subcuenta();
      
      $impuesto = new impuesto();
      $this->impuestos = array();
      foreach( $impuesto->all() as $imp )
      {
         $this->impuestos[$imp->codimpuesto] = $imp;
      }
      
      $this->messages = array();
      $this->errors = array();
      $this->soloasiento = FALSE;
   }
   
   private function new_message($msg)
   {
      $this->messages[] = $msg;
   }
   
   private function new_error_msg($msg)
   {
      $this->errors[] = $msg;
   }
   
   /**
    * Genera el asiento contable para una factura de compra.
    * Devuelve TRUE si el asiento se ha generado correctamente, False en caso contrario.
    * Si genera el asiento, este es accesible desde $this->asiento.
    * @param factura_proveedor $factura
    */
   public function generar_asiento_compra(&$factura)
   {
      $ok = FALSE;
      $this->asiento = FALSE;
      $proveedor0 = new proveedor();
      $subcuenta_prov = FALSE;
      
      /// obtenemos las tasas de conversión, para las ocasiones en que la factura está en otra divisa
      $tasaconv = 1;
      $tasaconv2 = $factura->tasaconv;
      if($factura->coddivisa != $this->empresa->coddivisa)
      {
         $divisa = $this->divisa->get($this->empresa->coddivisa);
         if($divisa)
         {
            $tasaconv = $divisa->tasaconv_compra/$factura->tasaconv;
            $tasaconv2 = $divisa->tasaconv_compra;
         }
      }
      
      /// obtenemos el proveedor de la factura y su subcuenta
      $proveedor = $proveedor0->get($factura->codproveedor);
      if($proveedor)
      {
         $subcuenta_prov = $proveedor->get_subcuenta($factura->codejercicio);
      }
      
      if( !$subcuenta_prov )
      {
         $eje0 = $this->ejercicio->get( $factura->codejercicio );
         $this->new_message("No se ha podido generar una subcuenta para el proveedor
            <a href='".$eje0->url()."'>¿Has importado los datos del ejercicio?</a>");
         
         if(!$this->soloasiento)
         {
            $this->new_message("Aun así la <a href='".$factura->url()."'>factura</a> se ha generado correctamente,
            pero sin asiento contable.");
         }
      }
      else
      {
         $asiento = new asiento();
         $asiento->codejercicio = $factura->codejercicio;
         
         if($factura->idfacturarect)
         {
            $asiento->concepto = ucfirst(FS_FACTURA_RECTIFICATIVA)." de ".$factura->codigorect." (compras) - ".$factura->nombre;
         }
         else
         {
            $asiento->concepto = "Factura de compra ".$factura->codigo." - ".$factura->nombre;
         }
         
         $asiento->documento = $factura->codigo;
         $asiento->editable = FALSE;
         $asiento->fecha = $factura->fecha;
         $asiento->importe = abs($factura->total*$tasaconv);
         $asiento->tipodocumento = "Factura de proveedor";
         if( $asiento->save() )
         {
            $asiento_correcto = TRUE;
            $partida0 = new partida();
            $partida0->idasiento = $asiento->idasiento;
            $partida0->concepto = $asiento->concepto;
            $partida0->idsubcuenta = $subcuenta_prov->idsubcuenta;
            $partida0->codsubcuenta = $subcuenta_prov->codsubcuenta;
            $partida0->haber = $factura->total*$tasaconv;
            $partida0->coddivisa = $this->empresa->coddivisa;
            $partida0->tasaconv = $tasaconv2;
            $partida0->codserie = $factura->codserie;
            if( !$partida0->save() )
            {
               $asiento_correcto = FALSE;
               $this->new_error_msg("¡Imposible generar la partida para la subcuenta ".$partida0->codsubcuenta."!");
            }
            
            /// generamos una partida por cada impuesto
            foreach($factura->get_lineas_iva() as $li)
            {
               $subcuenta_iva = FALSE;
               
               /// ¿El impuesto tiene una subcuenta específica?
               if( isset($this->impuestos[$li->codimpuesto]) )
               {
                  if($this->impuestos[$li->codimpuesto]->codsubcuentasop)
                  {
                     $subcuenta_iva = $this->subcuenta->get_by_codigo($this->impuestos[$li->codimpuesto]->codsubcuentasop, $asiento->codejercicio);
                  }
               }
               
               if(!$subcuenta_iva)
               {
                  $subcuenta_iva = $this->subcuenta->get_cuentaesp('IVASOP', $asiento->codejercicio);
               }
               
               if($li->totaliva == 0 AND $li->totalrecargo == 0)
               {
                  /// no hacemos nada si no hay IVA ni RE
               }
               else if($subcuenta_iva AND $asiento_correcto)
               {
                  $partida1 = new partida();
                  $partida1->idasiento = $asiento->idasiento;
                  $partida1->concepto = $asiento->concepto;
                  $partida1->idsubcuenta = $subcuenta_iva->idsubcuenta;
                  $partida1->codsubcuenta = $subcuenta_iva->codsubcuenta;
                  $partida1->debe = $li->totaliva*$tasaconv;
                  $partida1->idcontrapartida = $subcuenta_prov->idsubcuenta;
                  $partida1->codcontrapartida = $subcuenta_prov->codsubcuenta;
                  $partida1->cifnif = $proveedor->cifnif;
                  $partida1->documento = $asiento->documento;
                  $partida1->tipodocumento = $asiento->tipodocumento;
                  $partida1->codserie = $factura->codserie;
                  $partida1->factura = $factura->numero;
                  $partida1->baseimponible = $li->neto*$tasaconv;
                  $partida1->iva = $li->iva;
                  $partida1->coddivisa = $this->empresa->coddivisa;
                  $partida1->tasaconv = $tasaconv2;
                  if( !$partida1->save() )
                  {
                     $asiento_correcto = FALSE;
                     $this->new_error_msg("¡Imposible generar la partida para la subcuenta ".$partida1->codsubcuenta."!");
                  }
                  
                  if($li->recargo != 0)
                  {
                     $partida11 = new partida();
                     $partida11->idasiento = $asiento->idasiento;
                     $partida11->concepto = $asiento->concepto;
                     $partida11->idsubcuenta = $subcuenta_iva->idsubcuenta;
                     $partida11->codsubcuenta = $subcuenta_iva->codsubcuenta;
                     $partida11->debe = $li->totalrecargo*$tasaconv;
                     $partida11->idcontrapartida = $subcuenta_prov->idsubcuenta;
                     $partida11->codcontrapartida = $subcuenta_prov->codsubcuenta;
                     $partida11->cifnif = $proveedor->cifnif;
                     $partida11->documento = $asiento->documento;
                     $partida11->tipodocumento = $asiento->tipodocumento;
                     $partida11->codserie = $factura->codserie;
                     $partida11->factura = $factura->numero;
                     $partida11->baseimponible = $li->neto*$tasaconv;
                     $partida11->recargo = $li->recargo;
                     $partida11->coddivisa = $this->empresa->coddivisa;
                     $partida11->tasaconv = $tasaconv2;
                     if( !$partida11->save() )
                     {
                        $asiento_correcto = FALSE;
                        $this->new_error_msg("¡Imposible generar la partida para la subcuenta ".$partida11->codsubcuenta."!");
                     }
                  }
               }
               else if(!$subcuenta_iva)
               {
                  $asiento_correcto = FALSE;
                  $this->new_error_msg('No se encuentra la subcuenta de '.FS_IVA);
               }
            }
            
            $subcuenta_compras = $this->subcuenta->get_cuentaesp('COMPRA', $asiento->codejercicio);
            if($subcuenta_compras AND $asiento_correcto)
            {
               $partida2 = new partida();
               $partida2->idasiento = $asiento->idasiento;
               $partida2->concepto = $asiento->concepto;
               $partida2->idsubcuenta = $subcuenta_compras->idsubcuenta;
               $partida2->codsubcuenta = $subcuenta_compras->codsubcuenta;
               $partida2->debe = $factura->neto*$tasaconv;
               $partida2->coddivisa = $this->empresa->coddivisa;
               $partida2->tasaconv = $tasaconv2;
               $partida2->codserie = $factura->codserie;
               if( !$partida2->save() )
               {
                  $asiento_correcto = FALSE;
                  $this->new_error_msg("¡Imposible generar la partida para la subcuenta ".$partida2->codsubcuenta."!");
               }
            }
            else if(!$subcuenta_compras)
            {
               $asiento_correcto = FALSE;
               $this->new_error_msg('No se encuentra la subcuenta de compras.');
            }
            
            /// ¿IRPF?
            if($factura->totalirpf != 0 AND $asiento_correcto)
            {
               $subcuenta_irpf = $this->subcuenta->get_cuentaesp('IRPFPR', $asiento->codejercicio);
               if($subcuenta_irpf)
               {
                  $partida3 = new partida();
                  $partida3->idasiento = $asiento->idasiento;
                  $partida3->concepto = $asiento->concepto;
                  $partida3->idsubcuenta = $subcuenta_irpf->idsubcuenta;
                  $partida3->codsubcuenta = $subcuenta_irpf->codsubcuenta;
                  $partida3->haber = $factura->totalirpf*$tasaconv;
                  $partida3->coddivisa = $this->empresa->coddivisa;
                  $partida3->tasaconv = $tasaconv2;
                  $partida3->codserie = $factura->codserie;
                  if( !$partida3->save() )
                  {
                     $asiento_correcto = FALSE;
                     $this->new_error_msg("¡Imposible generar la partida para la subcuenta ".$partida3->codsubcuenta."!");
                  }
               }
               else if(!$subcuenta_irpf)
               {
                  $asiento_correcto = FALSE;
                  $this->new_error_msg('No se encuentra la subcuenta de '.FS_IRPF);
               }
            }
            
            /// comprobamos si los artículos tienen subcuentas asociadas
            if($asiento_correcto)
            {
               $partidaA = new partida();
               $partidaA->idasiento = $asiento->idasiento;
               $partidaA->concepto = $asiento->concepto;
               $partidaA->coddivisa = $this->empresa->coddivisa;
               $partidaA->tasaconv = $tasaconv2;
               
               /// importe a restar a la partida2
               $restar = 0;
               
               /**
                * Para cada artículo de la factura, buscamos su subcuenta de compra o compra con irpf
                */
               $art0 = new articulo();
               foreach($factura->get_lineas() as $lin)
               {
                  $subcart = FALSE;
                  $articulo = $art0->get($lin->referencia);
                  if($articulo)
                  {
                     if($lin->irpf != 0)
                     {
                        $subcart = $this->subcuenta->get_by_codigo($articulo->codsubcuentairpfcom, $factura->codejercicio);
                     }
                     else if($articulo->codsubcuentacom)
                     {
                        $subcart = $this->subcuenta->get_by_codigo($articulo->codsubcuentacom, $factura->codejercicio);
                     }
                     
                     if(!$subcart)
                     {
                        /// no hay / no se encuentra ninguna subcuenta asignada al artículo
                     }
                     else if($subcart->idsubcuenta != $subcuenta_compras->idsubcuenta)
                     {
                        if( is_null($partidaA->idsubcuenta) )
                        {
                           $partidaA->idsubcuenta = $subcart->idsubcuenta;
                           $partidaA->codsubcuenta = $subcart->codsubcuenta;
                           $partidaA->debe = $lin->pvptotal*$tasaconv;
                        }
                        else if($partidaA->idsubcuenta == $subcart->idsubcuenta)
                        {
                           $partidaA->debe += $lin->pvptotal*$tasaconv;
                        }
                        else
                        {
                           $partidaA->debe = round($partidaA->debe, FS_NF0);
                           $restar += $partidaA->debe;
                           if( !$partidaA->save() )
                           {
                              $asiento_correcto = FALSE;
                              $this->new_error_msg("¡Imposible generar la partida para la subcuenta del artículo "
                                      .$lin->referencia."!");
                           }
                           
                           $partidaA = new partida();
                           $partidaA->idasiento = $asiento->idasiento;
                           $partidaA->concepto = $asiento->concepto;
                           $partidaA->idsubcuenta = $subcart->idsubcuenta;
                           $partidaA->codsubcuenta = $subcart->codsubcuenta;
                           $partidaA->debe = $lin->pvptotal*$tasaconv;
                           $partidaA->coddivisa = $this->empresa->coddivisa;
                           $partidaA->tasaconv = $tasaconv2;
                        }
                     }
                  }
               }
               
               if($partidaA->idsubcuenta AND $partidaA->codsubcuenta)
               {
                  $partidaA->debe = round($partidaA->debe, FS_NF0);
                  $restar += $partidaA->debe;
                  if( $partidaA->save() )
                  {
                     $partida2->debe -= $restar;
                     
                     if($partida2->debe == 0)
                     {
                        $partida2->delete();
                     }
                     else
                     {
                        $partida2->save();
                     }
                  }
                  else
                  {
                     $asiento_correcto = FALSE;
                     $this->new_error_msg("¡Imposible generar la partida para la subcuenta del artículo ".$lin->referencia."!");
                  }
               }
            }
            
            if($asiento_correcto)
            {
               /// si es una factura rectificativa, invertimos los importes
               if($factura->idfacturarect)
               {
                  $this->invertir_asiento($asiento);
               }
               
               $factura->idasiento = $asiento->idasiento;
               if($factura->pagada)
               {
                  $factura->idasientop = $this->generar_asiento_pago($asiento, $factura->codpago, $factura->fecha);
               }
               
               if( $factura->save() )
               {
                  $ok = $this->check_asiento($asiento);
                  if(!$ok)
                  {
                     $this->new_error_msg('El asiento está descuadrado.');
                  }
                  
                  $this->asiento = $asiento;
               }
               else
                  $this->new_error_msg("¡Imposible añadir el asiento a la factura!");
            }
            else
            {
               if( $asiento->delete() )
               {
                  $this->new_message("El asiento se ha borrado.");
               }
               else
                  $this->new_error_msg("¡Imposible borrar el asiento!");
            }
         }
      }
      
      return $ok;
   }
   
   /**
    * Genera el asiento contable para una factura de venta.
    * Devuelve TRUE si el asiento se ha generado correctamente, False en caso contrario.
    * Si genera el asiento, este es accesible desde $this->asiento.
    * @param factura_cliente $factura
    */
   public function generar_asiento_venta(&$factura)
   {
      $ok = FALSE;
      $this->asiento = FALSE;
      $cliente0 = new cliente();
      $subcuenta_cli = FALSE;
      
      /// obtenemos las tasas de conversión, para las ocasiones en que la factura está en otra divisa
      $tasaconv = 1;
      $tasaconv2 = $factura->tasaconv;
      if($factura->coddivisa != $this->empresa->coddivisa)
      {
         $divisa = $this->divisa->get($this->empresa->coddivisa);
         if($divisa)
         {
            $tasaconv = $divisa->tasaconv/$factura->tasaconv;
            $tasaconv2 = $divisa->tasaconv_compra;
         }
      }
      
      /// obtenemos el clientes y su subcuenta
      $cliente = $cliente0->get($factura->codcliente);
      if($cliente)
      {
         $subcuenta_cli = $cliente->get_subcuenta($factura->codejercicio);
      }
      
      if( !$subcuenta_cli )
      {
         $eje0 = $this->ejercicio->get($factura->codejercicio);
         $this->new_message("No se ha podido generar una subcuenta para el cliente
            <a href='".$eje0->url()."'>¿Has importado los datos del ejercicio?</a>");
         
         if(!$this->soloasiento)
         {
            $this->new_message("Aun así la <a href='".$factura->url()."'>factura</a> se ha generado correctamente,
            pero sin asiento contable.");
         }
      }
      else
      {
         $asiento = new asiento();
         $asiento->codejercicio = $factura->codejercicio;
         
         if($factura->idfacturarect)
         {
            $asiento->concepto = ucfirst(FS_FACTURA_RECTIFICATIVA)." de ".$factura->codigo." (ventas) - ".$factura->nombrecliente;
         }
         else
         {
            $asiento->concepto = "Factura de venta ".$factura->codigo." - ".$factura->nombrecliente;
         }
         
         $asiento->documento = $factura->codigo;
         $asiento->editable = FALSE;
         $asiento->fecha = $factura->fecha;
         $asiento->importe = abs($factura->total*$tasaconv);
         $asiento->tipodocumento = 'Factura de cliente';
         if( $asiento->save() )
         {
            $asiento_correcto = TRUE;
            $partida0 = new partida();
            $partida0->idasiento = $asiento->idasiento;
            $partida0->concepto = $asiento->concepto;
            $partida0->idsubcuenta = $subcuenta_cli->idsubcuenta;
            $partida0->codsubcuenta = $subcuenta_cli->codsubcuenta;
            $partida0->debe = $factura->total*$tasaconv;
            $partida0->coddivisa = $this->empresa->coddivisa;
            $partida0->tasaconv = $tasaconv2;
            $partida0->codserie = $factura->codserie;
            if( !$partida0->save() )
            {
               $asiento_correcto = FALSE;
               $this->new_error_msg("¡Imposible generar la partida para la subcuenta ".$partida0->codsubcuenta."!");
            }
            
            /// generamos una partida por cada impuesto
            foreach($factura->get_lineas_iva() as $li)
            {
               $subcuenta_iva = FALSE;
               
               /// ¿El impuesto tiene una subcuenta específica?
               if( isset($this->impuestos[$li->codimpuesto]) )
               {
                  if($this->impuestos[$li->codimpuesto]->codsubcuentarep)
                  {
                     $subcuenta_iva = $this->subcuenta->get_by_codigo($this->impuestos[$li->codimpuesto]->codsubcuentarep, $asiento->codejercicio);
                  }
               }
               
               if(!$subcuenta_iva)
               {
                  $subcuenta_iva = $this->subcuenta->get_cuentaesp('IVAREP', $asiento->codejercicio);
               }
               
               if($li->totaliva == 0 AND $li->totalrecargo == 0)
               {
                  /// no hacemos nada si no hay IVA ni RE
               }
               else if($subcuenta_iva AND $asiento_correcto)
               {
                  $partida1 = new partida();
                  $partida1->idasiento = $asiento->idasiento;
                  $partida1->concepto = $asiento->concepto;
                  $partida1->idsubcuenta = $subcuenta_iva->idsubcuenta;
                  $partida1->codsubcuenta = $subcuenta_iva->codsubcuenta;
                  $partida1->haber = $li->totaliva*$tasaconv;
                  $partida1->idcontrapartida = $subcuenta_cli->idsubcuenta;
                  $partida1->codcontrapartida = $subcuenta_cli->codsubcuenta;
                  $partida1->cifnif = $cliente->cifnif;
                  $partida1->documento = $asiento->documento;
                  $partida1->tipodocumento = $asiento->tipodocumento;
                  $partida1->codserie = $factura->codserie;
                  $partida1->factura = $factura->numero;
                  $partida1->baseimponible = $li->neto*$tasaconv;
                  $partida1->iva = $li->iva;
                  $partida1->coddivisa = $this->empresa->coddivisa;
                  $partida1->tasaconv = $tasaconv2;
                  if( !$partida1->save() )
                  {
                     $asiento_correcto = FALSE;
                     $this->new_error_msg("¡Imposible generar la partida para la subcuenta ".$partida1->codsubcuenta."!");
                  }
                  
                  if($li->recargo != 0)
                  {
                     $partida11 = new partida();
                     $partida11->idasiento = $asiento->idasiento;
                     $partida11->concepto = $asiento->concepto;
                     $partida11->idsubcuenta = $subcuenta_iva->idsubcuenta;
                     $partida11->codsubcuenta = $subcuenta_iva->codsubcuenta;
                     $partida11->haber = $li->totalrecargo*$tasaconv;
                     $partida11->idcontrapartida = $subcuenta_cli->idsubcuenta;
                     $partida11->codcontrapartida = $subcuenta_cli->codsubcuenta;
                     $partida11->cifnif = $cliente->cifnif;
                     $partida11->documento = $asiento->documento;
                     $partida11->tipodocumento = $asiento->tipodocumento;
                     $partida11->codserie = $factura->codserie;
                     $partida11->factura = $factura->numero;
                     $partida11->baseimponible = $li->neto*$tasaconv;
                     $partida11->recargo = $li->recargo;
                     $partida11->coddivisa = $this->empresa->coddivisa;
                     $partida11->tasaconv = $tasaconv2;
                     if( !$partida11->save() )
                     {
                        $asiento_correcto = FALSE;
                        $this->new_error_msg("¡Imposible generar la partida para la subcuenta ".$partida11->codsubcuenta."!");
                     }
                  }
               }
               else if(!$subcuenta_iva)
               {
                  $asiento_correcto = FALSE;
                  $this->new_error_msg('No se encuentra la subcuenta de '.FS_IVA);
               }
            }
            
            $subcuenta_ventas = $this->subcuenta->get_cuentaesp('VENTAS', $asiento->codejercicio);
            if($subcuenta_ventas AND $asiento_correcto)
            {
               $partida2 = new partida();
               $partida2->idasiento = $asiento->idasiento;
               $partida2->concepto = $asiento->concepto;
               $partida2->idsubcuenta = $subcuenta_ventas->idsubcuenta;
               $partida2->codsubcuenta = $subcuenta_ventas->codsubcuenta;
               $partida2->haber = $factura->neto*$tasaconv;
               $partida2->coddivisa = $this->empresa->coddivisa;
               $partida2->tasaconv = $tasaconv2;
               $partida2->codserie = $factura->codserie;
               if( !$partida2->save() )
               {
                  $asiento_correcto = FALSE;
                  $this->new_error_msg("¡Imposible generar la partida para la subcuenta ".$partida2->codsubcuenta."!");
               }
            }
            else if(!$subcuenta_ventas)
            {
               $asiento_correcto = FALSE;
               $this->new_error_msg('No se encuentra la subcuenta de ventas');
            }
            
            /// ¿IRPF?
            if($factura->totalirpf != 0 AND $asiento_correcto)
            {
               $subcuenta_irpf = $this->subcuenta->get_cuentaesp('IRPF', $asiento->codejercicio);
               
               if(!$subcuenta_irpf)
               {
                  $subcuenta_irpf = $this->subcuenta->get_by_codigo('4730000000', $asiento->codejercicio);
               }
               
               if($subcuenta_irpf)
               {
                  $partida3 = new partida();
                  $partida3->idasiento = $asiento->idasiento;
                  $partida3->concepto = $asiento->concepto;
                  $partida3->idsubcuenta = $subcuenta_irpf->idsubcuenta;
                  $partida3->codsubcuenta = $subcuenta_irpf->codsubcuenta;
                  $partida3->debe = $factura->totalirpf*$tasaconv;
                  $partida3->coddivisa = $this->empresa->coddivisa;
                  $partida3->tasaconv = $tasaconv2;
                  $partida3->codserie = $factura->codserie;
                  if( !$partida3->save() )
                  {
                     $asiento_correcto = FALSE;
                     $this->new_error_msg("¡Imposible generar la partida para la subcuenta ".$partida3->codsubcuenta."!");
                  }
               }
               else if(!$subcuenta_irpf)
               {
                  $asiento_correcto = FALSE;
                  $this->new_error_msg('No se encuentra la subcuenta de '.FS_IRPF);
               }
            }
            
            /// comprobamos si algún artículo tiene una subcuenta asociada
            if($asiento_correcto)
            {
               $partidaA = new partida();
               $partidaA->idasiento = $asiento->idasiento;
               $partidaA->concepto = $asiento->concepto;
               $partidaA->coddivisa = $this->empresa->coddivisa;
               $partidaA->tasaconv = $tasaconv2;
               
               /// importe a restar a la partida2
               $restar = 0;
               
               /**
                * Para cada artículo de la factura, buscamos su subcuenta de compra o compra con irpf
                */
               $ap = new articulo_propiedad();
               foreach($factura->get_lineas() as $lin)
               {
                  $subcart = FALSE;
                  $aprops = $ap->array_get($lin->referencia);
                  
                  if( isset($aprops['codsubcuentaventa']) )
                  {
                     $subcart = $this->subcuenta->get_by_codigo($aprops['codsubcuentaventa'], $factura->codejercicio);
                  }
                  
                  if(!$subcart)
                  {
                     /// no hay / no se encuentra ninguna subcuenta asignada al artículo
                  }
                  else if($subcart->idsubcuenta != $subcuenta_ventas->idsubcuenta)
                  {
                     if( is_null($partidaA->idsubcuenta) )
                     {
                        $partidaA->idsubcuenta = $subcart->idsubcuenta;
                        $partidaA->codsubcuenta = $subcart->codsubcuenta;
                        $partidaA->haber = $lin->pvptotal*$tasaconv;
                     }
                     else if($partidaA->idsubcuenta == $subcart->idsubcuenta)
                     {
                        $partidaA->haber += $lin->pvptotal*$tasaconv;
                     }
                     else
                     {
                        $partidaA->haber = round($partidaA->haber, FS_NF0);
                        $restar += $partidaA->haber;
                        if( !$partidaA->save() )
                        {
                           $asiento_correcto = FALSE;
                           $this->new_error_msg("¡Imposible generar la partida para la subcuenta del artículo "
                                   .$lin->referencia."!");
                        }
                        
                        $partidaA = new partida();
                        $partidaA->idasiento = $asiento->idasiento;
                        $partidaA->concepto = $asiento->concepto;
                        $partidaA->idsubcuenta = $subcart->idsubcuenta;
                        $partidaA->codsubcuenta = $subcart->codsubcuenta;
                        $partidaA->haber = $lin->pvptotal*$tasaconv;
                        $partidaA->coddivisa = $this->empresa->coddivisa;
                        $partidaA->tasaconv = $tasaconv2;
                     }
                  }
               }
               
               if($partidaA->idsubcuenta AND $partidaA->codsubcuenta)
               {
                  $partidaA->haber = round($partidaA->haber, FS_NF0);
                  $restar += $partidaA->haber;
                  if( $partidaA->save() )
                  {
                     $partida2->haber -= $restar;
                     
                     if($partida2->haber == 0)
                     {
                        $partida2->delete();
                     }
                     else
                     {
                        $partida2->save();
                     }
                  }
                  else
                  {
                     $asiento_correcto = FALSE;
                     $this->new_error_msg("¡Imposible generar la partida para la subcuenta del artículo "
                             .$lin->referencia."!");
                  }
               }
            }
            
            if($asiento_correcto)
            {
               /// si es una factura rectificativa, invertimos los importes
               if($factura->idfacturarect)
               {
                  $this->invertir_asiento($asiento);
               }
               
               $factura->idasiento = $asiento->idasiento;
               if($factura->pagada)
               {
                  $factura->idasientop = $this->generar_asiento_pago($asiento, $factura->codpago, $factura->fecha);
               }
               
               if( $factura->save() )
               {
                  $ok = $this->check_asiento($asiento);
                  if(!$ok)
                  {
                     $this->new_error_msg('El asiento está descuadrado.');
                  }
                  
                  $this->asiento = $asiento;
               }
               else
                  $this->new_error_msg("¡Imposible añadir el asiento a la factura!");
            }
            else
            {
               if( $asiento->delete() )
               {
                  $this->new_message("El asiento se ha borrado.");
               }
               else
                  $this->new_error_msg("¡Imposible borrar el asiento!");
            }
         }
         else
         {
            $this->new_error_msg("¡Imposible guardar el asiento!");
         }
      }
      
      return $ok;
   }
   
   /**
    * Generamos un asiento de pago del asiento seleccionado.
    * @param asiento $asiento
    */
   public function generar_asiento_pago(&$asiento, $codpago=FALSE, $fecha=FALSE, $subclipro=FALSE)
   {
      $nasientop = new asiento();
      $nasientop->editable = FALSE;
      $nasientop->importe = $asiento->importe;
      $nasientop->tipodocumento = $asiento->tipodocumento;
      $nasientop->documento = $asiento->documento;
      
      if($asiento->tipodocumento == 'Factura de cliente')
      {
         $nasientop->concepto = 'Cobro '.$asiento->concepto;
      }
      else
      {
         $nasientop->concepto = 'Pago '.$asiento->concepto;
      }
      
      if($fecha)
      {
         $nasientop->fecha = $fecha;
      }
      
      /// asignamos la mejor fecha
      $eje = $this->ejercicio->get_by_fecha($nasientop->fecha);
      if($eje)
      {
         $nasientop->codejercicio = $eje->codejercicio;
         $nasientop->fecha = $eje->get_best_fecha($nasientop->fecha);
      }
      
      /// necesitamos la subcuenta de caja
      $subcaja = $this->subcuenta->get_cuentaesp('CAJA', $nasientop->codejercicio);
      if($codpago)
      {
         /**
          * Si nos han pasado una forma de pago, intentamos buscar la subcuenta
          * asociada a la cuenta bancaria.
          */
         $formap = $this->forma_pago->get($codpago);
         if($formap)
         {
            if($formap->codcuenta)
            {
               $cuentab = $this->cuenta_banco->get($formap->codcuenta);
               if($cuentab)
               {
                  $subc = $this->subcuenta->get_by_codigo($cuentab->codsubcuenta, $nasientop->codejercicio);
                  if($subc)
                  {
                     $subcaja = $subc;
                  }
               }
            }
         }
      }
      
      if(!$eje)
      {
         $this->new_error_msg('Ningún ejercico encontrado.');
      }
      else if( !$eje->abierto() )
      {
         $this->new_error_msg('El ejercicio '.$eje->codejercicio.' está cerrado.');
      }
      else if(!$subcaja)
      {
         $this->new_error_msg('No se ha encontrado ninguna subcuenta de caja para el ejercicio '
                 .$eje->codejercicio.'. <a href="'.$eje->url().'">¿Has importado los datos del ejercicio?</a>');
      }
      else if( $nasientop->save() )
      {
         /// buscamos la partida que coincida con el importe
         $encontrada = FALSE;
         foreach($asiento->get_partidas() as $par)
         {
            if( $nasientop->floatcmp( abs($par->debe), $nasientop->importe, FS_NF0) )
            {
               if(!$subclipro)
               {
                  $subclipro = $this->subcuenta->get_by_codigo($par->codsubcuenta, $nasientop->codejercicio);
               }
               
               if($subclipro)
               {
                  $partida1 = new partida();
                  $partida1->idasiento = $nasientop->idasiento;
                  $partida1->concepto = $nasientop->concepto;
                  $partida1->idsubcuenta = $subclipro->idsubcuenta;
                  $partida1->codsubcuenta = $subclipro->codsubcuenta;
                  $partida1->haber = $par->debe;
                  $partida1->coddivisa = $par->coddivisa;
                  $partida1->tasaconv = $par->tasaconv;
                  $partida1->codserie = $par->codserie;
                  $partida1->save();
                  
                  $partida2 = new partida();
                  $partida2->idasiento = $nasientop->idasiento;
                  $partida2->concepto = $nasientop->concepto;
                  $partida2->idsubcuenta = $subcaja->idsubcuenta;
                  $partida2->codsubcuenta = $subcaja->codsubcuenta;
                  $partida2->debe = $par->debe;
                  $partida2->coddivisa = $par->coddivisa;
                  $partida2->tasaconv = $par->tasaconv;
                  $partida2->codserie = $par->codserie;
                  $partida2->save();
                  $encontrada = TRUE;
               }
               else
               {
                  $this->new_error_msg('No se ha encontrado la subcuenta '.$par->codsubcuenta
                          .' en el ejercicio '.$nasientop->codejercicio);
                  $nasientop->delete();
               }
               break;
            }
            else if( $nasientop->floatcmp( abs($par->haber), $nasientop->importe, FS_NF0) )
            {
               if(!$subclipro)
               {
                  $subclipro = $this->subcuenta->get_by_codigo($par->codsubcuenta, $nasientop->codejercicio);
               }
               
               if($subclipro)
               {
                  $partida1 = new partida();
                  $partida1->idasiento = $nasientop->idasiento;
                  $partida1->concepto = $nasientop->concepto;
                  $partida1->idsubcuenta = $subclipro->idsubcuenta;
                  $partida1->codsubcuenta = $subclipro->codsubcuenta;
                  $partida1->debe = $par->haber;
                  $partida1->coddivisa = $par->coddivisa;
                  $partida1->tasaconv = $par->tasaconv;
                  $partida1->codserie = $par->codserie;
                  $partida1->save();
                  
                  $partida2 = new partida();
                  $partida2->idasiento = $nasientop->idasiento;
                  $partida2->concepto = $nasientop->concepto;
                  $partida2->idsubcuenta = $subcaja->idsubcuenta;
                  $partida2->codsubcuenta = $subcaja->codsubcuenta;
                  $partida2->haber = $par->haber;
                  $partida2->coddivisa = $par->coddivisa;
                  $partida2->tasaconv = $par->tasaconv;
                  $partida2->codserie = $par->codserie;
                  $partida2->save();
                  $encontrada = TRUE;
               }
               else
               {
                  $this->new_error_msg('No se ha encontrado la subcuenta '.$par->codsubcuenta
                          .' en el ejercicio '.$nasientop->codejercicio);
                  $nasientop->delete();
               }
               break;
            }
         }
         
         if(!$encontrada)
         {
            $this->new_error_msg('No se ha encontrado la partida necesaria para generar el asiento '.$nasientop->concepto);
            $nasientop->delete();
            $nasientop->idasiento = NULL;
         }
      }
      else
      {
         $this->new_error_msg('Error al guardar el asiento de pago.');
      }
      
      return $nasientop->idasiento;
   }
   
   /**
    * Invierte los valores debe/haber de las líneas del asiento
    * @param asiento $asiento
    */
   public function invertir_asiento(&$asiento)
   {
      foreach($asiento->get_partidas() as $part)
      {
         $debe = abs($part->debe);
         $haber = abs($part->haber);
         
         $part->debe = $haber;
         $part->haber = $debe;
         $part->baseimponible = abs($part->baseimponible);
         $part->save();
      }
   }
   
   /**
    * Comprueba la validez de un asiento contable
    * @param asiento $asiento
    * @return boolean
    */
   private function check_asiento($asiento)
   {
      $ok = FALSE;
      
      $debe = 0;
      $haber = 0;
      foreach($asiento->get_partidas() as $lin)
      {
         $debe += $lin->debe;
         $haber += $lin->haber;
      }
      
      if( abs($debe - $haber) < .01 )
      {
         $ok = TRUE;
      }
      
      return $ok;
   }
}
