<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2012-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('albaran_cliente.php');
require_model('albaran_proveedor.php');
require_model('fabricante.php');
require_model('familia.php');
require_model('impuesto.php');
require_model('stock.php');

/**
 * Almacena los datos de un artículos.
 */
class articulo extends fs_model
{
   /**
    * Clave primaria. Varchar (18).
    * @var type 
    */
   public $referencia;
   
   /**
    * Define el tipo de artículo, así se pueden establecer distinciones
    * según un tipo u otro.
    * @var type Varchar (10).
    */
   public $tipo;
   
   /**
    * Código de la familia a la que pertenece. En la clase familia.
    * @var type 
    */
   public $codfamilia;
   
   /**
    * Descripción del artículo. Tipo text, sin límite de caracteres.
    * @var type 
    */
   public $descripcion;
   
   /**
    * Código del fabricante al que pertenece. En la clase fabricante.
    * @var type 
    */
   public $codfabricante;
   
   /**
    * Precio del artículo, sin impuestos.
    * @var type 
    */
   public $pvp;
   
   /**
    * Almacena el valor del pvp antes de hacer el cambio.
    * Esta valor no se almacena en la base de datos, es decir,
    * no se recuerda.
    * @var type 
    */
   public $pvp_ant;
   
   /**
    * Fecha de actualización del pvp.
    * @var type 
    */
   public $factualizado;
   
   /**
    * Coste medio al comprar el artículo. Calculado.
    * @var type 
    */
   public $costemedio;
   
   /**
    * Precio de coste editado manualmente.
    * No necesariamente es el precio de compra, puede incluir
    * también otros costes.
    * @var type 
    */
   public $preciocoste;
   
   /**
    * Impuesto asignado. Clase impuesto.
    * @var type 
    */
   public $codimpuesto;
   
   /**
    * TRUE => el artículos está bloqueado / obsoleto.
    * @var type 
    */
   public $bloqueado;
   public $secompra;
   public $sevende;
   
   /**
    * TRUE -> se mostrará sincronizará con la tienda online.
    * @var type 
    */
   public $publico;
   
   /**
    * Código de equivalencia. Varchar (18).
    * Dos artículos o más son equivalentes si tienen el mismo código de equivalencia.
    * @var type 
    */
   public $equivalencia;
   
   /**
    * Partnumber del producto. Máximo 38 caracteres.
    * @var type 
    */
   public $partnumber;
   
   /**
    * Stock físico. La suma de las cantidades de esta referencia que en la tabla stocks.
    * @var type 
    */
   public $stockfis;
   public $stockmin;
   public $stockmax;
   
   /**
    * TRUE -> permitir ventas sin stock.
    * Si, sé que no tiene sentido que poner controlstock a TRUE
    * implique la ausencia de control de stock. Pero es una cagada de
    * FacturaLux -> Abanq -> Eneboo, y por motivos de compatibilidad
    * se mantiene.
    * @var type 
    */
   public $controlstock;
   
   /**
    * TRUE -> no controlar el stock.
    * Activarlo implica poner a TRUE $controlstock;
    * @var type 
    */
   public $nostock;
   
   /**
    * Código de barras.
    * @var type 
    */
   public $codbarras;
   public $observaciones;
   
   /**
    * Código de la subcuenta para compras.
    * @var type 
    */
   public $codsubcuentacom;
   
   /**
    * Código para la subcuenta de compras, pero con IRPF.
    * @var type 
    */
   public $codsubcuentairpfcom;
   
   /**
    * % IVA del impuesto asignado.
    * @var type 
    */
   private $iva;
   
   private $imagen;
   private $exists;
   
   private static $impuestos;
   private static $search_tags;
   private static $cleaned_cache;
   private static $column_list;


