<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2014-2016  Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2014-2015  Francesc Pineda Segarra  shawe.ewahs@gmail.com
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

require_model('agencia_transporte.php');
require_model('almacen.php');
require_model('articulo.php');
require_model('articulo_combinacion.php');
require_model('asiento_factura.php');
require_model('cliente.php');
require_model('divisa.php');
require_model('fabricante.php');
require_model('familia.php');
require_model('forma_pago.php');
require_model('grupo_clientes.php');
require_model('impuesto.php');
require_model('pais.php');
require_model('pedido_cliente.php');
require_model('presupuesto_cliente.php');
require_model('regularizacion_iva.php');
require_model('serie.php');
require_model('tarifa.php');

class nueva_venta extends fs_controller
{
   public $agente;
   public $almacen;
   public $articulo;
   public $cliente;
   public $cliente_s;
   public $direccion;
   public $divisa;
   public $fabricante;
   public $familia;
   public $forma_pago;
   public $grupo;
   public $impuesto;
   public $nuevocli_setup;
   public $pais;
   public $results;
   public $serie;
   public $tipo;
   public $agencia;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Nueva venta...', 'ventas', FALSE, FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->agencia = new agencia_transporte();
      $this->cliente = new cliente();
      $this->cliente_s = FALSE;
      $this->direccion = FALSE;
      $this->fabricante = new fabricante();
      $this->familia = new familia();
      $this->impuesto = new impuesto();
      $this->results = array();
      $this->grupo = new grupo_clientes();
      $this->pais = new pais();
      
      /// cargamos la configuración
      $fsvar = new fs_var();
      $this->nuevocli_setup = $fsvar->array_get(
         array(
            'nuevocli_cifnif_req' => 0,
            'nuevocli_direccion' => 0,
            'nuevocli_direccion_req' => 0,
            'nuevocli_codpostal' => 0,
            'nuevocli_codpostal_req' => 0,
            'nuevocli_pais' => 0,
            'nuevocli_pais_req' => 0,
            'nuevocli_provincia' => 0,
            'nuevocli_provincia_req' => 0,
            'nuevocli_ciudad' => 0,
            'nuevocli_ciudad_req' => 0,
            'nuevocli_telefono1' => 0,
            'nuevocli_telefono1_req' => 0,
            'nuevocli_telefono2' => 0,
            'nuevocli_telefono2_req' => 0,
            'nuevocli_codgrupo' => '',
         ),
         FALSE
      );
      
      if( isset($_REQUEST['tipo']) )
      {
         $this->tipo = $_REQUEST['tipo'];
      }
      else
      {
         foreach($this->tipos_a_guardar() as $t)
         {
            $this->tipo = $t['tipo'];
            break;
         }
      }
      
