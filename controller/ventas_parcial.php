<?php

/*
 * Copyright (C) 2016 Joe Nilson <joenilson at gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_model('almacen.php');
require_model('articulo.php');
require_model('pais.php');
require_model('asiento.php');
require_model('asiento_factura.php');
require_model('cliente.php');
require_model('ejercicio.php');
require_model('factura_cliente.php');
require_model('forma_pago.php');
require_model('partida.php');
require_model('subcuenta.php');
require_model('devolucion_parcial.php');


/**
 * Description of ventas_parcial
 *
 * @author Joe Nilson <joenilson at gmail.com>
 */
class ventas_parcial extends fs_controller
{

   public $almacen;
   public $asiento;
   public $asiento_factura;
   public $cliente;
   public $factura_cliente;
   public $factura;
   public $listado;
   public $resultados;
   public $devolucion;
   public $devolucion_parcial;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Devolucion parcial', 'ventas', FALSE, FALSE);
   }

   public function private_core()
   {
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      $this->share_extension();
      $this->factura_cliente = new factura_cliente();
      $this->devolucion_parcial = new devolucion_parcial();
      $id = \filter_input(INPUT_GET, 'id');
      $idfactura = \filter_input(INPUT_POST, 'id');
      if (!empty($id))
      {
         $this->factura = $id;
         $buscar_dev = $this->devolucion_parcial->get_devolucion($this->factura);
         $buscar_fact = $this->factura_cliente->get($this->factura);
         $factura_elegida = ($buscar_dev) ? $buscar_dev : $buscar_fact;
         $this->resultados = $factura_elegida;
         $this->devolucion = ($buscar_dev) ? TRUE : FALSE;
      } elseif (!empty($idfactura))
      {
         $factura_original = $this->factura_cliente->get($idfactura);
         $this->crear_devolucion($factura_original);
      }
   }

   private function crear_devolucion($fact)
   {
      $factura_original = $fact->idfactura;
      $fact_lineas = $fact->get_lineas();
      $fact->idfacturarect = $fact->idfactura;
      $fact->codigorect = $fact->codigo;
      $fact->numero2 = NULL;
      $fact->neto = 0;
      $fact->totaliva = 0;
      $fact->totalirpf = 0;
      $fact->totalrecargo = 0;
      $cantidad_devolucion = array();
      $monto_devolucion = array();
      /// Regresamos el stock al almacén de las cantidades ingresadas
      $art0 = new articulo();
      foreach ($fact_lineas as $key => $linea)
      {
         $dev = \filter_input(INPUT_POST, "id_" . $linea->referencia);
         $articulo = $art0->get($linea->referencia);
         if (!empty($dev) and isset($articulo))
         {
            $valor = $dev * $linea->pvpunitario;
            $articulo->sum_stock($fact->codalmacen, $dev);
            //Guardamos los valores de cantidad ingresados
            $linea->cantidad = ($dev * -1);
            $linea->pvpsindto = ($valor * -1);
            $linea->pvptotal = (($valor * (100 - $linea->dtopor) / 100) * -1);
            $fact->neto += $linea->pvptotal;
            $fact->totaliva += ($linea->pvptotal * $linea->iva / 100);
            $fact->totalirpf += ($linea->pvptotal * $linea->irpf / 100);
            $fact->totalrecargo += ($linea->pvptotal * $linea->recargo / 100);
            $cantidad_devolucion[$linea->referencia] = $dev;
            $monto_devolucion[$linea->referencia] = $valor;
         } elseif (empty($dev))
         {
            //Eliminamos lo que no devolveremos
            unset($fact_lineas[$key]);
         }
      }

      /*
       * Mantenemos los valores de la factura menos su id para no repetir toda la data
       */
      $fact->idfactura = NULL;
      $fact->codigo = NULL;
      $fact->idasiento = NULL;
      $fact->total = $fact->neto + $fact->totaliva + $fact->totalirpf + $fact->totalrecargo;
      $fact->fecha = Date('d-m-Y');
      $fact->vencimiento = Date('d-m-Y');
      $fact->codagente = $this->user->codagente;
      if ($fact->save())
      {
         $linea_factura = new linea_factura_cliente();
         /// Guardamos la información sin modificar el stock
         foreach ($fact_lineas as $linea)
         {
            $linea->idfactura = $fact->idfactura;
            $linea->idlinea = NULL;
            $linea->idalbaran = NULL;
            $linea_factura = $linea;
            $linea_factura->save();
         }
         /*
          * Generamos el asiento de venta y le agregamos el parámetro de $tipo en este caso con el valor 'inverso'
          */
         $asiento_factura = new asiento_factura();
         $asiento_factura->soloasiento = TRUE;
         if ($asiento_factura->generar_asiento_venta($fact))
         {
            $this->new_message("<a href='" . $asiento_factura->asiento->url() . "'>Asiento</a> generado correctamente.");
            $this->new_change(ucfirst(FS_FACTURA_RECTIFICATIVA).' ' . $fact->codigo, $fact->url());
            $this->new_message("Devolución ingresada correctamente, se generó la ".ucfirst(FS_FACTURA_RECTIFICATIVA).": " . $fact->codigo);
         }
         $devolucion = new devolucion_parcial();
         $lineas_devolucion = $devolucion->get_devolucion($factura_original);
         $this->resultados = ($lineas_devolucion) ? $lineas_devolucion : NULL;
         $this->devolucion = ($lineas_devolucion) ? TRUE : FALSE;
      } else
      {
         $this->new_error_msg("¡Imposible agregar la devolución a esta factura!");
      }
   }

   private function share_extension()
   {
      $extensiones = array(
         array(
            'name' => 'ventas_parcial',
            'page_from' => __CLASS__,
            'page_to' => 'ventas_factura',
            'type' => 'tab',
            'text' => '<span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span><span class="hidden-xs">&nbsp; Parciales</span>',
            'params' => ''
         )
      );
      foreach ($extensiones as $ext)
      {
         $fsext0 = new fs_extension($ext);
         if (!$fsext0->save())
         {
            $this->new_error_msg('Imposible guardar los datos de la extensión ' . $ext['name'] . '.');
         }
      }
   }

}