   public function __construct($a=FALSE)
   {
      parent::__construct('articulos', 'plugins/facturacion_base/');
      
      if( !isset(self::$impuestos) )
      {
         self::$impuestos = array();
      }
      
      if( !isset(self::$column_list) )
      {
         self::$column_list = 'referencia,codfamilia,codfabricante,descripcion,pvp,factualizado,costemedio,'.
                 'preciocoste,codimpuesto,stockfis,stockmin,stockmax,controlstock,nostock,bloqueado,'.
                 'secompra,sevende,equivalencia,codbarras,observaciones,imagen,publico,tipo,'.
                 'partnumber,codsubcuentacom,codsubcuentairpfcom';
      }
      
      if($a)
      {
         $this->referencia = $a['referencia'];
         $this->tipo = $a['tipo'];
         $this->codfamilia = $a['codfamilia'];
         $this->codfabricante = $a['codfabricante'];
         $this->descripcion = $this->no_html($a['descripcion']);
         $this->pvp = floatval($a['pvp']);
         $this->factualizado = Date('d-m-Y', strtotime($a['factualizado']));
         $this->costemedio = floatval($a['costemedio']);
         $this->preciocoste = floatval($a['preciocoste']);
         $this->codimpuesto = $a['codimpuesto'];
         $this->stockfis = floatval($a['stockfis']);
         $this->stockmin = floatval($a['stockmin']);
         $this->stockmax = floatval($a['stockmax']);
         
         $this->controlstock = $this->str2bool($a['controlstock']);
         $this->nostock = $this->str2bool($a['nostock']);
         if($this->nostock)
         {
            $this->controlstock = TRUE;
         }
         
         $this->bloqueado = $this->str2bool($a['bloqueado']);
         $this->secompra = $this->str2bool($a['secompra']);
         $this->sevende = $this->str2bool($a['sevende']);
         $this->publico = $this->str2bool($a['publico']);
         $this->equivalencia = $a['equivalencia'];
         $this->partnumber = $a['partnumber'];
         $this->codbarras = $a['codbarras'];
         $this->observaciones = $this->no_html($a['observaciones']);
         $this->codsubcuentacom = $a['codsubcuentacom'];
         $this->codsubcuentairpfcom = $a['codsubcuentairpfcom'];
         
         $this->imagen = NULL;
         if( isset($a['imagen']) )
         {
            /**
             * En versiones anteriores se guardaba la imagen en la tabla y en
             * tmp/articulos, ahora se guarda en images/articulos, así que hay
             * que moverlas.
             * 
             * Pero eneboo las guarda de otra forma, así que hay que discriminar.
             * RK@ => Eneboo
             */
            if( !file_exists('images/articulos') )
            {
               if( !mkdir('images/articulos', 0777, TRUE) )
               {
                  $this->new_error_msg('Error al crear la carpeta images/articulos.');
               }
            }
            
            if( substr($a['imagen'], 0, 3) == 'RK@' OR $a['imagen'] == '' )
            {
               /// eneboo, no hacemos nada
               $this->imagen = $a['imagen'];
            }
            else if( file_exists('tmp/articulos/'.$this->image_ref().'.png') )
            {
               /// si está el archivo, lo movemos
               if( !rename('tmp/articulos/'.$this->image_ref().'.png', 'images/articulos/'.$this->image_ref().'-1.png') )
               {
                  $this->new_error_msg('Error al mover la imagen del artículo.');
               }
            }
            else
            {
               /// sino está el archivo, intentamos extraer los datos de la base de datos
               $f = @fopen('images/articulos/'.$this->image_ref().'-1.png', 'a');
               if($f)
               {
                  fwrite( $f, $this->str2bin($a['imagen']) );
                  fclose($f);
               }
               else
               {
                  $this->new_error_msg('Error al extraer la imagen del artículo.');
               }
            }
         }
         
         $this->exists = TRUE;
      }
      else
      {
         $this->referencia = NULL;
         $this->tipo = NULL;
         $this->codfamilia = NULL;
         $this->codfabricante = NULL;
         $this->descripcion = '';
         $this->pvp = 0;
         $this->factualizado = Date('d-m-Y');
         $this->costemedio = 0;
         $this->preciocoste = 0;
         $this->codimpuesto = NULL;
         $this->stockfis = 0;
         $this->stockmin = 0;
         $this->stockmax = 0;
         $this->controlstock = (bool)FS_VENTAS_SIN_STOCK;
         $this->nostock = FALSE;
         $this->bloqueado = FALSE;
         $this->secompra = TRUE;
         $this->sevende = TRUE;
         $this->publico = FALSE;
         $this->equivalencia = NULL;
         $this->partnumber = NULL;
         $this->codbarras = '';
         $this->observaciones = '';
         $this->codsubcuentacom = NULL;
         $this->codsubcuentairpfcom = NULL;
         
         $this->imagen = NULL;
         $this->exists = FALSE;
      }
      
      $this->pvp_ant = 0;
      $this->iva = NULL;
   }
   
   protected function install()
   {
      /**
       * La tabla articulos tiene una clave ajea a impuestos, por eso creamos
       * un objeto impuesto, para forzar la comprobación de esa tabla.
       */
      new impuesto();
      
      /**
       * Limpiamos la caché por si el usuario ha borrado la tabla, pero ya tenía
       * búsquedas.
       */
      $this->clean_cache();
      
      return '';
   }
   
   public function descripcion($len = 120)
   {
      if( mb_strlen($this->descripcion, 'UTF8') > $len )
      {
         return mb_substr( nl2br($this->descripcion), 0, $len).'...';
      }
      else
      {
         return nl2br($this->descripcion);
      }
   }
   