      if( isset($_REQUEST['buscar_cliente']) )
      {
         $this->buscar_cliente();
      }
      else if( isset($_REQUEST['datoscliente']) )
      {
         $this->datos_cliente();
      }
      else if( isset($_REQUEST['new_articulo']) )
      {
         $this->new_articulo();
      }
      else if($this->query != '')
      {
         $this->new_search();
      }
      else if( isset($_POST['referencia4precios']) )
      {
         $this->get_precios_articulo();
      }
      else if( isset($_POST['referencia4combi']) )
      {
         $this->get_combinaciones_articulo();
      }
      else if( isset($_POST['cliente']) )
      {
         $this->cliente_s = $this->cliente->get($_POST['cliente']);
         
         /**
          * Nuevo cliente
          */
         if( isset($_POST['nuevo_cliente']) )
         {
            if($_POST['nuevo_cliente'] != '')
            {
               $this->cliente_s = FALSE;
               if($_POST['nuevo_cifnif'] != '')
               {
                  $this->cliente_s = $this->cliente->get_by_cifnif($_POST['nuevo_cifnif']);
                  if($this->cliente_s)
                  {
                     $this->new_advice('Ya existe un cliente con ese '.FS_CIFNIF.'. Se ha seleccionado.');
                  }
               }
               
               if(!$this->cliente_s)
               {
                  $this->cliente_s = new cliente();
                  $this->cliente_s->codcliente = $this->cliente_s->get_new_codigo();
                  $this->cliente_s->nombre = $this->cliente_s->razonsocial = $_POST['nuevo_cliente'];
                  $this->cliente_s->tipoidfiscal = $_POST['nuevo_tipoidfiscal'];
                  $this->cliente_s->cifnif = $_POST['nuevo_cifnif'];
                  $this->cliente_s->personafisica = isset($_POST['personafisica']);
                  
                  if( isset($_POST['codgrupo']) )
                  {
                     if($_POST['codgrupo'] != '')
                     {
                        $this->cliente_s->codgrupo = $_POST['codgrupo'];
                     }
                  }
                  
                  if( isset($_POST['nuevo_telefono1']) )
                  {
                     $this->cliente_s->telefono1 = $_POST['nuevo_telefono1'];
                  }
                  
                  if( isset($_POST['nuevo_telefono2']) )
                  {
                     $this->cliente_s->telefono2 = $_POST['nuevo_telefono2'];
                  }
                  
                  if( $this->cliente_s->save() )
                  {
                     if($this->empresa->contintegrada)
                     {
                        /// forzamos crear la subcuenta
                        $this->cliente_s->get_subcuenta($this->empresa->codejercicio);
                     }
                     
                     $dircliente = new direccion_cliente();
                     $dircliente->codcliente = $this->cliente_s->codcliente;
                     $dircliente->codpais = $this->empresa->codpais;
                     $dircliente->provincia = $this->empresa->provincia;
                     $dircliente->ciudad = $this->empresa->ciudad;
                     
                     if( isset($_POST['nuevo_pais']) )
                     {
                        $dircliente->codpais = $_POST['nuevo_pais'];
                     }
                     
                     if( isset($_POST['nuevo_provincia']) )
                     {
                        $dircliente->provincia = $_POST['nuevo_provincia'];
                     }
                     
                     if( isset($_POST['nuevo_ciudad']) )
                     {
                        $dircliente->ciudad = $_POST['nuevo_ciudad'];
                     }
                     
                     if( isset($_POST['nuevo_codpostal']) )
                     {
                        $dircliente->codpostal = $_POST['nuevo_codpostal'];
                     }
                     
                     if( isset($_POST['nuevo_direccion']) )
                     {
                        $dircliente->direccion = $_POST['nuevo_direccion'];
                     }
                     
                     if( $dircliente->save() )
                     {
                        $this->new_message('Cliente agregado correctamente.');
                     }
                  }
                  else
                     $this->new_error_msg("¡Imposible guardar la dirección del cliente!");  
               }
            }
         }
         
         if($this->cliente_s)
         {
            foreach($this->cliente_s->get_direcciones() as $dir)
            {
               if($dir->domfacturacion)
               {
                  $this->direccion = $dir;
                  break;
               }
            }
         }
         
         if( isset($_POST['codagente']) )
         {
            $agente = new agente();
            $this->agente = $agente->get($_POST['codagente']);
         }
         else
            $this->agente = $this->user->get_agente();
         
         $this->almacen = new almacen();
         $this->serie = new serie();
         $this->forma_pago = new forma_pago();
         $this->divisa = new divisa();
         
         if( isset($_POST['tipo']) )
         {
            if($_POST['tipo'] == 'albaran')
            {
               $this->nuevo_albaran_cliente();
            }
            else if($_POST['tipo'] == 'factura')
            {
               $this->nueva_factura_cliente();
            }
            else if($_POST['tipo'] == 'presupuesto' AND class_exists('presupuesto_cliente') )
            {
               $this->nuevo_presupuesto_cliente();
            }
            else if($_POST['tipo'] == 'pedido' AND class_exists('pedido_cliente') )
            {
               $this->nuevo_pedido_cliente();
            }
            
            /// si el cliente no tiene cifnif nos guardamos el que indique
            if($this->cliente_s->cifnif == '')
            {
               $this->cliente_s->cifnif = $_POST['cifnif'];
               $this->cliente_s->save();
            }
            
            /// ¿Guardamos la dirección como nueva?
            if($_POST['coddir'] == 'nueva')
            {
               $this->direccion = new direccion_cliente();
               $this->direccion->codcliente = $this->cliente_s->codcliente;
               $this->direccion->codpais = $_POST['codpais'];
               $this->direccion->provincia = $_POST['provincia'];
               $this->direccion->ciudad = $_POST['ciudad'];
               $this->direccion->codpostal = $_POST['codpostal'];
               $this->direccion->direccion = $_POST['direccion'];
               $this->direccion->apartado = $_POST['apartado'];
               $this->direccion->save();
            }
            else if($_POST['envio_coddir'] == 'nueva')
            {
               $this->direccion = new direccion_cliente();
               $this->direccion->codcliente = $this->cliente_s->codcliente;
               $this->direccion->codpais = $_POST['envio_codpais'];
               $this->direccion->provincia = $_POST['envio_provincia'];
               $this->direccion->ciudad = $_POST['envio_ciudad'];
               $this->direccion->codpostal = $_POST['envio_codpostal'];
               $this->direccion->direccion = $_POST['envio_direccion'];
               $this->direccion->apartado = $_POST['envio_apartado'];
               $this->direccion->domfacturacion = FALSE;
               $this->direccion->domenvio = TRUE;
               $this->direccion->save();
            }
         }
      }
   }
   
   /**
    * Devuelve los tipos de documentos a guardar,
    * así para añadir tipos no hay que tocar la vista.
    * @return type
    */
   public function tipos_a_guardar()
   {
      $tipos = array();
      
      if( $this->user->have_access_to('ventas_presupuesto') AND class_exists('presupuesto_cliente') )
      {
         $tipos[] = array('tipo' => 'presupuesto', 'nombre' => ucfirst(FS_PRESUPUESTO).' para cliente');
      }
      
      if( $this->user->have_access_to('ventas_pedido') AND class_exists('pedido_cliente') )
      {
         $tipos[] = array('tipo' => 'pedido', 'nombre' => ucfirst(FS_PEDIDO).' de cliente');
      }
      
      if( $this->user->have_access_to('ventas_albaran') )
      {
         $tipos[] = array('tipo' => 'albaran', 'nombre' => ucfirst(FS_ALBARAN).' de cliente');
      }
      
      if( $this->user->have_access_to('ventas_factura') )
      {
         $tipos[] = array('tipo' => 'factura', 'nombre' => 'Factura de cliente');
      }
      
      return $tipos;
   }
   
   public function url()
   {
      return 'index.php?page='.__CLASS__.'&tipo='.$this->tipo;
   }
   
   private function buscar_cliente()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $json = array();
      foreach($this->cliente->search($_REQUEST['buscar_cliente']) as $cli)
      {
         $json[] = array('value' => $cli->razonsocial, 'data' => $cli->codcliente);
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_cliente'], 'suggestions' => $json) );
   }
   
   private function datos_cliente()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      header('Content-Type: application/json');
      echo json_encode( $this->cliente->get($_REQUEST['datoscliente']) );
   }
   
   private function new_articulo()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $art0 = new articulo();
      if($_REQUEST['referencia'] != '')
      {
         $art0->referencia = $_REQUEST['referencia'];
      }
      else
      {
         $art0->referencia = $art0->get_new_referencia();
      }
      
      if( $art0->exists() )
      {
         $this->results[] = $art0->get($_REQUEST['referencia']);
      }
      else
      {
         $art0->descripcion = $_REQUEST['descripcion'];
         $art0->codbarras = $_REQUEST['codbarras'];
         $art0->set_impuesto($_REQUEST['codimpuesto']);
         $art0->set_pvp( floatval($_REQUEST['pvp']) );
         
         $art0->secompra = isset($_POST['secompra']);
         $art0->sevende = isset($_POST['sevende']);
         $art0->nostock = isset($_POST['nostock']);
         $art0->publico = isset($_POST['publico']);
         
         if($_REQUEST['codfamilia'] != '')
         {
            $art0->codfamilia = $_REQUEST['codfamilia'];
         }
         
         if($_REQUEST['codfabricante'] != '')
         {
            $art0->codfabricante = $_REQUEST['codfabricante'];
         }
         
         if( $art0->save() )
         {
            $this->results[] = $art0;
         }
      }
      
      header('Content-Type: application/json');
      echo json_encode($this->results);
   }
   
   private function new_search()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $fsvar = new fs_var();
      $multi_almacen = $fsvar->simple_get('multi_almacen');
      $stock = new stock();
      
      $articulo = new articulo();
      $codfamilia = '';
      if( isset($_REQUEST['codfamilia']) )
      {
         $codfamilia = $_REQUEST['codfamilia'];
      }
      $codfabricante = '';
      if( isset($_REQUEST['codfabricante']) )
      {
         $codfabricante = $_REQUEST['codfabricante'];
      }
      $con_stock = isset($_REQUEST['con_stock']);
      $this->results = $articulo->search($this->query, 0, $codfamilia, $con_stock, $codfabricante);
      
      /// añadimos la busqueda, el descuento, la cantidad, etc...
      foreach($this->results as $i => $value)
      {
         $this->results[$i]->query = $this->query;
         $this->results[$i]->dtopor = 0;
         $this->results[$i]->cantidad = 1;
         $this->results[$i]->coddivisa = $this->empresa->coddivisa;
         
         /// añadimos el stock del almacén y el general
         $this->results[$i]->stockalm = $this->results[$i]->stockfis;
         if( $multi_almacen AND isset($_REQUEST['codalmacen']) )
         {
            $this->results[$i]->stockalm = $stock->total_from_articulo($this->results[$i]->referencia, $_REQUEST['codalmacen']);
         }
      }
      
      /// ejecutamos las funciones de las extensiones
      foreach($this->extensions as $ext)
      {
         if($ext->type == 'function' AND $ext->params == 'new_search')
         {
            $name = $ext->text;
            $name($this->db, $this->results);
         }
      }
      
      /// buscamos el grupo de clientes y la tarifa
      if( isset($_REQUEST['codcliente']) )
      {
         $cliente = $this->cliente->get($_REQUEST['codcliente']);
         if($cliente)
         {
            if($cliente->codgrupo)
            {
               $grupo0 = new grupo_clientes();
               $tarifa0 = new tarifa();
               
               $grupo = $grupo0->get($cliente->codgrupo);
               if($grupo)
               {
                  $tarifa = $tarifa0->get($grupo->codtarifa);
                  if($tarifa)
                  {
                     $tarifa->set_precios($this->results);
                  }
               }
            }
         }
      }
      
      /// convertimos la divisa
      if( isset($_REQUEST['coddivisa']) )
      {
         if($_REQUEST['coddivisa'] != $this->empresa->coddivisa)
         {
            foreach($this->results as $i => $value)
            {
               $this->results[$i]->coddivisa = $_REQUEST['coddivisa'];
               $this->results[$i]->pvp = $this->divisa_convert($value->pvp, $this->empresa->coddivisa, $_REQUEST['coddivisa']);
            }
         }
      }
      
      header('Content-Type: application/json');
      echo json_encode($this->results);
   }
   
   private function get_precios_articulo()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/nueva_venta_precios';
      
      $articulo = new articulo();
      $this->articulo = $articulo->get($_POST['referencia4precios']);
   }
   
   private function get_combinaciones_articulo()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/nueva_venta_combinaciones';
      
      $this->results = array();
      $comb1 = new articulo_combinacion();
      foreach($comb1->all_from_ref($_POST['referencia4combi']) as $com)
      {
         if( isset($this->results[$com->codigo]) )
         {
            $this->results[$com->codigo]['desc'] .= ', '.$com->nombreatributo.' - '.$com->valor;
            $this->results[$com->codigo]['txt'] .= ', '.$com->nombreatributo.' - '.$com->valor;
         }
         else
         {
            $this->results[$com->codigo] = array(
                'ref' => $_POST['referencia4combi'],
                'desc' => base64_decode($_POST['desc'])."\n".$com->nombreatributo.' - '.$com->valor,
                'pvp' => floatval($_POST['pvp']) + $com->impactoprecio,
                'dto' => floatval($_POST['dto']),
                'codimpuesto' => $_POST['codimpuesto'],
                'cantidad' => floatval($_POST['cantidad']),
                'txt' => $com->nombreatributo.' - '.$com->valor
            );
         }
      }
   }
   
   public function get_tarifas_articulo($ref)
   {
      $tarlist = array();
      $articulo = new articulo();
      $tarifa = new tarifa();
      
      foreach($tarifa->all() as $tar)
      {
         $art = $articulo->get($ref);
         if($art)
         {
            $art->dtopor = 0;
            $aux = array($art);
            $tar->set_precios($aux);
            $tarlist[] = $aux[0];
         }
      }
      
      return $tarlist;
   }

   private function nuevo_albaran_cliente()
   {
      $continuar = TRUE;
      
      $cliente = $this->cliente->get($_POST['cliente']);
      if(!$cliente)
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }
      
      $almacen = $this->almacen->get($_POST['almacen']);
      if($almacen)
      {
         $this->save_codalmacen($_POST['almacen']);
      }
      else
      {
         $this->new_error_msg('Almacén no encontrado.');
         $continuar = FALSE;
      }
      
      $eje0 = new ejercicio();
      $ejercicio = $eje0->get_by_fecha($_POST['fecha'], FALSE);
      if(!$ejercicio)
      {
         $this->new_error_msg('Ejercicio no encontrado.');
         $continuar = FALSE;
      }
      
      $serie = $this->serie->get($_POST['serie']);
      if(!$serie)
      {
         $this->new_error_msg('Serie no encontrada.');
         $continuar = FALSE;
      }
      
      $forma_pago = $this->forma_pago->get($_POST['forma_pago']);
      if($forma_pago)
      {
         $this->save_codpago($_POST['forma_pago']);
      }
      else
      {
         $this->new_error_msg('Forma de pago no encontrada.');
         $continuar = FALSE;
      }
      
      $divisa = $this->divisa->get($_POST['divisa']);
      if(!$divisa)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      $albaran = new albaran_cliente();
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="'.$albaran->url().'">'.FS_ALBARANES.'</a>
               para ver si el '.FS_ALBARAN.' se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $albaran->fecha = $_POST['fecha'];
         $albaran->hora = $_POST['hora'];
         $albaran->codalmacen = $almacen->codalmacen;
         $albaran->codejercicio = $ejercicio->codejercicio;
         $albaran->codserie = $serie->codserie;
         $albaran->codpago = $forma_pago->codpago;
         $albaran->coddivisa = $divisa->coddivisa;
         $albaran->tasaconv = $divisa->tasaconv;
         
         if($_POST['tasaconv'] != '')
         {
            $albaran->tasaconv = floatval($_POST['tasaconv']);
         }
         
         $albaran->codagente = $this->agente->codagente;
         $albaran->numero2 = $_POST['numero2'];
         $albaran->observaciones = $_POST['observaciones'];
         $albaran->porcomision = $this->agente->porcomision;
         
         $albaran->codcliente = $cliente->codcliente;
         $albaran->cifnif = $_POST['cifnif'];
         $albaran->nombrecliente = $_POST['nombrecliente'];
         $albaran->codpais = $_POST['codpais'];
         $albaran->provincia = $_POST['provincia'];
         $albaran->ciudad = $_POST['ciudad'];
         $albaran->codpostal = $_POST['codpostal'];
         $albaran->direccion = $_POST['direccion'];
         $albaran->apartado = $_POST['apartado'];
         
         /// envío
         $albaran->envio_nombre = $_POST['envio_nombre'];
         $albaran->envio_apellidos = $_POST['envio_apellidos'];
         if($_POST['envio_codtrans'] != '')
         {
            $albaran->envio_codtrans = $_POST['envio_codtrans'];
         }
         $albaran->envio_codigo = $_POST['envio_codigo'];
         $albaran->envio_codpais = $_POST['envio_codpais'];
         $albaran->envio_provincia = $_POST['envio_provincia'];
         $albaran->envio_ciudad = $_POST['envio_ciudad'];
         $albaran->envio_codpostal = $_POST['envio_codpostal'];
         $albaran->envio_direccion = $_POST['envio_direccion'];
         $albaran->envio_apartado = $_POST['envio_apartado'];
         
         if( $albaran->save() )
         {
            $art0 = new articulo();
            $n = floatval($_POST['numlineas']);
            for($i = 0; $i <= $n; $i++)
            {
               if( isset($_POST['referencia_'.$i]) )
               {
                  $linea = new linea_albaran_cliente();
                  $linea->idalbaran = $albaran->idalbaran;
                  $linea->descripcion = $_POST['desc_'.$i];
                  
                  if( !$serie->siniva AND $cliente->regimeniva != 'Exento' )
                  {
                     $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$i]);
                     if($imp0)
                     {
                        $linea->codimpuesto = $imp0->codimpuesto;
                        $linea->iva = floatval($_POST['iva_'.$i]);
                        $linea->recargo = floatval($_POST['recargo_'.$i]);
                     }
                     else
                     {
                        $linea->iva = floatval($_POST['iva_'.$i]);
                        $linea->recargo = floatval($_POST['recargo_'.$i]);
                     }
                  }
                  
                  $linea->irpf = floatval($_POST['irpf_'.$i]);
                  $linea->pvpunitario = floatval($_POST['pvp_'.$i]);
                  $linea->cantidad = floatval($_POST['cantidad_'.$i]);
                  $linea->dtopor = floatval($_POST['dto_'.$i]);
                  $linea->pvpsindto = ($linea->pvpunitario * $linea->cantidad);
                  $linea->pvptotal = floatval($_POST['neto_'.$i]);
                  
                  $articulo = $art0->get($_POST['referencia_'.$i]);
                  if($articulo)
                  {
                     $linea->referencia = $articulo->referencia;
                  }
                  
                  if( $linea->save() )
                  {
                     if( $articulo AND isset($_POST['stock']) )
                     {
                        /// descontamos del stock
                        $articulo->sum_stock($albaran->codalmacen, 0 - $linea->cantidad);
                     }
                     
                     $albaran->neto += $linea->pvptotal;
                     $albaran->totaliva += ($linea->pvptotal * $linea->iva/100);
                     $albaran->totalirpf += ($linea->pvptotal * $linea->irpf/100);
                     $albaran->totalrecargo += ($linea->pvptotal * $linea->recargo/100);
                     
                     if($linea->irpf > $albaran->irpf)
                     {
                        $albaran->irpf = $linea->irpf;
                     }
                  }
                  else
                  {
                     $this->new_error_msg("¡Imposible guardar la linea con referencia: ".$linea->referencia);
                     $continuar = FALSE;
                  }
               }
            }
            
            if($continuar)
            {
               /// redondeamos
               $albaran->neto = round($albaran->neto, FS_NF0);
               $albaran->totaliva = round($albaran->totaliva, FS_NF0);
               $albaran->totalirpf = round($albaran->totalirpf, FS_NF0);
               $albaran->totalrecargo = round($albaran->totalrecargo, FS_NF0);
               $albaran->total = $albaran->neto + $albaran->totaliva - $albaran->totalirpf + $albaran->totalrecargo;
               
               if( abs(floatval($_POST['atotal']) - $albaran->total) >= .02 )
               {
                  $this->new_error_msg("El total difiere entre la vista y el controlador (".$_POST['atotal'].
                          " frente a ".$albaran->total."). Debes informar del error.");
                  $albaran->delete();
               }
               else if( $albaran->save() )
               {
                  $this->new_message("<a href='".$albaran->url()."'>".ucfirst(FS_ALBARAN)."</a> guardado correctamente.");
                  $this->new_change(ucfirst(FS_ALBARAN).' Cliente '.$albaran->codigo, $albaran->url(), TRUE);
                  
                  if($_POST['redir'] == 'TRUE')
                  {
                     header('Location: '.$albaran->url());
                  }
               }
               else
                  $this->new_error_msg("¡Imposible actualizar el <a href='".$albaran->url()."'>".FS_ALBARAN."</a>!");
            }
            else if( $albaran->delete() )
            {
               $this->new_message(FS_ALBARAN." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar el <a href='".$albaran->url()."'>".FS_ALBARAN."</a>!");
         }
         else
            $this->new_error_msg("¡Imposible guardar el ".FS_ALBARAN."!");
      }
   }
   
   private function nueva_factura_cliente()
   {
      $continuar = TRUE;
      
      $cliente = $this->cliente->get($_POST['cliente']);
      if(!$cliente)
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }
      
      $almacen = $this->almacen->get($_POST['almacen']);
      if($almacen)
      {
         $this->save_codalmacen($_POST['almacen']);
      }
      else
      {
         $this->new_error_msg('Almacén no encontrado.');
         $continuar = FALSE;
      }
      
      $eje0 = new ejercicio();
      $ejercicio = $eje0->get_by_fecha($_POST['fecha']);
      if(!$ejercicio)
      {
         $this->new_error_msg('Ejercicio no encontrado o está cerrado.');
         $continuar = FALSE;
      }
      
      $serie = $this->serie->get($_POST['serie']);
      if(!$serie)
      {
         $this->new_error_msg('Serie no encontrada.');
         $continuar = FALSE;
      }
      
      $forma_pago = $this->forma_pago->get($_POST['forma_pago']);
      if($forma_pago)
      {
         $this->save_codpago($_POST['forma_pago']);
      }
      else
      {
         $this->new_error_msg('Forma de pago no encontrada.');
         $continuar = FALSE;
      }
      
      $divisa = $this->divisa->get($_POST['divisa']);
      if(!$divisa)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      $factura = new factura_cliente();
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="'.$factura->url().'">Facturas</a>
               para ver si la factura se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $factura->codejercicio = $ejercicio->codejercicio;
         $factura->codserie = $serie->codserie;
         $factura->set_fecha_hora($_POST['fecha'], $_POST['hora']);
         
         $factura->codalmacen = $almacen->codalmacen;
         $factura->codpago = $forma_pago->codpago;
         $factura->coddivisa = $divisa->coddivisa;
         $factura->tasaconv = $divisa->tasaconv;
         
         if($_POST['tasaconv'] != '')
         {
            $factura->tasaconv = floatval($_POST['tasaconv']);
         }
         
         $factura->codagente = $this->agente->codagente;
         $factura->observaciones = $_POST['observaciones'];
         $factura->numero2 = $_POST['numero2'];
         $factura->porcomision = $this->agente->porcomision;
         
         if($forma_pago->genrecibos == 'Pagados')
         {
            $factura->pagada = TRUE;
         }
         
         $factura->vencimiento = Date('d-m-Y', strtotime($factura->fecha.' '.$forma_pago->vencimiento));
         
         $factura->codcliente = $cliente->codcliente;
         $factura->cifnif = $_POST['cifnif'];
         $factura->nombrecliente = $_POST['nombrecliente'];
         $factura->codpais = $_POST['codpais'];
         $factura->provincia = $_POST['provincia'];
         $factura->ciudad = $_POST['ciudad'];
         $factura->codpostal = $_POST['codpostal'];
         $factura->direccion = $_POST['direccion'];
         $factura->apartado = $_POST['apartado'];
         
         /// envío
         $factura->envio_nombre = $_POST['envio_nombre'];
         $factura->envio_apellidos = $_POST['envio_apellidos'];
         if($_POST['envio_codtrans'] != '')
         {
            $factura->envio_codtrans = $_POST['envio_codtrans'];
         }
         $factura->envio_codigo = $_POST['envio_codigo'];
         $factura->envio_codpais = $_POST['envio_codpais'];
         $factura->envio_provincia = $_POST['envio_provincia'];
         $factura->envio_ciudad = $_POST['envio_ciudad'];
         $factura->envio_codpostal = $_POST['envio_codpostal'];
         $factura->envio_direccion = $_POST['envio_direccion'];
         $factura->envio_apartado = $_POST['envio_apartado'];
         
         $regularizacion = new regularizacion_iva();
         if( $regularizacion->get_fecha_inside($factura->fecha) )
         {
            $this->new_error_msg("El ".FS_IVA." de ese periodo ya ha sido regularizado. No se pueden añadir más facturas en esa fecha.");
         }
         else if( $factura->save() )
         {
            $art0 = new articulo();
            $n = floatval($_POST['numlineas']);
            for($i = 0; $i <= $n; $i++)
            {
               if( isset($_POST['referencia_'.$i]) )
               {
                  $linea = new linea_factura_cliente();
                  $linea->idfactura = $factura->idfactura;
                  $linea->descripcion = $_POST['desc_'.$i];
                  
                  if( !$serie->siniva AND $cliente->regimeniva != 'Exento' )
                  {
                     $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$i]);
                     if($imp0)
                     {
                        $linea->codimpuesto = $imp0->codimpuesto;
                        $linea->iva = floatval($_POST['iva_'.$i]);
                        $linea->recargo = floatval($_POST['recargo_'.$i]);
                     }
                     else
                     {
                        $linea->iva = floatval($_POST['iva_'.$i]);
                        $linea->recargo = floatval($_POST['recargo_'.$i]);
                     }
                  }
                  
                  $linea->irpf = floatval($_POST['irpf_'.$i]);
                  $linea->pvpunitario = floatval($_POST['pvp_'.$i]);
                  $linea->cantidad = floatval($_POST['cantidad_'.$i]);
                  $linea->dtopor = floatval($_POST['dto_'.$i]);
                  $linea->pvpsindto = ($linea->pvpunitario * $linea->cantidad);
                  $linea->pvptotal = floatval($_POST['neto_'.$i]);
                  
                  $articulo = $art0->get($_POST['referencia_'.$i]);
                  if($articulo)
                  {
                     $linea->referencia = $articulo->referencia;
                  }
                  
                  if( $linea->save() )
                  {
                     if( $articulo AND isset($_POST['stock']) )
                     {
                        /// descontamos del stock
                        $articulo->sum_stock($factura->codalmacen, 0 - $linea->cantidad);
                     }
                     
                     $factura->neto += $linea->pvptotal;
                     $factura->totaliva += ($linea->pvptotal * $linea->iva/100);
                     $factura->totalirpf += ($linea->pvptotal * $linea->irpf/100);
                     $factura->totalrecargo += ($linea->pvptotal * $linea->recargo/100);
                     
                     if($linea->irpf > $factura->irpf)
                     {
                        $factura->irpf = $linea->irpf;
                     }
                  }
                  else
                  {
                     $this->new_error_msg("¡Imposible guardar la linea con referencia: ".$linea->referencia);
                     $continuar = FALSE;
                  }
               }
            }
            
            if($continuar)
            {
               /// redondeamos
               $factura->neto = round($factura->neto, FS_NF0);
               $factura->totaliva = round($factura->totaliva, FS_NF0);
               $factura->totalirpf = round($factura->totalirpf, FS_NF0);
               $factura->totalrecargo = round($factura->totalrecargo, FS_NF0);
               $factura->total = $factura->neto + $factura->totaliva - $factura->totalirpf + $factura->totalrecargo;
               
               if( abs(floatval($_POST['atotal']) - $factura->total) >= .02 )
               {
                  $this->new_error_msg("El total difiere entre la vista y el controlador (".$_POST['atotal'].
                          " frente a ".$factura->total."). Debes informar del error.");
                  $factura->delete();
               }
               else if( $factura->save() )
               {
                  $this->generar_asiento($factura);
                  $this->new_message("<a href='".$factura->url()."'>Factura</a> guardada correctamente.");
                  $this->new_change('Factura Cliente '.$factura->codigo, $factura->url(), TRUE);
                  
                  if($_POST['redir'] == 'TRUE')
                  {
                     header('Location: '.$factura->url());
                  }
               }
               else
                  $this->new_error_msg("¡Imposible actualizar la <a href='".$factura->url()."'>Factura</a>!");
            }
            else if( $factura->delete() )
            {
               $this->new_message("Factura eliminada correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar la <a href='".$factura->url()."'>Factura</a>!");
         }
         else
            $this->new_error_msg("¡Imposible guardar la Factura!");
      }
   }
   
   /**
    * Genera el asiento para la factura, si procede
    * @param factura_cliente $factura
    */
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
      else
      {
         /// de todas formas forzamos la generación de las líneas de iva
         $factura->get_lineas_iva();
      }
   }
   
   private function nuevo_presupuesto_cliente()
   {
      $continuar = TRUE;
      
      $cliente = $this->cliente->get($_POST['cliente']);
      if(!$cliente)
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }
      
      $almacen = $this->almacen->get($_POST['almacen']);
      if($almacen)
      {
         $this->save_codalmacen($_POST['almacen']);
      }
      else
      {
         $this->new_error_msg('Almacén no encontrado.');
         $continuar = FALSE;
      }
      
      $eje0 = new ejercicio();
      $ejercicio = $eje0->get_by_fecha($_POST['fecha'], FALSE);
      if(!$ejercicio)
      {
         $this->new_error_msg('Ejercicio no encontrado.');
         $continuar = FALSE;
      }
      
      $serie = $this->serie->get($_POST['serie']);
      if(!$serie)
      {
         $this->new_error_msg('Serie no encontrada.');
         $continuar = FALSE;
      }
      
      $forma_pago = $this->forma_pago->get($_POST['forma_pago']);
      if($forma_pago)
      {
         $this->save_codpago($_POST['forma_pago']);
      }
      else
      {
         $this->new_error_msg('Forma de pago no encontrada.');
         $continuar = FALSE;
      }
      
      $divisa = $this->divisa->get($_POST['divisa']);
      if(!$divisa)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      $presupuesto = new presupuesto_cliente();
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="'.$presupuesto->url().'">Presupuestos</a>
               para ver si el presupuesto se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $presupuesto->fecha = $_POST['fecha'];
         $presupuesto->codalmacen = $almacen->codalmacen;
         $presupuesto->codejercicio = $ejercicio->codejercicio;
         $presupuesto->codserie = $serie->codserie;
         $presupuesto->codpago = $forma_pago->codpago;
         $presupuesto->coddivisa = $divisa->coddivisa;
         $presupuesto->tasaconv = $divisa->tasaconv;
         
         /// establecemos la fecha de finoferta
         $presupuesto->finoferta = date("Y-m-d", strtotime($_POST['fecha']." +1 month"));
         $fsvar = new fs_var();
         $dias = $fsvar->simple_get('presu_validez');
         if($dias)
         {
            $presupuesto->finoferta = date("Y-m-d", strtotime($_POST['fecha']." +".intval($dias)." days"));
         }
         
         if($_POST['tasaconv'] != '')
         {
            $presupuesto->tasaconv = floatval($_POST['tasaconv']);
         }
         
         $presupuesto->codagente = $this->agente->codagente;
         $presupuesto->observaciones = $_POST['observaciones'];
         $presupuesto->numero2 = $_POST['numero2'];
         $presupuesto->porcomision = $this->agente->porcomision;
         
         $presupuesto->codcliente = $cliente->codcliente;
         $presupuesto->cifnif = $_POST['cifnif'];
         $presupuesto->nombrecliente = $_POST['nombrecliente'];
         $presupuesto->codpais = $_POST['codpais'];
         $presupuesto->provincia = $_POST['provincia'];
         $presupuesto->ciudad = $_POST['ciudad'];
         $presupuesto->codpostal = $_POST['codpostal'];
         $presupuesto->direccion = $_POST['direccion'];
         $presupuesto->apartado = $_POST['apartado'];
         
         /// envío
         $presupuesto->envio_nombre = $_POST['envio_nombre'];
         $presupuesto->envio_apellidos = $_POST['envio_apellidos'];
         if($_POST['envio_codtrans'] != '')
         {
            $presupuesto->envio_codtrans = $_POST['envio_codtrans'];
         }
         $presupuesto->envio_codigo = $_POST['envio_codigo'];
         $presupuesto->envio_codpais = $_POST['envio_codpais'];
         $presupuesto->envio_provincia = $_POST['envio_provincia'];
         $presupuesto->envio_ciudad = $_POST['envio_ciudad'];
         $presupuesto->envio_codpostal = $_POST['envio_codpostal'];
         $presupuesto->envio_direccion = $_POST['envio_direccion'];
         $presupuesto->envio_apartado = $_POST['envio_apartado'];
         
         if( $presupuesto->save() )
         {
            $art0 = new articulo();
            $n = floatval($_POST['numlineas']);
            for($i = 0; $i <= $n; $i++)
            {
               if( isset($_POST['referencia_'.$i]) )
               {
                  $linea = new linea_presupuesto_cliente();
                  $linea->idpresupuesto = $presupuesto->idpresupuesto;
                  $linea->descripcion = $_POST['desc_'.$i];
                  
                  if( !$serie->siniva AND $cliente->regimeniva != 'Exento' )
                  {
                     $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$i]);
                     if($imp0)
                     {
                        $linea->codimpuesto = $imp0->codimpuesto;
                        $linea->iva = floatval($_POST['iva_'.$i]);
                        $linea->recargo = floatval($_POST['recargo_'.$i]);
                     }
                     else
                     {
                        $linea->iva = floatval($_POST['iva_'.$i]);
                        $linea->recargo = floatval($_POST['recargo_'.$i]);
                     }
                  }
                  
                  $linea->irpf = floatval($_POST['irpf_'.$i]);
                  $linea->pvpunitario = floatval($_POST['pvp_'.$i]);
                  $linea->cantidad = floatval($_POST['cantidad_'.$i]);
                  $linea->dtopor = floatval($_POST['dto_'.$i]);
                  $linea->pvpsindto = ($linea->pvpunitario * $linea->cantidad);
                  $linea->pvptotal = floatval($_POST['neto_'.$i]);
                  
                  $articulo = $art0->get($_POST['referencia_'.$i]);
                  if($articulo)
                  {
                     $linea->referencia = $articulo->referencia;
                  }
                  
                  if( $linea->save() )
                  {
                     $presupuesto->neto += $linea->pvptotal;
                     $presupuesto->totaliva += ($linea->pvptotal * $linea->iva/100);
                     $presupuesto->totalirpf += ($linea->pvptotal * $linea->irpf/100);
                     $presupuesto->totalrecargo += ($linea->pvptotal * $linea->recargo/100);
                     
                     if($linea->irpf > $presupuesto->irpf)
                     {
                        $presupuesto->irpf = $linea->irpf;
                     }
                  }
                  else
                  {
                     $this->new_error_msg("¡Imposible guardar la linea con referencia: ".$linea->referencia);
                     $continuar = FALSE;
                  }
               }
            }
            
            if($continuar)
            {
               /// redondeamos
               $presupuesto->neto = round($presupuesto->neto, FS_NF0);
               $presupuesto->totaliva = round($presupuesto->totaliva, FS_NF0);
               $presupuesto->totalirpf = round($presupuesto->totalirpf, FS_NF0);
               $presupuesto->totalrecargo = round($presupuesto->totalrecargo, FS_NF0);
               $presupuesto->total = $presupuesto->neto + $presupuesto->totaliva - $presupuesto->totalirpf + $presupuesto->totalrecargo;
               
               if( abs(floatval($_POST['atotal']) - $presupuesto->total) >= .02)
               {
                  $this->new_error_msg("El total difiere entre el controlador y la vista (".
                          $presupuesto->total." frente a ".$_POST['atotal']."). Debes informar del error.");
                  $presupuesto->delete();
               }
               else if( $presupuesto->save() )
               {
                  $this->new_message("<a href='".$presupuesto->url()."'>".ucfirst(FS_PRESUPUESTO)."</a> guardado correctamente.");
                  $this->new_change(ucfirst(FS_PRESUPUESTO).' a Cliente '.$presupuesto->codigo, $presupuesto->url(), TRUE);
                  
                  if($_POST['redir'] == 'TRUE')
                  {
                     header('Location: '.$presupuesto->url());
                  }
               }
               else
                  $this->new_error_msg("¡Imposible actualizar el <a href='".$presupuesto->url()."'>".FS_PRESUPUESTO."</a>!");
            }
            else if( $presupuesto->delete() )
            {
               $this->new_message(ucfirst(FS_PRESUPUESTO)." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar el <a href='".$presupuesto->url()."'>".FS_PRESUPUESTO."</a>!");
         }
         else
            $this->new_error_msg("¡Imposible guardar el ".FS_PRESUPUESTO."!");
      }
   }
   
   private function nuevo_pedido_cliente()
   {
      $continuar = TRUE;
      
      $cliente = $this->cliente->get($_POST['cliente']);
      if(!$cliente)
      {
         $this->new_error_msg('Cliente no encontrado.');
         $continuar = FALSE;
      }
      
      $almacen = $this->almacen->get($_POST['almacen']);
      if($almacen)
      {
         $this->save_codalmacen($_POST['almacen']);
      }
      else
      {
         $this->new_error_msg('Almacén no encontrado.');
         $continuar = FALSE;
      }
      
      $eje0 = new ejercicio();
      $ejercicio = $eje0->get_by_fecha($_POST['fecha'], FALSE);
      if(!$ejercicio)
      {
         $this->new_error_msg('Ejercicio no encontrado.');
         $continuar = FALSE;
      }
      
      $serie = $this->serie->get($_POST['serie']);
      if(!$serie)
      {
         $this->new_error_msg('Serie no encontrada.');
         $continuar = FALSE;
      }
      
      $forma_pago = $this->forma_pago->get($_POST['forma_pago']);
      if($forma_pago)
      {
         $this->save_codpago($_POST['forma_pago']);
      }
      else
      {
         $this->new_error_msg('Forma de pago no encontrada.');
         $continuar = FALSE;
      }
      
      $divisa = $this->divisa->get($_POST['divisa']);
      if(!$divisa)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      $pedido = new pedido_cliente();
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guardar
               y se han enviado dos peticiones. Mira en <a href="'.$pedido->url().'">Pedidos</a>
               para ver si el pedido se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $pedido->fecha = $_POST['fecha'];
         $pedido->codalmacen = $almacen->codalmacen;
         $pedido->codejercicio = $ejercicio->codejercicio;
         $pedido->codserie = $serie->codserie;
         $pedido->codpago = $forma_pago->codpago;
         $pedido->coddivisa = $divisa->coddivisa;
         $pedido->tasaconv = $divisa->tasaconv;
         
         if($_POST['tasaconv'] != '')
         {
            $pedido->tasaconv = floatval($_POST['tasaconv']);
         }
         
         $pedido->codagente = $this->agente->codagente;
         $pedido->observaciones = $_POST['observaciones'];
         $pedido->numero2 = $_POST['numero2'];
         $pedido->porcomision = $this->agente->porcomision;
         
         $pedido->codcliente = $cliente->codcliente;
         $pedido->cifnif = $_POST['cifnif'];
         $pedido->nombrecliente = $_POST['nombrecliente'];
         $pedido->codpais = $_POST['codpais'];
         $pedido->provincia = $_POST['provincia'];
         $pedido->ciudad = $_POST['ciudad'];
         $pedido->codpostal = $_POST['codpostal'];
         $pedido->direccion = $_POST['direccion'];
         $pedido->apartado = $_POST['apartado'];
         
         /// envío
         $pedido->envio_nombre = $_POST['envio_nombre'];
         $pedido->envio_apellidos = $_POST['envio_apellidos'];
         if($_POST['envio_codtrans'] != '')
         {
            $pedido->envio_codtrans = $_POST['envio_codtrans'];
         }
         $pedido->envio_codigo = $_POST['envio_codigo'];
         $pedido->envio_codpais = $_POST['envio_codpais'];
         $pedido->envio_provincia = $_POST['envio_provincia'];
         $pedido->envio_ciudad = $_POST['envio_ciudad'];
         $pedido->envio_codpostal = $_POST['envio_codpostal'];
         $pedido->envio_direccion = $_POST['envio_direccion'];
         $pedido->envio_apartado = $_POST['envio_apartado'];
         
         if( $pedido->save() )
         {
            $art0 = new articulo();
            $n = floatval($_POST['numlineas']);
            for($i = 0; $i <= $n; $i++)
            {
               if( isset($_POST['referencia_'.$i]) )
               {
                  $linea = new linea_pedido_cliente();
                  $linea->idpedido = $pedido->idpedido;
                  $linea->descripcion = $_POST['desc_'.$i];
                  
                  if( !$serie->siniva AND $cliente->regimeniva != 'Exento' )
                  {
                     $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$i]);
                     if($imp0)
                     {
                        $linea->codimpuesto = $imp0->codimpuesto;
                        $linea->iva = floatval($_POST['iva_'.$i]);
                        $linea->recargo = floatval($_POST['recargo_'.$i]);
                     }
                     else
                     {
                        $linea->iva = floatval($_POST['iva_'.$i]);
                        $linea->recargo = floatval($_POST['recargo_'.$i]);
                     }
                  }
                  
                  $linea->irpf = floatval($_POST['irpf_'.$i]);
                  $linea->pvpunitario = floatval($_POST['pvp_'.$i]);
                  $linea->cantidad = floatval($_POST['cantidad_'.$i]);
                  $linea->dtopor = floatval($_POST['dto_'.$i]);
                  $linea->pvpsindto = ($linea->pvpunitario * $linea->cantidad);
                  $linea->pvptotal = floatval($_POST['neto_'.$i]);
                  
                  $articulo = $art0->get($_POST['referencia_'.$i]);
                  if($articulo)
                  {
                     $linea->referencia = $articulo->referencia;
                  }
                  
                  if( $linea->save() )
                  {
                     $pedido->neto += $linea->pvptotal;
                     $pedido->totaliva += ($linea->pvptotal * $linea->iva/100);
                     $pedido->totalirpf += ($linea->pvptotal * $linea->irpf/100);
                     $pedido->totalrecargo += ($linea->pvptotal * $linea->recargo/100);
                     
                     if($linea->irpf > $pedido->irpf)
                     {
                        $pedido->irpf = $linea->irpf;
                     }
                  }
                  else
                  {
                     $this->new_error_msg("¡Imposible guardar la linea con referencia: ".$linea->referencia);
                     $continuar = FALSE;
                  }
               }
            }
            
            if($continuar)
            {
               /// redondeamos
               $pedido->neto = round($pedido->neto, FS_NF0);
               $pedido->totaliva = round($pedido->totaliva, FS_NF0);
               $pedido->totalirpf = round($pedido->totalirpf, FS_NF0);
               $pedido->totalrecargo = round($pedido->totalrecargo, FS_NF0);
               $pedido->total = $pedido->neto + $pedido->totaliva - $pedido->totalirpf + $pedido->totalrecargo;
               
               if( abs(floatval($_POST['atotal']) - $pedido->total) >= .02)
               {
                  $this->new_error_msg("El total difiere entre el controlador y la vista (".
                          $pedido->total." frente a ".$_POST['atotal']."). Debes informar del error.");
                  $pedido->delete();
               }
               else if( $pedido->save() )
               {
                  $this->new_message("<a href='".$pedido->url()."'>".ucfirst(FS_PEDIDO)."</a> guardado correctamente.");
                  $this->new_change(ucfirst(FS_PEDIDO)." a Cliente ".$pedido->codigo, $pedido->url(), TRUE);
                  
                  if($_POST['redir'] == 'TRUE')
                  {
                     header('Location: '.$pedido->url());
                  }
               }
               else
                  $this->new_error_msg("¡Imposible actualizar el <a href='".$pedido->url()."'>".FS_PEDIDO."</a>!");
            }
            else if( $pedido->delete() )
            {
               $this->new_message(ucfirst(FS_PEDIDO)." eliminado correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar el <a href='".$pedido->url()."'>".FS_PEDIDO."</a>!");
         }
         else
            $this->new_error_msg("¡Imposible guardar el ".FS_PEDIDO."!");
      }
   }
}
