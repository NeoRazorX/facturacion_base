<?php

/*
 * @author Carlos García Gómez      neorazorx@gmail.com
 * @copyright 2016, Carlos García Gómez. All Rights Reserved. 
 */

require_model('articulo.php');
require_model('asiento_factura.php');
require_model('factura_cliente.php');
require_model('serie.php');

/**
 * Description of ventas_factura_devolucion
 *
 * @author carlos
 */
class ventas_factura_devolucion extends fs_controller
{
   public $factura;
   public $serie;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Devoluciones de factura de venta', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->share_extension();
      
      $this->serie = new serie();
      
      $fact0 = new factura_cliente();
      $this->factura = FALSE;
      if( isset($_REQUEST['id']) )
      {
         $this->factura = $fact0->get($_REQUEST['id']);
      }
      
      if($this->factura)
      {
         if( isset($_POST['id']) )
         {
            $this->nueva_rectificativa();
         }
      }
      else
      {
         $this->new_error_msg('Factura no encontrada.');
      }
   }
   
   private function nueva_rectificativa()
   {
      $frec = clone $this->factura;
            $frec->idfactura = NULL;
            $frec->numero = NULL;
            $frec->numero2 = NULL;
            $frec->codigo = NULL;
            $frec->idasiento = NULL;
            $frec->idfacturarect = $this->factura->idfactura;
            $frec->codigorect = $this->factura->codigo;
            $frec->codserie = $_POST['codserie'];
            $frec->fecha = $this->today();
            $frec->hora = $this->hour();
            $frec->observaciones = $_POST['motivo'];
            
            $frec->irpf = 0;
            $frec->neto = 0;
            $frec->total = 0;
            $frec->totalirpf = 0;
            $frec->totaliva = 0;
            $frec->totalrecargo = 0;
            
            $guardar = FALSE;
            foreach($this->factura->get_lineas() as $value)
            {
               if( isset($_POST['devolver_'.$value->idlinea]) )
               {
                  if( floatval($_POST['devolver_'.$value->idlinea]) > 0 )
                  {
                     $guardar = TRUE;
                  }
               }
            }
            
            if($guardar)
            {
               if( $frec->save() )
               {
                  $art0 = new articulo();
                  
                  foreach($this->factura->get_lineas() as $value)
                  {
                     if( isset($_POST['devolver_'.$value->idlinea]) )
                     {
                        if( floatval($_POST['devolver_'.$value->idlinea]) > 0 )
                        {
                           $linea = clone $value;
                           $linea->idlinea = NULL;
                           $linea->idfactura = $frec->idfactura;
                           $linea->idalbaran = NULL;
                           $linea->cantidad = 0 - floatval($_POST['devolver_'.$value->idlinea]);
                           $linea->pvpsindto = $linea->cantidad * $linea->pvpunitario;
                           $linea->pvptotal = $linea->cantidad * $linea->pvpunitario * (100 - $linea->dtopor) / 100;
                           if( $linea->save() )
                           {
                              $articulo = $art0->get($linea->referencia);
                              if($articulo)
                              {
                                 $articulo->sum_stock($frec->codalmacen, 0 - $linea->cantidad);
                              }
                              
                              $frec->neto += $linea->pvptotal;
                              $frec->totaliva += ($linea->pvptotal * $linea->iva/100);
                              $frec->totalirpf += ($linea->pvptotal * $linea->irpf/100);
                              $frec->totalrecargo += ($linea->pvptotal * $linea->recargo/100);
                              
                              if($linea->irpf > $frec->irpf)
                              {
                                 $frec->irpf = $linea->irpf;
                              }
                           }
                        }
                     }
                  }
                  
                  /// redondeamos
                  $frec->neto = round($frec->neto, FS_NF0);
                  $frec->totaliva = round($frec->totaliva, FS_NF0);
                  $frec->totalirpf = round($frec->totalirpf, FS_NF0);
                  $frec->totalrecargo = round($frec->totalrecargo, FS_NF0);
                  $frec->total = $frec->neto + $frec->totaliva - $frec->totalirpf + $frec->totalrecargo;
                  
                  if( $frec->save() )
                  {
                     $this->generar_asiento($frec);
                     $this->new_message(FS_FACTURA_RECTIFICATIVA.' creada correctamente.');
                  }
               }
               else
               {
                  $this->new_error_msg('Error al guardar la '.FS_FACTURA_RECTIFICATIVA);
               }
            }
            else
            {
               $this->new_advice('Todas las cantidades a devolver están a 0.');
            }
   }
   
   private function generar_asiento(&$factura)
   {
      if($this->empresa->contintegrada)
      {
         $asiento_factura = new asiento_factura();
         $asiento_factura->generar_asiento_venta($factura);
         
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
   
   private function share_extension()
   {
      $fsxet = new fs_extension();
      $fsxet->name = 'tab_devoluciones';
      $fsxet->from = __CLASS__;
      $fsxet->to = 'ventas_factura';
      $fsxet->type = 'tab';
      $fsxet->text = '<span class="glyphicon glyphicon-share" aria-hidden="true"></span>'
              . '<span class="hidden-xs">&nbsp; Devoluciones</span>';
      $fsxet->save();
   }
}