   public function pvp_iva()
   {
      return $this->pvp * (100+$this->get_iva()) / 100;
   }
   
   /**
    * @deprecated since version 50
    * @return type
    */
   public function costemedio_iva()
   {
      return $this->costemedio * (100+$this->get_iva()) / 100;
   }
   
   /**
    * Devuelve el precio de coste, ya esté configurado como calculado o editable.
    * @return type
    */
   public function preciocoste()
   {
      if(FS_COST_IS_AVERAGE)
      {
         return $this->costemedio;
      }
      else
         return $this->preciocoste;
   }
   
   public function preciocoste_iva()
   {
      return $this->preciocoste() * (100+$this->get_iva()) / 100;
   }
   
   public function url()
   {
      if( is_null($this->referencia) )
      {
         return "index.php?page=ventas_articulos";
      }
      else
         return "index.php?page=ventas_articulo&ref=".urlencode($this->referencia);
   }
   
   /**
    * Devuelve una nueva referencia, la siguiente a la última de la base de datos.
    */
   public function get_new_referencia()
   {
      if( strtolower(FS_DB_TYPE) == 'postgresql' )
      {
         $sql = "SELECT referencia from articulos where referencia ~ '^\d+$'"
                 . " ORDER BY referencia::integer DESC";
      }
      else
      {
         $sql = "SELECT referencia from articulos where referencia REGEXP '^[0-9]+$'"
                 . " ORDER BY CAST(`referencia` AS decimal) DESC";
      }
      
      $ref = 1;
      $data = $this->db->select_limit($sql, 1, 0);
      if($data)
      {
         $ref = sprintf(1 + intval($data[0]['referencia']));
      }
      
      return $ref;
   }
   
   /**
    * Devuelve un artículo a partir de su referencia
    * @param type $ref
    * @return boolean|\articulo
    */
   public function get($ref)
   {
      $art = $this->db->select("SELECT ".self::$column_list." FROM ".$this->table_name." WHERE referencia = ".$this->var2str($ref).";");
      if($art)
      {
         return new articulo($art[0]);
      }
      else
         return FALSE;
   }
   
   /**
    * Devuelve la familia del artículo.
    * @return familia
    */
   public function get_familia()
   {
      $fam = new familia();
      return $fam->get($this->codfamilia);
   }
   
   /**
    * Devuelve el fabricante del artículo.
    * @return fabricante
    */
   public function get_fabricante()
   {
      $fab = new fabricante();
      return $fab->get($this->codfabricante);
   }
   
   public function get_stock()
   {
      $stock = new stock();
      return $stock->all_from_articulo($this->referencia);
   }
   
   /**
    * Devuelve el impuesto del artículo
    * @return impuesto
    */
   public function get_impuesto()
   {
      $imp = new impuesto();
      return $imp->get($this->codimpuesto);
   }
   
   /**
    * Devuelve el % de IVA del artículo.
    * Si $reload es TRUE, vuelve a consultarlo en lugar de usar los datos cargados.
    * @param type $reload
    * @return type
    */
   public function get_iva($reload = FALSE)
   {
      if($reload)
      {
         $this->iva = NULL;
      }
      
      if( is_null($this->iva) )
      {
         $this->iva = 0;
         
         if( !is_null($this->codimpuesto) )
         {
            $encontrado = FALSE;
            foreach(self::$impuestos as $i)
            {
               if($i->codimpuesto == $this->codimpuesto)
               {
                  $this->iva = $i->iva;
                  $encontrado = TRUE;
                  break;
               }
            }
            if(!$encontrado)
            {
               $imp = new impuesto();
               $imp0 = $imp->get($this->codimpuesto);
               if($imp0)
               {
                  $this->iva = $imp0->iva;
                  self::$impuestos[] = $imp0;
               }
            }
         }
      }
      
      return $this->iva;
   }
   
   /**
    * Devuelve un array con los artículos que tengan el mismo código de
    * equivalencia que el artículo.
    * @return \articulo
    */
   public function get_equivalentes()
   {
      $artilist = array();
      
      if( isset($this->equivalencia) )
      {
         $data = $this->db->select("SELECT ".self::$column_list." FROM ".$this->table_name.
                 " WHERE equivalencia = ".$this->var2str($this->equivalencia)." ORDER BY referencia ASC;");
         if($data)
         {
            foreach($data as $d)
            {
               if($d['referencia'] != $this->referencia)
               {
                  $artilist[] = new articulo($d);
               }
            }
         }
      }
      
      return $artilist;
   }
   
   public function get_lineas_albaran_cli($offset=0, $limit=FS_ITEM_LIMIT)
   {
      $linea = new linea_albaran_cliente();
      return $linea->all_from_articulo($this->referencia, $offset, $limit);
   }
   
   public function get_lineas_albaran_prov($offset=0, $limit=FS_ITEM_LIMIT)
   {
      $linea = new linea_albaran_proveedor();
      return $linea->all_from_articulo($this->referencia, $offset, $limit);
   }
   
   /**
    * Devuelve la media del precio de compra del artículo
    * @return type
    */
   public function get_costemedio()
   {
      $coste = 0;
      $stock = 0;
      
      foreach($this->get_lineas_albaran_prov() as $linea)
      {
         if($stock < $this->stockfis)
         {
            $coste += $linea->pvptotal;
            $stock += $linea->cantidad;
         }
      }
      
      if($stock > 0)
      {
         return $coste/$stock;
      }
      else
         return $coste;
   }
   
   public function imagen_url()
   {
      if( file_exists('images/articulos/'.$this->image_ref().'-1.png') )
      {
         return 'images/articulos/'.$this->image_ref().'-1.png';
      }
      else if( file_exists('images/articulos/'.$this->image_ref().'-1.jpg') )
      {
         return 'images/articulos/'.$this->image_ref().'-1.jpg';
      }
      else
         return FALSE;
   }
   
   public function set_imagen($img, $png = TRUE)
   {
      $this->imagen = NULL;
      
      if( file_exists('images/articulos/'.$this->image_ref().'-1.png') )
      {
         unlink('images/articulos/'.$this->image_ref().'-1.png');
      }
      else if( file_exists('images/articulos/'.$this->image_ref().'-1.jpg') )
      {
         unlink('images/articulos/'.$this->image_ref().'-1.jpg');
      }
      
      if($img)
      {
         if( !file_exists('images/articulos') )
         {
            @mkdir('images/articulos', 0777, TRUE);
         }
         
         if($png)
         {
            $f = @fopen('images/articulos/'.$this->image_ref().'-1.png', 'a');
         }
         else
         {
            $f = @fopen('images/articulos/'.$this->image_ref().'-1.jpg', 'a');
         }
         
         if($f)
         {
            fwrite($f, $img);
            fclose($f);
         }
      }
   }
   
   public function set_pvp($p)
   {
      $p = bround($p, FS_NF0_ART);
      
      if( !$this->floatcmp($this->pvp, $p, FS_NF0_ART+2) )
      {
         $this->pvp_ant = $this->pvp;
         $this->factualizado = Date('d-m-Y');
         $this->pvp = $p;
      }
   }
   
   public function set_pvp_iva($p)
   {
      $this->set_pvp( (100*$p)/(100+$this->get_iva()) );
   }
   
   /**
    * Cambia la referencia del artículo.
    * Lo hace en el momento, no hace falta hacer save().
    * @param type $ref
    */
   public function set_referencia($ref)
   {
      $ref = str_replace(' ', '_', trim($ref));
      if( is_null($ref) OR strlen($ref) < 1 OR strlen($ref) > 18 )
      {
         $this->new_error_msg("¡Referencia de artículo no válida! Debe tener entre 1 y 18 caracteres.");
      }
      else if( $ref != $this->referencia AND !is_null($this->referencia) )
      {
         $sql = "UPDATE ".$this->table_name." SET referencia = ".$this->var2str($ref)
                 ." WHERE referencia = ".$this->var2str($this->referencia).";";
         if( $this->db->exec($sql) )
         {
            /// renombramos la imagen, si la hay
            if( file_exists('images/articulos/'.$this->image_ref().'-1.png') )
            {
               rename('images/articulos/'.$this->image_ref().'-1.png', 'images/articulos/'.$this->image_ref($ref).'-1.png');
            }
            
            $this->referencia = $ref;
         }
         else
         {
            $this->new_error_msg('Imposible modificar la referencia.');
         }
      }
   }
   
   /**
    * Cambia el impuesto asociado al artículo.
    * @param type $codimpuesto
    */
   public function set_impuesto($codimpuesto)
   {
      if($codimpuesto != $this->codimpuesto)
      {
         $this->codimpuesto = $codimpuesto;
         
         $encontrado = FALSE;
         foreach(self::$impuestos as $i)
         {
            if($i->codimpuesto == $this->codimpuesto)
            {
               $this->iva = floatval($i->iva);
               $encontrado = TRUE;
               break;
            }
         }
         if( !$encontrado )
         {
            $imp = new impuesto();
            $imp0 = $imp->get($this->codimpuesto);
            if($imp0)
            {
               $this->iva = floatval($imp0->iva);
               self::$impuestos[] = $imp0;
            }
            else
               $this->iva = 0;
         }
      }
   }
   
   /**
    * Modifica el stock del artículo en un almacén concreto.
    * @param type $almacen
    * @param type $cantidad
    * @return boolean
    */
   public function set_stock($almacen, $cantidad = 1)
   {
      if($this->nostock)
      {
         return TRUE;
      }
      else
      {
         $result = FALSE;
         $stock = new stock();
         $encontrado = FALSE;
         
         $stocks = $stock->all_from_articulo($this->referencia);
         foreach($stocks as $k => $value)
         {
            if($value->codalmacen == $almacen)
            {
               $stocks[$k]->set_cantidad($cantidad);
               $result = $stocks[$k]->save();
               $encontrado = TRUE;
               break;
            }
         }
         if( !$encontrado )
         {
            $stock->referencia = $this->referencia;
            $stock->codalmacen = $almacen;
            $stock->set_cantidad($cantidad);
            $result = $stock->save();
         }
         
         if($result)
         {
            $nuevo_stock = $stock->total_from_articulo($this->referencia);
            if($this->stockfis != $nuevo_stock)
            {
               $this->stockfis =  $nuevo_stock;
               
               if($this->exists)
               {
                  $this->clean_cache();
                  $result = $this->db->exec("UPDATE ".$this->table_name." SET stockfis = ".
                          $this->var2str($this->stockfis)." WHERE referencia = ".$this->var2str($this->referencia).";");
               }
               else if( !$this->save() )
               {
                  $this->new_error_msg("¡Error al actualizar el stock del artículo!");
               }
            }
         }
         else
            $this->new_error_msg("Error al guardar el stock");
         
         return $result;
      }
   }
   
   /**
    * Suma la cantidad especificada al stock del artículo en el almacén especificado.
    * @param type $almacen
    * @param type $cantidad
    * @param type $recalcula_coste
    * @return boolean
    */
   public function sum_stock($almacen, $cantidad = 1, $recalcula_coste = FALSE)
   {
      if($this->nostock)
      {
         return TRUE;
      }
      else
      {
         $result = FALSE;
         $stock = new stock();
         $encontrado = FALSE;
         
         $stocks = $stock->all_from_articulo($this->referencia);
         foreach($stocks as $k => $value)
         {
            if($value->codalmacen == $almacen)
            {
               $stocks[$k]->sum_cantidad($cantidad);
               $result = $stocks[$k]->save();
               $encontrado = TRUE;
               break;
            }
         }
         if( !$encontrado )
         {
            $stock->referencia = $this->referencia;
            $stock->codalmacen = $almacen;
            $stock->set_cantidad($cantidad);
            $result = $stock->save();
         }
         
         if($result)
         {
            $nuevo_stock = $stock->total_from_articulo($this->referencia);
            if($this->stockfis != $nuevo_stock)
            {
               $this->stockfis =  $nuevo_stock;
               if($recalcula_coste)
               {
                  $this->costemedio = $this->get_costemedio();
               }
               
               if($this->exists)
               {
                  $this->clean_cache();
                  $result = $this->db->exec("UPDATE ".$this->table_name." SET stockfis = ".$this->var2str($this->stockfis).",
                     costemedio = ".$this->var2str($this->costemedio)." WHERE referencia = ".$this->var2str($this->referencia).";");
               }
               else if( !$this->save() )
               {
                  $this->new_error_msg("¡Error al actualizar el stock del artículo!");
               }
            }
         }
         else
            $this->new_error_msg("¡Error al guardar el stock!");
         
         return $result;
      }
   }
   
   /**
    * Esta función devuelve TRUE si el artículo ya existe en la base de datos.
    * Por motivos de rendimiento y al ser esta una clase de uso intensivo,
    * se utiliza la variable $this->exists para almacenar el resultado.
    * @return boolean
    */
   public function exists()
   {
      if( !$this->exists )
      {
         if( $this->db->select("SELECT referencia FROM ".$this->table_name." WHERE referencia = ".$this->var2str($this->referencia).";") )
         {
            $this->exists = TRUE;
         }
      }
      
      return $this->exists;
   }
   
   /**
    * Devuelve TRUE  si los datos del artículo son correctos.
    * @return boolean
    */
   public function test()
   {
      $status = FALSE;
      
      $this->descripcion = $this->no_html($this->descripcion);
      $this->codbarras = $this->no_html($this->codbarras);
      $this->observaciones = $this->no_html($this->observaciones);
      
      if($this->equivalencia == '')
      {
         $this->equivalencia = NULL;
      }
      
      if($this->nostock)
      {
         $this->controlstock = TRUE;
      }
      
      if( is_null($this->referencia) OR strlen($this->referencia) < 1 OR strlen($this->referencia) > 18 )
      {
         $this->new_error_msg("Referencia de artículo no válida: ".$this->referencia.". Debe tener entre 1 y 18 caracteres.");
      }
      else if( isset($this->equivalencia) AND strlen($this->equivalencia) > 25 )
      {
         $this->new_error_msg("Código de equivalencia del artículos no válido: ".$this->equivalencia.
                 ". Debe tener entre 1 y 25 caracteres.");
      }
      else
         $status = TRUE;
      
      return $status;
   }
   
   /**
    * Guarda en la base de datos los datos del artículo.
    * @return boolean
    */
   public function save()
   {
      if( $this->test() )
      {
         $this->clean_cache();
         
         if( $this->exists() )
         {
            $sql = "UPDATE ".$this->table_name." SET descripcion = ".$this->var2str($this->descripcion).
                    ", codfamilia = ".$this->var2str($this->codfamilia).
                    ", codfabricante = ".$this->var2str($this->codfabricante).
                    ", pvp = ".$this->var2str($this->pvp).
                    ", factualizado = ".$this->var2str($this->factualizado).
                    ", costemedio = ".$this->var2str($this->costemedio).
                    ", preciocoste = ".$this->var2str($this->preciocoste).
                    ", codimpuesto = ".$this->var2str($this->codimpuesto).
                    ", stockfis = ".$this->var2str($this->stockfis).
                    ", stockmin = ".$this->var2str($this->stockmin).
                    ", stockmax = ".$this->var2str($this->stockmax).
                    ", controlstock = ".$this->var2str($this->controlstock).
                    ", nostock = ".$this->var2str($this->nostock).
                    ", bloqueado = ".$this->var2str($this->bloqueado).
                    ", sevende = ".$this->var2str($this->sevende).
                    ", publico = ".$this->var2str($this->publico).
                    ", secompra = ".$this->var2str($this->secompra).
                    ", equivalencia = ".$this->var2str($this->equivalencia).
                    ", partnumber = ".$this->var2str($this->partnumber).
                    ", codbarras = ".$this->var2str($this->codbarras).
                    ", observaciones = ".$this->var2str($this->observaciones).
                    ", tipo = ".$this->var2str($this->tipo).
                    ", imagen = ".$this->var2str($this->imagen).
                    ", codsubcuentacom = ".$this->var2str($this->codsubcuentacom).
                    ", codsubcuentairpfcom = ".$this->var2str($this->codsubcuentairpfcom).
                    " WHERE referencia = ".$this->var2str($this->referencia).";";
            
            if($this->nostock AND $this->stockfis != 0)
            {
               $this->stockfis = 0;
               $sql .= "DELETE FROM stocks WHERE referencia = ".$this->var2str($this->referencia).";";
               $sql .= "UPDATE ".$this->table_name." SET stockfis = ".$this->var2str($this->stockfis).
                    " WHERE referencia = ".$this->var2str($this->referencia).";";
            }
         }
         else
         {
            $sql = "INSERT INTO ".$this->table_name." (".self::$column_list.") VALUES (".
                    $this->var2str($this->referencia).",".
                    $this->var2str($this->codfamilia).",".
                    $this->var2str($this->codfabricante).",".
                    $this->var2str($this->descripcion).",".
                    $this->var2str($this->pvp).",".
                    $this->var2str($this->factualizado).",".
                    $this->var2str($this->costemedio).",".
                    $this->var2str($this->preciocoste).",".
                    $this->var2str($this->codimpuesto).",".
                    $this->var2str($this->stockfis).",".
                    $this->var2str($this->stockmin).",".
                    $this->var2str($this->stockmax).",".
                    $this->var2str($this->controlstock).",".
                    $this->var2str($this->nostock).",".
                    $this->var2str($this->bloqueado).",".
                    $this->var2str($this->secompra).",".
                    $this->var2str($this->sevende).",".
                    $this->var2str($this->equivalencia).",".
                    $this->var2str($this->codbarras).",".
                    $this->var2str($this->observaciones).",".
                    $this->var2str($this->imagen).",".
                    $this->var2str($this->publico).",".
                    $this->var2str($this->tipo).",".
                    $this->var2str($this->partnumber).",".
                    $this->var2str($this->codsubcuentacom).",".
                    $this->var2str($this->codsubcuentairpfcom).");";
         }
         
         if( $this->db->exec($sql) )
         {
            $this->exists = TRUE;
            return TRUE;
         }
         else
            return FALSE;
      }
      else
         return FALSE;
   }
   
   /**
    * Elimina el artículo de la base de datos.
    * @return boolean
    */
   public function delete()
   {
      $this->clean_cache();
      
      $sql  = "DELETE FROM articulosprov WHERE referencia = ".$this->var2str($this->referencia).";";
      $sql .= "DELETE FROM ".$this->table_name." WHERE referencia = ".$this->var2str($this->referencia).";";
      if( $this->db->exec($sql) )
      {
         $this->exists = FALSE;
         return TRUE;
      }
      else
         return FALSE;
   }
   
   /**
    * Comprueba y añade una cadena a la lista de búsquedas precargadas
    * en memcache. Devuelve TRUE si la cadena ya está en la lista de
    * precargadas.
    * @param type $tag
    * @return boolean
    */
   private function new_search_tag($tag)
   {
      $encontrado = FALSE;
      $actualizar = FALSE;
      
      if( strlen($tag) > 1 )
      {
         /// obtenemos los datos de memcache
         $this->get_search_tags();
         
         foreach(self::$search_tags as $i => $value)
         {
            if( $value['tag'] == $tag )
            {
               $encontrado = TRUE;
               if( time()+5400 > $value['expires']+300 )
               {
                  self::$search_tags[$i]['count']++;
                  self::$search_tags[$i]['expires'] = time() + (self::$search_tags[$i]['count'] * 5400);
                  $actualizar = TRUE;
               }
               break;
            }
         }
         if( !$encontrado )
         {
            self::$search_tags[] = array('tag' => $tag, 'expires' => time()+5400, 'count' => 1);
            $actualizar = TRUE;
         }
         
         if( $actualizar )
            $this->cache->set('articulos_searches', self::$search_tags, 5400);
      }
      
      return $encontrado;
   }
   
   public function get_search_tags()
   {
      if( !isset(self::$search_tags) )
      {
         self::$search_tags = $this->cache->get_array('articulos_searches');
      }
      
      return self::$search_tags;
   }
   
   public function cron_job()
   {
      /// aceleramos las búsquedas
      if( $this->get_search_tags() )
      {
         foreach(self::$search_tags as $i => $value)
         {
            if( $value['expires'] < time() )
            {
               /// eliminamos las búsquedas antiguas
               unset(self::$search_tags[$i]);
            }
            else if( $value['count'] > 1 )
            {
               /// guardamos los resultados de la búsqueda en memcache
               $this->cache->set('articulos_search_'.$value['tag'], $this->search($value['tag']), 5400);
               echo '.';
            }
         }
         
         /// guardamos en memcache la lista de búsquedas
         $this->cache->set('articulos_searches', self::$search_tags, 5400);
      }
   }
   
   private function clean_cache()
   {
      /*
       * Durante las actualizaciones masivas de artículos se ejecuta esta
       * función cada vez que se guarda un artículo, por eso es mejor limitarla.
       */
      if( !self::$cleaned_cache )
      {
         /// obtenemos los datos de memcache
         $this->get_search_tags();
         
         if( self::$search_tags )
         {
            foreach(self::$search_tags as $value)
               $this->cache->delete('articulos_search_'.$value['tag']);
         }
         
         self::$cleaned_cache = TRUE;
      }
   }
   
   public function search($query='', $offset=0, $codfamilia='', $con_stock=FALSE, $codfabricante='', $bloqueados=FALSE)
   {
      $artilist = array();
      $query = $this->no_html( strtolower($query) );
      
      if($query != '' AND $offset == 0 AND $codfamilia == '' AND $codfabricante == '' AND !$con_stock AND !$bloqueados)
      {
         /// intentamos obtener los datos de memcache
         if( $this->new_search_tag($query) )
         {
            $artilist = $this->cache->get_array('articulos_search_'.$query);
         }
      }
      
      if( count($artilist) <= 1 )
      {
         $sql = "SELECT ".self::$column_list." FROM ".$this->table_name;
         $separador = ' WHERE';
         
         if($codfamilia != '')
         {
            $sql .= $separador." codfamilia = ".$this->var2str($codfamilia);
            $separador = ' AND';
         }
         
         if($codfabricante != '')
         {
            $sql .= $separador." codfabricante = ".$this->var2str($codfabricante);
            $separador = ' AND';
         }
         
         if($con_stock)
         {
            $sql .= $separador." stockfis > 0";
            $separador = ' AND';
         }
         
         if($bloqueados)
         {
            $sql .= $separador." bloqueado";
            $separador = ' AND';
         }
         else
         {
            $sql .= $separador." bloqueado = FALSE";
            $separador = ' AND';
         }
         
         if($query == '')
         {
            /// nada
         }
         else if( is_numeric($query) )
         {
            $sql .= $separador." (referencia = ".$this->var2str($query)
                    . " OR referencia LIKE '%".$query."%' OR equivalencia LIKE '%".$query."%'"
                    . " OR descripcion LIKE '%".$query."%' OR codbarras = '".$query."')";
         }
         else
         {
            $buscar = str_replace(' ', '%', $query);
            $sql .= $separador." (lower(referencia) = ".$this->var2str($query)
                    . " OR lower(referencia) LIKE '%".$buscar."%' OR lower(equivalencia) LIKE '%".$buscar."%'"
                    . " OR lower(descripcion) LIKE '%".$buscar."%')";
         }
         
         if( strtolower(FS_DB_TYPE) == 'mysql' )
         {
            $sql .= " ORDER BY lower(referencia) ASC";
         }
         else
         {
            $sql .= " ORDER BY referencia ASC";
         }
         
         $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
         if($data)
         {
            foreach($data as $a)
            {
               $artilist[] = new articulo($a);
            }
         }
      }
      
      return $artilist;
   }
   
   /**
    * Devuelve un array con los artículos que tengan $cod como código de barras.
    * @param type $cod
    * @param type $offset
    * @param type $limit
    * @return \articulo
    */
   public function search_by_codbar($cod, $offset=0, $limit=FS_ITEM_LIMIT)
   {
      $artilist = array();
      $sql = "SELECT ".self::$column_list." FROM ".$this->table_name
              ." WHERE codbarras = ".$this->var2str($cod)
              ." ORDER BY lower(referencia) ASC";
      
      $data = $this->db->select_limit($sql, $limit, $offset);
      if($data)
      {
         foreach($data as $d)
            $artilist[] = new articulo($d);
      }
      
      return $artilist;
   }
   
   /**
    * Devuelve el listado de artículos desde el resultado $offset hasta $offset+$limit.
    * @param integer $offset desde
    * @param integer $limit nº de elementos devuelto
    * @return \articulo
    */
   public function all($offset=0, $limit=FS_ITEM_LIMIT)
   {
      $artilist = array();
      $sql = "SELECT ".self::$column_list." FROM ".$this->table_name
              ." ORDER BY lower(referencia) ASC";
      
      $data = $this->db->select_limit($sql, $limit, $offset);
      if($data)
      {
         foreach($data as $d)
            $artilist[] = new articulo($d);
      }
      
      return $artilist;
   }
   
   /**
    * Devuelve el listado de artículos públicos, desde $offset hasta $offset+$limit
    * @param type $offset
    * @param type $limit
    * @return \articulo
    */
   public function all_publico($offset=0, $limit=FS_ITEM_LIMIT)
   {
      $artilist = array();
      $sql = "SELECT ".self::$column_list." FROM ".$this->table_name
              ." WHERE publico ORDER BY lower(referencia) ASC";
      
      $data = $this->db->select_limit($sql, $limit, $offset);
      if($data)
      {
         foreach($data as $d)
            $artilist[] = new articulo($d);
      }
      
      return $artilist;
   }
   
   /**
    * Devuelve los artículos de una familia.
    * @param type $cod
    * @param type $offset
    * @param type $limit
    * @return \articulo
    */
   public function all_from_familia($cod, $offset=0, $limit=FS_ITEM_LIMIT)
   {
      $artilist = array();
      $sql = "SELECT ".self::$column_list." FROM ".$this->table_name." WHERE codfamilia = "
              .$this->var2str($cod)." ORDER BY lower(referencia) ASC";
      
      $data = $this->db->select_limit($sql, $limit, $offset);
      if($data)
      {
         foreach($data as $d)
            $artilist[] = new articulo($d);
      }
      
      return $artilist;
   }
   
   /**
    * Devuelve los artículos de un fabricante.
    * @param type $cod
    * @param type $offset
    * @param type $limit
    * @return \articulo
    */
   public function all_from_fabricante($cod, $offset=0, $limit=FS_ITEM_LIMIT)
   {
      $artilist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE codfabricante = "
              .$this->var2str($cod)." ORDER BY lower(referencia) ASC";
      
      $data = $this->db->select_limit($sql, $limit, $offset);
      if($data)
      {
         foreach($data as $d)
            $artilist[] = new articulo($d);
      }
      
      return $artilist;
   }
   
   /**
    * Devuelve la referencia codificada para poder ser usada en imágenes.
    * Evitamos así errores con caracteres especiales como / y \.
    * @param type $ref
    * @return type
    */
   public function image_ref($ref = FALSE)
   {
      if(!$ref)
      {
         $ref = $this->referencia;
      }
      
      $ref = str_replace('/', '_', $ref);
      $ref = str_replace('\\', '_', $ref);
      
      return rawurlencode($ref);
   }
}
